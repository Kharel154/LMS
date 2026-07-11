<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Validation des cours";
require_once 'header.php';

$csrf_token = generate_csrf_token();
?>

<style>
.val-btn {
    background: #4F46E5;
    color: white;
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    font-family: inherit;
    transition: opacity 0.2s;
}
.val-btn:hover { opacity: 0.9; }
.val-btn-success { background: #10B981; }
.val-btn-danger { background: #EF4444; }

.val-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 14px 24px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1100;
    display: none;
    box-shadow: 0 10px 15px rgba(0,0,0,0.2);
}
.val-toast.success { background: #10B981; }
.val-toast.error { background: #EF4444; }

.val-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 20px;
}
.val-card {
    background: white;
    border-radius: 12px;
    padding: 22px 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    font-family: inherit;
}
.val-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
}
.val-card h3 { margin: 0 0 4px; font-size: 17px; color: #1E293B; }
.val-meta { color: #64748B; font-size: 13.5px; }
.val-desc {
    color: #475569;
    font-size: 14px;
    margin: 12px 0;
    line-height: 1.5;
}
.val-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin: 10px 0 14px;
}
.val-tag {
    font-size: 12.5px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 9999px;
    background: #EEF2FF;
    color: #4F46E5;
}
.val-tag-warning {
    background: #FEF3C7;
    color: #92400E;
}
.val-actions {
    display: flex;
    gap: 10px;
}
.val-empty {
    background: white;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    color: #94A3B8;
    font-size: 15px;
}
</style>

<h1>Cours en attente de validation</h1>
<p style="color:#64748B; margin-bottom:10px;">
    Approuvez un cours pour le publier immédiatement, ou rejetez-le pour le renvoyer à l'enseignant.
    Un cours sans module assigné n'apparaîtra pas dans le catalogue étudiant même une fois publié.
</p>

<div id="val-list" class="val-list">
    <div class="val-empty">Chargement...</div>
</div>

<div id="toast" class="val-toast"></div>

<script>
const CSRF_TOKEN = "<?= $csrf_token ?>";

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `val-toast ${type}`;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
}

async function loadPendingCourses() {
    const list = document.getElementById('val-list');
    try {
        const res = await fetch('../api/courses.php?action=pending');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (!data.success) {
            list.innerHTML = `<div class="val-empty">${escapeHtml(data.message || 'Erreur serveur')}</div>`;
            return;
        }

        if (data.courses.length === 0) {
            list.innerHTML = '<div class="val-empty">Aucun cours en attente de validation pour le moment.</div>';
            return;
        }

        list.innerHTML = data.courses.map(c => `
            <div class="val-card" id="course-${c.id}">
                <div class="val-card-header">
                    <div>
                        <h3>${escapeHtml(c.titre)}</h3>
                        <div class="val-meta">Par ${escapeHtml(c.prenom)} ${escapeHtml(c.nom_enseignant)} — soumis le ${formatDate(c.date_creation)}</div>
                    </div>
                    <div class="val-actions">
                        <button class="val-btn val-btn-success" onclick="validateCourse(${c.id}, 'publie')">Approuver</button>
                        <button class="val-btn val-btn-danger" onclick="validateCourse(${c.id}, 'rejete')">Rejeter</button>
                    </div>
                </div>
                <div class="val-tags">
                    <span class="val-tag">${c.nb_lecons} leçon${c.nb_lecons > 1 ? 's' : ''}</span>
                    ${c.module_nom
                        ? `<span class="val-tag">Module : ${escapeHtml(c.module_nom)}</span>`
                        : `<span class="val-tag val-tag-warning">Aucun module assigné — invisible au catalogue</span>`}
                </div>
                ${c.description ? `<p class="val-desc">${escapeHtml(c.description)}</p>` : ''}
            </div>
        `).join('');
    } catch (err) {
        console.error(err);
        list.innerHTML = '<div class="val-empty">Erreur de chargement. Vérifiez la console.</div>';
    }
}

async function validateCourse(id, statut) {
    const formData = new FormData();
    formData.append('action', 'validate');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('course_id', id);
    formData.append('statut', statut);

    try {
        const res = await fetch('../api/courses.php', { method: 'POST', body: formData });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Succès' : 'Erreur'), data.success ? 'success' : 'error');
        if (data.success) {
            const card = document.getElementById('course-' + id);
            if (card) card.remove();
            const list = document.getElementById('val-list');
            if (!list.children.length) {
                list.innerHTML = '<div class="val-empty">Aucun cours en attente de validation pour le moment.</div>';
            }
        }
    } catch (err) {
        console.error(err);
        showToast('Erreur réseau', 'error');
    }
}

loadPendingCourses();
</script>

<?php require_once '../includes/footer.php'; ?>