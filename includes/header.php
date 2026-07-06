<?php
// includes/header.php
if (!isset($title)) $title = "LMS - Étudiant";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/ajax.js"></script>
    <script src="../assets/js/main.js"></script>
</head>
<body>
    <div class="layout">
        <!-- Overlay sombre derrière le tiroir mobile -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">LMS</div>
            <nav>
                <a href="dashboard.php" class="nav-link"><span>Tableau de bord</span></a>
                <a href="catalogue.php" class="nav-link"><span>Catalogue</span></a>
                <a href="my_notes.php" class="nav-link"><span>Mes notes</span></a>
                <a href="my_certificates.php" class="nav-link"><span>Certificats</span></a>
                <a href="upload_assignment.php" class="nav-link"><span>Déposer devoir</span></a>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="main-content">
            <!-- Top bar -->
            <header class="topbar">
                <div style="display:flex; align-items:center;">
                    <button class="menu-toggle" aria-label="Ouvrir le menu">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </button>
                    <div class="welcome">
                        Bonjour, <strong><?= htmlspecialchars($_SESSION['prenom'] ?? 'Étudiant') ?></strong>
                    </div>
                </div>

                <div class="user-menu">
                    <a href="../index.php?logout=1" 
                       style="background:#EF4444; color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:500;">
                         Déconnexion
                    </a>
                </div>
            </header>

            <div class="content">