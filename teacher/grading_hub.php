<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Corrections";
require_once 'header.php';

$stmt = $pdo->prepare("
    SELECT s.*, a.titre as assignment_title, u.prenom, u.nom 
    FROM assignment_submissions s 
    JOIN assignments a ON s.assignment_id = a.id 
    JOIN users u ON s.student_id = u.id 
    WHERE s.note IS NULL
");
$stmt->execute();
$submissions = $stmt->fetchAll();
?>

<h1>Devoirs à corriger</h1>

<table>
    <thead>
        <tr><th>Étudiant</th><th>Devoir</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
        <?php foreach ($submissions as $sub): ?>
        <tr>
            <td><?= htmlspecialchars($sub['prenom'].' '.$sub['nom']) ?></td>
            <td><?= htmlspecialchars($sub['assignment_title']) ?></td>
            <td><?= $sub['date_soumission'] ?></td>
            <td>
                <button onclick="gradeSubmission(<?= $sub['id'] ?>)">Noter</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php require_once '../includes/footer.php'; ?>