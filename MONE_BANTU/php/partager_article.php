<?php
// ============================================================
//  MONË BANTU — Partager une annonce
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

$stmt = $pdo->prepare("SELECT id, titre FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    echo json_encode(['success' => false, 'error' => 'Article introuvable.']);
    exit();
}

$pdo->prepare("UPDATE articles SET partages = partages + 1 WHERE id = ?")->execute([$article_id]);

$pseudo = nomAffiche($pdo, $user_id, 'personnel');
notifierInteresses($pdo, $article_id, 'partage', "$pseudo a partagé une annonce qui vous intéresse.", $user_id);

echo json_encode(['success' => true]);
?>
