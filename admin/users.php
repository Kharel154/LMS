<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Gestion des utilisateurs";
require_once 'header.php';

$stmt = $pdo->query("SELECT * FROM users ORDER BY date_inscription DESC");
$users = $stmt->fetchAll();

// Couleurs par rôle
$roleConfig = [
    'admin'   => ['label' => 'Admin',      'bg' => '#EEF2FF', 'color' => '#4338CA'],
    'teacher' => ['label' => 'Enseignant', 'bg' => '#FEF3C7', 'color' => '#92400E'],
    'student' => ['label' => 'Étudiant',   'bg' => '#ECFDF5', 'color' => '#065F46'],
];

// Compteurs rapides
$nbAdmin   = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$nbTeacher = count(array_filter($users, fn($u) => $u['role'] === 'teacher'));
$nbStudent = count(array_filter($users, fn($u) => $u['role'] === 'student'));
$nbSuspend = count(array_filter($users, fn($u) => $u['statut'] === 'suspendu'));
?>

<style>
.users-kpi {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 28px;
}
.users-kpi-card {
    background: white;
    border-radius: 10px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 14px;
}
.users-kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.users-kpi-num {
    font-size: 24px;
    font-weight: 700;
    color: #1E293B;
    line-height: 1;
}
.users-kpi-label {
    font-size: 12px;
    color: #94A3B8;
    margin-top: 3px;
}

.users-table-wrap {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow: hidden;
}
.users-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 20px;
    border-bottom: 1px solid #F1F5F9;
}
.users-table-title {
    font-size: 16px;
    font-weight: 700;
    color: #1E293B;
}
.users-search {
    padding: 8px 14px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    width: 220px;
    outline: none;
    transition: border-color 0.2s;
}
.users-search:focus { border-color: #4F46E5; }

.u-table {
    width: 100%;
    border-collapse: collapse;
}
.u-table th {
    text-align: left;
    padding: 11px 16px;
    font-size: 12px;
    font-weight: 600;
    color: #64748B;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #F8FAFC;
    border-bottom: 1px solid #F1F5F9;
}
.u-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #F8FAFC;
    font-size: 14px;
    vertical-align: middle;
}
.u-table tr:last-child td { border-bottom: none; }
.u-table tbody tr:hover { background: #FAFBFF; }

.user-identity {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    color: white;
    flex-shrink: 0;
}
.user-name { font-weight: 600; color: #1E293B; }
.user-date { font-size: 11px; color: #94A3B8; margin-top: 2px; }

.role-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.status-actif { background: #ECFDF5; color: #065F46; }
.status-actif::before { background: #10B981; }
.status-suspendu { background: #FEF2F2; color: #991B1B; }
.status-suspendu::before { background: #EF4444; }

.actions-cell { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.btn-role {
    background: #EEF2FF;
    color: #4338CA;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}
.btn-role:hover { background: #E0E7FF; }
.btn-suspend {
    background: #FEF3C7;
    color: #92400E;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}
.btn-suspend:hover { background: #FDE68A; }
.btn-activate {
    background: #ECFDF5;
    color: #065F46;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}
.btn-activate:hover { background: #D1FAE5; }

/* Responsive : cartes sur mobile */
@media (max-width: 768px) {
    .users-search { width: 140px; }
    .u-table thead { display: none; }
    .u-table, .u-table tbody, .u-table tr, .u-table td { display: block; width: 100%; }
    .u-table tr { padding: 14px 16px; border-bottom: 1px solid #F1F5F9; }
    .u-table td { padding: 4px 0; border: none; }
    .actions-cell { margin-top: 8px; }
}
</style>

<?php
// Couleurs d'avatar par rôle
$avatarColors = [
    'admin'   => '#4F46E5',
    'teacher' => '#F59E0B',
    'student' => '#10B981',
];
?>

<!-- KPIs -->
<div class="users-kpi">
    <div class="users-kpi-card">
        <div class="users-kpi-icon" style="background:#EEF2FF;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4338CA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/><path d="M12 2l1.5 3L17 6l-2.5 2.5.5 3.5L12 10.5 9 12l.5-3.5L7 6l3.5-1z"/>
            </svg>
        </div>
        <div>
            <div class="users-kpi-num"><?= $nbAdmin ?></div>
            <div class="users-kpi-label">Admins</div>
        </div>
    </div>
    <div class="users-kpi-card">
        <div class="users-kpi-icon" style="background:#FEF3C7;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
        </div>
        <div>
            <div class="users-kpi-num"><?= $nbTeacher ?></div>
            <div class="users-kpi-label">Enseignants</div>
        </div>
    </div>
    <div class="users-kpi-card">
        <div class="users-kpi-icon" style="background:#ECFDF5;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
        </div>
        <div>
            <div class="users-kpi-num"><?= $nbStudent ?></div>
            <div class="users-kpi-label">Étudiants</div>
        </div>
    </div>
    <div class="users-kpi-card">
        <div class="users-kpi-icon" style="background:#FEF2F2;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#991B1B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
            </svg>
        </div>
        <div>
            <div class="users-kpi-num"><?= $nbSuspend ?></div>
            <div class="users-kpi-label">Suspendus</div>
        </div>
    </div>
</div>

<!-- Tableau -->
<div class="users-table-wrap">
    <div class="users-table-header">
        <span class="users-table-title">Tous les utilisateurs (<?= count($users) ?>)</span>
        <input type="text" class="users-search" placeholder="Rechercher..."
               oninput="filterUsers(this.value)">
    </div>

    <table class="u-table" id="users-table">
        <thead>
            <tr>
                <th>Utilisateur</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Statut</th>
                <th>Inscription</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $rc = $roleConfig[$u['role']] ?? ['label' => $u['role'], 'bg' => '#F1F5F9', 'color' => '#475569'];
                $avatarColor = $avatarColors[$u['role']] ?? '#94A3B8';
                $initiales = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));
            ?>
            <tr id="user-row-<?= $u['id'] ?>" data-search="<?= htmlspecialchars(strtolower($u['prenom'] . ' ' . $u['nom'] . ' ' . $u['email'] . ' ' . $u['role'])) ?>">
                <td>
                    <div class="user-identity">
                        <div class="user-avatar" style="background:<?= $avatarColor ?>;">
                            <?= $initiales ?>
                        </div>
                        <div>
                            <div class="user-name"><?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?></div>
                            <div class="user-date">ID #<?= $u['id'] ?></div>
                        </div>
                    </div>
                </td>
                <td style="color:#64748B;"><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="role-badge" style="background:<?= $rc['bg'] ?>; color:<?= $rc['color'] ?>;">
                        <?= $rc['label'] ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?= $u['statut'] ?>">
                        <?= $u['statut'] === 'actif' ? 'Actif' : 'Suspendu' ?>
                    </span>
                </td>
                <td style="color:#94A3B8; font-size:13px;">
                    <?= date('d/m/Y', strtotime($u['date_inscription'])) ?>
                </td>
                <td>
                    <div class="actions-cell">
                        <button onclick="changeRole(<?= $u['id'] ?>, '<?= $u['role'] ?>')"
                                class="btn-role">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Rôle
                        </button>
                        <button onclick="toggleSuspend(<?= $u['id'] ?>)"
                                class="<?= $u['statut'] === 'actif' ? 'btn-suspend' : 'btn-activate' ?>">
                            <?php if ($u['statut'] === 'actif'): ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;">
                                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                </svg>
                                Suspendre
                            <?php else: ?>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle; margin-right:4px;">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                Activer
                            <?php endif; ?>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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

function filterUsers(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#users-table tbody tr').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
}

async function postData(url, data) {
    const formData = new FormData();
    for (const key in data) formData.append(key, data[key]);
    const res = await fetch(url, { method: 'POST', body: formData });
    return res.json();
}

async function changeRole(userId, currentRole) {
    const roles = ['student', 'teacher', 'admin'];
    const newRole = prompt(`Rôle actuel : ${currentRole}\n\nNouveau rôle (student / teacher / admin) :`, currentRole);
    if (!newRole || !roles.includes(newRole)) {
        if (newRole !== null) alert('Rôle invalide. Choisissez : student, teacher ou admin.');
        return;
    }
    if (newRole === currentRole) return;
    if (!confirm(`Changer le rôle en "${newRole}" ?`)) return;

    const res = await postData('../api/users.php', {
        action: 'change_role',
        user_id: userId,
        new_role: newRole
    });
    if (res.success) {
        showToast(res.message || 'Rôle mis à jour.', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}

async function toggleSuspend(userId) {
    if (!confirm('Confirmer cette action ?')) return;
    const res = await postData('../api/users.php', {
        action: 'toggle_status',
        user_id: userId
    });
    if (res.success) {
        showToast(res.message || 'Statut mis à jour.', 'success');
        setTimeout(() => location.reload(), 800);
    } else {
        showToast(res.message || 'Erreur', 'error');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>