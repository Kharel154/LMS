<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Dashboard Admin";
require_once 'header.php';

// KPIs
$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE role='student') as students,
    (SELECT COUNT(*) FROM users WHERE role='teacher') as teachers,
    (SELECT COUNT(*) FROM courses WHERE statut='publie') as published_courses,
    (SELECT COUNT(*) FROM certificates) as certificates
");
$kpis = $stmt->fetch();
?>

<h1>Tableau de bord Administrateur</h1>

<div class="kpi-grid">
    <div class="kpi-card"><h2><?= $kpis['students'] ?></h2><p>Étudiants</p></div>
    <div class="kpi-card"><h2><?= $kpis['teachers'] ?></h2><p>Enseignants</p></div>
    <div class="kpi-card"><h2><?= $kpis['published_courses'] ?></h2><p>Cours publiés</p></div>
    <div class="kpi-card"><h2><?= $kpis['certificates'] ?></h2><p>Certificats</p></div>
</div>

<canvas id="inscriptionsChart" width="400" height="200"></canvas>

<script>
    // Exemple Chart.js
    new Chart(document.getElementById('inscriptionsChart'), {
        type: 'bar',
        data: { labels: ['Jan','Fév','Mar'], datasets: [{ label: 'Inscriptions', data: [45,67,89] }] }
    });
</script>

<?php require_once '../includes/footer.php'; ?>