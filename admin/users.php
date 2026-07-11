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

<h1>Gestion des Utilisateurs</h1>

<table class="users-table">
    <thead>
        <tr>
            <th>Nom Complet</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
        <tr id="user-row-<?= $u['id'] ?>">
            <td><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><strong><?= ucfirst($u['role']) ?></strong></td>
            <td>
                <span class="badge <?= $u['statut'] === 'actif' ? 'success' : 'danger' ?>">
                    <?= ucfirst($u['statut']) ?>
                </span>
            </td>
            <td>
                <button onclick="changeRole(<?= $u['id'] ?>, '<?= $u['role'] ?>')" 
                        class="btn-small">Changer Rôle</button>
                <button onclick="toggleSuspend(<?= $u['id'] ?>)" 
                        class="btn-small <?= $u['statut'] === 'actif' ? 'warning' : 'success' ?>">
                    <?= $u['statut'] === 'actif' ? 'Suspendre' : 'Activer' ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
// Changer le rôle
async function changeRole(userId, currentRole) {
    const newRole = prompt(`Changer le rôle de l'utilisateur (actuel: ${currentRole})\n\nEntrez le nouveau rôle (student / teacher / admin):`, currentRole);
    
    if (!newRole || !['student','teacher','admin'].includes(newRole)) {
        alert("Rôle invalide !");
        return;
    }

    if (!confirm(`Changer le rôle en "${newRole}" ?`)) return;

    const res = await postData('../api/users.php', {
        action: 'change_role',
        user_id: userId,
        new_role: newRole
    });

    if (res.success) {
        showToast(res.message, 'success');
        location.reload();
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}

// Suspendre / Activer
async function toggleSuspend(userId) {
    if (!confirm('Confirmer cette action ?')) return;

    const res = await postData('../api/users.php', {
        action: 'toggle_status',
        user_id: userId
    });

    if (res.success) {
        showToast(res.message, 'success');
        location.reload();
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>