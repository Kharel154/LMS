<?php
// LLM/certificate.php — à la RACINE du projet (même niveau que index.php)
// Accessible sans connexion pour la vérification et le téléchargement

session_start();
require_once 'config/database.php';      // chemin depuis LLM/
require_once 'includes/functions.php';   // chemin depuis LLM/

$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    http_response_code(400);
    die('Code de vérification manquant.');
}

$stmt = $pdo->prepare("
    SELECT 
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
    WHERE cert.code_verification = ?
");
$stmt->execute([$code]);
$cert = $stmt->fetch();

if (!$cert) {
    http_response_code(404);
    die('Certificat introuvable. Vérifiez le code de vérification.');
}

// Date en français
$mois_fr = [
    'January'=>'janvier','February'=>'février','March'=>'mars',
    'April'=>'avril','May'=>'mai','June'=>'juin',
    'July'=>'juillet','August'=>'août','September'=>'septembre',
    'October'=>'octobre','November'=>'novembre','December'=>'décembre'
];
$dateFormatee = date('d F Y', strtotime($cert['date_obtention']));
$dateFormatee = str_replace(array_keys($mois_fr), array_values($mois_fr), $dateFormatee);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificat — <?= htmlspecialchars($cert['module_nom']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #F1F5F9;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 30px 16px;
        }

        .actions-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            width: 100%;
            max-width: 800px;
        }
        .btn-print {
            background: #4F46E5;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-print:hover { background: #4338CA; }
        .btn-back {
            background: white;
            color: #64748B;
            border: 1px solid #E2E8F0;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            font-family: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .certificate {
            width: 100%;
            max-width: 800px;
            background: white;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        }

        .certificate::before {
            content: '';
            position: absolute;
            inset: 14px;
            border: 2px solid #4F46E5;
            border-radius: 2px;
            opacity: 0.12;
            pointer-events: none;
            z-index: 0;
        }

        .cert-inner {
            padding: 60px 70px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .corner {
            position: absolute;
            width: 70px;
            height: 70px;
            opacity: 0.12;
            z-index: 1;
        }
        .corner-tl { top:22px; left:22px; border-top:3px solid #4F46E5; border-left:3px solid #4F46E5; }
        .corner-tr { top:22px; right:22px; border-top:3px solid #4F46E5; border-right:3px solid #4F46E5; }
        .corner-bl { bottom:22px; left:22px; border-bottom:3px solid #4F46E5; border-left:3px solid #4F46E5; }
        .corner-br { bottom:22px; right:22px; border-bottom:3px solid #4F46E5; border-right:3px solid #4F46E5; }

        .cert-logo {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: #4F46E5;
            margin-bottom: 4px;
        }
        .cert-tagline {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #94A3B8;
            margin-bottom: 36px;
        }
        .cert-title {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            font-weight: 700;
            color: #1E293B;
            margin-bottom: 6px;
        }
        .cert-title-sub {
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #94A3B8;
            margin-bottom: 36px;
        }
        .cert-label {
            font-size: 13px;
            color: #64748B;
            margin-bottom: 8px;
        }
        .cert-name {
            font-family: 'Playfair Display', serif;
            font-size: 44px;
            color: #1E293B;
            margin-bottom: 28px;
            line-height: 1.1;
        }
        .cert-for {
            font-size: 13px;
            color: #64748B;
            margin-bottom: 8px;
        }
        .cert-module {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: #4F46E5;
            margin-bottom: 8px;
        }
        .cert-courses {
            font-size: 12px;
            color: #94A3B8;
            margin-bottom: 36px;
        }
        .cert-divider {
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, #4F46E5, #818CF8);
            margin: 0 auto 36px;
            border-radius: 2px;
        }
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 48px;
        }
        .cert-date-label, .cert-sign-label {
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #94A3B8;
            margin-bottom: 6px;
        }
        .cert-date-value {
            font-size: 15px;
            font-weight: 600;
            color: #1E293B;
        }
        .cert-sign-line {
            width: 140px;
            height: 1px;
            background: #CBD5E1;
            margin: 0 auto 6px;
        }
        .cert-sign-name {
            font-size: 13px;
            color: #475569;
        }
        .cert-code {
            margin-top: 32px;
            padding: 10px 14px;
            background: #F8FAFC;
            border-radius: 6px;
            font-size: 11px;
            color: #94A3B8;
            font-family: monospace;
            letter-spacing: 0.5px;
        }

        @media print {
            body { background:white; padding:0; }
            .actions-bar { display:none !important; }
            .certificate { max-width:100%; box-shadow:none; }
            .cert-inner { padding:40px 60px; }
        }

        @media (max-width: 600px) {
            .cert-inner { padding: 30px 20px; }
            .cert-name { font-size: 28px; }
            .cert-module { font-size: 20px; }
            .cert-title { font-size: 28px; }
            .cert-footer { flex-direction: column; gap: 24px; align-items: center; }
        }
    </style>
</head>
<body>

<div class="actions-bar">
    <button class="btn-print" onclick="window.print()">⬇ Télécharger / Imprimer le PDF</button>
    <a href="javascript:history.back()" class="btn-back">← Retour</a>
</div>

<div class="certificate">
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    <div class="cert-inner">
        <div class="cert-logo">LMS Académie</div>
        <div class="cert-tagline">Plateforme d'apprentissage en ligne</div>

        <div class="cert-title">Certificat</div>
        <div class="cert-title-sub">de complétion de module</div>

        <div class="cert-label">Ce certificat est décerné à</div>
        <div class="cert-name"><?= htmlspecialchars($cert['etudiant_prenom'] . ' ' . $cert['etudiant_nom']) ?></div>

        <div class="cert-for">pour avoir complété avec succès le module</div>
        <div class="cert-module"><?= htmlspecialchars($cert['module_nom']) ?></div>
        <div class="cert-courses"><?= (int)$cert['nb_cours'] ?> cours validés</div>

        <?php if ($cert['module_description']): ?>
        <div style="font-size:13px; color:#64748B; font-style:italic; margin-bottom:16px; max-width:480px; margin-left:auto; margin-right:auto; line-height:1.6;">
            <?= htmlspecialchars($cert['module_description']) ?>
        </div>
        <?php endif; ?>

        <div class="cert-divider"></div>

        <div class="cert-footer">
            <div>
                <div class="cert-date-label">Date d'obtention</div>
                <div class="cert-date-value"><?= htmlspecialchars($dateFormatee) ?></div>
            </div>
            <div style="text-align:center;">
                <div class="cert-sign-line"></div>
                <div class="cert-sign-label">Direction pédagogique</div>
                <div class="cert-sign-name">LMS Académie</div>
            </div>
        </div>

        <div class="cert-code">
            Code de vérification : <?= htmlspecialchars($cert['code_verification']) ?>
        </div>
    </div>
</div>

</body>
</html>