<?php
// ============================================================
//  MONË BANTU — Envoyer un message
//  POST : destinataire_id, message
//  FILES: media (optionnel)
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

$exp_id  = userId();
$dest_id = (int)($_POST['destinataire_id'] ?? 0);
$contenu = trim($_POST['message'] ?? '');
$art_id  = !empty($_POST['article_id']) ? (int)$_POST['article_id'] : null;
$reel_id = !empty($_POST['reel_id']) ? (int)$_POST['reel_id'] : null;
$type    = 'texte';
$f_url   = null;
$f_nom   = null;
$f_taille = null;

// Validations
if ($dest_id <= 0) { echo json_encode(['success'=>false,'error'=>'Destinataire invalide']); exit(); }
if ($dest_id === $exp_id) { echo json_encode(['success'=>false,'error'=>'Impossible de vous écrire']); exit(); }

$s = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ?");
$s->execute([$dest_id]);
if (!$s->fetch()) { echo json_encode(['success'=>false,'error'=>'Destinataire introuvable']); exit(); }

// Upload media
if (!empty($_FILES['media']['tmp_name']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $file  = $_FILES['media'];
    $mime  = mime_content_type($file['tmp_name']);
    $taille = $file['size'];

    if ($taille > 50 * 1024 * 1024) {
        echo json_encode(['success'=>false,'error'=>'Fichier trop volumineux (max 50 Mo)']);
        exit();
    }

    $images = ['image/jpeg','image/png','image/webp','image/gif'];
    $videos = ['video/mp4','video/webm','video/quicktime','video/3gpp'];
    $audios = ['audio/mpeg','audio/mp4','audio/ogg','audio/wav','audio/webm','audio/aac'];

    if (in_array($mime, $images)) {
        $type = 'image';
        $folder = 'uploads/messages/images/';
        $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime] ?? 'jpg';
    } elseif (in_array($mime, $videos)) {
        $type = 'video';
        $folder = 'uploads/messages/videos/';
        $ext = ['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'][$mime] ?? 'mp4';
    } elseif (in_array($mime, $audios)) {
        $type = 'audio';
        $folder = 'uploads/messages/audio/';
        $ext = ['audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','audio/webm'=>'webm'][$mime] ?? 'mp3';
    } else {
        $type = 'fichier';
        $folder = 'uploads/messages/fichiers/';
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
    }

    $dir = dirname(__DIR__) . '/' . $folder;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = 'msg_' . $exp_id . '_' . uniqid() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) {
        echo json_encode(['success'=>false,'error'=>"Erreur upload"]);
        exit();
    }

    $f_url    = $folder . $fname;
    $f_nom    = $file['name'];
    $f_taille = $taille;
    if (empty($contenu)) $contenu = '';

} elseif (empty($contenu)) {
    echo json_encode(['success'=>false,'error'=>'Message vide']);
    exit();
}

if (mb_strlen($contenu) > 2000) {
    echo json_encode(['success'=>false,'error'=>'Message trop long']);
    exit();
}

// Vérifier article_id
if ($art_id !== null) {
    $sa = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
    $sa->execute([$art_id]);
    if (!$sa->fetch()) $art_id = null;
}

// Vérifier reel_id (et qu'il n'a pas expiré)
if ($reel_id !== null) {
    $sr = $pdo->prepare("SELECT id FROM reels WHERE id = ? AND date_expiration > NOW()");
    $sr->execute([$reel_id]);
    if (!$sr->fetch()) $reel_id = null;
}

$mode_actif = modeActif();
$nom_exp    = nomAffiche($pdo, $exp_id, $mode_actif);

// Insérer
$stmt = $pdo->prepare("
    INSERT INTO messages
        (expediteur_id, destinataire_id, article_id, reel_id, contenu, type, expediteur_nom_affiche, expediteur_type, fichier_url, fichier_nom, fichier_taille, date_envoi, lu)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
");

if ($stmt->execute([$exp_id, $dest_id, $art_id, $reel_id, $contenu, $type, $nom_exp, $mode_actif, $f_url, $f_nom, $f_taille])) {
    echo json_encode([
        'success'    => true,
        'message_id' => (int)$pdo->lastInsertId(),
        'type'       => $type,
        'date_envoi' => date('H:i'),
    ]);
} else {
    echo json_encode(['success'=>false,'error'=>"Erreur base de données"]);
}
?>
