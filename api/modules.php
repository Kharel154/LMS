<?php
// === UPDATED FILE: api/modules.php ===
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

redirect_if_not_logged('admin');   // session_start() géré ici

header('Content-Type: application/json');
header('Cache-Control: no-cache');

ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function($severity, $msg, $file, $line) {
    error_log("PHP Error: $msg in $file:$line");
    return false;
});

$response = ['success' => false, 'message' => 'Action invalide'];

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT m.*, cat.nom AS categorie_nom,
                       (SELECT COUNT(*) FROM courses WHERE module_id = m.id) AS nb_cours,
                       (SELECT COUNT(*) FROM certificates WHERE module_id = m.id) AS nb_certificats
                FROM modules m
                LEFT JOIN categories cat ON cat.id = m.categorie_id
                ORDER BY m.date_creation DESC
            ");
            $response = [
                'success' => true,
                'modules' => $stmt->fetchAll()
            ];
            break;

        // ... autres cases (get_courses_for_module, update, delete, assign_courses) restent inchangés ...

        case 'create':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Jeton CSRF invalide. Rechargez la page.');
            }

            $nom = sanitize_input($_POST['nom'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');
            $categorie_id = !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null;

            if (empty($nom)) {
                throw new Exception('Le titre du module est obligatoire.');
            }

            $stmt = $pdo->prepare("INSERT INTO modules (nom, description, categorie_id, admin_id, date_creation) 
                                  VALUES (?, ?, ?, ?, NOW())");
            $success = $stmt->execute([$nom, $description, $categorie_id, $_SESSION['user_id']]);

            $response = [
                'success' => $success,
                'message' => $success ? 'Module créé avec succès !' : 'Erreur lors de l\'insertion.'
            ];
            break;

        default:
            $response['message'] = 'Action non reconnue.';
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;