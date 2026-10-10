<?php
// ============================================================
//  MONË BANTU — Récupérer les articles (API JSON)
//  GET : limit, categorie, search, sort
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

$limit     = max(1, min(50, (int)($_GET['limit'] ?? 12)));
$categorie = trim($_GET['categorie'] ?? '');
$search    = trim($_GET['search'] ?? '');
$sort      = $_GET['sort'] ?? 'recent';

// Construction de la requête dynamique
$where  = ["a.statut = 'disponible'"];
$params = [];

if ($categorie !== '') {
    $where[]  = "a.categorie = ?";
    $params[] = $categorie;
}
if ($search !== '') {
    $where[]  = "(a.titre LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$orderBy = match($sort) {
    'prix_asc'  => 'a.prix ASC',
    'prix_desc' => 'a.prix DESC',
    'vues'      => 'a.vues DESC',
    default     => 'a.date_publication DESC',
};

$whereClause = implode(' AND ', $where);

$sql = "
    SELECT
        a.id, a.titre, a.prix, a.photos, a.etat, a.categorie,
        a.ville, a.vues, a.date_publication, a.statut,
        u.pseudo AS vendeur_pseudo, u.ville AS vendeur_ville,
        (SELECT COUNT(*) FROM likes WHERE article_id = a.id) AS nb_likes
    FROM articles a
    JOIN utilisateurs u ON a.vendeur_id = u.id
    WHERE $whereClause
    ORDER BY $orderBy
    LIMIT $limit
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Formater les photos
foreach ($articles as &$a) {
    $photos = array_filter(explode(',', $a['photos'] ?? ''));
    $a['photo_principale'] = !empty($photos) ? trim($photos[0]) : 'uploads/articles/default.jpg';
    $a['prix_formate'] = formatPrix((float)$a['prix']);
    $a['date_relative'] = tempsRelatif($a['date_publication']);
}

echo json_encode($articles);
?>
