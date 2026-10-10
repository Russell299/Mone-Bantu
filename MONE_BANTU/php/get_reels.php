<?php
// ============================================================
//  MONË BANTU — Liste des Réels actifs (groupés par utilisateur + mode)
//  GET — retourne du JSON
// ============================================================
require_once 'config.php';
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT
        r.id, r.video_url, r.legende, r.vues, r.date_publication, r.date_expiration, r.mode_publication,
        u.id AS user_id, u.pseudo, u.prenom, u.nom, u.photo_profil,
        b.nom_boutique
    FROM reels r
    JOIN utilisateurs u ON u.id = r.utilisateur_id
    LEFT JOIN boutiques b ON b.utilisateur_id = u.id
    WHERE r.date_expiration > NOW()
    ORDER BY r.date_publication DESC
");
$rows = $stmt->fetchAll();

$groupes = [];
foreach ($rows as $row) {
    // Groupé par utilisateur + mode : une boutique peut avoir des Réels
    // "boutique" et "personnel" actifs en même temps, affichés séparément
    $cle = $row['user_id'] . '_' . $row['mode_publication'];
    if (!isset($groupes[$cle])) {
        $est_boutique = $row['mode_publication'] === 'boutique' && !empty($row['nom_boutique']);
        $groupes[$cle] = [
            'user_id'      => (int)$row['user_id'],
            'pseudo'       => $row['pseudo'],
            'nom_affiche'  => $est_boutique ? $row['nom_boutique'] : trim($row['prenom'] . ' ' . $row['nom']),
            'is_boutique'  => $est_boutique,
            'photo_profil' => $row['photo_profil'],
            'reels'        => [],
        ];
    }
    $groupes[$cle]['reels'][] = [
        'id'               => (int)$row['id'],
        'video_url'        => $row['video_url'],
        'legende'          => $row['legende'],
        'vues'             => (int)$row['vues'],
        'date_publication' => $row['date_publication'],
    ];
}

echo json_encode(['success' => true, 'groupes' => array_values($groupes)]);
?>
