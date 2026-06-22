<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$course_id = (int)($_GET['id'] ?? 0);

// Vérifie l'inscription
$stmt = $pdo->prepare("
    SELECT c.*, e.id AS enrollment_id
    FROM courses c
    JOIN enrollments e ON e.course_id = c.id
    WHERE c.id = ? AND e.student_id = ?
");
$stmt->execute([$course_id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if (!$course) {
    die("Vous n'êtes pas inscrit à ce cours, ou il n'existe pas. <a href='catalogue.php'>Retour au catalogue</a>");
}

$title = $course['titre'];
require_once '../includes/header.php';

// Leçons du cours + statut de progression + infos quiz (s'il existe) + meilleure tentative
$stmt = $pdo->prepare("
    SELECT l.*,
           lp.statut AS progress_statut,
           q.id AS quiz_id,
           q.note_passage,
           (SELECT MAX(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ?) AS best_score,
           (SELECT MAX(qa.passed) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.student_id = ?) AS quiz_passed
    FROM lessons l
    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = ?
    LEFT JOIN quizzes q ON q.lesson_id = l.id
    WHERE l.course_id = ?
    ORDER BY l.ordre ASC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $course_id]);
$lessons = $stmt->fetchAll();

$total = count($lessons);
$completed = 0;
foreach ($lessons as $l) {
    if ($l['progress_statut'] === 'termine') $completed++;
}
$progress = $total > 0 ? round(($completed / $total) * 100) : 0;
?>

<p><a href="dashboard.php" style="color:#64748B; text-decoration:none;">← Retour au tableau de bord</a></p>
<h1><?= htmlspecialchars($course['titre']) ?></h1>
<p style="color:#64748B; margin-bottom:20px;"><?= htmlspecialchars($course['description']) ?></p>

<div style="background:white; border-radius:12px; padding:20px; margin-bottom:30px; box-shadow:0 4px 15px rgba(0,0,0,0.06);">
    <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
        <strong>Progression globale</strong>
        <strong><?= $progress ?>%</strong>
    </div>
    <div class="progress-bar">
        <div class="progress" style="width: <?= $progress ?>%"></div>
    </div>
    <span style="font-size:13px; color:#64748B;"><?= $completed ?>/<?= $total ?> leçons terminées (vues + quiz réussi)</span>
</div>

<div class="lesson-list">
    <?php foreach ($lessons as $i => $lesson):
        $vue = in_array($lesson['progress_statut'], ['en_cours', 'termine']);
        $quizReussi = (bool)$lesson['quiz_passed'];
        $hasQuiz = !empty($lesson['quiz_id']);
    ?>
    <div style="background:white; border-radius:10px; padding:18px 22px; margin-bottom:14px; box-shadow:0 2px 8px rgba(0,0,0,0.05); display:flex; align-items:center; justify-content:space-between; gap:16px;">
        <div style="flex:1;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <span style="background:#EEF2FF; color:#4F46E5; font-weight:700; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px;">
                    <?= $i + 1 ?>
                </span>
                <strong><?= htmlspecialchars($lesson['titre']) ?></strong>
                <span style="font-size:12px; color:#94A3B8; text-transform:uppercase;"><?= htmlspecialchars($lesson['type']) ?></span>
            </div>

            <div style="display:flex; gap:18px; font-size:13px; margin-left:38px;">
                <span style="color:<?= $vue ? '#10B981' : '#94A3B8' ?>;">
                    <?= $vue ? '✓ Vue' : '○ Non vue' ?>
                </span>
                <?php if ($hasQuiz): ?>
                    <span style="color:<?= $quizReussi ? '#10B981' : ($lesson['best_score'] !== null ? '#EF4444' : '#94A3B8') ?>;">
                        <?php if ($quizReussi): ?>
                            ✓ Quiz réussi (<?= round($lesson['best_score']) ?>%)
                        <?php elseif ($lesson['best_score'] !== null): ?>
                            ✗ Quiz non réussi (<?= round($lesson['best_score']) ?>% — seuil <?= (int)$lesson['note_passage'] ?>%)
                        <?php else: ?>
                            ○ Quiz non tenté
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span style="color:#94A3B8;">— Pas d'évaluation</span>
                <?php endif; ?>
            </div>
        </div>

        <a href="lesson.php?id=<?= $lesson['id'] ?>" class="btn" style="text-decoration:none; white-space:nowrap;">
            <?= $vue ? 'Revoir' : 'Commencer' ?>
        </a>
    </div>
    <?php endforeach; ?>

    <?php if (empty($lessons)): ?>
        <p style="color:#64748B;">Ce cours ne contient pas encore de leçon.</p>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>