<?php
// includes/auth.php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirect_if_not_logged($role = null) {
    session_start();
    if (!is_logged_in() || ($role && !has_role($role))) {
        header('Location: /lms/index.php');
        exit;
    }
    // Mise à jour dernière connexion
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET derniere_connexion = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>