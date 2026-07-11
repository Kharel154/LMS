<?php
// api/system_config.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Action invalide'];

$action = $_POST['action'] ?? 'save';

if ($action === 'clear_sessions') {
    // Vide le dossier des sessions PHP de LAMPP
    $sessionPath = session_save_path();
    if (empty($sessionPath)) $sessionPath = sys_get_temp_dir();

    $count = 0;
    foreach (glob($sessionPath . '/sess_*') as $file) {
        // Ne supprime pas la session de l'admin connecté
        $currentSessionFile = $sessionPath . '/sess_' . session_id();
        if ($file !== $currentSessionFile) {
            @unlink($file);
            $count++;
        }
    }

    $response = [
        'success' => true,
        'message' => "$count session(s) supprimée(s). Les utilisateurs devront se reconnecter."
    ];
    echo json_encode($response);
    exit;
}

// Action par défaut : sauvegarde des paramètres
$allowed_keys = [
    'site_name',
    'site_description',
    'contact_email',
    'max_upload_size',
    'note_passage_defaut',
    'inscription_libre',
    'validation_cours',
    'maintenance_mode',
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO system_config (cle, valeur)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)
    ");

    foreach ($allowed_keys as $key) {
        $value = $_POST[$key] ?? '0';

        // Sanitize selon le type de champ
        if (in_array($key, ['maintenance_mode', 'inscription_libre', 'validation_cours'])) {
            $value = $value === '1' ? '1' : '0';
        } elseif (in_array($key, ['max_upload_size', 'note_passage_defaut'])) {
            $value = (string)(int)$value;
        } else {
            $value = sanitize_input($value);
        }

        $stmt->execute([$key, $value]);
    }

    $response = [
        'success' => true,
        'message' => 'Paramètres sauvegardés avec succès.'
    ];
} catch (Throwable $e) {
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);