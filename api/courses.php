<?php
// api/courses.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Action non définie'];

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'list':
        $stmt = $pdo->query("
            SELECT c.*, u.prenom, u.nom as nom_enseignant 
            FROM courses c 
            JOIN users u ON c.enseignant_id = u.id 
            WHERE c.statut = 'publie'
            ORDER BY c.date_creation DESC
        ");
        $response['success'] = true;
        $response['courses'] = $stmt->fetchAll();
        break;

    case 'my_courses': // Pour les enseignants
        if (has_role('teacher')) {
            $stmt = $pdo->prepare("
                SELECT c.*, COUNT(e.id) as nb_inscrits 
                FROM courses c 
                LEFT JOIN enrollments e ON c.id = e.course_id 
                WHERE c.enseignant_id = ? 
                GROUP BY c.id
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $response['success'] = true;
            $response['courses'] = $stmt->fetchAll();
        }
        break;

    case 'create':
        if (has_role('teacher')) {
            $titre = sanitize_input($_POST['titre'] ?? '');
            $description = sanitize_input($_POST['description'] ?? '');

            $stmt = $pdo->prepare("
                INSERT INTO courses (titre, description, enseignant_id, statut, categorie_id) 
                VALUES (?, ?, ?, 'brouillon', 1)
            ");
            $success = $stmt->execute([$titre, $description, $_SESSION['user_id']]);

            $response = [
                'success' => $success,
                'message' => $success ? 'Cours créé avec succès' : 'Erreur lors de la création',
                'course_id' => $success ? $pdo->lastInsertId() : null
            ];
        }
        break;

    case 'enroll':
        if (has_role('student')) {
            $course_id = (int)($_POST['course_id'] ?? 0);
            $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $success = $stmt->execute([$_SESSION['user_id'], $course_id]);
            $response = ['success' => $success, 'message' => $success ? 'Inscription réussie !' : 'Vous êtes déjà inscrit'];
        }
        break;

    // === NOUVEAU : liste enrichie des cours en attente de validation ===
    // Utilisé par admin/course_validation.php (chargement AJAX, sans rechargement de page)
    case 'pending':
        if (has_role('admin')) {
            $stmt = $pdo->query("
                SELECT c.*, 
                       u.prenom, u.nom AS nom_enseignant,
                       m.nom AS module_nom,
                       (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS nb_lecons
                FROM courses c
                JOIN users u ON c.enseignant_id = u.id
                LEFT JOIN modules m ON c.module_id = m.id
                WHERE c.statut = 'en_attente'
                ORDER BY c.date_creation ASC
            ");
            $response['success'] = true;
            $response['courses'] = $stmt->fetchAll();
        } else {
            $response['message'] = 'Accès réservé aux administrateurs.';
        }
        break;

    case 'validate':
        if (has_role('admin')) {
            // Vérification CSRF ajoutée (absente jusqu'ici) — sécurise une action d'écriture admin
            if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
                $response['message'] = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
                break;
            }
            $course_id = (int)($_POST['course_id'] ?? 0);
            $statut = $_POST['statut'] ?? 'rejete';
            if (!in_array($statut, ['publie', 'rejete'], true)) {
                $response['message'] = 'Statut invalide.';
                break;
            }
            $stmt = $pdo->prepare("UPDATE courses SET statut = ?, date_publication = IF(? = 'publie', NOW(), date_publication) WHERE id = ?");
            $success = $stmt->execute([$statut, $statut, $course_id]);
            $response = [
                'success' => $success,
                'message' => $success
                    ? ($statut === 'publie' ? 'Cours approuvé et publié.' : 'Cours rejeté.')
                    : 'Erreur lors de la mise à jour.'
            ];
        }
        break;
}

echo json_encode($response);