<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Déposer un devoir";
require_once '../includes/header.php';

$studentId = $_SESSION['user_id'];

// Devoirs disponibles pour l'étudiant : appartenant à un cours où il est inscrit,
// et qu'il n'a pas encore soumis.
$stmt = $pdo->prepare("
    SELECT a.id, a.titre, a.date_limite,
           c.titre AS cours_titre,
           u.prenom AS prof_prenom, u.nom AS prof_nom
    FROM assignments a
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN users u ON c.enseignant_id = u.id
    JOIN enrollments e ON e.course_id = c.id AND e.student_id = ?
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
    WHERE s.id IS NULL
    ORDER BY c.titre ASC, a.date_limite ASC
");
$stmt->execute([$studentId, $studentId]);
$availableAssignments = $stmt->fetchAll();

// Historique des devoirs déjà déposés par l'étudiant
$stmt = $pdo->prepare("
    SELECT a.titre, c.titre AS cours_titre,
           s.date_soumission, s.note, s.commentaire_prof
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE s.student_id = ?
    ORDER BY s.date_soumission DESC
");
$stmt->execute([$studentId]);
$submittedAssignments = $stmt->fetchAll();

$csrfToken = generate_csrf_token();
?>

<h1>Déposer un devoir</h1>

<?php if (empty($availableAssignments)): ?>
    <p style="color:#64748B; font-size:14px;">
        Aucun devoir à déposer pour le moment. Vos professeurs n'en ont pas encore publié, ou vous êtes à jour sur tous vos devoirs.
    </p>
<?php else: ?>
<div style="background:white; border-radius:10px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:32px; max-width:520px;">
    <form id="upload-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <label style="font-weight:600; display:block; margin-bottom:6px;">Devoir à rendre *</label>
        <select name="assignment_id" id="assignment-select" required
                style="width:100%; padding:10px; margin-bottom:8px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">
            <option value="" disabled selected>— Sélectionner un devoir —</option>
            <?php
            $currentCourse = null;
            foreach ($availableAssignments as $a):
                if ($a['cours_titre'] !== $currentCourse):
                    if ($currentCourse !== null) echo '</optgroup>';
                    $currentCourse = $a['cours_titre'];
                    echo '<optgroup label="' . htmlspecialchars($currentCourse) . '">';
                endif;
                $dateLimiteLabel = $a['date_limite'] ? ' — à rendre avant le ' . date('d/m/Y', strtotime($a['date_limite'])) : '';
            ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['titre']) ?><?= htmlspecialchars($dateLimiteLabel) ?></option>
            <?php endforeach; ?>
            <?php if ($currentCourse !== null) echo '</optgroup>'; ?>
        </select>

        <div id="assignment-teacher-info" style="color:#64748B; font-size:13px; margin-bottom:16px; display:none;"></div>

        <label style="font-weight:600; display:block; margin-bottom:6px;">Fichier *</label>
        <input type="file" name="file" id="assignment-file" accept=".pdf,.zip,.doc,.docx" required
               style="width:100%; padding:8px 0; margin-bottom:6px;">
        <p style="color:#94A3B8; font-size:12px; margin:0 0 20px 0;">Formats acceptés : PDF, ZIP, DOC, DOCX — 10 Mo maximum.</p>

        <button type="submit" id="submit-btn" class="btn"
                style="background:#4F46E5; color:white; border:none; padding:10px 22px; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">
            Envoyer
        </button>
    </form>

    <div id="result" style="margin-top:16px;"></div>
</div>
<?php endif; ?>

<div style="background:white; border-radius:10px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
    <h2 style="margin-top:0;">Mes devoirs déposés</h2>
    <?php if (empty($submittedAssignments)): ?>
        <p style="color:#64748B; font-size:14px;">Vous n'avez encore déposé aucun devoir.</p>
    <?php else: ?>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left; padding:10px 12px; border-bottom:2px solid #E2E8F0; font-size:13px; color:#475569; text-transform:uppercase;">Devoir</th>
                    <th style="text-align:left; padding:10px 12px; border-bottom:2px solid #E2E8F0; font-size:13px; color:#475569; text-transform:uppercase;">Cours</th>
                    <th style="text-align:left; padding:10px 12px; border-bottom:2px solid #E2E8F0; font-size:13px; color:#475569; text-transform:uppercase;">Déposé le</th>
                    <th style="text-align:left; padding:10px 12px; border-bottom:2px solid #E2E8F0; font-size:13px; color:#475569; text-transform:uppercase;">Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submittedAssignments as $s): ?>
                <tr>
                    <td style="padding:12px; border-bottom:1px solid #F1F5F9; font-size:14px;"><?= htmlspecialchars($s['titre']) ?></td>
                    <td style="padding:12px; border-bottom:1px solid #F1F5F9; font-size:14px;"><?= htmlspecialchars($s['cours_titre']) ?></td>
                    <td style="padding:12px; border-bottom:1px solid #F1F5F9; font-size:14px;"><?= date('d/m/Y à H:i', strtotime($s['date_soumission'])) ?></td>
                    <td style="padding:12px; border-bottom:1px solid #F1F5F9; font-size:14px;">
                        <?php if ($s['note'] !== null): ?>
                            <span style="background:#ECFDF5; color:#065F46; padding:3px 10px; border-radius:9999px; font-size:12px; font-weight:600;">
                                <?= htmlspecialchars((string)$s['note']) ?> / 20
                            </span>
                        <?php else: ?>
                            <span style="background:#FEF3C7; color:#92400E; padding:3px 10px; border-radius:9999px; font-size:12px; font-weight:600;">
                                En attente de correction
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Associe à chaque devoir le nom du professeur qui le corrigera, affiché sous le select
const assignmentTeachers = {
    <?php foreach ($availableAssignments as $a): ?>
        "<?= $a['id'] ?>": "<?= htmlspecialchars(addslashes($a['prof_prenom'] . ' ' . $a['prof_nom'])) ?>",
    <?php endforeach; ?>
};

const assignmentSelect = document.getElementById('assignment-select');
const teacherInfo = document.getElementById('assignment-teacher-info');

if (assignmentSelect) {
    assignmentSelect.addEventListener('change', () => {
        const teacherName = assignmentTeachers[assignmentSelect.value];
        if (teacherName) {
            teacherInfo.textContent = `Ce devoir sera corrigé par ${teacherName}.`;
            teacherInfo.style.display = 'block';
        } else {
            teacherInfo.style.display = 'none';
        }
    });
}

const uploadForm = document.getElementById('upload-form');
if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.textContent = 'Envoi en cours...';

        const formData = new FormData(e.target);

        try {
            const res = await fetch('../api/assignments.php?action=submit', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            const resultBox = document.getElementById('result');
            if (data.success) {
                resultBox.innerHTML = `<div style="background:#ECFDF5; border:1px solid #10B981; color:#065F46; padding:12px 16px; border-radius:8px; font-size:14px;">${data.message}</div>`;
                setTimeout(() => location.reload(), 1200);
            } else {
                resultBox.innerHTML = `<div style="background:#FEF2F2; border:1px solid #EF4444; color:#991B1B; padding:12px 16px; border-radius:8px; font-size:14px;">${data.message || 'Erreur lors de l\'envoi.'}</div>`;
                btn.disabled = false;
                btn.textContent = 'Envoyer';
            }
        } catch (err) {
            document.getElementById('result').innerHTML = `<div style="background:#FEF2F2; border:1px solid #EF4444; color:#991B1B; padding:12px 16px; border-radius:8px; font-size:14px;">Erreur réseau. Veuillez réessayer.</div>`;
            btn.disabled = false;
            btn.textContent = 'Envoyer';
        }
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>