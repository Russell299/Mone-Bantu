<?php
// ============================================================
//  MONË BANTU — Vérifier les nouveaux messages (badge nav)
//  GET : (aucun paramètre)
//  Réponse JSON : { new_messages, messages[] }
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (!estConnecte()) {
    echo json_encode(['new_messages' => 0]);
    exit();
}

$user_id = userId();

// Nombre total de messages non lus
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nb
    FROM messages
    WHERE destinataire_id = ? AND lu = FALSE
");
$stmt->execute([$user_id]);
$nb_non_lus = (int)$stmt->fetchColumn();

// Aperçu des derniers messages non lus (pour notifications)
$stmt = $pdo->prepare("
    SELECT m.id, m.contenu, m.date_envoi, u.pseudo AS expediteur_pseudo
    FROM messages m
    JOIN utilisateurs u ON m.expediteur_id = u.id
    WHERE m.destinataire_id = ? AND m.lu = FALSE
    ORDER BY m.date_envoi DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$apercu = $stmt->fetchAll();

echo json_encode([
    'new_messages' => $nb_non_lus,
    'messages'     => $apercu,
    'timestamp'    => time(),
]);
?>
