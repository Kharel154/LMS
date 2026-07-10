<?php
// includes/functions.php

function sanitize_input($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function log_connection($user_id, $action) {
    global $pdo;
    if (!$user_id) return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO connection_logs (user_id, ip_address, action) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $ip, $action]);
}

function upload_file($file, $target_dir, $allowed_ext = ['pdf', 'mp4']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Aucun fichier reçu.'];
    }

    // Nettoyage et création du dossier
    $target_dir = rtrim($target_dir, '/') . '/';
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0775, true)) {
            return ['success' => false, 'error' => "Impossible de créer le dossier $target_dir"];
        }
        chmod($target_dir, 0775);
    }

    if (!is_writable($target_dir)) {
        return ['success' => false, 'error' => "Le dossier $target_dir n'est pas accessible en écriture."];
    }

    $filename = basename($file['name']);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        return ['success' => false, 'error' => "Extension .$ext non autorisée."];
    }

    if ($file['size'] > 200 * 1024 * 1024) {
        return ['success' => false, 'error' => "Fichier trop volumineux (max 200 Mo)."];
    }

    $new_name = uniqid('lesson_') . '.' . $ext;
    $target_file = $target_dir . $new_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        chmod($target_file, 0644); // fichier lisible
        return ['success' => true, 'filename' => $new_name];
    }

    return ['success' => false, 'error' => 'Échec du déplacement du fichier. Vérifiez les permissions.'];
}


 // Génère un code de vérification de certificat lisible et unique.
 // Format : CERT-2026-AB3X9K
 
function generate_certificate_code() {
    global $pdo;
    $annee = date('Y');
    do {
        $suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $code = "CERT-{$annee}-{$suffix}";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE code_verification = ?");
        $stmt->execute([$code]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists);

    return $code;
}


 //  Vérifie si un étudiant a validé TOUS les cours d'un module donné et lui attribue automatiquement un certificat si ce n'est pas déjà fait.
 
 // À appeler chaque fois qu'une leçon passe au statut 'termine' pour un étudiant.

function check_and_award_certificate($student_id, $lesson_id) {
    global $pdo;

    // 1. Retrouve le module concerné par cette leçon (via lesson -> course -> module)
    $stmt = $pdo->prepare("
        SELECT c.module_id
        FROM lessons l
        JOIN courses c ON c.id = l.course_id
        WHERE l.id = ?
    ");
    $stmt->execute([$lesson_id]);
    $module_id = $stmt->fetchColumn();

    if (!$module_id) {
        return null; // ce cours n'appartient à aucun module, rien à faire
    }

    // 2. Vérifie si un certificat existe déjà pour cet étudiant + ce module
    $stmt = $pdo->prepare("SELECT id FROM certificates WHERE student_id = ? AND module_id = ?");
    $stmt->execute([$student_id, $module_id]);
    if ($stmt->fetch()) {
        return null; // déjà certifié, rien à refaire
    }

    // 3. Liste tous les cours du module
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE module_id = ?");
    $stmt->execute([$module_id]);
    $courseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($courseIds)) {
        return null; // module sans cours, rien à valider
    }

    // 4. Pour chaque cours du module, vérifie que TOUTES ses leçons sont 'termine' pour cet étudiant
    foreach ($courseIds as $cid) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
        $stmt->execute([$cid]);
        $totalLessons = (int)$stmt->fetchColumn();

        if ($totalLessons === 0) {
            return null; // un cours du module n'a aucune leçon : on ne peut pas le considérer comme validé
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lesson_progress lp
            JOIN lessons l ON l.id = lp.lesson_id
            WHERE lp.student_id = ? AND l.course_id = ? AND lp.statut = 'termine'
        ");
        $stmt->execute([$student_id, $cid]);
        $completedLessons = (int)$stmt->fetchColumn();

        if ($completedLessons < $totalLessons) {
            return null; // au moins un cours du module n'est pas entièrement validé
        }
    }

    // 5. Tous les cours du module sont validés : attribution du certificat
    $code = generate_certificate_code();
    $stmt = $pdo->prepare("
        INSERT INTO certificates (student_id, module_id, code_verification)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$student_id, $module_id, $code]);

    return [
        'certificate_id' => $pdo->lastInsertId(),
        'module_id' => $module_id,
        'code_verification' => $code
    ];
}

?>