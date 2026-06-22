<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Gestion des utilisateurs";
require_once 'header.php';

$stmt = $pdo->query("SELECT * FROM users ORDER BY date_inscription DESC");
$users = $stmt->fetchAll();
?>

<h1>Utilisateurs</h1>

<table>
    <thead>
        <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Actions</th></tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['prenom'].' '.$u['nom']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['role'] ?></td>
            <td><span class="badge <?= $u['statut'] ?>"><?= $u['statut'] ?></span></td>
            <td>
                <button onclick="changeRole(<?= $u['id'] ?>)">Changer rôle</button>
                <button onclick="toggleSuspend(<?= $u['id'] ?>)">Suspendre</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>