<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Catalogue des cours";
require_once '../includes/header.php';

// Tous les cours publiés + indique si l'étudiant est déjà inscrit
$stmt = $pdo->prepare("
    SELECT c.*, u.prenom, u.nom AS nom_enseignant,
           cat.nom AS categorie_nom, cat.couleur AS categorie_couleur,
           (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS nb_lecons,
           (SELECT e.id FROM enrollments e WHERE e.course_id = c.id AND e.student_id = ?) AS enrollment_id
    FROM courses c
    JOIN users u ON c.enseignant_id = u.id
    LEFT JOIN categories cat ON cat.id = c.categorie_id
    WHERE c.statut = 'publie'
    ORDER BY c.date_creation DESC
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();
?>

<h1>Catalogue des cours</h1>

<div class="dashboard-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:24px;">
    <?php if (empty($courses)): ?>
        <p>Aucun cours disponible pour le moment.</p>
    <?php endif; ?>

    <?php foreach ($courses as $course): ?>
    <div class="course-card">
        <?php if (!empty($course['thumbnail'])): ?>
        <img src="../assets/uploads/<?= htmlspecialchars($course['thumbnail']) ?>"
             alt="<?= htmlspecialchars($course['titre']) ?>"
             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
        <?php endif; ?>
        <div style="width:100%; height:180px; background:linear-gradient(135deg, #4F46E5, #818CF8); display:<?= !empty($course['thumbnail']) ? 'none' : 'flex' ?>; align-items:center; justify-content:center; color:white; font-size:32px; font-weight:700;">
            <?= htmlspecialchars(mb_substr($course['titre'], 0, 1)) ?>
        </div>

        <div style="padding:18px;">
            <?php if ($course['categorie_nom']): ?>
                <span style="display:inline-block; background:<?= htmlspecialchars($course['categorie_couleur'] ?? '#4F46E5') ?>;
                             color:white; font-size:12px; padding:3px 10px; border-radius:9999px; margin-bottom:8px;">
                    <?= htmlspecialchars($course['categorie_nom']) ?>
                </span>
            <?php endif; ?>

            <h3 style="margin-bottom:6px;"><?= htmlspecialchars($course['titre']) ?></h3>
            <p style="color:#64748B; font-size:14px; margin-bottom:10px;">
                Par <?= htmlspecialchars($course['prenom'] . ' ' . $course['nom_enseignant']) ?>
            </p>
            <p style="color:#64748B; font-size:13px; margin-bottom:14px;">
                <?= (int)$course['nb_lecons'] ?> leçon(s)
                <?php if ($course['duree_estimee']): ?> · <?= (int)$course['duree_estimee'] ?> min<?php endif; ?>
            </p>

            <?php if ($course['enrollment_id']): ?>
                <a href="course-view.php?id=<?= $course['id'] ?>" class="btn btn-success" style="display:block; text-align:center; text-decoration:none;">
                    ✓ Déjà inscrit — Continuer
                </a>
            <?php else: ?>
                <button class="btn" style="width:100%;" onclick="enroll(<?= $course['id'] ?>, this)">
                    S'inscrire
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div id="toast" class="toast"></div>

<script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3000);
}

async function enroll(courseId, btnEl) {
    btnEl.disabled = true;
    btnEl.textContent = 'Inscription...';

    try {
        const formData = new FormData();
        formData.append('action', 'enroll');
        formData.append('course_id', courseId);

        const res = await fetch('../api/courses.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Inscription réussie !');
            setTimeout(() => window.location.href = 'course-view.php?id=' + courseId, 800);
        } else {
            showToast(data.message || 'Erreur lors de l\'inscription', 'error');
            btnEl.disabled = false;
            btnEl.textContent = "S'inscrire";
        }
    } catch (err) {
        showToast('Erreur réseau', 'error');
        btnEl.disabled = false;
        btnEl.textContent = "S'inscrire";
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>