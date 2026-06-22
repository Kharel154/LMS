<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Déposer un devoir";
require_once '../includes/header.php';
?>

<h1>Déposer un devoir</h1>

<form id="upload-form" enctype="multipart/form-data">
    <input type="file" name="file" accept=".pdf,.zip" required>
    <button type="submit">Envoyer</button>
</form>

<script>
document.getElementById('upload-form').onsubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch('../api/upload.php?action=assignment', {
        method: 'POST',
        body: formData
    });
    const data = await res.json();
    showToast(data.message || 'Fichier envoyé');
};
</script>

<?php require_once '../includes/footer.php'; ?>