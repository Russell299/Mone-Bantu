<?php
// ============================================================
//  MONË BANTU — Résoudre un litige (admin uniquement)
//  POST : transaction_id, decision ('vendeur' ou 'acheteur')
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

if (!estConnecte() || !estAdmin($pdo, userId())) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

$transaction_id = (int)($_POST['transaction_id'] ?? 0);
$decision        = $_POST['decision'] ?? '';

if (!in_array($decision, ['vendeur', 'acheteur'], true)) {
    echo json_encode(['success' => false, 'error' => 'Décision invalide.']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ? AND statut = 'litige'");
$stmt->execute([$transaction_id]);
$t = $stmt->fetch();

if (!$t) {
    echo json_encode(['success' => false, 'error' => 'Litige introuvable.']);
    exit();
}

$pdo->beginTransaction();
try {
    if ($decision === 'vendeur') {
        // Le vendeur avait raison : on le paie normalement
        $pdo->prepare("INSERT IGNORE INTO soldes_vendeurs (utilisateur_id, solde) VALUES (?, 0)")
            ->execute([$t['vendeur_id']]);
        $pdo->prepare("UPDATE soldes_vendeurs SET solde = solde + ? WHERE utilisateur_id = ?")
            ->execute([$t['prix_accepte'], $t['vendeur_id']]);
        $pdo->prepare("UPDATE articles SET statut = 'vendu' WHERE id = ?")->execute([$t['article_id']]);
        $pdo->prepare("UPDATE utilisateurs SET nombre_ventes = nombre_ventes + 1 WHERE id = ?")->execute([$t['vendeur_id']]);
        notifierInteresses($pdo, (int)$t['article_id'], 'vente', 'Une annonce qui vous intéresse vient d\'être vendue.');
        $nouveau_statut = 'terminee';
    } else {
        // L'acheteur avait raison : le vendeur n'est pas payé, l'article redevient disponible
        // (le remboursement de l'acheteur se fait hors plateforme pour l'instant — pas de
        // paiement réel en place, voir TODO_PAIEMENT)
        $pdo->prepare("UPDATE articles SET statut = 'disponible' WHERE id = ?")->execute([$t['article_id']]);
        $nouveau_statut = 'annulee';
    }

    $pdo->prepare("
        UPDATE transactions
        SET statut = ?, resolution_admin = ?, date_resolution = NOW()
        WHERE id = ?
    ")->execute([$nouveau_statut, $decision, $transaction_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la résolution.']);
}
?>
