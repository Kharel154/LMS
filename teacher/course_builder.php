<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Constructeur de cours";
require_once 'header.php';

$course_id = (int)($_GET['id'] ?? 0);
$course = null;

if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND enseignant_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $course = $stmt->fetch();
}

// Liste des modules disponibles (créés par l'admin)
$stmt = $pdo->query("SELECT id, nom FROM modules ORDER BY nom ASC");
$modules = $stmt->fetchAll();

$message = '';
$messageType = 'green';

// Traitement création/modification du cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titre'])) {
    $titre      = sanitize_input($_POST['titre'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $module_id  = !empty($_POST['module_id']) ? (int)$_POST['module_id'] : null;
    $duree_estimee = !empty($_POST['duree_estimee']) ? (int)$_POST['duree_estimee'] : null;
    $categorie_id = 1; // valeur par défaut

    // Si le module est sélectionné, on récupère sa catégorie
    if ($module_id) {
        $stmtMod = $pdo->prepare("SELECT categorie_id FROM modules WHERE id = ?");
        $stmtMod->execute([$module_id]);
        $mod = $stmtMod->fetch();
        if ($mod && $mod['categorie_id']) {
            $categorie_id = $mod['categorie_id'];
        }
    }

    if (empty($titre)) {
        $message = 'Le titre du cours est obligatoire.';
        $messageType = 'red';
    } elseif (!$module_id) {
        $message = 'Veuillez sélectionner un module pour ce cours.';
        $messageType = 'red';
    } else {
        if ($course_id) {
            $stmt = $pdo->prepare("
                UPDATE courses SET titre = ?, description = ?, module_id = ?, categorie_id = ?, duree_estimee = ?
                WHERE id = ? AND enseignant_id = ?
            ");
            $stmt->execute([$titre, $description, $module_id, $categorie_id, $duree_estimee, $course_id, $_SESSION['user_id']]);
            $message = "Cours mis à jour avec succès.";

            // Rafraîchit les données du cours
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND enseignant_id = ?");
            $stmt->execute([$course_id, $_SESSION['user_id']]);
            $course = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO courses (titre, description, enseignant_id, module_id, categorie_id, duree_estimee, statut)
                VALUES (?, ?, ?, ?, ?, ?, 'en_attente')
            ");
            $stmt->execute([$titre, $description, $_SESSION['user_id'], $module_id, $categorie_id, $duree_estimee]);
            $course_id = $pdo->lastInsertId();
            $message = "Cours créé. En attente de validation par l'administrateur.";

            // Charge le cours créé
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
        }
    }
}

// Récupérer les leçons du cours avec info quiz
$lessons = [];
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY ordre ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
}
?>

<?php
$statusLabels = [
    'publie'     => 'Publié',
    'en_attente' => 'En attente de validation',
    'brouillon'  => 'Brouillon',
    'rejete'     => 'Rejeté',
];
$nbInscrits = 0;
if ($course_id) {
    $stmtIns = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
    $stmtIns->execute([$course_id]);
    $nbInscrits = (int)$stmtIns->fetchColumn();
}
?>

<p><a href="my_courses.php" style="color:#64748B; text-decoration:none;">← Mes cours</a></p>
<div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
    <h1 style="margin:0;"><?= $course ? 'Modifier' : 'Créer' ?> un cours</h1>
    <?php if ($course): ?>
        <span class="badge <?= $course['statut'] ?>"><?= htmlspecialchars($statusLabels[$course['statut']] ?? $course['statut']) ?></span>
        <span style="color:#64748B; font-size:14px;"><?= $nbInscrits ?> étudiant<?= $nbInscrits > 1 ? 's' : '' ?> inscrit<?= $nbInscrits > 1 ? 's' : '' ?></span>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div style="background:<?= $messageType === 'green' ? '#ECFDF5' : '#FEF2F2' ?>; border:1px solid <?= $messageType === 'green' ? '#10B981' : '#EF4444' ?>; color:<?= $messageType === 'green' ? '#065F46' : '#991B1B' ?>; padding:12px 16px; border-radius:8px; margin-bottom:20px;">
        <?= $message ?>
    </div>
<?php endif; ?>

<?php if (empty($modules)): ?>
    <div style="background:#FEF3C7; border:1px solid #F59E0B; color:#92400E; padding:14px 16px; border-radius:8px; margin-bottom:20px;">
        ⚠️ Aucun module n'a encore été créé par l'administrateur. Un cours doit appartenir à un module.
        Demandez à l'admin de créer un module avant de pouvoir créer un cours.
    </div>
<?php endif; ?>

<div style="background:white; border-radius:10px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:24px;">
    <form method="POST">
        <input type="hidden" name="course_id" value="<?= $course['id'] ?? '' ?>">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Module * <span style="color:#64748B; font-weight:400; font-size:13px;">(obligatoire)</span></label>
        <select name="module_id" required style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;"
                <?= empty($modules) ? 'disabled' : '' ?>>
            <option value="" disabled <?= !($course['module_id'] ?? null) ? 'selected' : '' ?>>— Sélectionner un module —</option>
            <?php foreach ($modules as $mod): ?>
                <option value="<?= $mod['id'] ?>" <?= ($course['module_id'] ?? null) == $mod['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($mod['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label style="font-weight:600; display:block; margin-bottom:4px;">Titre du cours *</label>
        <input type="text" name="titre" value="<?= htmlspecialchars($course['titre'] ?? '') ?>" required
               style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Description</label>
        <textarea name="description" rows="4" style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>

        <label style="font-weight:600; display:block; margin-bottom:4px;">Durée estimée <span style="color:#64748B; font-weight:400; font-size:13px;">(en minutes, facultatif)</span></label>
        <input type="number" name="duree_estimee" min="0" step="1" value="<?= htmlspecialchars($course['duree_estimee'] ?? '') ?>"
               style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">

        <button type="submit" class="btn" <?= empty($modules) ? 'disabled' : '' ?>>
            <?= $course ? 'Mettre à jour le cours' : 'Créer le cours' ?>
        </button>
    </form>
</div>

<?php if ($course_id): ?>
<div style="background:white; border-radius:10px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Leçons du cours</h2>
        <a href="upload_lesson.php?course_id=<?= $course_id ?>" class="btn">+ Ajouter une leçon</a>
    </div>

    <?php if (empty($lessons)): ?>
        <p style="color:#64748B;">Aucune leçon pour le moment.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach ($lessons as $lesson):
                $stmtQuiz = $pdo->prepare("SELECT id, titre FROM quizzes WHERE lesson_id = ?");
                $stmtQuiz->execute([$lesson['id']]);
                $quiz = $stmtQuiz->fetch();

                $stmtQCount = null;
                $nbQuestions = 0;
                if ($quiz) {
                    $stmtQCount = $pdo->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = ?");
                    $stmtQCount->execute([$quiz['id']]);
                    $nbQuestions = (int)$stmtQCount->fetchColumn();
                }

                $stmtAssign = $pdo->prepare("SELECT id, titre FROM assignments WHERE lesson_id = ?");
                $stmtAssign->execute([$lesson['id']]);
                $assignment = $stmtAssign->fetch();

                $nbSubmissions = 0;
                if ($assignment) {
                    $stmtSub = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = ?");
                    $stmtSub->execute([$assignment['id']]);
                    $nbSubmissions = (int)$stmtSub->fetchColumn();
                }
            ?>
            <li style="padding:16px; border:1px solid #E2E8F0; border-radius:8px; margin-bottom:12px;" id="lesson-<?= $lesson['id'] ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                    <div>
                        <strong><?= htmlspecialchars($lesson['titre']) ?></strong>
                        <span style="color:#94A3B8; font-size:12px; text-transform:uppercase; margin-left:8px;"><?= $lesson['type'] ?></span>

                        <?php if ($quiz): ?>
                            <span style="display:inline-block; margin-left:8px; font-size:12px; padding:2px 8px; border-radius:9999px;
                                background:<?= $nbQuestions > 0 ? '#ECFDF5' : '#FEF3C7' ?>;
                                color:<?= $nbQuestions > 0 ? '#065F46' : '#92400E' ?>;">
                                <?= $nbQuestions > 0 ? "✓ Quiz ({$nbQuestions} questions)" : ' Quiz vide — à compléter' ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($assignment): ?>
                            <span style="display:inline-block; margin-left:8px; font-size:12px; padding:2px 8px; border-radius:9999px; background:#EEF2FF; color:#3730A3;">
                                Devoir — <?= $nbSubmissions ?> soumission<?= $nbSubmissions > 1 ? 's' : '' ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <?php if ($quiz): ?>
                        <a href="create_quiz.php?lesson_id=<?= $lesson['id'] ?>"
                           style="background:<?= $nbQuestions > 0 ? '#F59E0B' : '#EF4444' ?>; color:white; padding:5px 12px; border-radius:4px; font-size:13px; text-decoration:none;">
                            <?= $nbQuestions > 0 ? ' Modifier le quiz' : ' Compléter le quiz' ?>
                        </a>
                        <?php endif; ?>

                        <a href="create_assignment.php?lesson_id=<?= $lesson['id'] ?>"
                           style="background:#6366F1; color:white; padding:5px 12px; border-radius:4px; font-size:13px; text-decoration:none;">
                            <?= $assignment ? 'Modifier le devoir' : '+ Ajouter un devoir' ?>
                        </a>

                        <button type="button" class="btn-delete-lesson" data-lesson-id="<?= $lesson['id'] ?>"
                                style="background:#EF4444; color:white; border:none; padding:5px 12px; border-radius:4px; font-size:13px; cursor:pointer;">
                            Supprimer
                        </button>
                    </div>
                </div>

                <?php
                $chemin = '/LLM/assets/uploads/' . ($lesson['type'] === 'pdf' ? 'pdfs/' : 'videos/') . htmlspecialchars($lesson['fichier_url']);
                ?>
                <?php if ($lesson['type'] === 'video'): ?>
                    <div style="margin-top:10px;">
                        <video controls preload="metadata" style="width:100%; max-width:560px; border-radius:6px;">
                            <source src="<?= $chemin ?>" type="video/mp4">
                        </video>
                    </div>
                <?php else: ?>
                    <div style="margin-top:8px;">
                        <a href="<?= $chemin ?>" target="_blank" style="color:#4F46E5; font-size:13px;">📄 Voir le PDF</a>
                    </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('.btn-delete-lesson').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Supprimer définitivement cette leçon et son quiz ?')) return;

        const lessonId = btn.dataset.lessonId;
        const formData = new FormData();
        formData.append('lesson_id', lessonId);

        const res = await fetch('../api/delete_lesson.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            document.getElementById('lesson-' + lessonId).remove();
        } else {
            alert(' ' + (data.message || 'Erreur lors de la suppression.'));
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>