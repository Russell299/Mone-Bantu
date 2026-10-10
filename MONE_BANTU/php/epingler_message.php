<?php
// ============================================================
//  MONË BANTU — Épingler / désépingler un message
//  POST : message_id, duree ('24h', '7j', '1mois', 'annuler')
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

$user_id    = userId();
$message_id = (int)($_POST['message_id'] ?? 0);
$duree      = $_POST['duree'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->execute([$message_id]);
$message = $stmt->fetch();

if (!$message) {
    echo json_encode(['success' => false, 'error' => 'Message introuvable.']);
    exit();
}
// Seuls les deux participants de la conversation peuvent épingler/désépingler
if (!in_array($user_id, [(int)$message['expediteur_id'], (int)$message['destinataire_id']], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé.']);
    exit();
}

$intervalles = [
    '24h'   => '+24 hours',
    '7j'    => '+7 days',
    '1mois' => '+1 month',
];

if ($duree === 'annuler') {
    $pdo->prepare("UPDATE messages SET epingle_jusqu = NULL WHERE id = ?")->execute([$message_id]);
    echo json_encode(['success' => true, 'epingle_jusqu' => null]);
    exit();
}

if (!isset($intervalles[$duree])) {
    echo json_encode(['success' => false, 'error' => 'Durée invalide.']);
    exit();
}

$date_limite = (new DateTime())->modify($intervalles[$duree])->format('Y-m-d H:i:s');
$pdo->prepare("UPDATE messages SET epingle_jusqu = ? WHERE id = ?")->execute([$date_limite, $message_id]);

echo json_encode(['success' => true, 'epingle_jusqu' => $date_limite]);
?>
