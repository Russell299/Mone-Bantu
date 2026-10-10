<?php
// ============================================================
//  MONË BANTU — Supprimer une annonce
//  POST : article_id
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

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article introuvable.']);
    exit();
}
if ((int)$article['vendeur_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous ne pouvez supprimer que vos propres annonces.']);
    exit();
}

// Notifier les intéressés AVANT la suppression (l'article_id doit encore exister pour la requête)
notifierInteresses($pdo, $article_id, 'suppression', 'Une annonce "' . $article['titre'] . '" qui vous intéressait a été supprimée par son vendeur.', $user_id);

$stmt = $pdo->prepare("DELETE FROM articles WHERE id = ? AND vendeur_id = ?");
if ($stmt->execute([$article_id, $user_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression.']);
}
?>
