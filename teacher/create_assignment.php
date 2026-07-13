<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Créer un devoir";
require_once 'header.php';

$teacherId = $_SESSION['user_id'];
$lesson_id = (int)($_GET['lesson_id'] ?? 0);

// Vérifie que la leçon appartient bien à un cours de cet enseignant
$stmt = $pdo->prepare("
    SELECT l.id, l.titre AS lesson_titre, l.course_id, c.titre AS course_titre
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = ? AND c.enseignant_id = ?
");
$stmt->execute([$lesson_id, $teacherId]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo '<p style="color:#991B1B;">Leçon introuvable ou vous n\'avez pas les droits sur celle-ci.</p>';
    echo '<a href="my_courses.php" class="t-link">Retour à mes cours</a>';
    require_once '../includes/footer.php';
    exit;
}

// Devoir existant pour cette leçon (une leçon = un devoir maximum, comme pour les quiz)
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE lesson_id = ?");
$stmt->execute([$lesson_id]);
$assignment = $stmt->fetch();

$message = '';
$messageType = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = 'Jeton de sécurité invalide. Rechargez la page et réessayez.';
        $messageType = 'red';
    } else {
        $titre = sanitize_input($_POST['titre'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $date_limite = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;

        if (empty($titre)) {
            $message = 'Le titre du devoir est obligatoire.';
            $messageType = 'red';
        } else {
            if ($assignment) {
                $stmt = $pdo->prepare("
                    UPDATE assignments SET titre = ?, description = ?, date_limite = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titre, $description, $date_limite, $assignment['id']]);
                $message = "Devoir mis à jour avec succès.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO assignments (lesson_id, titre, description, date_limite)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$lesson_id, $titre, $description, $date_limite]);
                $message = "Devoir créé avec succès. Il est maintenant visible par les étudiants inscrits à ce cours.";
            }

            // Recharge le devoir à jour
            $stmt = $pdo->prepare("SELECT * FROM assignments WHERE lesson_id = ?");
            $stmt->execute([$lesson_id]);
            $assignment = $stmt->fetch();
        }
    }
}

$nbSubmissions = 0;
$nbGraded = 0;
if ($assignment) {
    $stmt = $pdo->prepare("SELECT COUNT(*), SUM(note IS NOT NULL) FROM assignment_submissions WHERE assignment_id = ?");
    $stmt->execute([$assignment['id']]);
    [$nbSubmissions, $nbGraded] = $stmt->fetch(PDO::FETCH_NUM);
    $nbSubmissions = (int)$nbSubmissions;
    $nbGraded = (int)$nbGraded;
}

$csrfToken = generate_csrf_token();
?>

<p><a href="course_builder.php?id=<?= $lesson['course_id'] ?>" class="t-link">← <?= htmlspecialchars($lesson['course_titre']) ?></a></p>
<h1><?= $assignment ? 'Modifier' : 'Créer' ?> un devoir</h1>
<p style="color:#64748B; margin-top:-8px;">Leçon : <strong><?= htmlspecialchars($lesson['lesson_titre']) ?></strong></p>

<?php if ($message): ?>
    <div style="background:<?= $messageType === 'green' ? '#ECFDF5' : '#FEF2F2' ?>; border:1px solid <?= $messageType === 'green' ? '#10B981' : '#EF4444' ?>; color:<?= $messageType === 'green' ? '#065F46' : '#991B1B' ?>; padding:12px 16px; border-radius:8px; margin-bottom:20px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($assignment): ?>
<div class="t-banner" style="background:#EEF2FF; border:1px solid #C7D2FE; color:#3730A3;">
    <strong><?= $nbSubmissions ?></strong> soumission<?= $nbSubmissions > 1 ? 's' : '' ?> reçue<?= $nbSubmissions > 1 ? 's' : '' ?>,
    dont <strong><?= $nbGraded ?></strong> corrigée<?= $nbGraded > 1 ? 's' : '' ?>.
    <a href="grading_hub.php" class="t-link">Aller aux corrections →</a>
</div>
<?php endif; ?>

<div class="t-card">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Titre du devoir *</label>
        <input type="text" name="titre" value="<?= htmlspecialchars($assignment['titre'] ?? '') ?>" required
               style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">

        <label style="font-weight:600; display:block; margin-bottom:4px;">Consigne <span style="color:#64748B; font-weight:400; font-size:13px;">(facultatif)</span></label>
        <textarea name="description" rows="5" style="width:100%; padding:10px; margin-bottom:16px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;"><?= htmlspecialchars($assignment['description'] ?? '') ?></textarea>

        <label style="font-weight:600; display:block; margin-bottom:4px;">Date limite <span style="color:#64748B; font-weight:400; font-size:13px;">(facultatif)</span></label>
        <input type="date" name="date_limite" value="<?= htmlspecialchars($assignment['date_limite'] ?? '') ?>"
               style="width:100%; max-width:220px; padding:10px; margin-bottom:20px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">

        <button type="submit" class="btn"><?= $assignment ? 'Mettre à jour le devoir' : 'Créer le devoir' ?></button>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>