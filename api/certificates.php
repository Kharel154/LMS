<?php
// api/certificates.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Action non définie'];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'my_certificates':
        if (has_role('student')) {
            $stmt = $pdo->prepare("
                SELECT cert.*, m.nom AS module_nom, m.description AS module_description
                FROM certificates cert
                JOIN modules m ON m.id = cert.module_id
                WHERE cert.student_id = ?
                ORDER BY cert.date_obtention DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $response['success'] = true;
            $response['certificates'] = $stmt->fetchAll();
        }
        break;

    case 'list_all': // admin
        if (has_role('admin')) {
            $stmt = $pdo->query("
                SELECT cert.*, m.nom AS module_nom, u.nom, u.prenom
                FROM certificates cert
                JOIN modules m ON m.id = cert.module_id
                JOIN users u ON u.id = cert.student_id
                ORDER BY cert.date_obtention DESC
            ");
            $response['success'] = true;
            $response['certificates'] = $stmt->fetchAll();
        }
        break;

    case 'verify':
        // Vérification publique d'un certificat par son code (pas besoin d'être connecté)
        $code = sanitize_input($_GET['code'] ?? '');
        if (empty($code)) {
            $response['message'] = 'Code de vérification manquant.';
            break;
        }
        $stmt = $pdo->prepare("
            SELECT cert.code_verification, cert.date_obtention, m.nom AS module_nom,
                   u.nom, u.prenom
            FROM certificates cert
            JOIN modules m ON m.id = cert.module_id
            JOIN users u ON u.id = cert.student_id
            WHERE cert.code_verification = ?
        ");
        $stmt->execute([$code]);
        $cert = $stmt->fetch();

        if ($cert) {
            $response['success'] = true;
            $response['certificate'] = $cert;
        } else {
            $response['message'] = 'Aucun certificat trouvé pour ce code.';
        }
        break;
}

echo json_encode($response);