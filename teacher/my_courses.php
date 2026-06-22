<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Mes cours";
require_once 'header.php';

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(e.id) as inscriptions 
    FROM courses c 
    LEFT JOIN enrollments e ON c.id = e.course_id 
    WHERE c.enseignant_id = ? 
    GROUP BY c.id
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<h1>Mes cours</h1>

<table>
    <thead>
        <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Inscrits</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($courses as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['titre']) ?></td>
            <td><span class="badge <?= $c['statut'] ?>"><?= $c['statut'] ?></span></td>
            <td><?= $c['inscriptions'] ?></td>
            <td>
                <a href="course_builder.php?id=<?= $c['id'] ?>" class="btn-small">Modifier</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>