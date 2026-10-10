<?php
// ============================================================
//  MONË BANTU — Incrémenter les vues d'un Réel
//  POST : reel_id
//  Réponse JSON : { success }
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

$reel_id = (int)($_POST['reel_id'] ?? 0);

if ($reel_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Réel invalide']);
    exit();
}

// Anti-spam : une seule vue comptée par Réel par session
if (empty($_SESSION['reels_vus'][$reel_id])) {
    $stmt = $pdo->prepare("UPDATE reels SET vues = vues + 1 WHERE id = ? AND date_expiration > NOW()");
    $stmt->execute([$reel_id]);
    $_SESSION['reels_vus'][$reel_id] = true;
}

echo json_encode(['success' => true]);