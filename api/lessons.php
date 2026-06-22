<?php
// api/lessons.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Empêche tout warning/notice PHP de casser le JSON renvoyé au front
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("$message in $file on line $line"); // log serveur, pas dans la réponse
    return true; // empêche l'affichage du warning dans la sortie
});

$response = ['success' => false, 'message' => 'Action invalide'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_role('teacher')) {

        $course_id = (int)($_POST['course_id'] ?? 0);
        $titre     = sanitize_input($_POST['titre'] ?? '');
        $type      = in_array($_POST['type'] ?? '', ['pdf', 'video']) ? $_POST['type'] : 'pdf';

        if (empty($course_id) || empty($titre)) {
            $response['message'] = 'Titre et ID du cours sont obligatoires';
            echo json_encode($response);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_code = $_FILES['file']['error'] ?? 'unknown';
            $response['message'] = "Erreur d'upload (code: $error_code). Vérifiez le fichier.";
            echo json_encode($response);
            exit;
        }

        $target_dir = '../assets/uploads/' . ($type === 'pdf' ? 'pdfs/' : 'videos/');

        // Vérification explicite que le dossier existe et est écrivable
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        if (!is_writable($target_dir)) {
            $response['message'] = " Le dossier $target_dir n'est pas accessible en écriture.";
            echo json_encode($response);
            exit;
        }

        $uploadResult = upload_file($_FILES['file'], $target_dir, ['pdf', 'mp4']);

        if ($uploadResult['success']) {
            $filename = $uploadResult['filename'];

            // Étape 1 : calculer le prochain ordre dans une requête séparée
            $stmtOrdre = $pdo->prepare("SELECT IFNULL(MAX(ordre), 0) + 1 FROM lessons WHERE course_id = ?");
            $stmtOrdre->execute([$course_id]);
            $nextOrdre = $stmtOrdre->fetchColumn();

            // Étape 2 : insérer avec la valeur déjà calculée
            $stmt = $pdo->prepare("
                INSERT INTO lessons (course_id, titre, ordre, type, fichier_url) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $success = $stmt->execute([$course_id, $titre, $nextOrdre, $type, $filename]);

            $response = [
                'success' => $success,
                'message' => $success ? ' Leçon ajoutée avec succès !' : 'Erreur lors de l\'enregistrement en base'
            ];
        } else {
            $response['message'] = ' ' . ($uploadResult['error'] ?? 'Échec du déplacement du fichier. Vérifiez les permissions des dossiers uploads/');
        }
    }
} catch (Throwable $e) {
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);