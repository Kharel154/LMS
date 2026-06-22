<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Signalements";
require_once 'header.php';

$stmt = $pdo->query("SELECT r.*, u.prenom FROM reports r JOIN users u ON r.reporter_id = u.id");
$reports = $stmt->fetchAll();
?>

<h1>Signalements</h1>

<table>
    <thead><tr><th>Utilisateur</th><th>Type</th><th>Description</th><th>Statut</th></tr></thead>
    <tbody>
        <?php foreach ($reports as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['prenom']) ?></td>
            <td><?= $r['type'] ?></td>
            <td><?= htmlspecialchars($r['description']) ?></td>
            <td><?= $r['statut'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>