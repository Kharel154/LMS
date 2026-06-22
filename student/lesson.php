<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$lesson_id = (int)($_GET['id'] ?? 0);
if (!$lesson_id) {
    header('Location: dashboard.php'); exit;
}

// Récupérer la leçon + cours, vérifier l'inscription en même temps
$stmt = $pdo->prepare("
    SELECT l.*, c.id AS course_id, c.titre AS course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    WHERE l.id = ?
");
$stmt->execute([$_SESSION['user_id'], $lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    die("Leçon introuvable ou vous n'êtes pas inscrit à ce cours.");
}

// Marque la leçon comme "en_cours" dès l'ouverture (si pas déjà "termine")
$stmt = $pdo->prepare("
    INSERT INTO lesson_progress (student_id, lesson_id, statut)
    VALUES (?, ?, 'en_cours')
    ON DUPLICATE KEY UPDATE statut = IF(statut = 'termine', 'termine', 'en_cours')
");
$stmt->execute([$_SESSION['user_id'], $lesson_id]);

// Vérifie si un quiz existe pour cette leçon
$stmt = $pdo->prepare("SELECT id, titre, note_passage FROM quizzes WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$quiz = $stmt->fetch();

$title = "Leçon";
require_once '../includes/header.php';
?>

<p><a href="course-view.php?id=<?= $lesson['course_id'] ?>" style="color:#64748B; text-decoration:none;">← Retour au cours</a></p>
<h2><?= htmlspecialchars($lesson['titre']) ?></h2>
<p style="color:#64748B; margin-bottom:20px;"><?= htmlspecialchars($lesson['course_title']) ?></p>

<div class="lesson-content" style="margin-bottom:20px;">
    <?php if ($lesson['type'] === 'video'): ?>
        <video controls width="100%">
            <source src="../assets/uploads/videos/<?= htmlspecialchars($lesson['fichier_url']) ?>" type="video/mp4">
        </video>
    <?php else: ?>
        <embed src="../assets/uploads/pdfs/<?= htmlspecialchars($lesson['fichier_url']) ?>"
               width="100%" height="700px" type="application/pdf">
    <?php endif; ?>
</div>

<?php if ($quiz): ?>
    <div style="background:white; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <p style="margin-bottom:12px;">
            Cette leçon est associée à une évaluation : <strong><?= htmlspecialchars($quiz['titre']) ?></strong>
            (note de passage : <?= (int)$quiz['note_passage'] ?>%)
        </p>
        <a href="quiz-attempt.php?lesson_id=<?= $lesson['id'] ?>" class="btn btn-success" style="text-decoration:none;">
            Passer l'évaluation
        </a>
    </div>
<?php else: ?>
    <p style="color:#64748B;">Aucune évaluation n'est associée à cette leçon.</p>
<?php endif; ?>

<div id="toast" class="toast"></div>

<script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>