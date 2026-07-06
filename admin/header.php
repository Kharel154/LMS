<?php
// admin/header.php
if (!isset($title)) $title = "LMS - Administration";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/ajax.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="layout">
        <!-- Overlay sombre derrière le tiroir mobile -->
        <div class="sidebar-overlay"></div>

        <!-- Sidebar Admin -->
        <aside class="sidebar">
            <div class="logo">LMS Admin</div>
            <nav>
                <a href="dashboard.php" class="nav-link"><span>Dashboard</span></a>
                <a href="users.php" class="nav-link"><span>Utilisateurs</span></a>
                <a href="modules.php" class="nav-link"><span>Modules</span></a>
                <a href="course_validation.php" class="nav-link"><span>Validation</span></a>
                <a href="analytics.php" class="nav-link"><span>Analytics</span></a>
                <a href="reports.php" class="nav-link"><span>Signalements</span></a>
                <a href="system_config.php" class="nav-link"><span>Configuration</span></a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div style="display:flex; align-items:center;">
                    <button class="menu-toggle" aria-label="Ouvrir le menu">
                        <span class="bar"></span>
                        <span class="bar"></span>
                        <span class="bar"></span>
                    </button>
                    <div class="welcome">
                        Administration — <strong><?= htmlspecialchars($_SESSION['prenom'] ?? '') ?></strong>
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