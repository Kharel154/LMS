<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$lesson_id = (int)($_GET['lesson_id'] ?? 0);

// Vérifie l'inscription au cours de cette leçon + récupère le quiz
$stmt = $pdo->prepare("
    SELECT l.id AS lesson_id, l.titre AS lesson_titre, l.course_id,
           q.id AS quiz_id, q.titre AS quiz_titre, q.note_passage
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    JOIN quizzes q ON q.lesson_id = l.id
    WHERE l.id = ?
");
$stmt->execute([$_SESSION['user_id'], $lesson_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die("Évaluation introuvable, ou vous n'êtes pas inscrit à ce cours.");
}

// Questions + choix (sans révéler is_correct au front)
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY ordre ASC");
$stmt->execute([$quiz['quiz_id']]);
$questions = $stmt->fetchAll();

foreach ($questions as &$q) {
    $stmtC = $pdo->prepare("SELECT id, choice_text FROM quiz_choices WHERE question_id = ?");
    $stmtC->execute([$q['id']]);
    $q['choices'] = $stmtC->fetchAll();
}

// Meilleure tentative précédente (pour affichage informatif)
$stmt = $pdo->prepare("SELECT MAX(score) AS best, MAX(passed) AS passed FROM quiz_attempts WHERE student_id = ? AND quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz['quiz_id']]);
$previous = $stmt->fetch();

$title = "Évaluation";
require_once '../includes/header.php';
?>

<p><a href="lesson.php?id=<?= $quiz['lesson_id'] ?>" style="color:#64748B; text-decoration:none;">← Retour à la leçon</a></p>
<h1><?= htmlspecialchars($quiz['quiz_titre']) ?></h1>
<p style="color:#64748B; margin-bottom:6px;">Leçon : <?= htmlspecialchars($quiz['lesson_titre']) ?></p>
<p style="color:#64748B; margin-bottom:20px;">Note de passage requise : <strong><?= (int)$quiz['note_passage'] ?>%</strong></p>

<?php if ($previous['best'] !== null): ?>
    <div style="background:<?= $previous['passed'] ? '#ECFDF5' : '#FEF2F2' ?>; border:1px solid <?= $previous['passed'] ? '#10B981' : '#EF4444' ?>; border-radius:8px; padding:14px 18px; margin-bottom:20px;">
        Meilleure tentative précédente : <strong><?= round($previous['best']) ?>%</strong>
        <?= $previous['passed'] ? '✓ Réussi' : '✗ Non réussi' ?>
    </div>
<?php endif; ?>

<form id="quiz-attempt-form">
    <input type="hidden" id="quiz_id" value="<?= $quiz['quiz_id'] ?>">

    <?php foreach ($questions as $i => $q): ?>
        <div class="question-block" data-question-id="<?= $q['id'] ?>" style="background:white; border-radius:10px; padding:20px; margin-bottom:16px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
            <p style="font-weight:600; margin-bottom:12px;"><?= $i + 1 ?>. <?= htmlspecialchars($q['question_text']) ?></p>
            <?php foreach ($q['choices'] as $choice): ?>
                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio" name="question-<?= $q['id'] ?>" value="<?= $choice['id'] ?>" required>
                    <?= htmlspecialchars($choice['choice_text']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success">Soumettre l'évaluation</button>
</form>

<div id="result" style="margin-top:20px;"></div>

<script>
    document.getElementById('quiz-attempt-form').onsubmit = async (e) => {
        e.preventDefault();

        const quizId = document.getElementById('quiz_id').value;
        const answers = [];

        document.querySelectorAll('.question-block').forEach(block => {
            const questionId = block.dataset.questionId;
            const checked = block.querySelector('input[type="radio"]:checked');
            if (checked) {
                answers.push({
                    question_id: questionId,
                    choice_id: checked.value
                });
            }
        });

        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi en cours...';

        try {
            const res = await fetch('../api/quiz.php?action=submit_attempt', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    quiz_id: quizId,
                    answers
                })
            });
            const data = await res.json();

            const resultDiv = document.getElementById('result');
            if (data.success) {
                resultDiv.innerHTML = `
                <div style="background:${data.passed ? '#ECFDF5' : '#FEF2F2'}; border:1px solid ${data.passed ? '#10B981' : '#EF4444'}; border-radius:8px; padding:18px;">
                    <strong>Score obtenu : ${data.score}%</strong><br>
                    ${data.passed ? 'Félicitations, vous avez réussi cette évaluation !' : 'Score insuffisant, vous pouvez retenter l\'évaluation.'}
                </div>
                `;
                submitBtn.style.display = 'none';
            } else {
                resultDiv.innerHTML = `<p style="color:#EF4444;">${data.message || 'Erreur lors de la soumission.'}</p>`;
                submitBtn.disabled = false;
                submitBtn.textContent = "Soumettre l'évaluation";
            }
        } catch (err) {
            document.getElementById('result').innerHTML = `<p style="color:#EF4444;">Erreur réseau.</p>`;
            submitBtn.disabled = false;
            submitBtn.textContent = "Soumettre l'évaluation";
        }
    };
</script>

<?php require_once '../includes/footer.php'; ?>



