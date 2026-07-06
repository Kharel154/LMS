<?php
// api/register.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Requête invalide'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

// Vérification CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $response['message'] = 'Jeton de sécurité invalide. Rechargez la page.';
    echo json_encode($response);
    exit;
}

$nom      = trim($_POST['nom'] ?? '');
$prenom   = trim($_POST['prenom'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';
$role     = $_POST['role'] ?? '';

// Validation des champs
if (empty($nom) || empty($prenom) || empty($email) || empty($password) || empty($role)) {
    $response['message'] = 'Tous les champs sont obligatoires.';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Adresse email invalide.';
    echo json_encode($response);
    exit;
}

if (!in_array($role, ['student', 'teacher'])) {
    $response['message'] = 'Rôle invalide.';
    echo json_encode($response);
    exit;
}

if (strlen($password) < 8) {
    $response['message'] = 'Le mot de passe doit contenir au moins 8 caractères.';
    echo json_encode($response);
    exit;
}

if ($password !== $confirm) {
    $response['message'] = 'Les mots de passe ne correspondent pas.';
    echo json_encode($response);
    exit;
}

// Vérifie que l'email n'est pas déjà utilisé
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    $response['message'] = 'Cette adresse email est déjà utilisée.';
    echo json_encode($response);
    exit;
}

// Création du compte
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("
    INSERT INTO users (nom, prenom, email, password_hash, role, statut)
    VALUES (?, ?, ?, ?, ?, 'actif')
");
$success = $stmt->execute([$nom, $prenom, $email, $hash, $role]);

if (!$success) {
    $response['message'] = 'Erreur lors de la création du compte. Veuillez réessayer.';
    echo json_encode($response);
    exit;
}

$newUserId = $pdo->lastInsertId();
log_connection($newUserId, 'register');

// Connexion automatique après inscription
session_regenerate_id(true);
$_SESSION['user_id'] = $newUserId;
$_SESSION['role']    = $role;
$_SESSION['nom']     = $nom;
$_SESSION['prenom']  = $prenom;

$redirect = match($role) {
    'student' => 'student/dashboard.php',
    'teacher' => 'teacher/dashboard.php',
    default   => 'index.php'
};

$response = [
    'success'  => true,
    'message'  => 'Compte créé avec succès !',
    'redirect' => $redirect
];

echo json_encode($response);