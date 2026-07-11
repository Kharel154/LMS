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

if (!$quiz) die("Évaluation introuvable.");

$stmt = $pdo->prepare("
    SELECT id, question_text 
    FROM quiz_questions 
    WHERE quiz_id = ? 
    ORDER BY ordre ASC
");
$stmt->execute([$quiz['quiz_id']]);
$questions = $stmt->fetchAll();

foreach ($questions as &$q) {
    $stmtC = $pdo->prepare("SELECT id, choice_text FROM quiz_choices WHERE question_id = ?");
    $stmtC->execute([$q['id']]);
    $q['choices'] = $stmtC->fetchAll();
}

$title = "Évaluation";
require_once '../includes/header.php';
?>

<p><a href="lesson.php?id=<?= $quiz['lesson_id'] ?>">← Retour à la leçon</a></p>
<h1><?= htmlspecialchars($quiz['quiz_titre']) ?></h1>
<p>Note de passage : <strong><?= (int)$quiz['note_passage'] ?>%</strong></p>

<form id="quiz-form">
    <input type="hidden" name="quiz_id" value="<?= $quiz['quiz_id'] ?>">

    <?php foreach ($questions as $i => $q): ?>
        <div style="background:white; padding:20px; margin-bottom:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
            <p style="font-weight:600; margin-bottom:15px;">
                <?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?>
            </p>
            <?php foreach ($q['choices'] as $choice): ?>
                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio" name="q<?= $q['id'] ?>" value="<?= $choice['id'] ?>" required>
                    <?= htmlspecialchars($choice['choice_text']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-success">Soumettre l'évaluation</button>
</form>

<div id="result"></div>

<script>
document.getElementById('quiz-form').onsubmit = async (e) => {
    e.preventDefault();
    const answers = [];
    document.querySelectorAll('input[type="radio"]:checked').forEach(r => {
        answers.push({
            question_id: r.name.replace('q', ''),
            choice_id: r.value
        });
    });

    const res = await fetch('../api/quiz.php?action=submit_attempt', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({quiz_id: <?= $quiz['quiz_id'] ?>, answers})
    });
    const data = await res.json();

    document.getElementById('result').innerHTML = data.success ? `
        <div style="padding:20px; background:#d1fae5; border-radius:8px;">
            Score : ${data.score}% - ${data.passed ? 'Réussi !' : 'Échoué'}
        </div>
    ` : `<p style="color:red;">${data.message}</p>`;
};
</script>

<?php require_once '../includes/footer.php'; ?>
