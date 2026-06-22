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
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">LMS</div>
            <nav>
                <a href="dashboard.php" class="nav-link"> Tableau de bord</a>
                <a href="catalogue.php" class="nav-link"> Catalogue</a>
                <a href="my_notes.php" class="nav-link"> Mes notes</a>
                <a href="my_certificates.php" class="nav-link"> Certificats</a>
                <a href="upload_assignment.php" class="nav-link"> Déposer devoir</a>
            </nav>
        </aside>

        <!-- Contenu principal -->
        <main class="main-content">
                        <!-- Top bar -->
            <header class="topbar">
                <div class="welcome">
                    Bonjour, <strong><?= htmlspecialchars($_SESSION['prenom'] ?? 'Étudiant') ?></strong>
                </div>
                
                <div class="user-menu">
                    
                    <a href="../index.php?logout=1" 
                       style="background:#EF4444; color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:500;">
                         Déconnexion
                    </a>
                </div>
            </header>

            <div class="content">