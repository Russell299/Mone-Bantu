<?php
// ============================================================
//  MONË BANTU — Marquer les messages comme lus
//  POST : expediteur_id (optionnel)
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit();
}

$user_id       = userId();
$expediteur_id = (int)($_POST['expediteur_id'] ?? 0);

if ($expediteur_id > 0) {
    // Marquer les messages d'un expéditeur spécifique
    $stmt = $pdo->prepare("
        UPDATE messages
        SET lu = TRUE
        WHERE destinataire_id = ? AND expediteur_id = ? AND lu = FALSE
    ");
    $stmt->execute([$user_id, $expediteur_id]);
} else {
    // Marquer tous les messages comme lus
    $stmt = $pdo->prepare("
        UPDATE messages
        SET lu = TRUE
        WHERE destinataire_id = ? AND lu = FALSE
    ");
    $stmt->execute([$user_id]);
}

echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
?>
