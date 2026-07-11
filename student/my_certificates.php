<?php
// student/my_certificates.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
redirect_if_not_logged('student');

$title = "Mes certificats";
require_once '../includes/header.php';

$stmt = $pdo->prepare("
    SELECT 
        cert.id,
        cert.code_verification,
        cert.date_obtention,
        m.nom AS module_nom,
        m.description AS module_description,
        (SELECT COUNT(*) FROM courses c WHERE c.module_id = m.id) AS nb_cours,
        u.nom AS etudiant_nom,
        u.prenom AS etudiant_prenom
    FROM certificates cert
    JOIN modules m ON m.id = cert.module_id
    JOIN users u ON u.id = cert.student_id
    WHERE cert.student_id = ?
    ORDER BY cert.date_obtention DESC
");
$stmt->execute([$_SESSION['user_id']]);
$certificates = $stmt->fetchAll();
?>

<h1>Mes certificats</h1>
<p style="color:#64748B; margin-bottom:24px;">
    <?= count($certificates) ?> certificat<?= count($certificates) > 1 ? 's' : '' ?> obtenu<?= count($certificates) > 1 ? 's' : '' ?>
</p>

<?php if (empty($certificates)): ?>
    <div style="background:white; border-radius:10px; padding:40px; text-align:center; color:#64748B; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
        <div style="font-size:48px; margin-bottom:16px;">🎓</div>
        <p style="margin-bottom:8px; font-weight:600;">Aucun certificat pour le moment</p>
        <p style="font-size:14px; margin-bottom:20px;">Complétez tous les cours d'un module pour obtenir votre certificat.</p>
        <a href="catalogue.php" class="btn">Parcourir le catalogue</a>
    </div>
<?php else: ?>

<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:24px;">
    <?php foreach ($certificates as $cert): ?>
    <div style="background:white; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08);">

        <!-- Bandeau supérieur -->
        <div style="background:linear-gradient(135deg, #4F46E5, #818CF8); padding:24px; text-align:center; color:white;">
            <div style="font-size:36px; margin-bottom:8px;">🎓</div>
            <div style="font-size:11px; text-transform:uppercase; letter-spacing:2px; opacity:0.8; margin-bottom:4px;">Certificat de complétion</div>
            <div style="font-size:20px; font-weight:700;"><?= htmlspecialchars($cert['module_nom']) ?></div>
        </div>

        <!-- Contenu -->
        <div style="padding:20px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
                <div>
                    <div style="font-size:12px; color:#94A3B8; margin-bottom:2px;">Décerné à</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($cert['etudiant_prenom'] . ' ' . $cert['etudiant_nom']) ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:12px; color:#94A3B8; margin-bottom:2px;">Date</div>
                    <div style="font-weight:600; font-size:14px;"><?= date('d/m/Y', strtotime($cert['date_obtention'])) ?></div>
                </div>
            </div>

            <?php if ($cert['module_description']): ?>
            <p style="color:#64748B; font-size:13px; margin-bottom:14px; line-height:1.5;">
                <?= htmlspecialchars($cert['module_description']) ?>
            </p>
            <?php endif; ?>

            <div style="background:#F8FAFC; border-radius:8px; padding:10px 14px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <span style="font-size:12px; color:#64748B; white-space:nowrap;"><?= (int)$cert['nb_cours'] ?> cours validés</span>
                <span style="font-size:11px; color:#94A3B8; font-family:monospace; word-break:break-all;"><?= htmlspecialchars($cert['code_verification']) ?></span>
            </div>

            <!-- Le lien pointe vers certificate.php à la racine LLM/ -->
            <a href="../certificate.php?code=<?= urlencode($cert['code_verification']) ?>"
               target="_blank"
               class="btn"
               style="display:block; text-align:center; text-decoration:none;">
                ⬇ Télécharger en PDF
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>