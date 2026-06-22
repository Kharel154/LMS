<?php
// api/delete_lesson.php
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_role('teacher')) {

        $lesson_id = (int)($_POST['lesson_id'] ?? 0);

        if (empty($lesson_id)) {
            $response['message'] = 'ID de la leçon manquant.';
            echo json_encode($response);
            exit;
        }

        // Vérifie que la leçon appartient bien à un cours de cet enseignant
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

        // Suppression du fichier physique sur disque
        $sous_dossier = $lesson['type'] === 'pdf' ? 'pdfs/' : 'videos/';
        $chemin_fichier = '../assets/uploads/' . $sous_dossier . $lesson['fichier_url'];

        if (file_exists($chemin_fichier)) {
            unlink($chemin_fichier);
        }

        // Suppression de l'entrée en base
        $stmtDelete = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
        $success = $stmtDelete->execute([$lesson_id]);

        $response = [
            'success' => $success,
            'message' => $success ? ' Leçon supprimée avec succès.' : 'Erreur lors de la suppression en base.'
        ];
    }
} catch (Throwable $e) {
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);