<?php

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Action invalide'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        log_connection($_SESSION['user_id'], 'logout');
    }
    
    session_unset();
    session_destroy();
    
    $response = [
        'success' => true,
        'message' => 'Déconnexion réussie',
        'redirect' => '../index.php'
    ];
}

echo json_encode($response);