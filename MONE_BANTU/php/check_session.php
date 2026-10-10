<?php
// ============================================================
//  MONË BANTU — Vérification de session (API JSON)
//  Appelé par index.html pour afficher le bon menu
// ============================================================

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

if (estConnecte()) {
    $stmt = $pdo->prepare("SELECT id, pseudo, photo_profil FROM utilisateurs WHERE id = ?");
    $stmt->execute([userId()]);
    $user = $stmt->fetch();

    // Compter les messages non lus
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as nb FROM messages WHERE destinataire_id = ? AND lu = FALSE");
    $stmt2->execute([userId()]);
    $nonLus = (int)$stmt2->fetchColumn();

    echo json_encode([
        'logged_in'    => true,
        'user_id'      => $user['id'],
        'pseudo'       => $user['pseudo'],
        'photo_profil' => $user['photo_profil'] ?? null,
        'messages_non_lus' => $nonLus,
    ]);
} else {
    echo json_encode([
        'logged_in' => false,
        'pseudo'    => null,
    ]);
}
?>
