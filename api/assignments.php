<?php
// api/assignments.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Action non définie'];

$action = $_REQUEST['action'] ?? '';

// Dossier de dépôt des devoirs étudiants
$uploadDir = '../assets/uploads/assignments/';
$allowedExtensions = ['pdf', 'zip', 'doc', 'docx'];
$maxFileSize = 10 * 1024 * 1024; // 10 Mo

switch ($action) {

    // === Étudiant : dépôt d'un fichier pour un devoir précis =================
    case 'submit':
        if (!has_role('student')) {
            $response['message'] = 'Accès réservé aux étudiants.';
            break;
        }

        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $response['message'] = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
            break;
        }

        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $studentId = $_SESSION['user_id'];

        if (!$assignmentId) {
            $response['message'] = 'Devoir invalide.';
            break;
        }

        // Vérifie que l'étudiant est bien inscrit au cours auquel appartient ce devoir
        $stmt = $pdo->prepare("
            SELECT a.id
            FROM assignments a
            JOIN lessons l ON a.lesson_id = l.id
            JOIN courses c ON l.course_id = c.id
            JOIN enrollments e ON e.course_id = c.id
            WHERE a.id = ? AND e.student_id = ?
        ");
        $stmt->execute([$assignmentId, $studentId]);

        if (!$stmt->fetch()) {
            $response['message'] = "Ce devoir ne correspond à aucun cours auquel vous êtes inscrit.";
            break;
        }

        // Une soumission déjà notée ne peut plus être remplacée
        $stmt = $pdo->prepare("SELECT id, note FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
        $stmt->execute([$assignmentId, $studentId]);
        $existing = $stmt->fetch();

        if ($existing && $existing['note'] !== null) {
            $response['message'] = 'Ce devoir a déjà été corrigé ; vous ne pouvez plus le modifier.';
            break;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = "Aucun fichier valide n'a été reçu.";
            break;
        }

        $file = $_FILES['file'];

        if ($file['size'] > $maxFileSize) {
            $response['message'] = 'Le fichier dépasse la taille maximale autorisée (10 Mo).';
            break;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $response['message'] = 'Format non autorisé. Formats acceptés : ' . implode(', ', $allowedExtensions) . '.';
            break;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'devoir_' . $studentId . '_' . $assignmentId . '_' . uniqid() . '.' . $ext;
        $destination = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $response['message'] = "Erreur lors de l'enregistrement du fichier sur le serveur.";
            break;
        }

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE assignment_submissions
                SET fichier_url = ?, date_soumission = NOW()
                WHERE id = ?
            ");
            $success = $stmt->execute([$fileName, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO assignment_submissions (assignment_id, student_id, fichier_url, date_soumission)
                VALUES (?, ?, ?, NOW())
            ");
            $success = $stmt->execute([$assignmentId, $studentId, $fileName]);
        }

        $response = [
            'success' => $success,
            'message' => $success ? 'Devoir envoyé avec succès.' : "Erreur lors de l'enregistrement en base de données.",
        ];
        break;
}

echo json_encode($response);