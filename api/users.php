<?php
// api/users.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Action non autorisée'];

$action = $_POST['action'] ?? '';

if (!has_role('admin')) {
    echo json_encode($response);
    exit;
}

if ($action === 'change_role') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_role = in_array($_POST['new_role'] ?? '', ['student', 'teacher', 'admin']) ? $_POST['new_role'] : null;

    if ($user_id && $new_role) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $success = $stmt->execute([$new_role, $user_id]);
        
        $response = [
            'success' => $success,
            'message' => $success ? 'Rôle modifié avec succès' : 'Erreur lors de la modification'
        ];
    }
}

elseif ($action === 'toggle_status') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id) {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET statut = IF(statut = 'actif', 'suspendu', 'actif') 
            WHERE id = ?
        ");
        $success = $stmt->execute([$user_id]);
        
        $response = [
            'success' => $success,
            'message' => $success ? 'Statut modifié avec succès' : 'Erreur lors du changement de statut'
        ];
    }
}

echo json_encode($response);