<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Corrections";
require_once 'header.php';

$teacherId = $_SESSION['user_id'];

// Devoirs en attente de correction (note_passage NULL) pour les cours de ce prof
$stmt = $pdo->prepare("
    SELECT s.id, s.fichier_url, s.date_soumission,
           a.titre AS assignment_title, a.date_limite,
           u.prenom, u.nom
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.enseignant_id = ? AND s.note IS NULL
    ORDER BY s.date_soumission ASC
");
$stmt->execute([$teacherId]);
$submissions = $stmt->fetchAll();

// Devoirs déjà corrigés (10 plus récents), pour garder une trace visible
$stmt = $pdo->prepare("
    SELECT s.id, s.note, s.commentaire_prof, s.date_correction,
           a.titre AS assignment_title,
           u.prenom, u.nom
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.enseignant_id = ? AND s.note IS NOT NULL
    ORDER BY s.date_correction DESC
    LIMIT 10
");
$stmt->execute([$teacherId]);
$graded = $stmt->fetchAll();

$csrfToken = generate_csrf_token();

// Dossier des dépôts de devoirs. À ajuster si le chemin réel diffère
// (non documenté au moment de cette mise à jour — vérifier avec student/upload_assignment.php).
$assignmentUploadPath = '../assets/uploads/assignments/';
?>

<h1>Devoirs à corriger</h1>

<?php if (empty($submissions)): ?>
    <p class="t-empty">Aucun devoir en attente de correction pour le moment.</p>
<?php else: ?>
<table class="t-table">
    <thead>
        <tr>
            <th>Étudiant</th>
            <th>Devoir</th>
            <th>Soumis le</th>
            <th>Fichier</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($submissions as $sub): ?>
        <tr id="submission-row-<?= $sub['id'] ?>">
            <td data-label="Étudiant"><?= htmlspecialchars($sub['prenom'] . ' ' . $sub['nom']) ?></td>
            <td data-label="Devoir"><?= htmlspecialchars($sub['assignment_title']) ?></td>
            <td data-label="Soumis le"><?= date('d/m/Y à H:i', strtotime($sub['date_soumission'])) ?></td>
            <td data-label="Fichier">
                <?php if (!empty($sub['fichier_url'])): ?>
                    <a href="<?= $assignmentUploadPath . htmlspecialchars($sub['fichier_url']) ?>" target="_blank" class="t-link">Voir le fichier</a>
                <?php else: ?>
                    <span style="color:#94A3B8;">—</span>
                <?php endif; ?>
            </td>
            <td data-label="Action">
                <button type="button" class="t-btn t-btn-small t-btn-primary btn-grade"
                        data-submission-id="<?= $sub['id'] ?>"
                        data-student-name="<?= htmlspecialchars($sub['prenom'] . ' ' . $sub['nom'], ENT_QUOTES) ?>"
                        data-assignment-title="<?= htmlspecialchars($sub['assignment_title'], ENT_QUOTES) ?>">
                    Noter
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="t-card" style="margin-top:32px;">
    <h2>Corrections récentes</h2>
    <?php if (empty($graded)): ?>
        <p class="t-empty">Aucune correction effectuée pour le moment.</p>
    <?php else: ?>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Devoir</th>
                    <th>Note</th>
                    <th>Corrigé le</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($graded as $g): ?>
                <tr>
                    <td data-label="Étudiant"><?= htmlspecialchars($g['prenom'] . ' ' . $g['nom']) ?></td>
                    <td data-label="Devoir"><?= htmlspecialchars($g['assignment_title']) ?></td>
                    <td data-label="Note"><?= htmlspecialchars((string)$g['note']) ?> / 20</td>
                    <td data-label="Corrigé le"><?= date('d/m/Y', strtotime($g['date_correction'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modale de notation -->
<div id="grade-modal-overlay" class="t-modal-overlay" style="display:none;">
    <div class="t-modal">
        <h2 id="grade-modal-title">Noter le devoir</h2>
        <form id="grade-form">
            <input type="hidden" id="grade-submission-id" name="submission_id" value="">

            <label class="t-form-label">Note <span style="color:#64748B; font-weight:400; font-size:13px;">(sur 20)</span></label>
            <input type="number" id="grade-note" name="note" min="0" max="20" step="0.5" required class="t-form-input">

            <label class="t-form-label">Commentaire</label>
            <textarea id="grade-comment" name="comment" rows="4" class="t-form-input"></textarea>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:16px;">
                <button type="button" id="grade-cancel" class="t-btn">Annuler</button>
                <button type="submit" class="t-btn t-btn-primary">Enregistrer la note</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = <?= json_encode($csrfToken) ?>;

const modalOverlay = document.getElementById('grade-modal-overlay');
const gradeForm = document.getElementById('grade-form');
const modalTitle = document.getElementById('grade-modal-title');

document.querySelectorAll('.btn-grade').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('grade-submission-id').value = btn.dataset.submissionId;
        document.getElementById('grade-note').value = '';
        document.getElementById('grade-comment').value = '';
        modalTitle.textContent = `Noter — ${btn.dataset.studentName} (${btn.dataset.assignmentTitle})`;
        modalOverlay.style.display = 'flex';
    });
});

document.getElementById('grade-cancel').addEventListener('click', () => {
    modalOverlay.style.display = 'none';
});

modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) modalOverlay.style.display = 'none';
});

gradeForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const submissionId = document.getElementById('grade-submission-id').value;
    const formData = new FormData();
    formData.append('submission_id', submissionId);
    formData.append('note', document.getElementById('grade-note').value);
    formData.append('comment', document.getElementById('grade-comment').value);
    formData.append('csrf_token', csrfToken);

    try {
        const res = await fetch('../api/grades.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            document.getElementById('submission-row-' + submissionId).remove();
            modalOverlay.style.display = 'none';
            if (typeof showToast === 'function') showToast('Note enregistrée.', 'success');
            else location.reload();
        } else {
            alert(data.message || 'Erreur lors de l\'enregistrement de la note.');
        }
    } catch (err) {
        alert('Erreur réseau lors de l\'enregistrement de la note.');
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>