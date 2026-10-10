<?php
// ============================================================
//  MONË BANTU — Publier un Réel
//  POST : legende (optionnel), FILES : video
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

$user_id = userId();
$legende = trim($_POST['legende'] ?? '');

if (mb_strlen($legende) > 255) {
    echo json_encode(['success' => false, 'error' => 'La légende est trop longue (255 caractères max).']);
    exit();
}

if (empty($_FILES['video']['tmp_name']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Aucune vidéo sélectionnée.']);
    exit();
}

$max_size = 25 * 1024 * 1024;
$tmp      = $_FILES['video']['tmp_name'];
$taille   = $_FILES['video']['size'];
$mimetype = mime_content_type($tmp);

$types_autorises = ['video/mp4', 'video/webm', 'video/quicktime', 'video/3gpp'];
$ext_map         = ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/3gpp' => '3gp'];

if ($taille > $max_size) {
    echo json_encode(['success' => false, 'error' => 'La vidéo ne doit pas dépasser 25 Mo.']);
    exit();
}
if (!in_array($mimetype, $types_autorises, true)) {
    echo json_encode(['success' => false, 'error' => 'Format non autorisé. Utilisez MP4, WebM ou MOV.']);
    exit();
}

$upload_dir = dirname(__DIR__) . '/uploads/reels/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$ext      = $ext_map[$mimetype] ?? 'mp4';
$filename = 'reel_' . $user_id . '_' . uniqid() . '.' . $ext;
$dest     = $upload_dir . $filename;

if (!move_uploaded_file($tmp, $dest)) {
    echo json_encode(['success' => false, 'error' => "Erreur lors de l'envoi du fichier."]);
    exit();
}

$video_url  = 'uploads/reels/' . $filename;
$mode_actif = (modeActif() === 'boutique' && estBoutique($pdo, $user_id)) ? 'boutique' : 'personnel';

$stmt = $pdo->prepare("
    INSERT INTO reels (utilisateur_id, video_url, legende, mode_publication, date_publication, date_expiration)
    VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
");

if ($stmt->execute([$user_id, $video_url, $legende ?: null, $mode_actif])) {
    echo json_encode(['success' => true, 'reel_id' => (int)$pdo->lastInsertId()]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement.']);
}