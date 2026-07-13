<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Mes cours";
require_once 'header.php';

// Sous-requêtes plutôt que des LEFT JOIN multiples : évite tout risque de
// duplication de lignes (produit cartésien enrollments x lessons).
$stmt = $pdo->prepare("
    SELECT c.*,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS inscriptions,
           (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS nb_lecons
    FROM courses c
    WHERE c.enseignant_id = ?
    ORDER BY c.date_creation DESC
");
$stmt->execute([$_SESSION['user_id']]);
$courses = $stmt->fetchAll();

$statusLabels = [
    'publie'     => 'Publié',
    'en_attente' => 'En attente',
    'brouillon'  => 'Brouillon',
    'rejete'     => 'Rejeté',
];

$csrfToken = generate_csrf_token();
?>

<div class="t-card-header" style="margin-bottom:20px;">
    <h1 style="margin:0;">Mes cours</h1>
    <a href="course_builder.php" class="t-btn t-btn-primary">+ Créer un cours</a>
</div>

<?php if (empty($courses)): ?>
    <p class="t-empty">Vous n'avez encore créé aucun cours. Cliquez sur « Créer un cours » pour commencer.</p>
<?php else: ?>
<table class="t-table">
    <thead>
        <tr>
            <th>Titre</th>
            <th>Statut</th>
            <th>Leçons</th>
            <th>Inscrits</th>
            <th>Créé le</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($courses as $c): ?>
        <tr id="course-row-<?= $c['id'] ?>">
            <td data-label="Titre"><?= htmlspecialchars($c['titre']) ?></td>
            <td data-label="Statut">
                <span class="badge <?= $c['statut'] ?>"><?= htmlspecialchars($statusLabels[$c['statut']] ?? $c['statut']) ?></span>
            </td>
            <td data-label="Leçons"><?= (int)$c['nb_lecons'] ?></td>
            <td data-label="Inscrits"><?= (int)$c['inscriptions'] ?></td>
            <td data-label="Créé le"><?= date('d/m/Y', strtotime($c['date_creation'])) ?></td>
            <td data-label="Actions">
                <a href="course_builder.php?id=<?= $c['id'] ?>" class="t-btn t-btn-small">Modifier</a>
                <button type="button" class="t-btn t-btn-small t-btn-danger btn-delete-course"
                        data-course-id="<?= $c['id'] ?>" data-course-title="<?= htmlspecialchars($c['titre'], ENT_QUOTES) ?>">
                    Supprimer
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;

document.querySelectorAll('.btn-delete-course').forEach(btn => {
    btn.addEventListener('click', async () => {
        const courseId = btn.dataset.courseId;
        const courseTitle = btn.dataset.courseTitle;

        if (!confirm(`Supprimer définitivement le cours « ${courseTitle} » ainsi que toutes ses leçons et inscriptions ?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('course_id', courseId);
        formData.append('csrf_token', csrfToken);

        try {
            const res = await fetch('../api/delete_course.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                document.getElementById('course-row-' + courseId).remove();
                if (typeof showToast === 'function') showToast('Cours supprimé.', 'success');
            } else {
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Erreur lors de la suppression.', 'error');
                } else {
                    alert(data.message || 'Erreur lors de la suppression.');
                }
            }
        } catch (err) {
            alert('Erreur réseau lors de la suppression.');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>