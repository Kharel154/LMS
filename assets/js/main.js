

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


// Gère l'ouverture/fermeture de la sidebar en tiroir sur mobile

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.menu-toggle');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!sidebar || !toggleBtn || !overlay) return; // sécurité si une page n'a pas ces éléments

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }

    toggleBtn.addEventListener('click', () => {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    // Ferme le tiroir automatiquement si on clique sur un lien du menu
    sidebar.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', closeSidebar);
    });

    // Ferme le tiroir si on repasse en desktop (resize)
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
});