<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Gestion des Modules";
require_once 'header.php';

$stmt = $pdo->query("SELECT m.*, c.nom as categorie FROM modules m JOIN categories c ON m.categorie_id = c.id");
$modules = $stmt->fetchAll();
?>

<h1>Modules de formation</h1>

<button onclick="createModule()">Nouveau module</button>

<table>
    <thead><tr><th>Nom</th><th>Catégorie</th><th>Actions</th></tr></thead>
    <tbody>
        <?php foreach ($modules as $m): ?>
        <tr>
            <td><?= htmlspecialchars($m['nom']) ?></td>
            <td><?= htmlspecialchars($m['categorie']) ?></td>
            <td><button>Associer cours</button></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>