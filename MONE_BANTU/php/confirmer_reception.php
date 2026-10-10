<?php
// ============================================================
//  MONË BANTU — Confirmer la réception d'un article
//  POST : transaction_id
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

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND acheteur_id = ?");
$stmt->execute([$transaction_id, $user_id]);
$t = $stmt->fetch();

if (!$t) {
    echo json_encode(['success' => false, 'error' => 'Transaction introuvable.']);
    exit();
}
if ($t['statut'] !== 'payee') {
    echo json_encode(['success' => false, 'error' => 'Cette transaction ne peut pas être confirmée dans son état actuel.']);
    exit();
}

$pdo->beginTransaction();
try {
    // S'assurer que le vendeur a bien une ligne de solde
    $pdo->prepare("INSERT IGNORE INTO soldes_vendeurs (utilisateur_id, solde) VALUES (?, 0)")
        ->execute([$t['vendeur_id']]);

    // Créditer le vendeur (commission toujours retenue par la plateforme au préalable)
    $pdo->prepare("UPDATE soldes_vendeurs SET solde = solde + ? WHERE utilisateur_id = ?")
        ->execute([$t['prix_accepte'], $t['vendeur_id']]);

    $pdo->prepare("
        UPDATE transactions SET statut = 'terminee', date_confirmation = NOW() WHERE id = ?
    ")->execute([$transaction_id]);
    $pdo->prepare("UPDATE articles SET statut = 'vendu' WHERE id = ?")->execute([$t['article_id']]);
    $pdo->prepare("UPDATE utilisateurs SET nombre_ventes = nombre_ventes + 1 WHERE id = ?")->execute([$t['vendeur_id']]);
    notifierInteresses($pdo, (int)$t['article_id'], 'vente', 'Une annonce qui vous intéresse vient d\'être vendue.');

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la confirmation.']);
}
?>
