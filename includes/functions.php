<?php
// includes/functions.php

function sanitize_input($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function log_connection($user_id, $action) {
    global $pdo;
    if (!$user_id) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO connection_logs (user_id, ip_address, action) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $ip, $action]);
}

function upload_file($file, $target_dir, $allowed_ext = ['pdf', 'mp4']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Fichier temporaire introuvable.'];
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        return ['success' => false, 'error' => "Extension .$ext non autorisée."];
    }

    if ($file['size'] > 200 * 1024 * 1024) {
        $taille_mo = round($file['size'] / 1024 / 1024, 1);
        return ['success' => false, 'error' => "Fichier trop volumineux ($taille_mo Mo, max 200 Mo)."];
    }

    $new_name = uniqid('lesson_') . '.' . $ext;
    $target_file = rtrim($target_dir, '/') . '/' . $new_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => $new_name];
    }

    return ['success' => false, 'error' => 'Échec du déplacement physique du fichier (permissions ?).'];
}
?>