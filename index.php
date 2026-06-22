<?php

session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Gestion du logout
if (isset($_GET['logout'])) {
    log_connection($_SESSION['user_id'] ?? null, 'logout');
    session_destroy();
    header('Location: index.php');
    exit;
}

if (is_logged_in()) {
    $redirect = match($_SESSION['role']) {
        'student' => 'student/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'admin'   => 'admin/dashboard.php',
        default   => 'index.php'
    };
    header('Location: ' . $redirect);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($token)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND statut = 'actif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];

            log_connection($user['id'], 'login');
            header('Location: ' . $user['role'] . '/dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Connexion</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>LMS Académie</h1>
        <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="email" name="email" class="email" placeholder="Email" required>
            <input type="password" class="pwd" name="password" placeholder="Mot de passe" required>
            <button type="submit" class="connect">Se connecter</button>
        </form>
    </div>
</body>
</html>