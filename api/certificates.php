<?php
// api/certificates.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (has_role('student') && ($_GET['action'] ?? '') === 'check') {
    // Vérifier si un certificat doit être généré (exemple)
    $module_id = (int)($_GET['module_id'] ?? 0);
    // Logique complète d’obtention de certificat à implémenter selon vos règles métier
    $response = ['success' => true, 'has_certificate' => false];
}

echo json_encode($response ?? ['success' => false]);