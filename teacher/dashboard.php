<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Tableau de bord Enseignant";
require_once 'header.php';

$teacherId = $_SESSION['user_id'];

// --- Statistiques principales -------------------------------------------------

$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE enseignant_id = ?");
$stmt->execute([$teacherId]);
$totalCourses = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT student_id)
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$teacherId]);
$totalStudents = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM lessons l
    JOIN courses c ON l.course_id = c.id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$teacherId]);
$totalLessons = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE c.enseignant_id = ? AND s.note IS NULL
");
$stmt->execute([$teacherId]);
$pendingGrading = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT AVG(qa.passed) * 100
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN lessons l ON q.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    WHERE c.enseignant_id = ?
");
$stmt->execute([$teacherId]);
$successRateRaw = $stmt->fetchColumn();
$successRate = $successRateRaw !== null ? round((float)$successRateRaw) : null;

// --- Cours nécessitant une attention (en attente ou rejetés) -------------------

$stmt = $pdo->prepare("
    SELECT id, titre, statut
    FROM courses
    WHERE enseignant_id = ? AND statut IN ('en_attente', 'rejete')
    ORDER BY date_creation DESC
");
$stmt->execute([$teacherId]);
$coursesNeedingAttention = $stmt->fetchAll();

// --- Devoirs en attente de correction (5 plus anciens) -------------------------

$stmt = $pdo->prepare("
    SELECT s.id, a.titre AS devoir_titre, c.titre AS cours_titre,
           u.prenom, u.nom, s.date_soumission
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN lessons l ON a.lesson_id = l.id
    JOIN courses c ON l.course_id = c.id
    JOIN users u ON s.student_id = u.id
    WHERE c.enseignant_id = ? AND s.note IS NULL
    ORDER BY s.date_soumission ASC
    LIMIT 5
");
$stmt->execute([$teacherId]);
$pendingSubmissions = $stmt->fetchAll();

$statusLabels = [
    'en_attente' => 'En attente de validation',
    'rejete'     => 'Rejeté par l\'administrateur',
];
?>

<h1>Tableau de bord</h1>

<div class="stats-grid">
    <div class="stat-card">
        <h3><?= $totalCourses ?></h3>
        <p>Cours créés</p>
    </div>
    <div class="stat-card">
        <h3><?= $totalStudents ?></h3>
        <p>Étudiants inscrits</p>
    </div>
    <div class="stat-card">
        <h3><?= $totalLessons ?></h3>
        <p>Leçons publiées</p>
    </div>
    <div class="stat-card">
        <h3><?= $pendingGrading ?></h3>
        <p>Devoirs à corriger</p>
    </div>
    <div class="stat-card">
        <h3><?= $successRate !== null ? $successRate . '%' : '—' ?></h3>
        <p>Taux de réussite aux quiz</p>
    </div>
</div>

<?php if (!empty($coursesNeedingAttention)): ?>
<div class="t-banner t-banner-warning">
    <strong>À vérifier :</strong> certains de vos cours nécessitent une action.
    <ul>
        <?php foreach ($coursesNeedingAttention as $c): ?>
            <li>
                <?= htmlspecialchars($c['titre']) ?>
                — <span class="badge <?= $c['statut'] ?>"><?= htmlspecialchars($statusLabels[$c['statut']] ?? $c['statut']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="t-card">
    <div class="t-card-header">
        <h2>Devoirs en attente</h2>
        <a href="grading_hub.php" class="t-link">Voir toutes les corrections →</a>
    </div>

    <?php if (empty($pendingSubmissions)): ?>
        <p class="t-empty">Aucun devoir en attente de correction pour le moment.</p>
    <?php else: ?>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Devoir</th>
                    <th>Cours</th>
                    <th>Soumis le</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingSubmissions as $s): ?>
                <tr>
                    <td data-label="Étudiant"><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></td>
                    <td data-label="Devoir"><?= htmlspecialchars($s['devoir_titre']) ?></td>
                    <td data-label="Cours"><?= htmlspecialchars($s['cours_titre']) ?></td>
                    <td data-label="Soumis le"><?= date('d/m/Y à H:i', strtotime($s['date_soumission'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>