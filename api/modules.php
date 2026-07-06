<?php
// api/modules.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    error_log("$message in $file on line $line");
    return true;
});

$response = ['success' => false, 'message' => 'Action invalide'];
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        case 'list':
            // Lecture seule : pas besoin de CSRF
            $stmt = $pdo->query("
                SELECT m.*, cat.nom AS categorie_nom,
                       (SELECT COUNT(*) FROM courses c WHERE c.module_id = m.id) AS nb_cours,
                       (SELECT COUNT(*) FROM certificates cert WHERE cert.module_id = m.id) AS nb_certificats
                FROM modules m
                LEFT JOIN categories cat ON cat.id = m.categorie_id
                ORDER BY m.date_creation DESC
            ");
            $response['success'] = true;
            $response['modules'] = $stmt->fetchAll();
            break;

        case 'get_courses_for_module':
            // Liste tous les cours, en indiquant lesquels appartiennent déjà à ce module
            $module_id = (int)($_GET['module_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT id, titre, module_id,
                       (module_id = ?) AS is_in_module
                FROM courses
                WHERE module_id IS NULL OR module_id = ?
                ORDER BY titre ASC
            ");
            $stmt->execute([$module_id, $module_id]);
            $response['success'] = true;
            $response['courses'] = $stmt->fetchAll();
            break;

        case 'create':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response['message'] = 'Jeton de sécurité invalide. Rechargez la page.';
                break;
            }

            $nom = sanitize_input($_POST['nom'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $categorie_id = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;

            if (empty($nom)) {
                $response['message'] = 'Le titre du module est obligatoire.';
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO modules (nom, description, categorie_id, admin_id)
                VALUES (?, ?, ?, ?)
            ");
            $success = $stmt->execute([$nom, $description, $categorie_id, $_SESSION['user_id']]);

            $response = [
                'success' => $success,
                'message' => $success ? ' Module créé avec succès.' : 'Erreur lors de la création.',
                'module_id' => $success ? $pdo->lastInsertId() : null
            ];
            break;

        case 'update':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response['message'] = 'Jeton de sécurité invalide. Rechargez la page.';
                break;
            }

            $module_id = (int)($_POST['module_id'] ?? 0);
            $nom = sanitize_input($_POST['nom'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $categorie_id = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;

            if (empty($module_id) || empty($nom)) {
                $response['message'] = 'Données incomplètes.';
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE modules SET nom = ?, description = ?, categorie_id = ?
                WHERE id = ?
            ");
            $success = $stmt->execute([$nom, $description, $categorie_id, $module_id]);

            $response = [
                'success' => $success,
                'message' => $success ? ' Module mis à jour.' : 'Erreur lors de la mise à jour.'
            ];
            break;

        case 'delete':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response['message'] = 'Jeton de sécurité invalide. Rechargez la page.';
                break;
            }

            $module_id = (int)($_POST['module_id'] ?? 0);
            if (empty($module_id)) {
                $response['message'] = 'Module invalide.';
                break;
            }

            // Détache les cours du module avant suppression (ne supprime pas les cours eux-mêmes)
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE courses SET module_id = NULL WHERE module_id = ?");
            $stmt->execute([$module_id]);

            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $success = $stmt->execute([$module_id]);
            $pdo->commit();

            $response = [
                'success' => $success,
                'message' => $success ? ' Module supprimé. Les cours associés ont été détachés.' : 'Erreur lors de la suppression.'
            ];
            break;

        case 'assign_courses':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response['message'] = 'Jeton de sécurité invalide. Rechargez la page.';
                break;
            }

            $module_id = (int)($_POST['module_id'] ?? 0);
            $course_ids = $_POST['course_ids'] ?? []; // tableau d'IDs cochés

            if (empty($module_id)) {
                $response['message'] = 'Module invalide.';
                break;
            }

            if (!is_array($course_ids)) {
                $course_ids = [];
            }
            $course_ids = array_map('intval', $course_ids);

            $pdo->beginTransaction();

            // 1. Détache tous les cours actuellement liés à ce module (on va réassigner depuis zéro)
            $stmt = $pdo->prepare("UPDATE courses SET module_id = NULL WHERE module_id = ?");
            $stmt->execute([$module_id]);

            // 2. Rattache uniquement les cours cochés
            if (!empty($course_ids)) {
                $placeholders = implode(',', array_fill(0, count($course_ids), '?'));
                $stmt = $pdo->prepare("UPDATE courses SET module_id = ? WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$module_id], $course_ids));
            }

            $pdo->commit();

            $response = [
                'success' => true,
                'message' => ' Association des cours mise à jour.'
            ];
            break;

        default:
            $response['message'] = 'Action non reconnue.';
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Erreur serveur : ' . $e->getMessage();
}

echo json_encode($response);