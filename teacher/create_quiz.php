<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$lesson_id = (int)($_GET['lesson_id'] ?? 0);

// Vérifie que la leçon existe et appartient à un cours de cet enseignant
$stmt = $pdo->prepare("
    SELECT l.id, l.titre AS lesson_titre, c.id AS course_id, c.titre AS course_titre
    FROM lessons l
    JOIN courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.enseignant_id = ?
");
$stmt->execute([$lesson_id, $_SESSION['user_id']]);
$lesson = $stmt->fetch();

if (!$lesson) {
    die('Leçon introuvable ou accès non autorisé.');
}

// Quiz existant pour cette leçon (s'il y en a déjà un)
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
        value="<?= htmlspecialchars($existing_quiz['titre'] ?? '') ?>">

    <label>Note de passage (%) :</label>
    <input type="number" id="note_passage" min="0" max="100" value="<?= $existing_quiz['note_passage'] ?? 50 ?>"
        style="width:100px; padding:8px; margin-bottom:20px;">

    <div id="questions-container"></div>

    <button type="button" class="btn" onclick="addQuestion()" style="background:#6366F1;">+ Ajouter une question</button>
    <button type="submit" class="btn" style="background:#10B981;">💾 Enregistrer le Quiz</button>
</form>

<div id="result" style="margin-top:15px;"></div>

<script>
    const LESSON_ID = document.getElementById('lesson_id').value;
    const EXISTING_QUESTIONS = <?= json_encode($existing_questions) ?>;
    let questionCount = 0;

    function addQuestion(data = null) {
        questionCount++;
        const qIndex = questionCount;
        const container = document.getElementById('questions-container');

        const block = document.createElement('div');
        block.className = 'question-block';
        block.dataset.qindex = qIndex;
        block.style = 'border:1px solid #E5E7EB; border-radius:8px; padding:15px; margin-bottom:15px;';

        const qType = data?.type || 'qcm';
        const qText = data?.question_text || '';

        block.innerHTML = `
                                        <div style="display:flex; justify-content:space-between; align-items:center;">
                                            <strong>Question ${qIndex}</strong>
                                            <button type="button" onclick="this.closest('.question-block').remove()"
                                                    style="background:#dc2626; color:white; border:none; padding:4px 10px; border-radius:4px; cursor:pointer;">
                                                Supprimer
                                            </button>
                                        </div>

                                        <input type="text" class="q-text" placeholder="Énoncé de la question" required
                                            value="${qText.replace(/"/g, '&quot;')}"
                                            style="width:100%; padding:8px; margin:10px 0;">

                                        <label>Type :</label>
                                        <select class="q-type" onchange="toggleChoices(this)" style="margin-bottom:10px;">
                                            <option value="qcm" ${qType === 'qcm' ? 'selected' : ''}>QCM</option>
                                            <option value="vrai_faux" ${qType === 'vrai_faux' ? 'selected' : ''}>Vrai / Faux</option>
                                        </select>

                                        <div class="choices-container"></div>
                                        <button type="button" class="btn-add-choice" onclick="addChoice(this)"
                                                style="background:#9CA3AF; margin-top:5px;">+ Ajouter un choix</button>
                                    `;

        container.appendChild(block);

        const choicesContainer = block.querySelector('.choices-container');
        const addChoiceBtn = block.querySelector('.btn-add-choice');

        if (qType === 'vrai_faux') {
            addChoiceBtn.style.display = 'none';
            renderVraiFaux(choicesContainer, data?.choices);
        } else {
            if (data?.choices?.length) {
                data.choices.forEach(c => addChoice(addChoiceBtn, c.choice_text, c.is_correct));
            } else {
                addChoice(addChoiceBtn);
                addChoice(addChoiceBtn);
            }
        }
    }

    function toggleChoices(selectEl) {
        const block = selectEl.closest('.question-block');
        const choicesContainer = block.querySelector('.choices-container');
        const addChoiceBtn = block.querySelector('.btn-add-choice');
        choicesContainer.innerHTML = '';

        if (selectEl.value === 'vrai_faux') {
            addChoiceBtn.style.display = 'none';
            renderVraiFaux(choicesContainer);
        } else {
            addChoiceBtn.style.display = 'inline-block';
            addChoice(addChoiceBtn);
            addChoice(addChoiceBtn);
        }
    }

    function renderVraiFaux(container, choices = null) {
        const vraiChecked = choices ? choices.find(c => c.choice_text === 'Vrai')?.is_correct : true;
        container.innerHTML = `
                                        <label><input type="radio" name="vf-${Date.now()}" class="vf-radio" value="Vrai" ${vraiChecked ? 'checked' : ''}> Vrai</label>
                                        <label style="margin-left:15px;"><input type="radio" name="vf-${Date.now()}" class="vf-radio" value="Faux" ${!vraiChecked ? 'checked' : ''}> Faux</label>
                                    `;
        // Corrige le name pour que les deux radios soient bien liées entre elles
        const radios = container.querySelectorAll('.vf-radio');
        const sharedName = 'vf-' + Math.random();
        radios.forEach(r => r.name = sharedName);
    }

    function addChoice(btnEl, text = '', isCorrect = false) {
        const block = btnEl.closest('.question-block');
        const choicesContainer = block.querySelector('.choices-container');
        const groupName = 'correct-' + block.dataset.qindex;

        const row = document.createElement('div');
        row.className = 'choice-row';
        row.style = 'display:flex; align-items:center; gap:8px; margin-bottom:6px;';
        row.innerHTML = `
                                        <input type="radio" name="${groupName}" class="choice-correct" ${isCorrect ? 'checked' : ''}>
                                        <input type="text" class="choice-text" placeholder="Texte du choix" value="${text.replace(/"/g, '&quot;')}"
                                            required style="flex:1; padding:6px;">
                                        <button type="button" onclick="this.closest('.choice-row').remove()"
                                                style="background:#F87171; color:white; border:none; padding:3px 8px; border-radius:4px; cursor:pointer;">×</button>
                                    `;
        choicesContainer.appendChild(row);
    }

    // Pré-remplissage si un quiz existe déjà
    if (EXISTING_QUESTIONS.length > 0) {
        EXISTING_QUESTIONS.forEach(q => addQuestion(q));
    } else {
        addQuestion();
    }

    document.getElementById('quiz-form').onsubmit = async (e) => {
                e.preventDefault();

                const questions = [];
                document.querySelectorAll('.question-block').forEach(block => {
                    const type = block.querySelector('.q-type').value;
                    const text = block.querySelector('.q-text').value;
                    let choices = [];

                    if (type === 'vrai_faux') {
                        block.querySelectorAll('.vf-radio').forEach(r => {
                            choices.push({
                                text: r.value,
                                is_correct: r.checked
                            });
                        });
                    } else {
                        block.querySelectorAll('.choice-row').forEach(row => {
                            choices.push({
                                text: row.querySelector('.choice-text').value,
                                is_correct: row.querySelector('.choice-correct').checked
                            });
                        });
                    }

                    questions.push({
                        type,
                        text,
                        choices
                    });
                });

                const payload = {
                        lesson_id: LESSON_ID,
                        quiz_id: document.getElementById('quiz_id').value || null,
                        titre: document.getElementById('quiz_title').value,
                        note_passage: parseInt(document.getElementById('note_passage').value, 10),
				questions
			};

			try {
				const res = await fetch('../api/quiz.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(payload)
				});

				const data = await res.json();

				document.getElementById('result').innerHTML = data.success
					? `<p style="color:green;"> ${data.message}</p>`
					: `<p style="color:red;"> ${data.message}</p>`;

			} catch (err) {
				document.getElementById('result').innerHTML = `<p style="color:red;"> Erreur réseau lors de l'enregistrement.</p>`;
			}
		};

</script>