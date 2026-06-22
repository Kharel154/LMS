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

// Traitement création/modification du cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titre'])) {
    $titre = sanitize_input($_POST['titre'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if ($course_id) {
        $stmt = $pdo->prepare("UPDATE courses SET titre = ?, description = ? WHERE id = ?");
        $stmt->execute([$titre, $description, $course_id]);
        $message = "Cours mis à jour avec succès !";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO courses (titre, description, enseignant_id, statut, categorie_id) 
            VALUES (?, ?, ?, 'en_attente', 1)
        ");
        $stmt->execute([$titre, $description, $_SESSION['user_id']]);
        $course_id = $pdo->lastInsertId();
        $message = "Cours créé avec succès ! En attente de validation par l'admin.";
    }
}

// Récupérer les leçons du cours
$lessons = [];
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY ordre ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
}
?>

<h1><?= $course ? 'Modifier' : 'Créer' ?> un cours</h1>

<?php if (isset($message)): ?>
    <p style="color: green; font-weight: bold;"><?= $message ?></p>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="course_id" value="<?= $course['id'] ?? '' ?>">
    <label>Titre du cours :</label>
    <input type="text" name="titre" value="<?= htmlspecialchars($course['titre'] ?? '') ?>" required style="width:100%; padding:10px; margin-bottom:15px;">

    <label>Description :</label>
    <textarea name="description" rows="5" style="width:100%;"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>

    <button type="submit" class="btn"><?= $course ? 'Mettre à jour le cours' : 'Créer le cours' ?></button>
</form>

<hr>

<?php if ($course_id): ?>
    <h2>Leçons du cours</h2>
    <a href="upload_lesson.php?course_id=<?= $course_id ?>" class="btn">+ Ajouter une nouvelle leçon</a>

    <?php if (empty($lessons)): ?>
        <p>Aucune leçon pour le moment.</p>
    <?php else: ?>
        <ul style="list-style:none; padding:0;">
            <?php foreach ($lessons as $lesson): ?>
            <li style="margin-bottom: 20px; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px;" id="lesson-<?= $lesson['id'] ?>">
                <strong><?= htmlspecialchars($lesson['titre']) ?></strong>
                (<?= htmlspecialchars($lesson['type']) ?>)

                <button type="button" class="btn-delete-lesson" data-lesson-id="<?= $lesson['id'] ?>" style="background:#dc2626; color:white; border:none; padding:4px 10px; border-radius:4px; cursor:pointer; margin-left:10px;">
                    Supprimer
                </button>

                <?php
                // Vérifie si un quiz existe déjà pour cette leçon
                $stmtQuiz = $pdo->prepare("SELECT id FROM quizzes WHERE lesson_id = ?");
                $stmtQuiz->execute([$lesson['id']]);
                $hasQuiz = $stmtQuiz->fetch();
                ?>
                <a href="create_quiz.php?lesson_id=<?= $lesson['id'] ?>" class="btn" style="background:<?= $hasQuiz ? '#F59E0B' : '#6366F1' ?>; margin-left:10px; display:inline-block; padding:4px 10px; font-size:14px; text-decoration:none; color:white; border-radius:4px;">
                    <?= $hasQuiz ? ' Modifier l\'évaluation' : ' Ajouter une évaluation' ?>
                </a>

                <?php
                $chemin_fichier = '/LLM/assets/uploads/' . ($lesson['type'] === 'pdf' ? 'pdfs/' : 'videos/') . htmlspecialchars($lesson['fichier_url']);
                ?>

                <?php if ($lesson['type'] === 'video'): ?>
                    <div style="margin-top: 8px;">
                        <video controls preload="metadata" style="width: 100%; max-width: 640px; border-radius: 6px;">
                            <source src="<?= $chemin_fichier ?>" type="video/mp4">
                            Votre navigateur ne supporte pas la lecture vidéo.
                        </video>
                    </div>
                <?php else: ?>
                    <br>
                    <a href="<?= $chemin_fichier ?>" target="_blank">Voir le PDF</a>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endif; ?>

<script>
document.querySelectorAll('.btn-delete-lesson').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Supprimer définitivement cette leçon ?')) return;

        const lessonId = btn.dataset.lessonId;
        const formData = new FormData();
        formData.append('lesson_id', lessonId);

        const res = await fetch('../api/delete_lesson.php', {
            method: 'POST',
            body: formData
        });
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