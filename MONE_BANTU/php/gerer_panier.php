<?php
// ============================================================
//  MONË BANTU — Ajouter/retirer un article du panier (BOUTIQUE)
//  POST : article_id, action ('ajouter' ou 'retirer')
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
$article_id = (int)($_POST['article_id'] ?? 0);
$action     = $_POST['action'] ?? 'ajouter';

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article introuvable.']);
    exit();
}

if ($action === 'retirer') {
    $pdo->prepare("DELETE FROM paniers WHERE utilisateur_id = ? AND article_id = ?")
        ->execute([$user_id, $article_id]);
    echo json_encode(['success' => true, 'action' => 'retire']);
    exit();
}

// ---- Ajout ----
if ($article['statut'] !== 'disponible') {
    echo json_encode(['success' => false, 'error' => 'Cet article n\'est plus disponible.']);
    exit();
}
if ((int)$article['vendeur_id'] === $user_id) {
    echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas acheter votre propre article.']);
    exit();
}
if ($article['mode_vente'] !== 'boutique') {
    echo json_encode(['success' => false, 'error' => 'Cet article vient d\'un particulier — utilisez l\'achat direct.']);
    exit();
}

$stmt = $pdo->prepare("INSERT IGNORE INTO paniers (utilisateur_id, article_id) VALUES (?, ?)");
$stmt->execute([$user_id, $article_id]);

$compte = $pdo->prepare("SELECT COUNT(*) FROM paniers WHERE utilisateur_id = ?");
$compte->execute([$user_id]);

echo json_encode(['success' => true, 'action' => 'ajoute', 'total_panier' => (int)$compte->fetchColumn()]);
?>
