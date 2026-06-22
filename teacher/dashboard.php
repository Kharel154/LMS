<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Tableau de bord Enseignant";
require_once 'header.php';

// Statistiques rapides
$stmt = $pdo->prepare("SELECT COUNT(*) as total_courses FROM courses WHERE enseignant_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_courses = $stmt->fetch()['total_courses'];

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id) as total_students 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.enseignant_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$total_students = $stmt->fetch()['total_students'];
?>

<h1>Tableau de bord</h1>

<div class="stats-grid">
    <div class="stat-card">
        <h3><?= $total_courses ?></h3>
        <p>Cours créés</p>
    </div>
    <div class="stat-card">
        <h3><?= $total_students ?></h3>
        <p>Étudiants inscrits</p>
    </div>
</div>

<!-- Devoirs à corriger -->
<h2>Devoirs en attente</h2>
<!-- Liste chargée via AJAX ou PHP -->

<?php require_once '../includes/footer.php'; ?>