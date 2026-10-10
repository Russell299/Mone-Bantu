<?php
// ============================================================
//  MONË BANTU — Ajouter un commentaire
//  POST : article_id, commentaire
//  Réponse JSON : { success, commentaire:{...} }
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

$article_id     = (int)($_POST['article_id'] ?? 0);
$commentaire    = trim($_POST['commentaire'] ?? '');
$utilisateur_id = userId();

// Validations
if ($article_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Article invalide']);
    exit();
}
if (empty($commentaire)) {
    echo json_encode(['success' => false, 'message' => 'Le commentaire ne peut pas être vide']);
    exit();
}
if (mb_strlen($commentaire) > 500) {
    echo json_encode(['success' => false, 'message' => 'Commentaire trop long (max 500 caractères)']);
    exit();
}

// Vérifier que l'article existe
$stmt = $pdo->prepare("SELECT id FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Article introuvable']);
    exit();
}

// Insérer le commentaire
$stmt = $pdo->prepare("
    INSERT INTO commentaires (article_id, utilisateur_id, commentaire, date_publication)
    VALUES (?, ?, ?, NOW())
");

if ($stmt->execute([$article_id, $utilisateur_id, $commentaire])) {
    $comment_id = $pdo->lastInsertId();

    // Notifier les intéressés (sauf l'auteur du commentaire)
    $pseudo_auteur = nomAffiche($pdo, $utilisateur_id, 'personnel');
    notifierInteresses($pdo, $article_id, 'commentaire', "$pseudo_auteur a commenté une annonce qui vous intéresse.", $utilisateur_id);

    // Récupérer le commentaire avec les infos utilisateur
    $stmt = $pdo->prepare("
        SELECT c.id, c.commentaire, c.date_publication, u.pseudo, u.photo_profil
        FROM commentaires c
        JOIN utilisateurs u ON c.utilisateur_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $new_comment = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'commentaire' => [
            'id'          => $new_comment['id'],
            'pseudo'      => esc($new_comment['pseudo']),
            'initiales'   => initiales($new_comment['pseudo']),
            'commentaire' => esc($new_comment['commentaire']),
            'date'        => tempsRelatif($new_comment['date_publication']),
            'date_iso'    => $new_comment['date_publication'],
        ],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'ajout du commentaire']);
}
?>
