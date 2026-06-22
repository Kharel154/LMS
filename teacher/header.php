<?php
// teacher/header.php
if (!isset($title)) $title = "LMS - Enseignant";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="../assets/js/ajax.js"></script>
    <script src="../assets/js/main.js"></script>
</head>
<body>
    <div class="layout">
        <!-- Sidebar Enseignant -->
        <aside class="sidebar">
            <div class="logo">LMS Prof</div>
            <nav>
                <a href="dashboard.php" class="nav-link">Tableau de bord</a>
                <a href="my_courses.php" class="nav-link">Mes cours</a>
                <a href="course_builder.php" class="nav-link">Créer un cours</a>
                <a href="grading_hub.php" class="nav-link">Corrections</a>
                <a href="student_progress.php" class="nav-link">Suivi élèves</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <div class="welcome">
                    Prof. <strong><?= htmlspecialchars($_SESSION['prenom'] ?? '') ?></strong>
                </div>
                <div class="user-menu">
                    <a href="../index.php?logout=1" 
                       style="background:#EF4444; color:white; padding:8px 16px; border-radius:6px; text-decoration:none; font-weight:500;">
                         Déconnexion
                    </a>
                </div>
            </header>
            <div class="content">