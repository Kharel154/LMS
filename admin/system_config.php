<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Configuration Système";
require_once 'header.php';

$stmt = $pdo->query("SELECT cle, valeur FROM system_config");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Valeurs par défaut si non définies en base
$defaults = [
    'site_name'           => 'LMS Académie',
    'site_description'    => 'Plateforme d\'apprentissage en ligne',
    'contact_email'       => 'admin@lms.com',
    'max_upload_size'     => '200',
    'note_passage_defaut' => '50',
    'inscription_libre'   => '1',
    'validation_cours'    => '1',
    'maintenance_mode'    => '0',
];
foreach ($defaults as $k => $v) {
    if (!isset($config[$k])) $config[$k] = $v;
}

// Statistiques système
$stmtStats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM users)                            AS total_users,
        (SELECT COUNT(*) FROM courses)                         AS total_courses,
        (SELECT COUNT(*) FROM lessons)                         AS total_lessons,
        (SELECT COUNT(*) FROM quiz_attempts)                   AS total_attempts,
        (SELECT COUNT(*) FROM certificates)                    AS total_certs,
        (SELECT COUNT(*) FROM enrollments)                     AS total_enrollments
");
$stats = $stmtStats->fetch();
?>

<style>
.cfg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}
.cfg-stat {
    background: white;
    border-radius: 10px;
    padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 14px;
}
.cfg-stat-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.cfg-stat-num { font-size: 22px; font-weight: 700; color: #1E293B; line-height: 1; }
.cfg-stat-label { font-size: 12px; color: #94A3B8; margin-top: 3px; }

.cfg-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    overflow: hidden;
}
.cfg-section-header {
    padding: 16px 22px;
    border-bottom: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 10px;
}
.cfg-section-title {
    font-size: 15px;
    font-weight: 700;
    color: #1E293B;
}
.cfg-section-body { padding: 20px 22px; }

.cfg-row {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    padding: 14px 0;
    border-bottom: 1px solid #F8FAFC;
}
.cfg-row:last-child { border-bottom: none; padding-bottom: 0; }
.cfg-label-wrap { flex: 1; min-width: 0; }
.cfg-label { font-size: 14px; font-weight: 600; color: #1E293B; margin-bottom: 3px; }
.cfg-hint { font-size: 12px; color: #94A3B8; }
.cfg-control { flex-shrink: 0; }

.cfg-input {
    padding: 8px 12px;
    border: 1px solid #E2E8F0;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    color: #1E293B;
    outline: none;
    transition: border-color 0.2s;
    width: 240px;
}
.cfg-input:focus { border-color: #4F46E5; }

.cfg-input-sm { width: 100px; }

/* Toggle switch */
.toggle-wrap { display: flex; align-items: center; gap: 10px; }
.toggle {
    position: relative;
    width: 46px; height: 26px;
    flex-shrink: 0;
}
.toggle input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    inset: 0;
    background: #CBD5E1;
    border-radius: 26px;
    cursor: pointer;
    transition: background 0.2s;
}
.toggle-slider::before {
    content: '';
    position: absolute;
    width: 20px; height: 20px;
    left: 3px; top: 3px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.15);
}
.toggle input:checked + .toggle-slider { background: #4F46E5; }
.toggle input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-label { font-size: 13px; color: #64748B; }

/* Bouton de sauvegarde */
.cfg-save-btn {
    background: #4F46E5;
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cfg-save-btn:hover { background: #4338CA; }
.cfg-save-btn:disabled { background: #94A3B8; cursor: not-allowed; }

.danger-btn {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.2s;
}
.danger-btn:hover { background: #FEE2E2; }

/* Mode maintenance banner */
<?php if ($config['maintenance_mode'] === '1'): ?>
.maintenance-banner {
    background: #FEF3C7;
    border: 1px solid #F59E0B;
    color: #92400E;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 10px;
}
<?php endif; ?>

@media (max-width: 768px) {
    .cfg-row { flex-direction: column; gap: 10px; }
    .cfg-input, .cfg-input-sm { width: 100%; }
    .cfg-control { width: 100%; }
}
</style>

<?php if ($config['maintenance_mode'] === '1'): ?>
<div class="maintenance-banner">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
    </svg>
    Mode maintenance activé — les étudiants et enseignants ne peuvent pas se connecter.
</div>
<?php endif; ?>

<h1>Configuration du LMS</h1>
<p style="color:#64748B; margin-bottom:24px;">Gérez les paramètres globaux de votre plateforme.</p>

<!-- Statistiques système -->
<div class="cfg-grid">
    <div class="cfg-stat">
        <div class="cfg-stat-icon" style="background:#EEF2FF;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4338CA" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <div class="cfg-stat-num"><?= $stats['total_users'] ?></div>
            <div class="cfg-stat-label">Utilisateurs total</div>
        </div>
    </div>
    <div class="cfg-stat">
        <div class="cfg-stat-icon" style="background:#ECFDF5;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
        </div>
        <div>
            <div class="cfg-stat-num"><?= $stats['total_courses'] ?></div>
            <div class="cfg-stat-label">Cours créés</div>
        </div>
    </div>
    <div class="cfg-stat">
        <div class="cfg-stat-icon" style="background:#FEF3C7;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        </div>
        <div>
            <div class="cfg-stat-num"><?= $stats['total_lessons'] ?></div>
            <div class="cfg-stat-label">Leçons</div>
        </div>
    </div>
    <div class="cfg-stat">
        <div class="cfg-stat-icon" style="background:#F0FDF4;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/></svg>
        </div>
        <div>
            <div class="cfg-stat-num"><?= $stats['total_certs'] ?></div>
            <div class="cfg-stat-label">Certificats décernés</div>
        </div>
    </div>
</div>

<form id="config-form">

<!-- Section 1 : Général -->
<div class="cfg-section">
    <div class="cfg-section-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M20 12h2M2 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
        <span class="cfg-section-title">Informations générales</span>
    </div>
    <div class="cfg-section-body">
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Nom de la plateforme</div>
                <div class="cfg-hint">Affiché dans le titre des pages et les emails</div>
            </div>
            <div class="cfg-control">
                <input type="text" name="site_name" class="cfg-input"
                       value="<?= htmlspecialchars($config['site_name']) ?>">
            </div>
        </div>
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Description</div>
                <div class="cfg-hint">Courte phrase décrivant la plateforme</div>
            </div>
            <div class="cfg-control">
                <input type="text" name="site_description" class="cfg-input"
                       value="<?= htmlspecialchars($config['site_description']) ?>">
            </div>
        </div>
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Email de contact</div>
                <div class="cfg-hint">Adresse pour les notifications système</div>
            </div>
            <div class="cfg-control">
                <input type="email" name="contact_email" class="cfg-input"
                       value="<?= htmlspecialchars($config['contact_email']) ?>">
            </div>
        </div>
    </div>
</div>

<!-- Section 2 : Pédagogie -->
<div class="cfg-section">
    <div class="cfg-section-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        <span class="cfg-section-title">Paramètres pédagogiques</span>
    </div>
    <div class="cfg-section-body">
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Note de passage par défaut (%)</div>
                <div class="cfg-hint">Seuil appliqué automatiquement à chaque nouveau quiz créé</div>
            </div>
            <div class="cfg-control">
                <input type="number" name="note_passage_defaut" class="cfg-input cfg-input-sm"
                       min="0" max="100"
                       value="<?= (int)$config['note_passage_defaut'] ?>">
            </div>
        </div>
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Validation des cours par l'admin</div>
                <div class="cfg-hint">Si activé, les cours soumis par les profs passent en "en_attente" avant d'être publiés</div>
            </div>
            <div class="cfg-control">
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="validation_cours" value="1"
                               <?= $config['validation_cours'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label" id="label-validation">
                        <?= $config['validation_cours'] === '1' ? 'Activé' : 'Désactivé' ?>
                    </span>
                </div>
            </div>
        </div>
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Inscription libre</div>
                <div class="cfg-hint">Si désactivé, seul l'admin peut créer des comptes étudiants</div>
            </div>
            <div class="cfg-control">
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="inscription_libre" value="1"
                               <?= $config['inscription_libre'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-label" id="label-inscription">
                        <?= $config['inscription_libre'] === '1' ? 'Activée' : 'Désactivée' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section 3 : Fichiers -->
<div class="cfg-section">
    <div class="cfg-section-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <span class="cfg-section-title">Gestion des fichiers</span>
    </div>
    <div class="cfg-section-body">
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Taille max d'upload (Mo)</div>
                <div class="cfg-hint">Limite pour les fichiers PDF et vidéo des leçons</div>
            </div>
            <div class="cfg-control">
                <input type="number" name="max_upload_size" class="cfg-input cfg-input-sm"
                       min="1" max="2000"
                       value="<?= (int)$config['max_upload_size'] ?>">
            </div>
        </div>
    </div>
</div>

<!-- Section 4 : Maintenance -->
<div class="cfg-section">
    <div class="cfg-section-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93l-1.41 1.41M4.93 4.93l1.41 1.41M12 2v2M12 20v2M20 12h2M2 12h2M19.07 19.07l-1.41-1.41M4.93 19.07l1.41-1.41"/></svg>
        <span class="cfg-section-title" style="color:#EF4444;">Zone de maintenance</span>
    </div>
    <div class="cfg-section-body">
        <div class="cfg-row">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Mode maintenance</div>
                <div class="cfg-hint">
                    Bloque l'accès à la plateforme pour les étudiants et enseignants.
                    Seul l'admin peut se connecter.
                </div>
            </div>
            <div class="cfg-control">
                <div class="toggle-wrap">
                    <label class="toggle">
                        <input type="checkbox" name="maintenance_mode" value="1"
                               <?= $config['maintenance_mode'] === '1' ? 'checked' : '' ?>>
                        <span class="toggle-slider" style="<?= $config['maintenance_mode'] === '1' ? 'background:#EF4444;' : '' ?>"></span>
                    </label>
                    <span class="toggle-label" id="label-maintenance">
                        <?= $config['maintenance_mode'] === '1' ? 'Activé' : 'Désactivé' ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="cfg-row" style="padding-top:16px;">
            <div class="cfg-label-wrap">
                <div class="cfg-label">Vider le cache des sessions</div>
                <div class="cfg-hint">Déconnecte tous les utilisateurs actuellement connectés</div>
            </div>
            <div class="cfg-control">
                <button type="button" class="danger-btn" onclick="clearSessions()">
                    Vider les sessions
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bouton de sauvegarde -->
<div style="display:flex; justify-content:flex-end; margin-top:8px;">
    <button type="submit" class="cfg-save-btn" id="save-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
        </svg>
        Sauvegarder les paramètres
    </button>
</div>

</form>

<div id="toast" class="toast"></div>

<script>
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.style.display = 'block';
    setTimeout(() => toast.style.display = 'none', 3500);
}

// Met à jour le label des toggles en temps réel
document.querySelectorAll('.toggle input[type="checkbox"]').forEach(chk => {
    chk.addEventListener('change', () => {
        const name = chk.name;
        const labelMap = {
            'validation_cours':  ['label-validation',  'Activé',   'Désactivé'],
            'inscription_libre': ['label-inscription',  'Activée',  'Désactivée'],
            'maintenance_mode':  ['label-maintenance',  'Activé',   'Désactivé'],
        };
        if (labelMap[name]) {
            const [id, on, off] = labelMap[name];
            document.getElementById(id).textContent = chk.checked ? on : off;
        }
        // Couleur rouge pour le toggle maintenance
        if (name === 'maintenance_mode') {
            chk.nextElementSibling.style.background = chk.checked ? '#EF4444' : '';
        }
    });
});

// Sauvegarde
document.getElementById('config-form').onsubmit = async (e) => {
    e.preventDefault();
    const btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.lastChild.textContent = ' Sauvegarde...';

    const formData = new FormData(e.target);
    // Les checkboxes non cochées ne sont pas dans FormData → on les force à '0'
    ['validation_cours', 'inscription_libre', 'maintenance_mode'].forEach(k => {
        if (!formData.has(k)) formData.set(k, '0');
    });

    try {
        const res = await fetch('../api/system_config.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            showToast('Paramètres sauvegardés avec succès.', 'success');
        } else {
            showToast(data.message || 'Erreur lors de la sauvegarde.', 'error');
        }
    } catch (err) {
        showToast('Erreur réseau.', 'error');
    } finally {
        btn.disabled = false;
        btn.lastChild.textContent = ' Sauvegarder les paramètres';
    }
};

async function clearSessions() {
    if (!confirm('Déconnecter tous les utilisateurs actuellement connectés ?')) return;
    const fd = new FormData();
    fd.append('action', 'clear_sessions');
    try {
        const res = await fetch('../api/system_config.php', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message || 'Sessions vidées.', data.success ? 'success' : 'error');
    } catch (err) {
        showToast('Erreur réseau.', 'error');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>