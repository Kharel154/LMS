<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Analytics";
require_once 'header.php';
?>

<h1>Statistiques globales</h1>

<canvas id="activityChart"></canvas>

<script>
    // Graphiques avancés via Chart.js
    new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: { /* données chargées via API */ }
    });
</script>

<?php require_once '../includes/footer.php'; ?>