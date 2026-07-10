<?php
// === UPDATED FILE: admin/modules.php ===
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Gestion des Modules";
require_once 'header.php';

$csrf_token = generate_csrf_token();

$stmt = $pdo->query("SELECT id, nom FROM categories ORDER BY nom ASC");
$categories = $stmt->fetchAll();
?>

<style>
.mod-btn {
    background: #4F46E5;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    font-family: inherit;
    transition: opacity 0.2s;
}
.mod-btn:hover { opacity: 0.9; }
.mod-btn-success { background: #10B981; }
.mod-btn-secondary { background: #94A3B8; }
.mod-btn-danger { background: #EF4444; }
.mod-btn-indigo { background: #6366F1; }
.mod-btn-sm { padding: 6px 12px; font-size: 13px; }

.mod-toast {
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
.mod-toast.success { background: #10B981; }
.mod-toast.error { background: #EF4444; }

.mod-table {
    width: 100%;
    background: white;
    border-collapse: collapse;
    margin-top: 20px;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    font-family: inherit;
}
.mod-table th { text-align: left; padding: 12px 16px; background: #F8FAFC; }
.mod-table td { padding: 12px 16px; border-top: 1px solid #F1F5F9; }

.mod-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.mod-modal-box {
    background: white;
    border-radius: 12px;
    padding: 28px;
    width: 480px;
    max-width: 90vw;
    font-family: inherit;
}
.mod-modal-box input[type="text"],
.mod-modal-box textarea,
.mod-modal-box select {
    width: 100%;
    padding: 10px;
    margin: 6px 0 16px;
    border: 1px solid #E2E8F0;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    box-sizing: border-box;
}
.mod-modal-box label { font-weight: 600; font-size: 14px; color: #1E293B; }
</style>

<h1>Modules de formation</h1>
<p style="color:#64748B; margin-bottom:20px;">
    Un module regroupe plusieurs cours. Quand un étudiant valide 100% de tous les cours d'un module,
    un certificat lui est automatiquement attribué.
</p>

<button class="mod-btn" onclick="openCreateModal()">+ Nouveau module</button>

<table class="mod-table">
    <thead>
        <tr>
            <th>Nom</th>
            <th>Catégorie</th>
            <th>Cours associés</th>
            <th>Certificats décernés</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="modules-tbody">
        <tr><td colspan="5" style="padding:16px; color:#94A3B8;">Chargement...</td></tr>
    </tbody>
</table>

<!-- Modal Créer / Modifier -->
<div id="module-modal" class="mod-modal-overlay">
    <div class="mod-modal-box">
        <h2 id="modal-title" style="margin-bottom:18px;">Nouveau module</h2>
        <form id="module-form">
            <input type="hidden" id="module_id" value="">

            <label>Titre du module *</label>
            <input type="text" id="module_nom" required>

            <label>Description</label>
            <textarea id="module_description" rows="3"></textarea>

            <label>Catégorie</label>
            <select id="module_categorie">
                <option value="">— Aucune —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="mod-btn mod-btn-secondary" onclick="closeModal('module-modal')">Annuler</button>
                <button type="submit" class="mod-btn mod-btn-success">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Associer cours -->
<div id="courses-modal" class="mod-modal-overlay">
    <div class="mod-modal-box" style="width:520px; max-height:80vh; overflow-y:auto;">
        <h2 style="margin-bottom:18px;">Associer des cours</h2>
        <input type="hidden" id="assign_module_id" value="">
        <div id="courses-checklist" style="margin-bottom:20px;">
            Chargement...
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" class="mod-btn mod-btn-secondary" onclick="closeModal('courses-modal')">Annuler</button>
            <button type="button" class="mod-btn mod-btn-success" onclick="saveCourseAssignment()">Enregistrer l'association</button>
        </div>
    </div>
</div>

<div id="toast" class="mod-toast"></div>

<script>
const CSRF_TOKEN = "<?= $csrf_token ?>";

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `mod-toast ${type}`;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 4000);
}

function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Nouveau module';
    document.getElementById('module_id').value = '';
    document.getElementById('module_nom').value = '';
    document.getElementById('module_description').value = '';
    document.getElementById('module_categorie').value = '';
    openModal('module-modal');
}

function openEditModal(id, nom, description, categorieId) {
    document.getElementById('modal-title').textContent = 'Modifier le module';
    document.getElementById('module_id').value = id;
    document.getElementById('module_nom').value = nom;
    document.getElementById('module_description').value = description || '';
    document.getElementById('module_categorie').value = categorieId || '';
    openModal('module-modal');
}

async function loadModules() {
    const tbody = document.getElementById('modules-tbody');
    try {
        const res = await fetch('../api/modules.php?action=list');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="5" style="padding:16px; color:#EF4444;">${data.message || 'Erreur serveur'}</td></tr>`;
            return;
        }

        if (data.modules.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="padding:16px; color:#94A3B8;">Aucun module créé pour le moment.</td></tr>';
            return;
        }

        tbody.innerHTML = data.modules.map(m => `
            <tr>
                <td style="padding:12px 16px; font-weight:600;">${escapeHtml(m.nom)}</td>
                <td style="padding:12px 16px; color:#64748B;">${escapeHtml(m.categorie_nom || '—')}</td>
                <td style="padding:12px 16px;">${m.nb_cours} cours</td>
                <td style="padding:12px 16px;">${m.nb_certificats} décernés</td>
                <td style="padding:12px 16px; display:flex; gap:8px;">
                    <button class="mod-btn mod-btn-sm" onclick="openEditModal(${m.id}, '${escapeJs(m.nom)}', '${escapeJs(m.description || '')}', ${m.categorie_id || 'null'})">Modifier</button>
                    <button class="mod-btn mod-btn-sm mod-btn-indigo" onclick="openAssignCoursesModal(${m.id})">Associer cours</button>
                    <button class="mod-btn mod-btn-sm mod-btn-danger" onclick="deleteModule(${m.id})">Supprimer</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error(err);
        tbody.innerHTML = '<tr><td colspan="5" style="padding:16px; color:#EF4444;">Erreur de chargement. Vérifiez la console.</td></tr>';
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
function escapeJs(str) {
    return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, '\\n');
}

// === FORM SUBMIT (CREATE / UPDATE) ===
document.getElementById('module-form').onsubmit = async (e) => {
    e.preventDefault();

    const moduleId = document.getElementById('module_id').value.trim();
    const action = moduleId ? 'update' : 'create';

    const formData = new FormData();
    formData.append('action', action);
    formData.append('csrf_token', CSRF_TOKEN);
    if (moduleId) formData.append('module_id', moduleId);
    formData.append('nom', document.getElementById('module_nom').value.trim());
    formData.append('description', document.getElementById('module_description').value.trim());
    formData.append('categorie_id', document.getElementById('module_categorie').value);

    try {
        const res = await fetch('../api/modules.php', { 
            method: 'POST', 
            body: formData 
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();

        if (data.success) {
            showToast(data.message || 'Succès', 'success');
            closeModal('module-modal');
            loadModules();
        } else {
            showToast(data.message || 'Erreur inconnue', 'error');
        }
    } catch (err) {
        console.error('Fetch error:', err);
        showToast('Erreur réseau - Vérifiez console (F12)', 'error');
    }
};

async function deleteModule(moduleId) {
    if (!confirm('Supprimer ce module ? Les cours associés seront détachés mais pas supprimés.')) return;

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('module_id', moduleId);

    try {
        const res = await fetch('../api/modules.php', { method: 'POST', body: formData });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) loadModules();
    } catch (err) {
        showToast('Erreur réseau', 'error');
    }
}

// ... (le reste du fichier pour assign courses reste identique - pas modifié)

loadModules();
</script>

<?php require_once '../includes/footer.php'; ?>