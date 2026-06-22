<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Validation des cours";
require_once 'header.php';

$stmt = $pdo->prepare("SELECT c.*, u.prenom, u.nom FROM courses c 
                       JOIN users u ON c.enseignant_id = u.id 
                       WHERE c.statut = 'en_attente'");
$stmt->execute();
$courses = $stmt->fetchAll();
?>

<h1>Cours en attente de validation</h1>

<?php foreach ($courses as $c): ?>
<div class="course-review">
    <h3><?= htmlspecialchars($c['titre']) ?> — par <?= htmlspecialchars($c['prenom'].' '.$c['nom']) ?></h3>
    <button onclick="validateCourse(<?= $c['id'] ?>, 'publie')">✅ Approuver</button>
    <button onclick="validateCourse(<?= $c['id'] ?>, 'rejete')">❌ Rejeter</button>
</div>
<?php endforeach; ?>

<script>
async function validateCourse(id, status) {
    const res = await postData('../api/courses.php', { action: 'validate', course_id: id, statut: status });
    if (res.success) location.reload();
}
</script>

<?php require_once '../includes/footer.php'; ?>