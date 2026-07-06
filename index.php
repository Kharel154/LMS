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
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (verify_csrf_token($token)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND statut = 'actif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['nom']     = $user['nom'];
            $_SESSION['prenom']  = $user['prenom'];

            log_connection($user['id'], 'login');
            header('Location: ' . $user['role'] . '/dashboard.php');
            exit;
        } else {
            $error = "Identifiants incorrects.";
        }
    }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Connexion</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <style>
        /* Styles autonomes pour l'inscription — alignés sur login.css */
        .form-panel { display: none; width: 100%; }
        .form-panel.active { display: flex; flex-direction: column; align-items: center; }

        /* Le formulaire d'inscription hérite du style "form" de login.css */
        .register-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .register-form input,
        .register-form select {
            width: 100%;
            max-width: 300px;
            height: 42px;
            border-radius: 21px;
            padding: 0 16px;
            font-size: 14px;
            font-family: inherit;
            border: 1.5px solid #CBD5E1;
            background: white;
            outline: none;
            transition: border-color 0.2s;
        }
        .register-form input:focus,
        .register-form select:focus {
            border-color: #404e9c;
        }
        .register-form select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            color: #64748B;
        }
        .register-form select:valid { color: #1E293B; }

        .tab-row {
            display: flex;
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #E2E8F0;
        }
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            padding: 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #94A3B8;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .tab-btn.active {
            color: #404e9c;
            border-bottom-color: #404e9c;
        }

        .form-error {
            color: #EF4444;
            font-size: 13px;
            margin-bottom: 6px;
            text-align: center;
            min-height: 18px;
            width: 100%;
        }

        @media (max-width: 480px) {
            .register-form input,
            .register-form select {
                max-width: 100%;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <h1>LMS Académie</h1>

        <!-- Onglets Connexion / Inscription -->
        <div class="tab-row">
            <button class="tab-btn active" onclick="switchTab('login')">Se connecter</button>
            <button class="tab-btn" onclick="switchTab('register')">Créer un compte</button>
        </div>

        <!-- Formulaire de connexion (existant, inchangé) -->
        <div id="panel-login" class="form-panel active">
            <?php if ($error): ?>
                <p class="form-error"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="email" name="email" class="email" placeholder="Email" required>
                <input type="password" class="pwd" name="password" placeholder="Mot de passe" required>
                <button type="submit" class="connect">Se connecter</button>
            </form>
        </div>

        <!-- Formulaire d'inscription (nouveau, AJAX) -->
        <div id="panel-register" class="form-panel">
            <div id="register-error" class="form-error"></div>
            <form id="register-form" class="register-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

                <input type="text" name="prenom" placeholder="Prénom" required>
                <input type="text" name="nom" placeholder="Nom" required>
                <input type="email" name="email" placeholder="Email" required>

                <select name="role" required>
                    <option value="" disabled selected>Je suis...</option>
                    <option value="student">Étudiant(e)</option>
                    <option value="teacher">Enseignant(e)</option>
                </select>

                <input type="password" name="password" placeholder="Mot de passe (min. 8 caractères)" required minlength="8">
                <input type="password" name="password_confirm" placeholder="Confirmer le mot de passe" required minlength="8">

                <button type="submit" class="connect" id="register-btn">Créer mon compte</button>
            </form>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        document.getElementById('panel-login').classList.toggle('active', tab === 'login');
        document.getElementById('panel-register').classList.toggle('active', tab === 'register');
        document.querySelectorAll('.tab-btn').forEach((btn, i) => {
            btn.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
        });
        document.getElementById('register-error').textContent = '';
    }

    document.getElementById('register-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const errorEl = document.getElementById('register-error');
        const btn = document.getElementById('register-btn');

        errorEl.textContent = '';
        btn.disabled = true;
        btn.textContent = 'Création en cours...';

        try {
            const res = await fetch('api/register.php', {
                method: 'POST',
                body: new FormData(e.target)
            });
            const data = await res.json();

            if (data.success) {
                btn.textContent = '✓ Compte créé !';
                btn.style.background = '#10B981';
                setTimeout(() => window.location.href = data.redirect, 700);
            } else {
                errorEl.textContent = data.message || 'Erreur lors de la création du compte.';
                btn.disabled = false;
                btn.textContent = 'Créer mon compte';
            }
        } catch (err) {
            errorEl.textContent = 'Erreur réseau. Veuillez réessayer.';
            btn.disabled = false;
            btn.textContent = 'Créer mon compte';
        }
    });
    </script>
</body>
</html>