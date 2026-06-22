<?php
// api/quiz.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("$message in $file on line $line");
    return true;
});

$response = ['success' => false, 'message' => 'Action invalide'];

try {


    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if ($action === 'submit_attempt' && $_SERVER['REQUEST_METHOD'] === 'POST' && has_role('student')) {

        $input = json_decode(file_get_contents('php://input'), true);
        $quiz_id = (int)($input['quiz_id'] ?? 0);
        $answers = $input['answers'] ?? [];

        if (empty($quiz_id) || empty($answers)) {
            $response['message'] = 'Réponses manquantes.';
            echo json_encode($response);
            exit;
        }

        // Vérifie que l'étudiant est bien inscrit au cours de ce quiz
        $stmt = $pdo->prepare("
            SELECT q.id, q.note_passage, q.lesson_id, l.course_id
            FROM quizzes q
            JOIN lessons l ON l.id = q.lesson_id
            JOIN courses c ON c.id = l.course_id
            JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
            WHERE q.id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        $quizInfo = $stmt->fetch();

        if (!$quizInfo) {
            $response['message'] = 'Évaluation introuvable ou accès non autorisé.';
            echo json_encode($response);
            exit;
        }

        // Nombre total de questions du quiz (pour calculer le score sur l'ensemble, même si une réponse manque)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?");
        $stmt->execute([$quiz_id]);
        $totalQuestions = (int)$stmt->fetchColumn();

        if ($totalQuestions === 0) {
            $response['message'] = "Ce quiz ne contient aucune question.";
            echo json_encode($response);
            exit;
        }

        $pdo->beginTransaction();

        // Crée la tentative (score provisoire à 0, mis à jour ensuite)
        $stmt = $pdo->prepare("INSERT INTO quiz_attempts (student_id, quiz_id, score, passed) VALUES (?, ?, 0, 0)");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        $attempt_id = $pdo->lastInsertId();

        $correctCount = 0;

        $stmtCheck = $pdo->prepare("SELECT is_correct FROM quiz_choices WHERE id = ? AND question_id = ?");
        $stmtInsertAnswer = $pdo->prepare("INSERT INTO quiz_answers (attempt_id, question_id, choice_id) VALUES (?, ?, ?)");

        foreach ($answers as $a) {
            $question_id = (int)($a['question_id'] ?? 0);
            $choice_id   = (int)($a['choice_id'] ?? 0);
            if (!$question_id || !$choice_id) continue;

            $stmtCheck->execute([$choice_id, $question_id]);
            $isCorrect = $stmtCheck->fetchColumn();

            if ($isCorrect) $correctCount++;

            $stmtInsertAnswer->execute([$attempt_id, $question_id, $choice_id]);
        }

        $score = round(($correctCount / $totalQuestions) * 100, 2);
        $passed = $score >= $quizInfo['note_passage'] ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE quiz_attempts SET score = ?, passed = ? WHERE id = ?");
        $stmt->execute([$score, $passed, $attempt_id]);

        // Si réussi : marque la leçon comme "termine" dans lesson_progress
        if ($passed) {
            $stmt = $pdo->prepare("
                INSERT INTO lesson_progress (student_id, lesson_id, statut, date_completion)
                VALUES (?, ?, 'termine', NOW())
                ON DUPLICATE KEY UPDATE statut = 'termine', date_completion = NOW()
            ");
            $stmt->execute([$_SESSION['user_id'], $quizInfo['lesson_id']]);
        }

        $pdo->commit();

        $response = [
            'success' => true,
            'score' => $score,
            'passed' => (bool)$passed,
            'message' => $passed ? 'Évaluation réussie !' : 'Score insuffisant.'
        ];

        echo json_encode($response);
        exit;
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_role('teacher')) {

        $input = json_decode(file_get_contents('php://input'), true);

        $lesson_id     = (int)($input['lesson_id'] ?? 0);
        $quiz_id       = !empty($input['quiz_id']) ? (int)$input['quiz_id'] : null;
        $titre         = sanitize_input($input['titre'] ?? '');
        $note_passage  = (int)($input['note_passage'] ?? 50);
        $questions     = $input['questions'] ?? [];

        if (empty($lesson_id) || empty($titre) || empty($questions)) {
            $response['message'] = 'Données incomplètes (titre, leçon ou questions manquantes).';
            echo json_encode($response);
            exit;
        }

        // Vérifie que la leçon appartient bien à un cours de cet enseignant
        $stmt = $pdo->prepare("
            SELECT l.id FROM lessons l
            JOIN courses c ON c.id = l.course_id
            WHERE l.id = ? AND c.enseignant_id = ?
        ");
        $stmt->execute([$lesson_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Accès non autorisé à cette leçon.';
            echo json_encode($response);
            exit;
        }

        $pdo->beginTransaction();

        if ($quiz_id) {
            // Mise à jour : on supprime les anciennes questions/choix puis on réinsère
            $stmt = $pdo->prepare("UPDATE quizzes SET titre = ?, note_passage = ? WHERE id = ?");
            $stmt->execute([$titre, $note_passage, $quiz_id]);

            $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quiz_id]);
            // quiz_choices est supprimé automatiquement via ON DELETE CASCADE sur question_id
        } else {
            $stmt = $pdo->prepare("INSERT INTO quizzes (lesson_id, titre, note_passage) VALUES (?, ?, ?)");
            $stmt->execute([$lesson_id, $titre, $note_passage]);
            $quiz_id = $pdo->lastInsertId();
        }

        $stmtQ = $pdo->prepare("
            INSERT INTO quiz_questions (quiz_id, question_text, type, ordre) VALUES (?, ?, ?, ?)
        ");
        $stmtC = $pdo->prepare("
            INSERT INTO quiz_choices (question_id, choice_text, is_correct) VALUES (?, ?, ?)
        ");

        foreach ($questions as $ordre => $q) {
            $qText = sanitize_input($q['text'] ?? '');
            $qType = in_array($q['type'] ?? '', ['qcm', 'vrai_faux']) ? $q['type'] : 'qcm';

            if (empty($qText) || empty($q['choices'])) {
                continue; // ignore les questions incomplètes
            }

            $stmtQ->execute([$quiz_id, $qText, $qType, $ordre + 1]);
            $question_id = $pdo->lastInsertId();

            foreach ($q['choices'] as $choice) {
                $choiceText = sanitize_input($choice['text'] ?? '');
                $isCorrect = !empty($choice['is_correct']) ? 1 : 0;
                if ($choiceText === '') continue;
                $stmtC->execute([$question_id, $choiceText, $isCorrect]);
            }
        }

        $pdo->commit();

        $response = [
            'success' => true,
            'message' => 'Quiz enregistré avec succès !',
            'quiz_id' => $quiz_id
        ];
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);