<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Suivi des étudiants";
require_once 'header.php';

// Exemple de requête
$stmt = $pdo->prepare("
    SELECT u.prenom, u.nom, c.titre, 
           COUNT(lp.id) as completed_lessons
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN lesson_progress lp ON lp.student_id = u.id
    WHERE c.enseignant_id = ?
    GROUP BY u.id, c.id
");
$stmt->execute([$_SESSION['user_id']]);
$progress = $stmt->fetchAll();
?>

<h1>Suivi des étudiants</h1>

<table>
    <thead><tr><th>Étudiant</th><th>Cours</th><th>Progression</th></tr></thead>
    <tbody>
        <?php foreach ($progress as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['prenom'].' '.$p['nom']) ?></td>
            <td><?= htmlspecialchars($p['titre']) ?></td>
            <td><?= $p['completed_lessons'] ?> leçons terminées</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>