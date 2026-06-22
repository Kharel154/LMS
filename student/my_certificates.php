<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Mes certificats";
require_once '../includes/header.php';

$stmt = $pdo->prepare("
    SELECT c.*, m.nom as module_name 
    FROM certificates c 
    JOIN modules m ON c.module_id = m.id 
    WHERE c.student_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();
?>

<h1>Mes certificats</h1>

<div class="certificates-grid">
    <?php foreach ($certificates as $cert): ?>
    <div class="certificate-card">
        <h3><?= htmlspecialchars($cert['module_name']) ?></h3>
        <p>Obtenu le <?= $cert['date_obtention'] ?></p>
        <a href="#" onclick="downloadCertificate('<?= $cert['code_verification'] ?>')" 
           class="btn">Télécharger PDF</a>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../includes/footer.php'; ?>