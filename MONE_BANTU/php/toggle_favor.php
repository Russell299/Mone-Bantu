<?php
// ============================================================
//  MONË BANTU — Toggle Favori
//  POST : article_id
//  Réponse JSON : { success, added }
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');

if (!estConnecte()) {
    echo json_encode(['success' => false, 'require_login' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

$article_id = (int)($_POST['article_id'] ?? 0);
$user_id    = userId();

if ($article_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Article invalide']);
    exit();
}

// Vérifier que l'article existe
$stmt = $pdo->prepare("SELECT id, vendeur_id FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article introuvable']);
    exit();
}

// Vérifier si déjà en favori
$stmt = $pdo->prepare("SELECT id FROM favoris WHERE utilisateur_id = ? AND article_id = ?");
$stmt->execute([$user_id, $article_id]);
$existant = $stmt->fetch();

if ($existant) {
    // Retirer des favoris
    $pdo->prepare("DELETE FROM favoris WHERE id = ?")->execute([$existant['id']]);
    $added = false;
} else {
    // Ajouter aux favoris
    $pdo->prepare("INSERT INTO favoris (utilisateur_id, article_id, date_ajout) VALUES (?, ?, NOW())")
        ->execute([$user_id, $article_id]);
    $added = true;
}

// Mettre à jour le compteur dans la table articles
$pdo->prepare("
    UPDATE articles
    SET favoris = (SELECT COUNT(*) FROM favoris WHERE article_id = ?)
    WHERE id = ?
")->execute([$article_id, $article_id]);

// Compter le total actuel
$stmt_nf = $pdo->prepare("SELECT COUNT(*) FROM favoris WHERE article_id = ?");
$stmt_nf->execute([$article_id]);
$nb_favoris = (int)$stmt_nf->fetchColumn();

echo json_encode([
    'success'    => true,
    'added'      => $added,
    'nb_favoris' => $nb_favoris,
    'message'    => $added ? 'Ajouté aux favoris' : 'Retiré des favoris',
]);
?>
