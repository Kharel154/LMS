<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$course_id = (int)($_GET['course_id'] ?? 0);
$title = "Ajouter une leçon";
require_once 'header.php';
?>

<h1>Ajouter une leçon</h1>

<?php if (!$course_id): ?>
    <p style="color:red;">Erreur : Aucun cours sélectionné.</p>
    <a href="my_courses.php">Retour à mes cours</a>
<?php else: ?>
    <form id="lesson-form" enctype="multipart/form-data">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <label>Titre de la leçon :</label>
        <input type="text" name="titre" placeholder="Ex: Introduction à PHP" required>

        <label>Type de contenu :</label>
        <select name="type" required>
            <option value="pdf">Document PDF</option>
            <option value="video">Vidéo (MP4)</option>
        </select>

        <label>Fichier :</label>
        <input type="file" name="file" accept=".pdf,.mp4" required>

        <button type="submit" class="btn">Uploader la leçon</button>
    </form>

    <div id="result"></div>
<?php endif; ?>

<script>
document.getElementById('lesson-form').onsubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    const res = await fetch('../api/lessons.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await res.json();
    
    if (data.success) {
        document.getElementById('result').innerHTML = `
            <p style="color:green"> ${data.message}</p>
            <p>
                <a href="create_quiz.php?lesson_id=${data.lesson_id}" class="btn" style="background:#6366F1;">
                     Ajouter l'évaluation de cette leçon
                </a>
                ou
                <a href="course_builder.php?id=${formData.get('course_id')}">retourner au cours</a>
            </p>
        `;  
    } else {
        document.getElementById('result').innerHTML = `<p style="color:red"> ${data.message || 'Erreur'}</p>`;
    }
};
</script>

<?php require_once '../includes/footer.php'; ?>