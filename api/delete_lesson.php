<?php

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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !has_role('teacher')) {
        echo json_encode($response);
        exit;
    }

    $lesson_id = (int)($_POST['lesson_id'] ?? 0);

    if (empty($lesson_id)) {
        $response['message'] = 'ID de la leçon manquant.';
        echo json_encode($response);
        exit;
    }

    // Vérifie que la leçon appartient à un cours de cet enseignant.
    // La jointure sur courses garantit qu'un enseignant ne peut pas
    // supprimer la leçon d'un autre enseignant en forgeant l'ID.
    $stmt = $pdo->prepare("
        SELECT l.id, l.type, l.fichier_url
        FROM lessons l
        JOIN courses c ON c.id = l.course_id
        WHERE l.id = ? AND c.enseignant_id = ?
    ");
    $stmt->execute([$lesson_id, $_SESSION['user_id']]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        $response['message'] = 'Leçon introuvable ou accès non autorisé.';
        echo json_encode($response);
        exit;
    }

    $pdo->beginTransaction();

    
    //SUPPRESSION DU FICHIER PHYSIQUE
    // On tente la suppression sans bloquer la transaction si le fichier
    // est déjà absent (suppression partielle précédente par exemple).
    
    $sous_dossier   = $lesson['type'] === 'pdf' ? 'pdfs/' : 'videos/';
    $chemin_fichier = '../assets/uploads/' . $sous_dossier . $lesson['fichier_url'];

    if (file_exists($chemin_fichier)) {
        unlink($chemin_fichier);
    }

    
    //SUPPRESSION DES DONNÉES DE PROGRESSION DES ÉTUDIANTS
    // La table lesson_progress ne dispose pas de ON DELETE CASCADE
    // dans le schéma actuel. Sans cette suppression explicite, les lignes
    // restent en base après suppression de la leçon, ce qui :
    //   - fausse le calcul de progression dans course-view.php
    //   - affiche la leçon supprimée comme "en cours" pour les étudiants
    
    $stmt = $pdo->prepare("DELETE FROM lesson_progress WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);

    
    //SUPPRESSION DES TENTATIVES DE QUIZ LIÉES À CETTE LEÇON
    // Les réponses (quiz_answers) sont supprimées en cascade depuis
    // quiz_attempts si la contrainte CASCADE est présente, sinon on
    // les supprime explicitement en premier.
    
    $stmt = $pdo->prepare("
        SELECT id FROM quizzes WHERE lesson_id = ?
    ");
    $stmt->execute([$lesson_id]);
    $quiz = $stmt->fetch();

    if ($quiz) {
        // Suppression des réponses aux tentatives
        $stmt = $pdo->prepare("
            DELETE qa FROM quiz_answers qa
            JOIN quiz_attempts att ON att.id = qa.attempt_id
            WHERE att.quiz_id = ?
        ");
        $stmt->execute([$quiz['id']]);

        // Suppression des tentatives elles-mêmes
        $stmt = $pdo->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
        $stmt->execute([$quiz['id']]);
    }

    
    //SUPPRESSION DE LA LEÇON
    // Les quizzes, quiz_questions et quiz_choices sont supprimés
    // automatiquement par les contraintes ON DELETE CASCADE définies
    // dans le schéma sur ces tables.
   
    $stmtDelete = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $success    = $stmtDelete->execute([$lesson_id]);

    if (!$success) {
        $pdo->rollBack();
        $response['message'] = 'Erreur lors de la suppression en base.';
        echo json_encode($response);
        exit;
    }

    $pdo->commit();

    $response = [
        'success' => true,
        'message' => 'Leçon supprimée avec succès.'
    ];

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);