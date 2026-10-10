<?php
// ============================================================
//  MONË BANTU — Récupérer les messages d'une conversation
//  GET : user_id, since_id (optionnel)
// ============================================================
require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit();
}

$user_id       = userId();
$autre_user_id = (int)($_GET['user_id'] ?? 0);
$since_id      = (int)($_GET['since_id'] ?? 0);

if ($autre_user_id <= 0) {
    echo json_encode([]);
    exit();
}

// Nettoyage des épingles expirées (auto, pas besoin de tâche planifiée)
$pdo->exec("UPDATE messages SET epingle_jusqu = NULL WHERE epingle_jusqu IS NOT NULL AND epingle_jusqu < NOW()");

// Construction de la requête
$where_since = $since_id > 0 ? "AND m.id > :since_id" : "";

$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.contenu,
        m.type,
        m.fichier_url,
        m.fichier_nom,
        m.fichier_taille,
        m.date_envoi,
        m.lu,
        m.epingle_jusqu,
        m.expediteur_id,
        u.pseudo AS expediteur_pseudo
    FROM messages m
    JOIN utilisateurs u ON m.expediteur_id = u.id
    WHERE (
        (m.expediteur_id = :me  AND m.destinataire_id = :him)
     OR (m.expediteur_id = :him2 AND m.destinataire_id = :me2)
    )
    $where_since
    ORDER BY m.date_envoi ASC
");

$params = [
    ':me'   => $user_id,
    ':him'  => $autre_user_id,
    ':him2' => $autre_user_id,
    ':me2'  => $user_id,
];
if ($since_id > 0) $params[':since_id'] = $since_id;

$stmt->execute($params);
$messages = $stmt->fetchAll();

// Marquer les messages reçus comme lus
$pdo->prepare("
    UPDATE messages
    SET lu = 1
    WHERE expediteur_id = ? AND destinataire_id = ? AND lu = 0
")->execute([$autre_user_id, $user_id]);

// Réactions
$reactions_map = [];
if (!empty($messages)) {
    $ids = array_column($messages, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    try {
        $sr = $pdo->prepare("
            SELECT mr.message_id, mr.emoji, u.pseudo
            FROM message_reactions mr
            JOIN utilisateurs u ON mr.utilisateur_id = u.id
            WHERE mr.message_id IN ($ph)
        ");
        $sr->execute($ids);
        foreach ($sr->fetchAll() as $r) {
            $reactions_map[$r['message_id']][] = [
                'emoji'  => $r['emoji'],
                'pseudo' => $r['pseudo'],
            ];
        }
    } catch (Exception $e) {
        // Table pas encore créée — on ignore
    }
}

// Formatage
$result = [];
foreach ($messages as $m) {
    $date   = new DateTime($m['date_envoi']);
    $now    = new DateTime();
    $diff   = $now->diff($date);
    $heure  = $date->format('H:i');
    $affich = $diff->days === 0
        ? $heure
        : ($diff->days === 1 ? "Hier $heure" : $date->format('d/m') . " $heure");

    $result[] = [
        'id'                => (int)$m['id'],
        'contenu'           => (string)($m['contenu'] ?? ''),
        'type'              => (string)($m['type'] ?? 'texte'),
        'fichier_url'       => $m['fichier_url'],
        'fichier_nom'       => $m['fichier_nom'],
        'fichier_taille'    => $m['fichier_taille'] ? (int)$m['fichier_taille'] : null,
        'date_envoi'        => $m['date_envoi'],
        'date_affichage'    => $affich,
        'expediteur_id'     => (int)$m['expediteur_id'],
        'expediteur_pseudo' => $m['expediteur_pseudo'],
        'est_moi'           => ($m['expediteur_id'] == $user_id),
        'lu'                => (bool)$m['lu'],
        'epingle_jusqu'     => $m['epingle_jusqu'],
        'reactions'         => $reactions_map[$m['id']] ?? [],
    ];
}

echo json_encode($result);
?>
