<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Tableau de bord - Étudiant";
require_once '../includes/header.php';

// Cours auxquels l'étudiant est inscrit, avec progression réelle (leçons "termine" = vues + quiz réussi)
$stmt = $pdo->prepare("
    SELECT c.*, e.statut AS enrollment_statut,
           (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS total_lessons,
           (SELECT COUNT(*) FROM lesson_progress lp
            WHERE lp.student_id = ? AND lp.statut = 'termine'
              AND lp.lesson_id IN (SELECT id FROM lessons WHERE course_id = c.id)) AS completed
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND c.statut = 'publie'
    ORDER BY e.date_inscription DESC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<h1>Tableau de bord</h1>

<?php if (empty($courses)): ?>
    <p style="color:#64748B;">
        Vous n'êtes inscrit à aucun cours pour le moment.
        <a href="catalogue.php" style="color:#4F46E5; font-weight:600;">Parcourir le catalogue →</a>
    </p>
<?php endif; ?>

<div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:24px;">
    <?php foreach ($courses as $course):
        $progress = $course['total_lessons'] > 0
            ? round(($course['completed'] / $course['total_lessons']) * 100)
            : 0;
    ?>
    <div class="course-card">
        <?php if (!empty($course['thumbnail'])): ?>
        <img src="../assets/uploads/<?= htmlspecialchars($course['thumbnail']) ?>"
             alt="<?= htmlspecialchars($course['titre']) ?>"
             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <?php endif; ?>
        <div style="width:100%; height:180px; background:linear-gradient(135deg, #4F46E5, #818CF8); display:<?= !empty($course['thumbnail']) ? 'none' : 'flex' ?>; align-items:center; justify-content:center; color:white; font-size:32px; font-weight:700;">
            <?= htmlspecialchars(mb_substr($course['titre'], 0, 1)) ?>
        </div>
        <div style="padding:18px;">
            <h3 style="margin-bottom:10px;"><?= htmlspecialchars($course['titre']) ?></h3>
            <div class="progress-bar">
                <div class="progress" style="width: <?= $progress ?>%"></div>
            </div>
            <span style="font-size:13px; color:#64748B;">
                <?= $progress ?>% complété (<?= (int)$course['completed'] ?>/<?= (int)$course['total_lessons'] ?> leçons)
            </span>
            <a href="course-view.php?id=<?= $course['id'] ?>" class="btn" style="display:block; text-align:center; text-decoration:none; margin-top:14px;">
                Continuer
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once '../includes/footer.php'; ?>