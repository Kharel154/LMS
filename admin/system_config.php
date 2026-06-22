<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('admin');

$title = "Configuration Système";
require_once 'header.php';

// Exemple de lecture config
$stmt = $pdo->query("SELECT * FROM system_config");
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<h1>Paramètres du LMS</h1>

<form id="config-form">
    <label>Nom du LMS</label>
    <input type="text" name="site_name" value="<?= htmlspecialchars($config['site_name'] ?? 'LMS Académie') ?>">
    <button type="submit">Sauvegarder</button>
</form>

<?php require_once '../includes/footer.php'; ?>