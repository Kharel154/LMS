<?php
// api/analytics.php
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

try {
    switch ($action) {

        case 'dashboard_stats':

            // 1. Inscriptions par mois (année en cours)
            $stmt = $pdo->query("
                SELECT MONTH(date_inscription) AS mois, COUNT(*) AS nb
                FROM enrollments
                WHERE YEAR(date_inscription) = YEAR(NOW())
                GROUP BY MONTH(date_inscription)
                ORDER BY mois ASC
            ");
            $rows = $stmt->fetchAll();
            $moisNoms = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
            $monthly = array_fill(1, 12, 0);
            foreach ($rows as $r) {
                $monthly[(int)$r['mois']] = (int)$r['nb'];
            }
            $monthlyLabels = [];
            $monthlyData = [];
            foreach ($monthly as $m => $nb) {
                $monthlyLabels[] = $moisNoms[$m];
                $monthlyData[] = $nb;
            }

            // 2. Répartition des cours par statut
            $stmt = $pdo->query("
                SELECT statut, COUNT(*) AS nb
                FROM courses
                GROUP BY statut
            ");
            $statutLabelsMap = [
                'publie' => 'Publié',
                'en_attente' => 'En attente',
                'brouillon' => 'Brouillon',
                'rejete' => 'Rejeté'
            ];
            $coursesByStatus = ['publie' => 0, 'en_attente' => 0, 'brouillon' => 0, 'rejete' => 0];
            foreach ($stmt->fetchAll() as $r) {
                if (isset($coursesByStatus[$r['statut']])) {
                    $coursesByStatus[$r['statut']] = (int)$r['nb'];
                }
            }

            // 3. Taux de réussite global des quiz
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) AS total_tentatives,
                    SUM(passed) AS total_reussies
                FROM quiz_attempts
            ");
            $quizRow = $stmt->fetch();
            $totalTentatives = (int)($quizRow['total_tentatives'] ?? 0);
            $totalReussies = (int)($quizRow['total_reussies'] ?? 0);
            $tauxReussite = $totalTentatives > 0 ? round(($totalReussies / $totalTentatives) * 100, 1) : null;

            // 4. Top 5 modules par nombre d'étudiants distincts inscrits
            $stmt = $pdo->query("
                SELECT m.id, m.nom, COUNT(DISTINCT e.student_id) AS nb_etudiants
                FROM modules m
                JOIN courses c ON c.module_id = m.id
                JOIN enrollments e ON e.course_id = c.id
                GROUP BY m.id, m.nom
                ORDER BY nb_etudiants DESC
                LIMIT 5
            ");
            $topModules = $stmt->fetchAll();

            // 5. Top 5 enseignants par nombre de cours publiés
            $stmt = $pdo->query("
                SELECT u.id, u.prenom, u.nom, COUNT(c.id) AS nb_cours
                FROM users u
                JOIN courses c ON c.enseignant_id = u.id AND c.statut = 'publie'
                WHERE u.role = 'teacher'
                GROUP BY u.id, u.prenom, u.nom
                ORDER BY nb_cours DESC
                LIMIT 5
            ");
            $topTeachers = $stmt->fetchAll();

            // 6. Dernières connexions
            $stmt = $pdo->query("
                SELECT cl.date_connexion, cl.action, cl.ip_address, u.prenom, u.nom, u.role
                FROM connection_logs cl
                LEFT JOIN users u ON cl.user_id = u.id
                ORDER BY cl.date_connexion DESC
                LIMIT 10
            ");
            $recentConnections = $stmt->fetchAll();

            $response = [
                'success' => true,
                'monthly_enrollments' => [
                    'labels' => $monthlyLabels,
                    'data' => $monthlyData
                ],
                'courses_by_status' => [
                    'labels' => array_values($statutLabelsMap),
                    'data' => array_values($coursesByStatus)
                ],
                'quiz_success' => [
                    'total_tentatives' => $totalTentatives,
                    'total_reussies' => $totalReussies,
                    'taux' => $tauxReussite
                ],
                'top_modules' => $topModules,
                'top_teachers' => $topTeachers,
                'recent_connections' => $recentConnections
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