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

<p><a href="course_builder.php?id=<?= $course_id ?>" style="color:#64748B; text-decoration:none;">← Retour au cours</a></p>
<h1>Ajouter une leçon</h1>

<?php if (!$course_id): ?>
    <p style="color:red;">Erreur : Aucun cours sélectionné.</p>
    <a href="my_courses.php">Retour à mes cours</a>
<?php else: ?>

<div style="background:white; border-radius:10px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
    <form id="lesson-form" enctype="multipart/form-data">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Titre de la leçon *</label>
        <input type="text" name="titre" placeholder="Ex : Introduction à PHP" required
               style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px;">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Type de contenu *</label>
        <select name="type" required style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px;">
            <option value="pdf">Document PDF</option>
            <option value="video">Vidéo (MP4)</option>
        </select>

        <label style="font-weight:600; display:block; margin-bottom:4px;">Fichier *</label>
        <input type="file" name="file" accept=".pdf,.mp4" required style="margin-bottom:20px;">

        <button type="submit" class="btn" id="submit-btn">Uploader la leçon</button>
    </form>

    <div id="result" style="margin-top:16px;"></div>
</div>

<script>
document.getElementById('lesson-form').onsubmit = async (e) => {
    e.preventDefault();

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.textContent = 'Upload en cours...';

    const formData = new FormData(e.target);

    try {
        const res = await fetch('../api/lessons.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('result').innerHTML = `
                <div style="background:#ECFDF5; border:1px solid #10B981; border-radius:8px; padding:16px;">
                    <p style="color:#065F46; font-weight:600; margin-bottom:12px;">✅ ${data.message}</p>
                    <p style="color:#065F46; font-size:14px; margin-bottom:12px;">
                        Un quiz vide a été automatiquement créé pour cette leçon.
                        <strong>Complétez-le maintenant pour que les étudiants puissent passer l'évaluation.</strong>
                    </p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="create_quiz.php?lesson_id=${data.lesson_id}"
                           style="background:#6366F1; color:white; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:600;">
                            ✏️ Compléter le quiz maintenant
                        </a>
                        <a href="course_builder.php?id=${formData.get('course_id')}"
                           style="background:#94A3B8; color:white; padding:10px 18px; border-radius:6px; text-decoration:none;">
                            Retour au cours
                        </a>
                    </div>
                </div>
            `;
            e.target.style.display = 'none';
        } else {
            document.getElementById('result').innerHTML = `
                <div style="background:#FEF2F2; border:1px solid #EF4444; border-radius:8px; padding:14px; color:#991B1B;">
                    ❌ ${data.message || 'Erreur inconnue'}
                </div>
            `;
            btn.disabled = false;
            btn.textContent = 'Uploader la leçon';
        }
    } catch (err) {
        document.getElementById('result').innerHTML = `
            <div style="background:#FEF2F2; border:1px solid #EF4444; border-radius:8px; padding:14px; color:#991B1B;">
                ❌ Erreur réseau. Veuillez réessayer.
            </div>
        `;
        btn.disabled = false;
        btn.textContent = 'Uploader la leçon';
    }
};
</script>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>