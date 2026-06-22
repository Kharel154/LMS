<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$quiz_id = (int)($_GET['id'] ?? 0);
require_once '../includes/header.php';
?>

<div id="quiz-container">
    <!-- Chargé dynamiquement par JS -->
</div>

<script src="../assets/js/quiz.js"></script>

<?php require_once '../includes/footer.php'; ?>