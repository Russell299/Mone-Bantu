<?php
// ============================================================
//  MONË BANTU — Réaction à un message (toggle)
//  POST : message_id, emoji
// ============================================================
require_once 'config.php';

header('Content-Type: application/json');

if (!estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'require_login' => true]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit();
}

$user_id    = userId();
$message_id = (int)($_POST['message_id'] ?? 0);
$emoji      = trim($_POST['emoji'] ?? '');

if ($message_id <= 0 || empty($emoji)) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit();
}

// Vérifier que le message existe et que l'user y a accès
$stmt = $pdo->prepare("
    SELECT id FROM messages
    WHERE id = ? AND (expediteur_id = ? OR destinataire_id = ?)
");
$stmt->execute([$message_id, $user_id, $user_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Message introuvable']);
    exit();
}

// Toggle : si même réaction existe → supprimer, sinon remplacer/ajouter
$stmt = $pdo->prepare("SELECT id, emoji FROM message_reactions WHERE message_id = ? AND utilisateur_id = ?");
$stmt->execute([$message_id, $user_id]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['emoji'] === $emoji) {
        // Même emoji → retirer
        $pdo->prepare("DELETE FROM message_reactions WHERE id = ?")->execute([$existing['id']]);
        $action = 'removed';
    } else {
        // Emoji différent → remplacer
        $pdo->prepare("UPDATE message_reactions SET emoji = ? WHERE id = ?")->execute([$emoji, $existing['id']]);
        $action = 'updated';
    }
} else {
    // Nouvelle réaction
    $pdo->prepare("INSERT INTO message_reactions (message_id, utilisateur_id, emoji) VALUES (?, ?, ?)")
        ->execute([$message_id, $user_id, $emoji]);
    $action = 'added';
}

// Retourner les réactions à jour
$stmt = $pdo->prepare("
    SELECT mr.emoji, u.pseudo
    FROM message_reactions mr
    JOIN utilisateurs u ON mr.utilisateur_id = u.id
    WHERE mr.message_id = ?
");
$stmt->execute([$message_id]);
$reactions = $stmt->fetchAll();

echo json_encode([
    'success'   => true,
    'action'    => $action,
    'reactions' => $reactions,
]);
?>
