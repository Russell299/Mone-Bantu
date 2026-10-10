<?php
// ============================================================
//  MONË BANTU — Liker / Unliker un article
//  POST : article_id
//  Réponse JSON : { success, action, nb_likes }
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Connexion requise', 'require_login' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$article_id    = (int)($_POST['article_id'] ?? 0);
$utilisateur_id = userId();

if ($article_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Article invalide']);
    exit();
}

// Vérifier que l'article existe
$stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Article introuvable']);
    exit();
}

// Vérifier si déjà liké
$stmt = $pdo->prepare("SELECT id FROM likes WHERE article_id = ? AND utilisateur_id = ?");
$stmt->execute([$article_id, $utilisateur_id]);
$like = $stmt->fetch();

if ($like) {
    // Retirer le like
    $pdo->prepare("DELETE FROM likes WHERE id = ?")->execute([$like['id']]);
    $action = 'unliked';
} else {
    // Ajouter le like
    $pdo->prepare("INSERT INTO likes (article_id, utilisateur_id) VALUES (?, ?)")
        ->execute([$article_id, $utilisateur_id]);
    $action = 'liked';

    // Notifier les intéressés (sauf celui qui vient de liker)
    $pseudo_liker = nomAffiche($pdo, $utilisateur_id, 'personnel');
    notifierInteresses($pdo, $article_id, 'like', "$pseudo_liker a aimé une annonce qui vous intéresse.", $utilisateur_id);
}

// Compter le total des likes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE article_id = ?");
$stmt->execute([$article_id]);
$nb_likes = (int)$stmt->fetchColumn();

echo json_encode([
    'success'  => true,
    'action'   => $action,
    'nb_likes' => $nb_likes,
]);
?>
