<?php
// ============================================================
//  MONË BANTU — Appliquer une sanction (admin uniquement)
//  POST : utilisateur_id, type, motif, duree_jours (si suspension), retour
// ============================================================
require_once 'config.php';

if (!estConnecte() || !estAdmin($pdo, userId())) {
    redirect('../index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin_utilisateurs.php');
}

$admin_id       = userId();
$utilisateur_id = (int)($_POST['utilisateur_id'] ?? 0);
$type           = $_POST['type'] ?? '';
$motif          = trim($_POST['motif'] ?? '');
$duree_jours    = !empty($_POST['duree_jours']) ? (int)$_POST['duree_jours'] : null;
$retour         = $_POST['retour'] ?? 'admin_utilisateurs.php';

if (!in_array($type, ['avertissement', 'suspension', 'bannissement', 'levee'], true) || empty($motif)) {
    redirect($retour);
}

$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE id = ?");
$stmt->execute([$utilisateur_id]);
if (!$stmt->fetch()) {
    redirect($retour);
}

// Empêcher un admin de se sanctionner lui-même
if ($utilisateur_id === $admin_id) {
    redirect($retour);
}

$pdo->beginTransaction();
try {
    $pdo->prepare("
        INSERT INTO sanctions (utilisateur_id, admin_id, type, motif, duree_jours, date_creation)
        VALUES (?, ?, ?, ?, ?, NOW())
    ")->execute([$utilisateur_id, $admin_id, $type, $motif, $type === 'suspension' ? $duree_jours : null]);

    switch ($type) {
        case 'avertissement':
            // Le compte reste actif, juste un avertissement enregistré + notifié
            break;
        case 'suspension':
            $jours = $duree_jours ?: 1;
            $pdo->prepare("
                UPDATE utilisateurs SET statut_compte = 'suspendu', suspension_fin = DATE_ADD(NOW(), INTERVAL ? DAY)
                WHERE id = ?
            ")->execute([$jours, $utilisateur_id]);
            break;
        case 'bannissement':
            $pdo->prepare("UPDATE utilisateurs SET statut_compte = 'banni', suspension_fin = NULL WHERE id = ?")
                ->execute([$utilisateur_id]);
            break;
        case 'levee':
            $pdo->prepare("UPDATE utilisateurs SET statut_compte = 'actif', suspension_fin = NULL WHERE id = ?")
                ->execute([$utilisateur_id]);
            break;
    }

    // Notifier l'utilisateur concerné (sauf en cas de levée, moins critique)
    if ($type !== 'levee') {
        $libelles = [
            'avertissement' => 'Vous avez reçu un avertissement de la part de Monë Bantu',
            'suspension'    => 'Votre compte a été suspendu temporairement',
            'bannissement'  => 'Votre compte a été banni définitivement',
        ];
        creerNotification($pdo, $utilisateur_id, null, 'sanction', $libelles[$type] . ' — motif : ' . $motif);
    }

    // Marquer les signalements en attente contre cet utilisateur comme traités
    if ($type !== 'levee') {
        $pdo->prepare("
            UPDATE signalements SET statut = 'traite', admin_id = ?, date_traitement = NOW()
            WHERE utilisateur_signale_id = ? AND statut = 'en_attente'
        ")->execute([$admin_id, $utilisateur_id]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}

redirect($retour);
?>
