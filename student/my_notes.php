<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Mes notes";
require_once '../includes/header.php';

// Récupère toutes les tentatives de l'étudiant, avec le contexte leçon/cours/module
$stmt = $pdo->prepare("
    SELECT 
        qa.id AS attempt_id,
        qa.score,
        qa.passed,
        qa.date_tentative,
        qz.titre AS quiz_titre,
        qz.note_passage,
        l.titre AS lesson_titre,
        c.titre AS course_titre,
        m.nom AS module_nom,
        -- Rang de la tentative pour cette leçon (pour afficher 'Tentative N')
        ROW_NUMBER() OVER (
            PARTITION BY qz.id 
            ORDER BY qa.date_tentative ASC
        ) AS numero_tentative,
        -- Meilleur score pour ce quiz
        MAX(qa2.score) OVER (PARTITION BY qz.id) AS meilleur_score
    FROM quiz_attempts qa
    JOIN quizzes qz ON qz.id = qa.quiz_id
    JOIN lessons l ON l.id = qz.lesson_id
    JOIN courses c ON c.id = l.course_id
    LEFT JOIN modules m ON m.id = c.module_id
    -- Sous-requête pour le MAX (alias nécessaire pour MySQL)
    JOIN quiz_attempts qa2 ON qa2.quiz_id = qz.id AND qa2.student_id = qa.student_id
    WHERE qa.student_id = ?
    ORDER BY qa.date_tentative DESC
");
$stmt->execute([$_SESSION['user_id']]);
$attempts = $stmt->fetchAll();

// Statistiques globales
$totalAttempts = count($attempts);
$passed = array_filter($attempts, fn($a) => $a['passed']);
$totalPassed = count($passed);
$avgScore = $totalAttempts > 0
    ? round(array_sum(array_column($attempts, 'score')) / $totalAttempts, 1)
    : 0;
?>

<h1>Mes évaluations</h1>

<!-- Statistiques rapides -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:16px; margin-bottom:28px;">
    <div style="background:white; border-radius:10px; padding:20px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <div style="font-size:28px; font-weight:700; color:#4F46E5;"><?= $totalAttempts ?></div>
        <div style="color:#64748B; font-size:14px; margin-top:4px;">Tentatives au total</div>
    </div>
    <div style="background:white; border-radius:10px; padding:20px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <div style="font-size:28px; font-weight:700; color:#10B981;"><?= $totalPassed ?></div>
        <div style="color:#64748B; font-size:14px; margin-top:4px;">Évaluations réussies</div>
    </div>
    <div style="background:white; border-radius:10px; padding:20px; text-align:center; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <div style="font-size:28px; font-weight:700; color:#F59E0B;"><?= $avgScore ?>%</div>
        <div style="color:#64748B; font-size:14px; margin-top:4px;">Score moyen</div>
    </div>
</div>

<?php if (empty($attempts)): ?>
    <div style="background:white; border-radius:10px; padding:40px; text-align:center; color:#64748B; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <p style="margin-bottom:16px;">Vous n'avez encore passé aucune évaluation.</p>
        <a href="catalogue.php" class="btn">Parcourir le catalogue</a>
    </div>
<?php else: ?>

<!-- Tableau des résultats -->
<div style="background:white; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); overflow:hidden;">
    <table style="width:100%; border-collapse:collapse;">
        <thead style="background:#F8FAFC;">
            <tr>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Évaluation</th>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Cours / Module</th>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Score</th>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Seuil</th>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Résultat</th>
                <th style="text-align:left; padding:12px 16px; font-size:13px; color:#64748B; font-weight:600;">Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attempts as $a): ?>
            <tr style="border-top:1px solid #F1F5F9;">
                <td style="padding:14px 16px;">
                    <div style="font-weight:600; font-size:14px;"><?= htmlspecialchars($a['quiz_titre']) ?></div>
                    <div style="color:#94A3B8; font-size:12px; margin-top:2px;">
                        Leçon : <?= htmlspecialchars($a['lesson_titre']) ?>
                        — Tentative n°<?= (int)$a['numero_tentative'] ?>
                    </div>
                </td>
                <td style="padding:14px 16px;">
                    <div style="font-size:14px; color:#1E293B;"><?= htmlspecialchars($a['course_titre']) ?></div>
                    <?php if ($a['module_nom']): ?>
                        <div style="font-size:12px; color:#94A3B8; margin-top:2px;"><?= htmlspecialchars($a['module_nom']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 16px;">
                    <!-- Barre de progression du score -->
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="flex:1; height:8px; background:#E2E8F0; border-radius:9999px; min-width:80px;">
                            <div style="height:100%; width:<?= min((float)$a['score'], 100) ?>%; background:<?= $a['passed'] ? '#10B981' : '#EF4444' ?>; border-radius:9999px; transition:width 0.6s;"></div>
                        </div>
                        <span style="font-weight:700; font-size:14px; color:<?= $a['passed'] ? '#10B981' : '#EF4444' ?>; min-width:40px;">
                            <?= round((float)$a['score']) ?>%
                        </span>
                    </div>
                </td>
                <td style="padding:14px 16px; color:#64748B; font-size:14px;">
                    <?= (int)$a['note_passage'] ?>%
                </td>
                <td style="padding:14px 16px;">
                    <span style="
                        display:inline-block;
                        padding:3px 10px;
                        border-radius:9999px;
                        font-size:12px;
                        font-weight:600;
                        background:<?= $a['passed'] ? '#ECFDF5' : '#FEF2F2' ?>;
                        color:<?= $a['passed'] ? '#065F46' : '#991B1B' ?>;">
                        <?= $a['passed'] ? '✓ Réussi' : '✗ Échoué' ?>
                    </span>
                </td>
                <td style="padding:14px 16px; color:#64748B; font-size:13px; white-space:nowrap;">
                    <?= date('d/m/Y', strtotime($a['date_tentative'])) ?>
                    <div style="color:#CBD5E1; font-size:12px;"><?= date('H:i', strtotime($a['date_tentative'])) ?></div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Responsive : cards sur mobile -->
<style>
@media (max-width: 768px) {
    table, thead, tbody, tr, th, td { display: block; width: 100%; }
    thead { display: none; }
    tr {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 14px;
        padding: 4px 0;
    }
    td { border-top: none !important; padding: 8px 16px !important; }
    td:not(:last-child)::before {
        content: attr(data-label);
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #94A3B8;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    /* Cache le tableau parent et utilise les cards */
    div[style*="border-radius:10px; box-shadow"] { box-shadow: none !important; background: transparent !important; }
}
</style>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>