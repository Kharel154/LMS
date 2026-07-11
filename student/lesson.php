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

// Récupérer la leçon + vérification inscription
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

// Marque comme "en_cours"
$stmt = $pdo->prepare("
    INSERT INTO lesson_progress (student_id, lesson_id, statut)
    VALUES (?, ?, 'en_cours')
    ON DUPLICATE KEY UPDATE statut = IF(statut = 'termine', 'termine', 'en_cours')
");
$stmt->execute([$_SESSION['user_id'], $lesson_id]);

// Quiz associé
$stmt = $pdo->prepare("SELECT id, titre, note_passage FROM quizzes WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$quiz = $stmt->fetch();

$title = "Leçon";
require_once '../includes/header.php';
?>

<p><a href="course-view.php?id=<?= $lesson['course_id'] ?>" style="color:#64748B; text-decoration:none;">← Retour au cours</a></p>
<h2><?= htmlspecialchars($lesson['titre']) ?></h2>
<p style="color:#64748B; margin-bottom:20px;"><?= htmlspecialchars($lesson['course_title']) ?></p>

<div class="lesson-content" style="margin-bottom:30px;">
    <?php if ($lesson['type'] === 'video'): ?>
        <div style="position:relative; padding-top:56.25%; background:#000; border-radius:8px; overflow:hidden;">
            <video id="lesson-video" controls style="position:absolute; top:0; left:0; width:100%; height:100%;">
                <source src="../assets/uploads/videos/<?= htmlspecialchars($lesson['fichier_url']) ?>" type="video/mp4">
            </video>
        </div>
    <?php else: ?>
        <embed src="../assets/uploads/pdfs/<?= htmlspecialchars($lesson['fichier_url']) ?>"
               width="100%" height="700px" type="application/pdf">
    <?php endif; ?>
</div>

<?php if ($quiz): ?>
    <div id="quiz-section" style="background:white; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.05); text-align:center;">
        <p style="margin-bottom:15px;">
            <strong>Évaluation :</strong> <?= htmlspecialchars($quiz['titre']) ?> 
            (note de passage : <?= (int)$quiz['note_passage'] ?>%)
        </p>
        <a id="quiz-btn" href="quiz-attempt.php?lesson_id=<?= $lesson['id'] ?>" 
           class="btn btn-success" style="text-decoration:none; pointer-events:none; opacity:0.6;">
            Passer l'évaluation
        </a>
        <p id="quiz-hint" style="color:#64748B; font-size:0.9em; margin-top:10px;">
            <?= $lesson['type'] === 'video' ? 'Regardez la vidéo jusqu’à la fin pour débloquer l’évaluation.' : 'Cliquez sur "J’ai lu le document" pour débloquer.' ?>
        </p>
    </div>
<?php else: ?>
    <p style="color:#64748B;">Aucune évaluation associée à cette leçon.</p>
<?php endif; ?>

<div id="toast" class="toast"></div>

<script>
const quizBtn = document.getElementById('quiz-btn');
const quizHint = document.getElementById('quiz-hint');

// === VIDÉO ===
if (document.getElementById('lesson-video')) {
    const video = document.getElementById('lesson-video');
    
    video.addEventListener('ended', () => {
        if (quizBtn) {
            quizBtn.style.pointerEvents = 'auto';
            quizBtn.style.opacity = '1';
            quizHint.textContent = 'Vous pouvez maintenant passer l’évaluation !';
        }
    });

    // Option : débloquer aussi après 95% de la vidéo
    video.addEventListener('timeupdate', () => {
        if (video.duration && video.currentTime / video.duration > 0.95) {
            if (quizBtn) {
                quizBtn.style.pointerEvents = 'auto';
                quizBtn.style.opacity = '1';
            }
        }
    });
}

// === PDF ===
else {
    // Pour les PDFs : bouton "J'ai lu"
    const pdfContainer = document.querySelector('.lesson-content');
    if (pdfContainer) {
        const readBtn = document.createElement('button');
        readBtn.textContent = " J'ai lu le document";
        readBtn.style = 'margin-top:15px; padding:10px 20px; background:#10B981; color:white; border:none; border-radius:6px; cursor:pointer;';
        pdfContainer.appendChild(readBtn);

        readBtn.addEventListener('click', () => {
            readBtn.disabled = true;
            readBtn.textContent = "✓ Document lu";
            if (quizBtn) {
                quizBtn.style.pointerEvents = 'auto';
                quizBtn.style.opacity = '1';
                quizHint.textContent = 'Vous pouvez maintenant passer l’évaluation !';
            }
        });
    }
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `toast ${type}`;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>