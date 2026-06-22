<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Mes notes";
require_once '../includes/header.php';

$stmt = $pdo->prepare("
    SELECT a.titre as assignment, s.note, s.commentaire_prof, s.date_correction
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    WHERE s.student_id = ?
    ORDER BY s.date_correction DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notes = $stmt->fetchAll();
?>

<h1>Mes évaluations</h1>

<table>
    <thead>
        <tr><th>Devoir</th><th>Note</th><th>Commentaire</th><th>Date</th></tr>
    </thead>
    <tbody>
        <?php foreach ($notes as $n): ?>
        <tr>
            <td><?= htmlspecialchars($n['assignment']) ?></td>
            <td><?= $n['note'] ? $n['note'].'/100' : 'En attente' ?></td>
            <td><?= htmlspecialchars($n['commentaire_prof'] ?? '-') ?></td>
            <td><?= $n['date_correction'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>