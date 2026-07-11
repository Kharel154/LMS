<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$lesson_id = (int)($_GET['lesson_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT l.id, l.titre AS lesson_titre, c.id AS course_id, c.titre AS course_titre
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.enseignant_id = ?
");
$stmt->execute([$lesson_id, $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) die('Leçon introuvable ou accès non autorisé.');

$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$existing_quiz = $stmt->fetch();

$existing_questions = [];
if ($existing_quiz) {
    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY ordre ASC");
    $stmt->execute([$existing_quiz['id']]);
    $existing_questions = $stmt->fetchAll();

    foreach ($existing_questions as &$q) {
        $stmtC = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id = ?");
        $stmtC->execute([$q['id']]);
        $q['choices'] = $stmtC->fetchAll();
    }
}

$title = "Créer un Quiz";
require_once 'header.php';
?>

<h1>Évaluation — <?= htmlspecialchars($lesson['lesson_titre']) ?></h1>
<p style="color:#6B7280;">Cours : <?= htmlspecialchars($lesson['course_titre']) ?></p>

<form id="quiz-form">
    <input type="hidden" id="lesson_id" value="<?= $lesson_id ?>">
    <input type="hidden" id="quiz_id" value="<?= $existing_quiz['id'] ?? '' ?>">

    <label>Titre du quiz :</label>
    <input type="text" id="quiz_title" required style="width:100%; padding:10px; margin-bottom:15px;"
           value="<?= htmlspecialchars($existing_quiz['titre'] ?? 'Évaluation — ' . $lesson['lesson_titre']) ?>">

    <label>Note de passage (%) :</label>
    <input type="number" id="note_passage" min="0" max="100" value="<?= $existing_quiz['note_passage'] ?? 50 ?>"
           style="width:100px; padding:8px; margin-bottom:20px;">

    <div id="questions-container"></div>

    <button type="button" onclick="addQuestion()" style="background:#6366F1; color:white; padding:10px 16px; border:none; border-radius:6px; margin:10px 0;">+ Ajouter une question</button>
    <button type="submit" style="background:#10B981; color:white; padding:10px 20px; border:none; border-radius:6px;">💾 Enregistrer le Quiz</button>
</form>

<div id="result" style="margin-top:20px; font-weight:600;"></div>

<script>
let questionCount = 0;

function addQuestion(data = null) {
    questionCount++;
    const qIndex = questionCount;
    const container = document.getElementById('questions-container');

    const block = document.createElement('div');
    block.className = 'question-block';
    block.dataset.index = qIndex;
    block.style = 'border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:20px; background:#fff;';

    const qType = data?.type || 'qcm';
    const qText = data?.question_text || '';

    block.innerHTML = `
        <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
            <strong>Question ${qIndex}</strong>
            <button type="button" onclick="this.closest('.question-block').remove()" style="background:#ef4444;color:white;border:none;padding:4px 10px;border-radius:4px;cursor:pointer;">Supprimer</button>
        </div>

        <input type="text" class="q-text" placeholder="Énoncé de la question" required value="${qText.replace(/"/g,'&quot;')}" style="width:100%;padding:10px;margin-bottom:10px;">

        <label>Type :</label>
        <select class="q-type" onchange="toggleChoices(this)" style="margin-bottom:10px;">
            <option value="qcm" ${qType==='qcm'?'selected':''}>QCM</option>
            <option value="vrai_faux" ${qType==='vrai_faux'?'selected':''}>Vrai / Faux</option>
        </select>

        <div class="choices-container"></div>
        <button type="button" class="add-choice-btn" onclick="addChoice(this)" style="background:#64748b;color:white;padding:6px 12px;border:none;border-radius:4px;margin-top:8px;">+ Ajouter un choix</button>
    `;

    container.appendChild(block);

    const choicesCont = block.querySelector('.choices-container');

    if (qType === 'vrai_faux') {
        block.querySelector('.add-choice-btn').style.display = 'none';
        renderVraiFaux(choicesCont, data?.choices, qIndex);
    } else if (data?.choices && data.choices.length > 0) {
        data.choices.forEach(c => addChoice(block.querySelector('.add-choice-btn'), c.choice_text, c.is_correct));
    } else {
        addChoice(block.querySelector('.add-choice-btn'));
        addChoice(block.querySelector('.add-choice-btn'));
    }
}

function toggleChoices(select) {
    const block = select.closest('.question-block');
    const cont = block.querySelector('.choices-container');
    cont.innerHTML = '';
    if (select.value === 'vrai_faux') {
        block.querySelector('.add-choice-btn').style.display = 'none';
        renderVraiFaux(cont, null, block.dataset.index);
    } else {
        block.querySelector('.add-choice-btn').style.display = 'inline-block';
        addChoice(block.querySelector('.add-choice-btn'));
        addChoice(block.querySelector('.add-choice-btn'));
    }
}

function renderVraiFaux(container, choices = null, qIndex) {
    const uniqueName = `vf-q-${qIndex}`;
    const vraiCorrect = choices ? choices.find(c => c.choice_text === 'Vrai')?.is_correct : true;

    container.innerHTML = `
        <label style="margin-right:20px;">
            <input type="radio" name="${uniqueName}" value="Vrai" ${vraiCorrect ? 'checked' : ''}> Vrai
        </label>
        <label>
            <input type="radio" name="${uniqueName}" value="Faux" ${!vraiCorrect ? 'checked' : ''}> Faux
        </label>
    `;
}

function addChoice(btn, text = '', isCorrect = false) {
    const block = btn.closest('.question-block');
    const cont = block.querySelector('.choices-container');
    const name = 'correct-' + block.dataset.index;

    const div = document.createElement('div');
    div.style = 'display:flex; align-items:center; gap:8px; margin:6px 0;';
    div.innerHTML = `
        <input type="radio" name="${name}" ${isCorrect ? 'checked' : ''}>
        <input type="text" class="choice-text" value="${text}" required style="flex:1; padding:8px;">
        <button type="button" onclick="this.parentElement.remove()" style="background:#f87171;color:white;border:none;padding:4px 8px;border-radius:4px;">×</button>
    `;
    cont.appendChild(div);
}

// Chargement initial
const EXISTING = <?= json_encode($existing_questions ?? []) ?>;

if (EXISTING.length > 0) {
    EXISTING.forEach(q => addQuestion(q));
} else {
    addQuestion();
}

// Soumission finale
document.getElementById('quiz-form').onsubmit = async (e) => {
    e.preventDefault();
    const questions = [];

    document.querySelectorAll('.question-block').forEach(block => {
        const text = block.querySelector('.q-text').value.trim();
        const type = block.querySelector('.q-type').value;
        const choices = [];

        if (type === 'vrai_faux') {
            block.querySelectorAll('input[type="radio"]').forEach(r => {
                if (r.name.includes('vf-q')) {
                    choices.push({ text: r.value, is_correct: r.checked });
                }
            });
        } else {
            // QCM - meilleure détection
            block.querySelectorAll('.choices-container > div').forEach(row => {
                const input = row.querySelector('.choice-text');
                const radio = row.querySelector('input[type="radio"]');
                if (input) {
                    const txt = input.value.trim();
                    if (txt) {
                        choices.push({
                            text: txt,
                            is_correct: radio ? radio.checked : false
                        });
                    }
                }
            });
        }

        if (text && choices.length > 0) {
            questions.push({ type, text, choices });
        }
    });

    if (questions.length === 0) {
        alert("Ajoutez au moins une question avec du texte et des choix.");
        return;
    }

    const payload = {
        lesson_id: document.getElementById('lesson_id').value,
        quiz_id: document.getElementById('quiz_id').value || null,
        titre: document.getElementById('quiz_title').value.trim(),
        note_passage: parseInt(document.getElementById('note_passage').value) || 50,
        questions
    };

    try {
        const res = await fetch('../api/quiz.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        document.getElementById('result').innerHTML = data.success 
            ? `<span style="color:green;">${data.message}</span>` 
            : `<span style="color:red;">${data.message}</span>`;
    } catch (err) {
        document.getElementById('result').innerHTML = `<span style="color:red;">Erreur réseau</span>`;
    }
};
</script>

<?php require_once '../includes/footer.php'; ?>