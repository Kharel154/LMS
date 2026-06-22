<?php
// api/upload.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Erreur upload'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $type = $_POST['action'] ?? 'general';
    $target_dir = '../assets/uploads/';

    if ($type === 'assignment') {
        $target_dir .= 'assignments/';
    } elseif (strpos($_POST['type'] ?? '', 'pdf') !== false) {
        $target_dir .= 'pdfs/';
    } else {
        $target_dir .= 'videos/';
    }

    $filename = upload_file($_FILES['file'], $target_dir, ['pdf','mp4','zip']);

    if ($filename) {
        $response = [
            'success' => true,
            'filename' => $filename,
            'message' => 'Fichier uploadé avec succès'
        ];
    } else {
        $response['message'] = 'Échec de l’upload ou type de fichier non autorisé';
    }
}

echo json_encode($response);