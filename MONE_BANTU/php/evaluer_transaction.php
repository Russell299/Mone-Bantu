<?php
// ============================================================
//  MONË BANTU — Laisser un avis sur une transaction terminée
//  POST : transaction_id, note (1-5), commentaire (optionnel)
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

$user_id        = userId();
$transaction_id = (int)($_POST['transaction_id'] ?? 0);
$note           = (int)($_POST['note'] ?? 0);
$commentaire    = trim($_POST['commentaire'] ?? '');

if ($note < 1 || $note > 5) {
    echo json_encode(['success' => false, 'error' => 'Note invalide (1 à 5).']);
    exit();
}
if (mb_strlen($commentaire) > 500) {
    echo json_encode(['success' => false, 'error' => 'Le commentaire est trop long (500 caractères max).']);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$transaction_id]);
$t = $stmt->fetch();

if (!$t) {
    echo json_encode(['success' => false, 'error' => 'Transaction introuvable.']);
    exit();
}
if ((int)$t['acheteur_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vous ne pouvez évaluer que vos propres achats.']);
    exit();
}
if ($t['statut'] !== 'terminee') {
    echo json_encode(['success' => false, 'error' => 'Cette transaction doit être terminée pour être évaluée.']);
    exit();
}

// Une seule évaluation par transaction
$stmt = $pdo->prepare("SELECT id FROM evaluations WHERE transaction_id = ? AND evaluateur_id = ?");
$stmt->execute([$transaction_id, $user_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Vous avez déjà évalué cette transaction.']);
    exit();
}

$vendeur_id = (int)$t['vendeur_id'];

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO evaluations (transaction_id, evaluateur_id, evalue_id, note, commentaire, date_evaluation)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$transaction_id, $user_id, $vendeur_id, $note, $commentaire ?: null]);

    // Recalcule la note moyenne du vendeur
    $stmt = $pdo->prepare("SELECT AVG(note) AS moyenne FROM evaluations WHERE evalue_id = ?");
    $stmt->execute([$vendeur_id]);
    $moyenne = round((float)$stmt->fetchColumn(), 2);
    $pdo->prepare("UPDATE utilisateurs SET note_moyenne = ? WHERE id = ?")->execute([$moyenne, $vendeur_id]);

    // Notifie le vendeur qu'il a reçu un avis
    creerNotification($pdo, $vendeur_id, (int)$t['article_id'], 'avis', "Vous avez reçu une nouvelle évaluation ($note/5).");

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => "Erreur lors de l'enregistrement."]);
}
?>
