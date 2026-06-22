<?php
// api/grades.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (has_role('teacher') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $note = (float)($_POST['note'] ?? 0);
    $comment = sanitize_input($_POST['comment'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE assignment_submissions 
        SET note = ?, commentaire_prof = ?, date_correction = NOW() 
        WHERE id = ?
    ");
    $success = $stmt->execute([$note, $comment, $submission_id]);

    echo json_encode(['success' => $success]);
    exit;
}

echo json_encode(['success' => false]);