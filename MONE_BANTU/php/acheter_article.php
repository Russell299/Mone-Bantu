<?php
// ============================================================
//  MONË BANTU — Achat direct (article d'un PARTICULIER)
//  POST : article_id
//  ⚠️ Paiement Orange Money/MTN MoMo pas encore branché :
//     la transaction est créée au statut 'en_cours' en attendant
//     l'intégration réelle. Voir TODO_PAIEMENT ci-dessous.
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

$acheteur_id   = userId();
$article_id    = (int)($_POST['article_id'] ?? 0);
$mode_virement = ($_POST['mode_virement'] ?? 'securise') === 'direct' ? 'direct' : 'securise';

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article introuvable.']);
    exit();
}
if ($article['statut'] !== 'disponible') {
    echo json_encode(['success' => false, 'error' => 'Cet article n\'est plus disponible.']);
    exit();
}
if ((int)$article['vendeur_id'] === $acheteur_id) {
    echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas acheter votre propre article.']);
    exit();
}

// Cet endpoint est réservé aux articles de PARTICULIERS (pas boutique)
if ($article['mode_vente'] === 'boutique') {
    echo json_encode(['success' => false, 'error' => 'Cet article appartient à une boutique — ajoutez-le au panier.', 'redirect_panier' => true]);
    exit();
}

// Le virement direct ne peut être demandé que s'il est réellement débloqué entre les deux (jamais faire confiance au client)
if ($mode_virement === 'direct' && !virementDirectDisponible($pdo, $acheteur_id, (int)$article['vendeur_id'])) {
    $mode_virement = 'securise';
}

$prix       = (int)$article['prix'];
$commission = (int)round($prix * TAUX_COMMISSION_PARTICULIER);
$total      = $prix + $commission;

$pdo->beginTransaction();
try {
    // Virement direct (paire de confiance) : réglé immédiatement, sans attente de
    // confirmation ni délai de 3 jours. Mode sécurisé : en attente du paiement
    // Mobile Money (pas encore branché — voir TODO_PAIEMENT).
    if ($mode_virement === 'direct') {
        $statut_initial = 'terminee';
        $stmt = $pdo->prepare("
            INSERT INTO transactions (article_id, acheteur_id, vendeur_id, prix_accepte, commission, montant_total, statut, mode_virement, date_paiement, date_confirmation, date_transaction)
            VALUES (?, ?, ?, ?, ?, ?, 'terminee', 'direct', NOW(), NOW(), NOW())
        ");
        $stmt->execute([$article_id, $acheteur_id, $article['vendeur_id'], $prix, $commission, $total]);
        $transaction_id = $pdo->lastInsertId();

        // Créditer le vendeur immédiatement et clôturer l'article
        $pdo->prepare("INSERT IGNORE INTO soldes_vendeurs (utilisateur_id, solde) VALUES (?, 0)")
            ->execute([$article['vendeur_id']]);
        $pdo->prepare("UPDATE soldes_vendeurs SET solde = solde + ? WHERE utilisateur_id = ?")
            ->execute([$prix, $article['vendeur_id']]);
        $pdo->prepare("UPDATE articles SET statut = 'vendu' WHERE id = ?")->execute([$article_id]);
        $pdo->prepare("UPDATE utilisateurs SET nombre_ventes = nombre_ventes + 1 WHERE id = ?")->execute([$article['vendeur_id']]);
        notifierInteresses($pdo, $article_id, 'vente', 'Une annonce qui vous intéresse vient d\'être vendue.', $acheteur_id);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (article_id, acheteur_id, vendeur_id, prix_accepte, commission, montant_total, statut, mode_virement, date_transaction)
            VALUES (?, ?, ?, ?, ?, ?, 'en_attente_paiement', 'securise', NOW())
        ");
        $stmt->execute([$article_id, $acheteur_id, $article['vendeur_id'], $prix, $commission, $total]);
        $transaction_id = $pdo->lastInsertId();

        // L'article est réservé le temps que la réception soit confirmée
        $pdo->prepare("UPDATE articles SET statut = 'reserve' WHERE id = ?")->execute([$article_id]);
    }

    // TODO_PAIEMENT (mode 'securise' uniquement) : déclencher ici la demande de paiement
    // Orange Money / MTN MoMo pour le montant $total. Une fois confirmé (webhook/callback) :
    //   - statut = 'payee', date_paiement = NOW(), date_limite = NOW() + 3 jours
    //   - l'argent reste "retenu" par la plateforme (aucun crédit vendeur pour l'instant)
    // Puis, une fois que l'acheteur clique "J'ai bien reçu l'article" (ou après 3 jours
    // sans action — voir liberationPaiementsExpires()) :
    //   - statut = 'terminee', créditer soldes_vendeurs.solde de $prix
    //   - articles.statut = 'vendu'
    // Si l'acheteur signale un problème avant : statut = 'litige' (voir admin_litiges.php)

    $pdo->commit();
    echo json_encode([
        'success'        => true,
        'transaction_id' => (int)$transaction_id,
        'mode_virement'  => $mode_virement,
        'prix'           => $prix,
        'commission'     => $commission,
        'total'          => $total,
        'message'        => $mode_virement === 'direct'
            ? 'Achat en virement direct enregistré — le vendeur a été payé immédiatement.'
            : 'Commande enregistrée. Le paiement Mobile Money sera bientôt disponible.',
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la création de la commande.']);
}
?>
