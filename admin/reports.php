<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Signalements";
require_once 'header.php';

$csrf_token = generate_csrf_token();
?>

<style>
.rep-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    margin: 20px 0 24px;
}
.rep-stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.rep-stat-card h2 { margin: 0 0 4px; font-size: 26px; color: #1E293B; }
.rep-stat-card p { margin: 0; color: #64748B; font-size: 13.5px; }

.rep-filters {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.rep-filter-btn {
    background: white;
    border: 1px solid #E2E8F0;
    color: #475569;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 13.5px;
    font-family: inherit;
}
.rep-filter-btn.active {
    background: #4F46E5;
    color: white;
    border-color: #4F46E5;
}

.rep-table {
    width: 100%;
    background: white;
    border-collapse: collapse;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    font-family: inherit;
}
.rep-table th { text-align: left; padding: 12px 16px; background: #F8FAFC; font-size: 13.5px; color: #475569; }
.rep-table td { padding: 12px 16px; border-top: 1px solid #F1F5F9; font-size: 14px; vertical-align: top; }

.rep-badge {
    padding: 4px 12px;
    border-radius: 9999px;
    font-size: 12.5px;
    font-weight: 600;
    white-space: nowrap;
}
.rep-badge-ouvert { background: #FEE2E2; color: #B91C1C; }
.rep-badge-en_cours { background: #FEF3C7; color: #92400E; }
.rep-badge-resolu { background: #ECFDF5; color: #047857; }

.rep-select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #E2E8F0;
    font-family: inherit;
    font-size: 13.5px;
}
.rep-empty {
    background: white;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    color: #94A3B8;
    font-size: 15px;
}
.rep-toast {
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
.rep-toast.success { background: #10B981; }
.rep-toast.error { background: #EF4444; }
</style>

<h1>Signalements</h1>
<p style="color:#64748B; margin-bottom:6px;">Consultez et traitez les signalements remontés par les utilisateurs.</p>

<div class="rep-stats" id="rep-stats"></div>

<div class="rep-filters">
    <button class="rep-filter-btn active" data-filter="all" onclick="setFilter('all')">Tous</button>
    <button class="rep-filter-btn" data-filter="ouvert" onclick="setFilter('ouvert')">Ouverts</button>
    <button class="rep-filter-btn" data-filter="en_cours" onclick="setFilter('en_cours')">En cours</button>
    <button class="rep-filter-btn" data-filter="resolu" onclick="setFilter('resolu')">Résolus</button>
</div>

<div id="rep-table-wrapper">
    <div class="rep-empty">Chargement...</div>
</div>

<div id="toast" class="rep-toast"></div>

<script>
const CSRF_TOKEN = "<?= $csrf_token ?>";
let ALL_REPORTS = [];
let CURRENT_FILTER = 'all';

const STATUT_LABELS = { ouvert: 'Ouvert', en_cours: 'En cours', resolu: 'Résolu' };
const TYPE_LABELS = { technique: 'Technique', comportemental: 'Comportemental' };

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `rep-toast ${type}`;
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
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function renderStats() {
    const total = ALL_REPORTS.length;
    const ouverts = ALL_REPORTS.filter(r => r.statut === 'ouvert').length;
    const enCours = ALL_REPORTS.filter(r => r.statut === 'en_cours').length;
    const resolus = ALL_REPORTS.filter(r => r.statut === 'resolu').length;

    document.getElementById('rep-stats').innerHTML = `
        <div class="rep-stat-card"><h2>${total}</h2><p>Total signalements</p></div>
        <div class="rep-stat-card"><h2>${ouverts}</h2><p>Ouverts</p></div>
        <div class="rep-stat-card"><h2>${enCours}</h2><p>En cours de traitement</p></div>
        <div class="rep-stat-card"><h2>${resolus}</h2><p>Résolus</p></div>
    `;
}

function renderTable() {
    const wrapper = document.getElementById('rep-table-wrapper');
    const filtered = CURRENT_FILTER === 'all'
        ? ALL_REPORTS
        : ALL_REPORTS.filter(r => r.statut === CURRENT_FILTER);

    if (filtered.length === 0) {
        wrapper.innerHTML = '<div class="rep-empty">Aucun signalement pour ce filtre.</div>';
        return;
    }

    wrapper.innerHTML = `
        <table class="rep-table">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                ${filtered.map(r => `
                    <tr>
                        <td>
                            <strong>${escapeHtml(r.prenom)} ${escapeHtml(r.nom)}</strong><br>
                            <span style="color:#94A3B8; font-size:12.5px;">${escapeHtml(r.email)} · ${escapeHtml(r.reporter_role)}</span>
                        </td>
                        <td>${TYPE_LABELS[r.type] || escapeHtml(r.type)}</td>
                        <td style="max-width:340px;">${escapeHtml(r.description)}</td>
                        <td>${formatDate(r.date_creation)}</td>
                        <td>
                            <span class="rep-badge rep-badge-${r.statut}">${STATUT_LABELS[r.statut] || r.statut}</span>
                            <select class="rep-select" onchange="updateStatus(${r.id}, this.value)">
                                <option value="">Changer...</option>
                                <option value="ouvert" ${r.statut === 'ouvert' ? 'disabled' : ''}>Ouvert</option>
                                <option value="en_cours" ${r.statut === 'en_cours' ? 'disabled' : ''}>En cours</option>
                                <option value="resolu" ${r.statut === 'resolu' ? 'disabled' : ''}>Résolu</option>
                            </select>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function setFilter(filter) {
    CURRENT_FILTER = filter;
    document.querySelectorAll('.rep-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === filter);
    });
    renderTable();
}

async function loadReports() {
    const wrapper = document.getElementById('rep-table-wrapper');
    try {
        const res = await fetch('../api/reports.php?action=list');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (!data.success) {
            wrapper.innerHTML = `<div class="rep-empty">${escapeHtml(data.message || 'Erreur serveur')}</div>`;
            return;
        }

        ALL_REPORTS = data.reports;
        renderStats();
        renderTable();
    } catch (err) {
        console.error(err);
        wrapper.innerHTML = '<div class="rep-empty">Erreur de chargement. Vérifiez la console.</div>';
    }
}

async function updateStatus(reportId, statut) {
    if (!statut) return;

    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('report_id', reportId);
    formData.append('statut', statut);

    try {
        const res = await fetch('../api/reports.php', { method: 'POST', body: formData });
        const data = await res.json();
        showToast(data.message || (data.success ? 'Succès' : 'Erreur'), data.success ? 'success' : 'error');
        if (data.success) {
            const report = ALL_REPORTS.find(r => r.id === reportId);
            if (report) report.statut = statut;
            renderStats();
            renderTable();
        }
    } catch (err) {
        console.error(err);
        showToast('Erreur réseau', 'error');
    }
}

loadReports();
</script>

<?php require_once '../includes/footer.php'; ?>