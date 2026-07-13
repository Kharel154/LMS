<?php
// api/grades.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Requête invalide.'];

if (has_role('teacher') && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $response['message'] = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
        echo json_encode($response);
        exit;
    }

    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $note = isset($_POST['note']) ? (float)$_POST['note'] : null;
    $comment = sanitize_input($_POST['comment'] ?? '');

    if (!$submission_id || $note === null || $note < 0 || $note > 20) {
        $response['message'] = 'Note invalide. Elle doit être comprise entre 0 et 20.';
        echo json_encode($response);
        exit;
    }

    // Vérifie que la soumission appartient bien à un cours de cet enseignant
    $stmtCheck = $pdo->prepare("
        SELECT s.id
        FROM assignment_submissions s
        JOIN assignments a ON s.assignment_id = a.id
        JOIN lessons l ON a.lesson_id = l.id
        JOIN courses c ON l.course_id = c.id
        WHERE s.id = ? AND c.enseignant_id = ?
    ");
    $stmtCheck->execute([$submission_id, $_SESSION['user_id']]);

    if (!$stmtCheck->fetch()) {
        $response['message'] = "Ce devoir n'appartient pas à l'un de vos cours.";
        echo json_encode($response);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE assignment_submissions
        SET note = ?, commentaire_prof = ?, date_correction = NOW()
        WHERE id = ?
    ");
    $success = $stmt->execute([$note, $comment, $submission_id]);

    $response = [
        'success' => $success,
        'message' => $success ? 'Note enregistrée avec succès.' : "Erreur lors de l'enregistrement de la note.",
    ];
}

echo json_encode($response);