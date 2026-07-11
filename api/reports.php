<?php
// api/reports.php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

redirect_if_not_logged('admin'); // session_start() géré ici

header('Content-Type: application/json');
header('Cache-Control: no-cache');

ini_set('display_errors', 0);
error_reporting(E_ALL);

set_error_handler(function ($severity, $msg, $file, $line) {
    error_log("PHP Error: $msg in $file:$line");
    return false;
});

$response = ['success' => false, 'message' => 'Action invalide'];
$action = $_REQUEST['action'] ?? '';

$STATUTS_VALIDES = ['ouvert', 'en_cours', 'resolu'];

try {
    switch ($action) {

        case 'list':
            $stmt = $pdo->query("
                SELECT r.*, u.prenom, u.nom, u.email, u.role AS reporter_role
                FROM reports r
                JOIN users u ON r.reporter_id = u.id
                ORDER BY 
                    FIELD(r.statut, 'ouvert', 'en_cours', 'resolu'),
                    r.date_creation DESC
            ");
            $response = [
                'success' => true,
                'reports' => $stmt->fetchAll()
            ];
            break;

        case 'update_status':
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                throw new Exception('Jeton de sécurité invalide. Rechargez la page.');
            }

            $report_id = (int)($_POST['report_id'] ?? 0);
            $statut = $_POST['statut'] ?? '';

            if (!in_array($statut, $STATUTS_VALIDES, true)) {
                throw new Exception('Statut invalide.');
            }
            if ($report_id <= 0) {
                throw new Exception('Signalement introuvable.');
            }

            $stmt = $pdo->prepare("UPDATE reports SET statut = ? WHERE id = ?");
            $success = $stmt->execute([$statut, $report_id]);

            $response = [
                'success' => $success,
                'message' => $success ? 'Statut mis à jour.' : 'Erreur lors de la mise à jour.'
            ];
            break;

        default:
            $response['message'] = 'Action non reconnue.';
    }
} catch (Throwable $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;