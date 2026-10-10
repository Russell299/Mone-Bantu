<?php
// ============================================================
//  MONË BANTU — Signaler un problème (ouvre un litige)
//  POST : transaction_id, motif
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

$user_id        = userId();
$transaction_id = (int)($_POST['transaction_id'] ?? 0);
$motif          = trim($_POST['motif'] ?? '');

if (mb_strlen($motif) < 5) {
    echo json_encode(['success' => false, 'error' => 'Merci de décrire le problème plus précisément.']);
    exit();
}
if (mb_strlen($motif) > 500) {
    echo json_encode(['success' => false, 'error' => 'Le texte est trop long (500 caractères max).']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND acheteur_id = ?");
$stmt->execute([$transaction_id, $user_id]);
$t = $stmt->fetch();

if (!$t) {
    echo json_encode(['success' => false, 'error' => 'Transaction introuvable.']);
    exit();
}
if ($t['statut'] !== 'payee') {
    echo json_encode(['success' => false, 'error' => 'Cette transaction ne peut pas être signalée dans son état actuel.']);
    exit();
}

$stmt = $pdo->prepare("
    UPDATE transactions SET statut = 'litige', litige_motif = ?, date_litige = NOW() WHERE id = ?
");
if ($stmt->execute([$motif, $transaction_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'enregistrement.']);
}
?>
