<?php
/**
 * student/quiz-attempt.php
 *
 * Affiche les questions d'une evaluation et gere la soumission des reponses.
 *
 * Fonctionnement general :
 *   1. Verifie que l'etudiant est inscrit au cours auquel appartient la lecon.
 *   2. Charge les questions et leurs choix de reponse depuis la base de donnees.
 *      Le champ "is_correct" n'est deliberement pas transmis au navigateur
 *      afin d'empecher la triche par inspection du code source.
 *   3. Affiche l'historique de la meilleure tentative precedente (si elle existe).
 *   4. A la soumission, envoie les reponses en JSON vers api/quiz.php
 *      (action submit_attempt) qui calcule le score cote serveur.
 *   5. Affiche le resultat sans rechargement de page.
 *
 * Corrections appliquees :
 *   - Ajout de "unset($q)" apres le foreach par reference pour eviter
 *     qu'une reference residuelle duplique la derniere question dans le HTML.
 *   - Lecture du question_id depuis "data-question-id" (attribut HTML) plutot
 *     que depuis le "name" du radio, ce qui elimine tout risque de confusion
 *     entre questions Vrai/Faux dont les groupes radio partagent la meme racine.
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');


// Recuperation et validation de l'identifiant de lecon


$lesson_id = (int)($_GET['lesson_id'] ?? 0);

if (!$lesson_id) {
    header('Location: dashboard.php');
    exit;
}


// Chargement du quiz avec verification d'acces
// La jointure sur enrollments garantit qu'un etudiant non inscrit au cours
// ne peut pas acceder a l'evaluation, meme en forgeant l'URL.


$stmt = $pdo->prepare("
    SELECT l.id   AS lesson_id,
           l.titre AS lesson_titre,
           q.id   AS quiz_id,
           q.titre AS quiz_titre,
           q.note_passage
    FROM lessons l
    JOIN courses    c ON c.id = l.course_id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
    JOIN quizzes    q ON q.lesson_id = l.id
    WHERE l.id = :lesson_id
");
$stmt->execute([
    ':student_id' => $_SESSION['user_id'],
    ':lesson_id'  => $lesson_id,
]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die("Evaluation introuvable ou acces non autorise.");
}


// Chargement des questions dans l'ordre d'affichage defini par le professeur
// Le champ "type" est necessaire pour distinguer QCM et Vrai/Faux et
// appliquer le bon rendu HTML (groupe de radios unique vs choix multiples).


$stmt = $pdo->prepare("
    SELECT id, question_text, type
    FROM quiz_questions
    WHERE quiz_id = :quiz_id
    ORDER BY ordre ASC
");
$stmt->execute([':quiz_id' => $quiz['quiz_id']]);
$questions = $stmt->fetchAll();


// Chargement des choix de reponse pour chaque question
//
// IMPORTANT : seuls "id" et "choice_text" sont selectionnes.
// Le champ "is_correct" est volontairement exclu pour ne pas l'exposer
// dans le code HTML source visible par l'etudiant.
//
// IMPORTANT : le "unset($q)" apres la boucle est obligatoire.
// Sans lui, $q reste une reference vers le dernier element du tableau.
// Le foreach suivant (dans le HTML) ecraserait cet element a chaque iteration,
// provoquant la duplication de la derniere question et la disparition
// de l'avant-derniere dans l'affichage.


foreach ($questions as &$q) {
    $stmtC = $pdo->prepare("
        SELECT id, choice_text
        FROM quiz_choices
        WHERE question_id = :question_id
    ");
    $stmtC->execute([':question_id' => $q['id']]);
    $q['choices'] = $stmtC->fetchAll();
}
unset($q); // Liberation obligatoire de la reference residuelle


// Recuperation de la meilleure tentative precedente
// Utilisee pour afficher un bandeau d'historique avant le formulaire.
// MAX(passed) retourne 1 si l'etudiant a deja reussi ce quiz au moins une fois.


$stmt = $pdo->prepare("
    SELECT MAX(score)  AS best_score,
           MAX(passed) AS best_passed
    FROM quiz_attempts
    WHERE student_id = :student_id AND quiz_id = :quiz_id
");
$stmt->execute([
    ':student_id' => $_SESSION['user_id'],
    ':quiz_id'    => $quiz['quiz_id'],
]);
$previous = $stmt->fetch();

$title = "Evaluation";
require_once '../includes/header.php';
?>

<!-- Fil d'Ariane -->
<p>
    <a href="lesson.php?id=<?= $quiz['lesson_id'] ?>"
       style="color:#64748B; text-decoration:none;">
        &larr; Retour a la lecon
    </a>
</p>

<h1><?= htmlspecialchars($quiz['quiz_titre']) ?></h1>
<p style="color:#64748B; margin-bottom:6px;">
    Lecon : <?= htmlspecialchars($quiz['lesson_titre']) ?>
</p>
<p style="margin-bottom:20px;">
    Note de passage requise : <strong><?= (int)$quiz['note_passage'] ?>%</strong>
</p>

<!-- Bandeau d'historique : affiche uniquement si une tentative existe -->
<?php if ($previous['best_score'] !== null): ?>
    <div style="background:<?= $previous['best_passed'] ? '#ECFDF5' : '#FEF2F2' ?>;
                border:1px solid <?= $previous['best_passed'] ? '#10B981' : '#EF4444' ?>;
                border-radius:8px; padding:14px 18px; margin-bottom:20px;">
        Meilleure tentative precedente :
        <strong><?= round((float)$previous['best_score']) ?>%</strong>
        &mdash;
        <?= $previous['best_passed'] ? 'Reussi' : 'Non reussi' ?>
    </div>
<?php endif; ?>

<!--
    Formulaire d'evaluation
    Chaque bloc de question porte les attributs "data-question-id" et "data-type"
    qui sont lus par JavaScript lors de la soumission pour construire le tableau
    de reponses sans dependre du format du "name" des inputs radio.
-->
<form id="quiz-form">
    <input type="hidden" name="quiz_id" value="<?= $quiz['quiz_id'] ?>">

    <?php foreach ($questions as $i => $q): ?>

        <div class="question-block"
             data-question-id="<?= $q['id'] ?>"
             data-type="<?= $q['type'] ?>"
             style="background:white; padding:20px; margin-bottom:20px;
                    border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">

            <p style="font-weight:600; margin-bottom:15px;">
                <?= ($i + 1) ?>. <?= htmlspecialchars($q['question_text']) ?>
            </p>

            <?php if ($q['type'] === 'vrai_faux'): ?>
                <!--
                    Questions Vrai/Faux : les deux radios partagent un "name"
                    base sur l'ID de la question (et non sur l'index $i).
                    Cela garantit que chaque groupe de radios est independant
                    des autres questions, meme en cas de creation/suppression
                    de questions qui decalerait les indices.
                -->
                <?php $groupName = 'q_' . $q['id']; ?>

                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio"
                           name="<?= $groupName ?>"
                           value="<?= $q['choices'][0]['id'] ?? '' ?>"
                           required>
                    <?= htmlspecialchars($q['choices'][0]['choice_text'] ?? 'Vrai') ?>
                </label>
                <label style="display:block; padding:8px 0; cursor:pointer;">
                    <input type="radio"
                           name="<?= $groupName ?>"
                           value="<?= $q['choices'][1]['id'] ?? '' ?>">
                    <?= htmlspecialchars($q['choices'][1]['choice_text'] ?? 'Faux') ?>
                </label>

            <?php else: ?>
                <!-- Questions QCM : un radio par choix, groupe par ID de question -->
                <?php foreach ($q['choices'] as $choice): ?>
                    <label style="display:block; padding:8px 0; cursor:pointer;">
                        <input type="radio"
                               name="q_<?= $q['id'] ?>"
                               value="<?= $choice['id'] ?>"
                               required>
                        <?= htmlspecialchars($choice['choice_text']) ?>
                    </label>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    <?php endforeach; ?>

    <button type="submit" class="btn btn-success" id="submit-btn">
        Soumettre l'evaluation
    </button>
</form>

<!-- Zone d'affichage du resultat apres soumission -->
<div id="result" style="margin-top:20px;"></div>

<script>
/**
 * Gestionnaire de soumission du formulaire d'evaluation.
 * Collecte les reponses cochees, les envoie en JSON vers l'API,
 * puis affiche le resultat sans recharger la page.
 */
document.getElementById('quiz-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    /*
     * Construction du tableau de reponses.
     * On parcourt chaque bloc de question et on lit la valeur du radio coche.
     * Le question_id est lu depuis l'attribut "data-question-id" du bloc parent
     * plutot que depuis le "name" du radio, ce qui est plus robuste et evite
     * toute manipulation de chaine susceptible de produire un mauvais identifiant.
     */
    var answers = [];
    document.querySelectorAll('.question-block').forEach(function (block) {
        var questionId = block.dataset.questionId;
        var checked    = block.querySelector('input[type="radio"]:checked');
        if (checked) {
            answers.push({
                question_id: questionId,
                choice_id:   checked.value
            });
        }
    });

    var submitBtn = document.getElementById('submit-btn');
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Envoi en cours...';

    try {
        /*
         * Envoi des reponses vers api/quiz.php (action submit_attempt).
         * Le score est calcule entierement cote serveur pour garantir
         * l'integrite du resultat independamment du client.
         */
        var res = await fetch('../api/quiz.php?action=submit_attempt', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                quiz_id: <?= (int)$quiz['quiz_id'] ?>,
                answers: answers
            })
        });

        var data = await res.json();
        var resultDiv = document.getElementById('result');

        if (data.success) {
            /* Affichage du resultat avec couleur conditionnelle */
            var bgColor     = data.passed ? '#ECFDF5' : '#FEF2F2';
            var borderColor = data.passed ? '#10B981' : '#EF4444';
            var statusMsg   = data.passed
                ? 'Felicitations, vous avez reussi cette evaluation.'
                : 'Score insuffisant. Vous pouvez retenter l\'evaluation.';

            var certMsg = '';
            if (data.certificate_awarded) {
                certMsg = '<br><br><strong>Certificat decerne. Module valide avec succes.</strong>';
            }

            resultDiv.innerHTML =
                '<div style="padding:20px; background:' + bgColor + ';'
                + 'border:1px solid ' + borderColor + '; border-radius:8px;">'
                + '<strong>Score obtenu : ' + data.score + '%</strong><br>'
                + statusMsg
                + certMsg
                + '</div>';

            /* Cache le bouton apres une soumission reussie */
            submitBtn.style.display = 'none';

        } else {
            /* Affichage du message d'erreur retourne par l'API */
            resultDiv.innerHTML =
                '<p style="color:#EF4444;">'
                + (data.message || 'Erreur lors de la soumission.')
                + '</p>';

            submitBtn.disabled    = false;
            submitBtn.textContent = "Soumettre l'evaluation";
        }

    } catch (err) {
        /*
         * Gestion des erreurs reseau (timeout, serveur indisponible, etc.).
         * L'etudiant est invite a reessayer sans perdre ses reponses.
         */
        document.getElementById('result').innerHTML =
            '<p style="color:#EF4444;">Erreur reseau. Veuillez reessayer.</p>';

        submitBtn.disabled    = false;
        submitBtn.textContent = "Soumettre l'evaluation";
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>