<?php
// ============================================================
//  MONË BANTU — Signaler un utilisateur
//  POST : utilisateur_signale_id, motif, contexte ('article'|'message'|'autre'), article_id (optionnel)
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non connecté', 'require_login' => true]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

$signalant_id = userId();
$signale_id   = (int)($_POST['utilisateur_signale_id'] ?? 0);
$motif        = trim($_POST['motif'] ?? '');
$contexte     = $_POST['contexte'] ?? 'autre';
$gravite      = $_POST['gravite'] ?? 'moyen';
$article_id   = !empty($_POST['article_id']) ? (int)$_POST['article_id'] : null;

if (!in_array($contexte, ['article', 'message', 'autre'], true)) $contexte = 'autre';
if (!in_array($gravite, ['leger', 'moyen', 'grave'], true)) $gravite = 'moyen';

if ($signale_id <= 0 || $signale_id === $signalant_id) {
    echo json_encode(['success' => false, 'error' => 'Signalement invalide.']);
    exit();
}
if (mb_strlen($motif) < 5) {
    echo json_encode(['success' => false, 'error' => 'Merci de décrire le problème plus précisément.']);
    exit();
}
if (mb_strlen($motif) > 500) {
    echo json_encode(['success' => false, 'error' => 'Le texte est trop long (500 caractères max).']);
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ?");
$stmt->execute([$signale_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable.']);
    exit();
}

// ---- Preuve jointe (optionnelle) ----
$preuve_url = null;
if (!empty($_FILES['preuve']['tmp_name']) && $_FILES['preuve']['error'] === UPLOAD_ERR_OK) {
    $types_autorises = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size        = 5 * 1024 * 1024; // 5 Mo
    $tmp             = $_FILES['preuve']['tmp_name'];
    $taille          = $_FILES['preuve']['size'];
    $mimetype        = mime_content_type($tmp);

    if ($taille > $max_size) {
        echo json_encode(['success' => false, 'error' => 'La preuve ne doit pas dépasser 5 Mo.']);
        exit();
    }
    if (!in_array($mimetype, $types_autorises, true)) {
        echo json_encode(['success' => false, 'error' => 'Format non autorisé pour la preuve (JPEG, PNG ou WebP).']);
        exit();
    }

    $upload_dir = '../uploads/signalements/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $filename = 'preuve_' . $signalant_id . '_' . uniqid() . '.' . ($ext_map[$mimetype] ?? 'jpg');
    $dest     = $upload_dir . $filename;

    if (move_uploaded_file($tmp, $dest)) {
        $preuve_url = 'uploads/signalements/' . $filename;
    }
}

$stmt = $pdo->prepare("
    INSERT INTO signalements (signalant_id, utilisateur_signale_id, motif, gravite, contexte, article_id, preuve_url, statut, date_creation)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
");
if ($stmt->execute([$signalant_id, $signale_id, $motif, $gravite, $contexte, $article_id, $preuve_url])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => "Erreur lors de l'enregistrement."]);
}
?>
