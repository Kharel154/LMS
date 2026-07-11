<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$lesson_id = (int)($_GET['lesson_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT l.id AS lesson_id, l.titre AS lesson_titre, 
           q.id AS quiz_id, q.titre AS quiz_titre, q.note_passage
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    JOIN quizzes q ON q.lesson_id = l.id
    WHERE l.id = ?
");
$stmt->execute([$_SESSION['user_id'], $lesson_id]);
$quiz = $stmt->fetch();

if (!$quiz) die("Évaluation introuvable ou accès non autorisé.");

// ✅ BUG 1 FIX : on sélectionne aussi le champ "type" pour distinguer QCM et Vrai/Faux
$stmt = $pdo->prepare("
    SELECT id, question_text, type
    FROM quiz_questions 
    WHERE quiz_id = ? 
    ORDER BY ordre ASC
");
$stmt->execute([$quiz['quiz_id']]);
$questions = $stmt->fetchAll();

// ✅ BUG 2 FIX : après un foreach avec référence (&$q), $q pointe encore
// sur le dernier élément du tableau. Sans unset(), le foreach du HTML
// en dessous écrase ce dernier élément à chaque itération →
// la dernière question est dupliquée et celle d'avant disparaît.
foreach ($questions as &$q) {
    $stmtC = $pdo->prepare("SELECT id, choice_text FROM quiz_choices WHERE question_id = ?");
    $stmtC->execute([$q['id']]);
    $q['choices'] = $stmtC->fetchAll();
}
unset($q); // ← CORRECTION CRITIQUE : libère la référence

// Meilleure tentative précédente
$stmt = $pdo->prepare("
    SELECT MAX(score) AS best_score, MAX(passed) AS best_passed 
    FROM quiz_attempts 
    WHERE student_id = ? AND quiz_id = ?
");
$stmt->execute([$_SESSION['user_id'], $quiz['quiz_id']]);
$previous = $stmt->fetch();

$title = "Évaluation";
require_once '../includes/header.php';
?>

<p><a href="lesson.php?id=<?= $quiz['lesson_id'] ?>" style="color:#64748B; text-decoration:none;">← Retour à la leçon</a></p>
<h1><?= htmlspecialchars($quiz['quiz_titre']) ?></h1>
<p style="color:#64748B; margin-bottom:6px;">Leçon : <?= htmlspecialchars($quiz['lesson_titre']) ?></p>
<p style="margin-bottom:20px;">Note de passage requise : <strong><?= (int)$quiz['note_passage'] ?>%</strong></p>

<?php if ($previous['best_score'] !== null): ?>
    <div style="background:<?= $previous['best_passed'] ? '#ECFDF5' : '#FEF2F2' ?>; border:1px solid <?= $previous['best_passed'] ? '#10B981' : '#EF4444' ?>; border-radius:8px; padding:14px 18px; margin-bottom:20px;">
        Meilleure tentative précédente : <strong><?= round($previous['best_score']) ?>%</strong>
        — <?= $previous['best_passed'] ? '✓ Réussi' : '✗ Non réussi' ?>
    </div>
<?php endif; ?>

<form id="quiz-form">
    <input type="hidden" name="quiz_id" value="<?= $quiz['quiz_id'] ?>">

    <?php foreach ($questions as $i => $q): ?>
        <div class="question-block"
             data-question-id="<?= $q['id'] ?>"
             data-type="<?= $q['type'] ?>"
             style="background:white; padding:20px; margin-bottom:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">

            <p style="font-weight:600; margin-bottom:15px;">
                <?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?>
            </p>

            <?php if ($q['type'] === 'vrai_faux'): ?>
                <?php
                // Pour Vrai/Faux : nom de groupe unique basé sur l'ID de la question (pas d'index)
                $groupName = 'q_' . $q['id'];
                ?>
                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio" name="<?= $groupName ?>" value="<?= $q['choices'][0]['id'] ?? '' ?>" required>
                    <?= htmlspecialchars($q['choices'][0]['choice_text'] ?? 'Vrai') ?>
                </label>
                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio" name="<?= $groupName ?>" value="<?= $q['choices'][1]['id'] ?? '' ?>">
                    <?= htmlspecialchars($q['choices'][1]['choice_text'] ?? 'Faux') ?>
                </label>

            <?php else: // QCM ?>
                <?php foreach ($q['choices'] as $choice): ?>
                    <label style="display:block; padding:8px 0; cursor:pointer;">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $choice['id'] ?>" required>
                        <?= htmlspecialchars($choice['choice_text']) ?>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success" id="submit-btn">Soumettre l'évaluation</button>
</form>

<div id="result" style="margin-top:20px;"></div>

<script>
document.getElementById('quiz-form').onsubmit = async (e) => {
    e.preventDefault();

    const answers = [];

    // ✅ BUG 3 FIX : on lit le question_id depuis data-question-id sur le bloc parent,
    // pas depuis le name du radio (évite les erreurs de parsing avec .replace('q_', ''))
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

    const submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Envoi en cours...';

    try {
        const res = await fetch('../api/quiz.php?action=submit_attempt', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                quiz_id: <?= $quiz['quiz_id'] ?>,
                answers
            })
        });
        const data = await res.json();

        const resultDiv = document.getElementById('result');
        if (data.success) {
            resultDiv.innerHTML = `
                <div style="padding:20px; background:${data.passed ? '#ECFDF5' : '#FEF2F2'}; border:1px solid ${data.passed ? '#10B981' : '#EF4444'}; border-radius:8px;">
                    <strong>Score obtenu : ${data.score}%</strong><br>
                    ${data.passed
                        ? '✓ Félicitations, vous avez réussi cette évaluation !'
                        : '✗ Score insuffisant. Vous pouvez retenter l\'évaluation.'}
                    ${data.certificate_awarded
                        ? '<br><br>🎓 <strong>Certificat décerné !</strong> Module validé avec succès.'
                        : ''}
                </div>
            `;
            submitBtn.style.display = 'none';
        } else {
            resultDiv.innerHTML = `<p style="color:#EF4444;">${data.message || 'Erreur lors de la soumission.'}</p>`;
            submitBtn.disabled = false;
            submitBtn.textContent = "Soumettre l'évaluation";
        }
    } catch (err) {
        document.getElementById('result').innerHTML = `<p style="color:#EF4444;">Erreur réseau. Veuillez réessayer.</p>`;
        submitBtn.disabled = false;
        submitBtn.textContent = "Soumettre l'évaluation";
    }
};
</script>

<?php require_once '../includes/footer.php'; ?>