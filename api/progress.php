<?php
// api/progress.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false];

$action = $_POST['action'] ?? '';

if ($action === 'complete_lesson' && has_role('student')) {
    $lesson_id = (int)($_POST['lesson_id'] ?? 0);
    
    $stmt = $pdo->prepare("
        INSERT INTO lesson_progress (student_id, lesson_id, statut, date_completion) 
        VALUES (?, ?, 'termine', NOW())
        ON DUPLICATE KEY UPDATE statut = 'termine', date_completion = NOW()
    ");
    $success = $stmt->execute([$_SESSION['user_id'], $lesson_id]);
    $response = ['success' => $success];
}

echo json_encode($response);