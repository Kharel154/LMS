<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Dashboard Admin";
require_once 'header.php';

// KPIs principaux
$stmt = $pdo->query("SELECT 
    (SELECT COUNT(*) FROM users WHERE role='student') as students,
    (SELECT COUNT(*) FROM users WHERE role='teacher') as teachers,
    (SELECT COUNT(*) FROM courses WHERE statut='publie') as published_courses,
    (SELECT COUNT(*) FROM certificates) as certificates,
    (SELECT COUNT(*) FROM courses WHERE statut='en_attente') as pending_courses,
    (SELECT COUNT(*) FROM reports WHERE statut='ouvert') as open_reports
");
$kpis = $stmt->fetch();

// Inscriptions par mois (année en cours) — remplace les données statiques [45, 67, 89]
$stmt = $pdo->query("
    SELECT MONTH(date_inscription) AS mois, COUNT(*) AS nb
    FROM enrollments
    WHERE YEAR(date_inscription) = YEAR(NOW())
    GROUP BY MONTH(date_inscription)
    ORDER BY mois ASC
");
$moisNoms = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
$monthly = array_fill(1, 12, 0);
foreach ($stmt->fetchAll() as $r) {
    $monthly[(int)$r['mois']] = (int)$r['nb'];
}
$chartLabels = [];
$chartData = [];
foreach ($monthly as $m => $nb) {
    $chartLabels[] = $moisNoms[$m];
    $chartData[] = $nb;
}
?>

<style>
.dash-alert-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.dash-alert-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: white;
    padding: 20px 24px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    text-decoration: none;
    color: inherit;
    border-left: 4px solid #E2E8F0;
    transition: box-shadow 0.2s, transform 0.2s;
}
.dash-alert-card:hover { box-shadow: 0 6px 20px rgba(0,0,0,0.12); transform: translateY(-1px); }
.dash-alert-card.warning { border-left-color: #F59E0B; }
.dash-alert-card.danger { border-left-color: #EF4444; }
.dash-alert-card h2 { margin: 0; font-size: 26px; color: #1E293B; }
.dash-alert-card p { margin: 2px 0 0; color: #64748B; font-size: 13.5px; }
.dash-alert-card span.arrow { color: #94A3B8; font-size: 18px; }
</style>

<h1>Tableau de bord Administrateur</h1>

<div class="kpi-grid">
    <div class="kpi-card"><h2><?= $kpis['students'] ?></h2><p>Étudiants</p></div>
    <div class="kpi-card"><h2><?= $kpis['teachers'] ?></h2><p>Enseignants</p></div>
    <div class="kpi-card"><h2><?= $kpis['published_courses'] ?></h2><p>Cours publiés</p></div>
    <div class="kpi-card"><h2><?= $kpis['certificates'] ?></h2><p>Certificats</p></div>
</div>

<div class="dash-alert-grid">
    <a href="course_validation.php" class="dash-alert-card <?= $kpis['pending_courses'] > 0 ? 'warning' : '' ?>">
        <div>
            <h2><?= $kpis['pending_courses'] ?></h2>
            <p>Cours en attente de validation</p>
        </div>
        <span class="arrow">→</span>
    </a>
    <a href="reports.php" class="dash-alert-card <?= $kpis['open_reports'] > 0 ? 'danger' : '' ?>">
        <div>
            <h2><?= $kpis['open_reports'] ?></h2>
            <p>Signalements ouverts</p>
        </div>
        <span class="arrow">→</span>
    </a>
</div>

<div style="background:white; border-radius:12px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
    <h3 style="margin:0 0 16px; font-size:16px; color:#1E293B;">Inscriptions par mois</h3>
    <canvas id="inscriptionsChart" height="90"></canvas>
</div>

<script>
    new Chart(document.getElementById('inscriptionsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Inscriptions',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: '#818CF8'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
</script>

<?php require_once '../includes/footer.php'; ?>