

document.addEventListener('DOMContentLoaded', () => {
    // Activation du lien actif dans la sidebar
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-link').forEach(link => {
        if (currentPath.includes(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
});

// Fonction de déconnexion sécurisée
async function logout() {
    if (!confirm('Voulez-vous vraiment vous déconnecter ?')) return;

    try {
        const res = await postData('../api/auth.php', { action: 'logout' });
        
        if (res.success) {
            showToast(res.message || 'Déconnexion...', 'success');
            setTimeout(() => {
                window.location.href = res.redirect || '../index.php';
            }, 800);
        }
    } catch (e) {
        showToast('Erreur lors de la déconnexion', 'error');
        // Fallback
        window.location.href = '../index.php?logout=1';
    }
}

// Gestion du dropdown
function toggleDropdown() {
    const dropdown = document.getElementById('dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
}

// Fermer le dropdown en cliquant ailleurs
document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-menu')) {
        const dropdown = document.getElementById('dropdown');
        if (dropdown) dropdown.style.display = 'none';
    }
});