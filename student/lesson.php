<?php
/**
 * student/lesson.php
 *
 * Affiche le contenu d'une leçon (vidéo MP4 ou document PDF) et gère
 * le déverrouillage conditionnel du lien vers l'évaluation associée.
 *
 * Règles de déverrouillage :
 *   - Leçon vidéo  : l'évaluation se déverrouille automatiquement
 *                    lorsque la lecture atteint 100% (événement "ended").
 *   - Leçon PDF    : l'évaluation se déverrouille après confirmation
 *                    manuelle via le bouton "J'ai lu le document".
 *
 * Pré-requis :
 *   - L'étudiant doit être inscrit au cours (vérification via enrollments).
 *   - La progression est enregistrée en "en_cours" à l'ouverture de la page.
 *   - La progression passe à "termine" uniquement après réussite du quiz
 *     (géré dans api/quiz.php, action submit_attempt).
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

// ---------------------------------------------------------------------------
// Récupération et validation de l'identifiant de leçon
// ---------------------------------------------------------------------------

$lesson_id = (int)($_GET['id'] ?? 0);
if (!$lesson_id) {
    header('Location: dashboard.php');
    exit;
}

// ---------------------------------------------------------------------------
// Chargement de la leçon avec vérification de l'inscription au cours
// La jointure sur enrollments garantit qu'un étudiant non inscrit
// ne peut pas accéder au contenu de la leçon.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT l.*, c.id AS course_id, c.titre AS course_title
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = :student_id
    WHERE l.id = :lesson_id
");
$stmt->execute([
    ':student_id' => $_SESSION['user_id'],
    ':lesson_id'  => $lesson_id,
]);
$lesson = $stmt->fetch();

if (!$lesson) {
    die("Leçon introuvable ou vous n'êtes pas inscrit à ce cours.");
}

// ---------------------------------------------------------------------------
// Mise à jour de la progression à "en_cours"
// La clause ON DUPLICATE KEY UPDATE protège le statut "termine" :
// une leçon déjà validée ne peut pas rétrograder à "en_cours".
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    INSERT INTO lesson_progress (student_id, lesson_id, statut)
    VALUES (:student_id, :lesson_id, 'en_cours')
    ON DUPLICATE KEY UPDATE
        statut = IF(statut = 'termine', 'termine', 'en_cours')
");
$stmt->execute([
    ':student_id' => $_SESSION['user_id'],
    ':lesson_id'  => $lesson_id,
]);

// ---------------------------------------------------------------------------
// Récupération du quiz associé à cette leçon (s'il existe)
// Un quiz peut ne pas encore avoir de questions si le professeur
// ne l'a pas encore complété après l'upload automatique.
// ---------------------------------------------------------------------------

$stmt = $pdo->prepare("
    SELECT q.id, q.titre, q.note_passage,
           (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS nb_questions
    FROM quizzes q
    WHERE q.lesson_id = :lesson_id
");
$stmt->execute([':lesson_id' => $lesson_id]);
$quiz = $stmt->fetch();

$title = "Leçon";
require_once '../includes/header.php';
?>

<!-- Fil d'Ariane -->
<p>
    <a href="course-view.php?id=<?= $lesson['course_id'] ?>"
       style="color:#64748B; text-decoration:none;">
        &larr; Retour au cours
    </a>
</p>

<h2><?= htmlspecialchars($lesson['titre']) ?></h2>
<p style="color:#64748B; margin-bottom:20px;"><?= htmlspecialchars($lesson['course_title']) ?></p>

<!-- 
     Lecteur de contenu
     - Vidéo  : balise <video> avec rapport 16:9 forcé via padding-top.
     - PDF    : balise <embed> en pleine largeur.
    -->
<div class="lesson-content" style="margin-bottom:30px;">

    <?php if ($lesson['type'] === 'video'): ?>

        
        <div style="position:relative; padding-top:56.25%; background:#000; border-radius:8px; overflow:hidden;">
            <video id="lesson-video"
                   controls
                   style="position:absolute; top:0; left:0;  height:100%;">
                <source src="../assets/uploads/videos/<?= htmlspecialchars($lesson['fichier_url']) ?>"
                        type="video/mp4">
                Votre navigateur ne prend pas en charge la lecture vidéo HTML5.
            </video>
        </div>

    <?php else: ?>

        <!-- Visionneuse PDF intégrée -->
        <embed src="../assets/uploads/pdfs/<?= htmlspecialchars($lesson['fichier_url']) ?>"
               width="100%"
               height="700px"
               type="application/pdf">

    <?php endif; ?>

</div>

<!--
     Bloc d'évaluation
     Affiché uniquement si un quiz existe ET contient au moins une question.
     Si le quiz existe mais est vide (créé automatiquement, non complété
     par le professeur), un message informatif est affiché à la place.
    -->
<?php if ($quiz): ?>

    <?php if ((int)$quiz['nb_questions'] === 0): ?>

        <!--
            Quiz vide : le professeur n'a pas encore ajouté de questions.
            On informe l'étudiant sans bloquer sa navigation.
        -->
        <div style="background:#FEF3C7; border:1px solid #F59E0B; border-radius:10px;
                    padding:18px 20px; color:#92400E;">
            L'évaluation de cette leçon n'est pas encore disponible.
            Revenez plus tard ou contactez votre enseignant.
        </div>

    <?php else: ?>

        <!--
            Bloc évaluation principal.
            Le lien est désactivé par défaut (pointer-events:none + opacity réduite).
            Il sera réactivé par JavaScript une fois la condition de visionnage remplie.
        -->
        <div id="quiz-section"
             style="background:white; border-radius:10px; padding:24px 20px;
                    box-shadow:0 2px 8px rgba(0,0,0,0.05); text-align:center;">

            <p style="margin-bottom:6px; font-size:15px;">
                <strong>Évaluation :</strong> <?= htmlspecialchars($quiz['titre']) ?>
            </p>
            <p style="color:#64748B; font-size:13px; margin-bottom:18px;">
                Note de passage requise : <strong><?= (int)$quiz['note_passage'] ?>%</strong>
                &nbsp;&mdash;&nbsp;
                <?= (int)$quiz['nb_questions'] ?> question(s)
            </p>

            <!--
                Lien vers l'évaluation — désactivé à l'initialisation.
                L'attribut href est présent dès le chargement de la page mais
                le pointer-events:none empêche tout clic avant déverrouillage.
            -->
            <a id="quiz-btn"
               href="quiz-attempt.php?lesson_id=<?= $lesson['id'] ?>"
               class="btn btn-success"
               style="text-decoration:none; pointer-events:none; opacity:0.5;
                      display:inline-block; transition:opacity 0.3s;">
                Passer l'évaluation
            </a>

            <!-- Message d'instruction contextuel, mis à jour par JavaScript -->
            <p id="quiz-hint"
               style="color:#94A3B8; font-size:13px; margin-top:12px;">
                <?php if ($lesson['type'] === 'video'): ?>
                    Regardez la vidéo en entier pour débloquer l'évaluation.
                <?php else: ?>
                    Cliquez sur "J'ai lu le document" pour débloquer l'évaluation.
                <?php endif; ?>
            </p>

        </div>

    <?php endif; ?>

<?php else: ?>

    <p style="color:#94A3B8; font-size:14px;">
        Aucune évaluation n'est associée à cette leçon.
    </p>

<?php endif; ?>

<div id="toast" class="toast"></div>

<script>
/**
 * Déverrouille le bouton d'accès à l'évaluation et met à jour
 * le message d'instruction pour indiquer que l'accès est ouvert.
 */
function unlockQuiz() {
    var btn  = document.getElementById('quiz-btn');
    var hint = document.getElementById('quiz-hint');

    if (!btn) return;

    btn.style.pointerEvents = 'auto';
    btn.style.opacity       = '1';

    if (hint) {
        hint.style.color   = '#10B981';
        hint.textContent   = 'Vous pouvez maintenant passer l\'evaluation.';
    }
}

/*
 * Lecon VIDEO
 * L'evaluation se deverrouille uniquement a la fin de la lecture (100%).
 * L'evenement "ended" est declenche par le navigateur lorsque la tete de
 * lecture atteint la duree totale de la video. Il est plus fiable que
 * "timeupdate" pour detecter une lecture complete car il ne depend pas
 * d'un seuil flottant susceptible d'etre atteint de maniere imprecise.
*/
var video = document.getElementById('lesson-video');
if (video) {
    /**
     * Evenement "ended" : declenche a 100% de la duree de la video.
     */
    video.addEventListener('ended', function () {
        unlockQuiz();
    });
}

/*
 * Lecon PDF
 * Le deverrouillage est manuel : l'etudiant confirme la lecture en cliquant
 * sur un bouton ajoute dynamiquement sous le visualiseur PDF.
 * Cette approche est necessaire car les navigateurs n'exposent pas d'API
 * permettant de detecter le defilement ou la lecture complete d'un PDF
 * embarque dans une balise <embed>.
*/
else {
    var pdfContainer = document.querySelector('.lesson-content');

    if (pdfContainer) {
        /*
         * Creation et insertion du bouton de confirmation de lecture.
         * Le bouton est ajoute apres le visualiseur PDF (appendChild)
         * pour ne pas interrompre le flux de lecture.
         */
        var readBtn       = document.createElement('button');
        readBtn.textContent = 'J\'ai lu le document';
        readBtn.setAttribute('type', 'button');
        readBtn.style.cssText = [
            'margin-top:14px',
            'padding:10px 22px',
            'background:#10B981',
            'color:white',
            'border:none',
            'border-radius:6px',
            'font-size:14px',
            'font-weight:600',
            'cursor:pointer',
            'font-family:inherit',
            'transition:opacity 0.2s',
        ].join(';');

        pdfContainer.appendChild(readBtn);

        /**
         * Au clic : desactive le bouton pour eviter les doubles clics,
         * met a jour son libelle, puis deverrouille l'evaluation.
         */
        readBtn.addEventListener('click', function () {
            readBtn.disabled        = true;
            readBtn.style.opacity   = '0.7';
            readBtn.textContent     = 'Document confirme';
            unlockQuiz();
        });
    }
}

/**
 * Affiche un message toast temporaire en bas de l'ecran.
 * @param {string} message - Texte a afficher.
 * @param {string} type    - Classe CSS : "success" ou "error".
 */
function showToast(message, type) {
    var toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent  = message;
    toast.className    = 'toast ' + (type || 'success');
    toast.style.display = 'block';
    setTimeout(function () {
        toast.style.display = 'none';
    }, 3000);
}
</script>

<?php require_once '../includes/footer.php'; ?>