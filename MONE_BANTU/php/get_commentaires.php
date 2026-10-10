<?php
// ============================================================
//  MONË BANTU — Récupérer les commentaires d'un article
//  GET : article_id
//  Réponse JSON : tableau de commentaires
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');

$article_id = (int)($_GET['article_id'] ?? 0);

if ($article_id <= 0) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.commentaire,
        c.date_publication,
        u.pseudo,
        u.photo_profil
    FROM commentaires c
    JOIN utilisateurs u ON c.utilisateur_id = u.id
    WHERE c.article_id = ?
    ORDER BY c.date_publication ASC
");
$stmt->execute([$article_id]);
$commentaires = $stmt->fetchAll();

// Formater les données pour le front
$result = array_map(function($c) {
    return [
        'id'          => $c['id'],
        'pseudo'      => $c['pseudo'],
        'initiales'   => initiales($c['pseudo']),
        'commentaire' => $c['commentaire'],
        'date'        => tempsRelatif($c['date_publication']),
        'date_iso'    => $c['date_publication'],
    ];
}, $commentaires);

echo json_encode($result);
?>
