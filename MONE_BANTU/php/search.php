<?php
// ============================================================
//  MONË BANTU — Recherche rapide (suggestions live)
//  GET : q (query), limit (optionnel)
//  Réponse JSON : tableau d'articles
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$query = trim($_GET['q'] ?? '');
$limit = max(1, min(20, (int)($_GET['limit'] ?? 8)));

// Minimum 2 caractères
if (mb_strlen($query) < 2) {
    echo json_encode([]);
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.titre,
        a.prix,
        a.photos,
        a.etat,
        a.ville,
        a.statut,
        a.date_publication,
        u.pseudo AS vendeur_pseudo
    FROM articles a
    JOIN utilisateurs u ON a.vendeur_id = u.id
    WHERE a.statut = 'disponible'
      AND (
          a.titre      LIKE :q
          OR a.description LIKE :q
          OR a.categorie   LIKE :q
          OR u.pseudo      LIKE :q
          OR a.ville       LIKE :q
      )
    ORDER BY a.date_publication DESC
    LIMIT :limit
");

$stmt->bindValue(':q',     '%' . $query . '%', PDO::PARAM_STR);
$stmt->bindValue(':limit', $limit,              PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();

// Formater les résultats
$result = array_map(function($a) {
    $photos = array_filter(array_map('trim', explode(',', $a['photos'] ?? '')));
    return [
        'id'             => $a['id'],
        'titre'          => $a['titre'],
        'prix'           => $a['prix'],
        'prix_formate'   => formatPrix((float)$a['prix']),
        'etat'           => $a['etat'],
        'ville'          => $a['ville'],
        'vendeur_pseudo' => $a['vendeur_pseudo'],
        'premiere_photo' => !empty($photos) ? trim(reset($photos)) : 'uploads/articles/default.jpg',
        'date_relative'  => tempsRelatif($a['date_publication']),
        'url'            => 'php/article.php?id=' . $a['id'],
    ];
}, $articles);

echo json_encode($result);
?>
