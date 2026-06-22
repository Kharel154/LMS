<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Gestion des Rôles & Permissions";
require_once 'header.php';

// Récupération des logs de connexion (exemple concret pour cette page)
$stmt = $pdo->prepare("
    SELECT cl.*, u.prenom, u.nom, u.role 
    FROM connection_logs cl 
    LEFT JOIN users u ON cl.user_id = u.id 
    ORDER BY cl.date_connexion DESC 
    LIMIT 50
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<h1>Gestion des Rôles & Logs de Sécurité</h1>

<div class="grid-2">
    <!-- Section Gestion des Rôles -->
    <div class="card">
        <h2>Permissions par Rôle</h2>
        <table>
            <thead>
                <tr>
                    <th>Rôle</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Étudiant</strong></td>
                    <td>Accès cours, progression, quizzes, certificats</td>
                    <td><button onclick="editRolePermissions('student')">Modifier</button></td>
                </tr>
                <tr>
                    <td><strong>Enseignant</strong></td>
                    <td>Création cours, correction, suivi élèves</td>
                    <td><button onclick="editRolePermissions('teacher')">Modifier</button></td>
                </tr>
                <tr>
                    <td><strong>Administrateur</strong></td>
                    <td>Tous les accès + configuration système</td>
                    <td><button onclick="editRolePermissions('admin')">Modifier</button></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Section Logs de Connexion -->
   