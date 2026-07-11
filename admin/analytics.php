<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Analytics";
require_once 'header.php';
?>

<style>
.ana-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0 30px;
}
.ana-stat-card {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.ana-stat-card h2 { margin: 0 0 4px; font-size: 28px; color: #1E293B; }
.ana-stat-card p { margin: 0; color: #64748B; font-size: 13.5px; }

.ana-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}
@media (max-width: 900px) {
    .ana-grid { grid-template-columns: 1fr; }
}
.ana-panel {
    background: white;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.ana-panel h3 { margin: 0 0 16px; font-size: 16px; color: #1E293B; }

.ana-list { list-style: none; margin: 0; padding: 0; }
.ana-list li {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #F1F5F9;
    font-size: 14px;
    color: #334155;
}
.ana-list li:last-child { border-bottom: none; }
.ana-list .ana-count {
    font-weight: 600;
    color: #4F46E5;
}

.ana-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.ana-table th { text-align: left; padding: 8px 6px; color: #64748B; border-bottom: 1px solid #E2E8F0; }
.ana-table td { padding: 8px 6px; border-bottom: 1px solid #F1F5F9; color: #334155; }
.ana-empty { color: #94A3B8; font-size: 14px; padding: 10px 0; }
</style>

<h1>Statistiques globales</h1>

<div class="ana-stats" id="ana-stats">
    <div class="ana-stat-card"><h2>—</h2><p>Chargement...</p></div>
</div>

<div class="ana-grid">
    <div class="ana-panel">
        <h3>Inscriptions par mois (année en cours)</h3>
        <canvas id="enrollmentsChart" height="110"></canvas>
    </div>
    <div class="ana-panel">
        <h3>Répartition des cours par statut</h3>
        <canvas id="statusChart" height="110"></canvas>
    </div>
</div>

<div class="ana-grid">
    <div class="ana-panel">
        <h3>Top 5 modules les plus suivis</h3>
        <ul class="ana-list" id="top-modules"><li class="ana-empty">Chargement...</li></ul>
    </div>
    <div class="ana-panel">
        <h3>Top 5 enseignants (cours publiés)</h3>
        <ul class="ana-list" id="top-teachers"><li class="ana-empty">Chargement...</li></ul>
    </div>
</div>

<div class="ana-panel">
    <h3>Dernières connexions</h3>
    <div id="recent-connections"><p class="ana-empty">Chargement...</p></div>
</div>

<script>
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    return d.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

async function loadAnalytics() {
    try {
        const res = await fetch('../api/analytics.php?action=dashboard_stats');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (!data.success) {
            document.getElementById('ana-stats').innerHTML =
                `<div class="ana-stat-card"><p style="color:#EF4444;">${escapeHtml(data.message || 'Erreur serveur')}</p></div>`;
            return;
        }

        // KPI cards
        const q = data.quiz_success;
        document.getElementById('ana-stats').innerHTML = `
            <div class="ana-stat-card"><h2>${q.taux !== null ? q.taux + '%' : '—'}</h2><p>Taux de réussite global des quiz</p></div>
            <div class="ana-stat-card"><h2>${q.total_tentatives}</h2><p>Tentatives de quiz au total</p></div>
            <div class="ana-stat-card"><h2>${q.total_reussies}</h2><p>Quiz réussis</p></div>
        `;

        // Graphique inscriptions par mois
        new Chart(document.getElementById('enrollmentsChart'), {
            type: 'bar',
            data: {
                labels: data.monthly_enrollments.labels,
                datasets: [{
                    label: 'Inscriptions',
                    data: data.monthly_enrollments.data,
                    backgroundColor: '#818CF8'
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });

        // Graphique répartition des statuts
        new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: data.courses_by_status.labels,
                datasets: [{
                    data: data.courses_by_status.data,
                    backgroundColor: ['#10B981', '#F59E0B', '#94A3B8', '#EF4444']
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        });

        // Top modules
        const topModulesEl = document.getElementById('top-modules');
        topModulesEl.innerHTML = data.top_modules.length
            ? data.top_modules.map(m => `<li>${escapeHtml(m.nom)} <span class="ana-count">${m.nb_etudiants} étudiant${m.nb_etudiants > 1 ? 's' : ''}</span></li>`).join('')
            : '<li class="ana-empty">Aucune inscription pour le moment.</li>';

        // Top enseignants
        const topTeachersEl = document.getElementById('top-teachers');
        topTeachersEl.innerHTML = data.top_teachers.length
            ? data.top_teachers.map(t => `<li>${escapeHtml(t.prenom)} ${escapeHtml(t.nom)} <span class="ana-count">${t.nb_cours} cours</span></li>`).join('')
            : '<li class="ana-empty">Aucun cours publié pour le moment.</li>';

        // Dernières connexions
        const recentEl = document.getElementById('recent-connections');
        recentEl.innerHTML = data.recent_connections.length
            ? `<table class="ana-table">
                <thead><tr><th>Utilisateur</th><th>Action</th><th>Date</th><th>IP</th></tr></thead>
                <tbody>
                    ${data.recent_connections.map(c => `
                        <tr>
                            <td>${c.prenom ? escapeHtml(c.prenom) + ' ' + escapeHtml(c.nom) : 'Inconnu'} ${c.role ? '(' + escapeHtml(c.role) + ')' : ''}</td>
                            <td>${escapeHtml(c.action)}</td>
                            <td>${formatDateTime(c.date_connexion)}</td>
                            <td>${escapeHtml(c.ip_address)}</td>
                        </tr>
                    `).join('')}
                </tbody>
               </table>`
            : '<p class="ana-empty">Aucune connexion enregistrée.</p>';

    } catch (err) {
        console.error(err);
        document.getElementById('ana-stats').innerHTML =
            '<div class="ana-stat-card"><p style="color:#EF4444;">Erreur de chargement. Vérifiez la console.</p></div>';
    }
}

loadAnalytics();
</script>

<?php require_once '../includes/footer.php'; ?>