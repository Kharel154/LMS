<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('teacher');

$title = "Suivi des étudiants";
require_once 'header.php';

$teacherId = $_SESSION['user_id'];

// Liste des cours du prof, pour le filtre
$stmt = $pdo->prepare("SELECT id, titre FROM courses WHERE enseignant_id = ? ORDER BY titre ASC");
$stmt->execute([$teacherId]);
$teacherCourses = $stmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? 0);

$sql = "
    SELECT u.id AS student_id, u.prenom, u.nom,
           c.id AS course_id, c.titre,
           (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) AS total_lessons,
           COUNT(DISTINCT CASE WHEN lp.statut = 'termine' THEN lp.lesson_id END) AS completed_lessons,
           (
               SELECT ROUND(AVG(qa.score))
               FROM quiz_attempts qa
               JOIN quizzes q ON qa.quiz_id = q.id
               JOIN lessons l2 ON q.lesson_id = l2.id
               WHERE l2.course_id = c.id AND qa.student_id = u.id
           ) AS avg_quiz_score
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN lesson_progress lp
        ON lp.student_id = u.id
        AND lp.lesson_id IN (SELECT id FROM lessons WHERE course_id = c.id)
    WHERE c.enseignant_id = ?
";
$params = [$teacherId];

if ($selectedCourseId) {
    $sql .= " AND c.id = ? ";
    $params[] = $selectedCourseId;
}

$sql .= " GROUP BY u.id, c.id ORDER BY u.nom ASC, u.prenom ASC ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$progress = $stmt->fetchAll();
?>

<h1>Suivi des étudiants</h1>

<form method="GET" style="margin-bottom:20px;">
    <label for="course_id" style="font-weight:600; margin-right:8px;">Filtrer par cours :</label>
    <select name="course_id" id="course_id" onchange="this.form.submit()"
            style="padding:8px 12px; border:1px solid #E2E8F0; border-radius:6px; font-size:14px;">
        <option value="0">Tous les cours</option>
        <?php foreach ($teacherCourses as $tc): ?>
            <option value="<?= $tc['id'] ?>" <?= $selectedCourseId === (int)$tc['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tc['titre']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (empty($progress)): ?>
    <p class="t-empty">Aucun étudiant inscrit pour le moment.</p>
<?php else: ?>
<table class="t-table">
    <thead>
        <tr>
            <th>Étudiant</th>
            <th>Cours</th>
            <th>Progression</th>
            <th>Moyenne quiz</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($progress as $p):
            $total = (int)$p['total_lessons'];
            $done = (int)$p['completed_lessons'];
            $percent = $total > 0 ? round(($done / $total) * 100) : 0;
        ?>
        <tr>
            <td data-label="Étudiant"><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
            <td data-label="Cours"><?= htmlspecialchars($p['titre']) ?></td>
            <td data-label="Progression">
                <div class="t-progress-bar">
                    <div class="t-progress-fill" style="width:<?= $percent ?>%;"></div>
                </div>
                <span style="font-size:13px; color:#475569;"><?= $done ?> / <?= $total ?> leçons (<?= $percent ?>%)</span>
            </td>
            <td data-label="Moyenne quiz">
                <?= $p['avg_quiz_score'] !== null ? round($p['avg_quiz_score']) . '%' : '—' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>