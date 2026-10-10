<?php
// ============================================================
//  MONË BANTU — Mise à jour du profil
//  POST : prenom, nom, email, telephone, ville
// ============================================================
require_once 'config.php';

if (!estConnecte()) redirect('../index.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('profil.php');

$user_id   = userId();

// Verrou de 7 mois sur les informations personnelles
$statut = statutModificationProfil($pdo, $user_id);
if (!$statut['autorise']) {
    $_SESSION['profil_error'] = "Vos informations personnelles ne sont modifiables qu'à partir du "
        . $statut['date_limite']->format('d/m/Y') . ' (7 mois après inscription).';
    redirect('profil.php?section=parametres');
}

$prenom    = trim($_POST['prenom']    ?? '');
$nom       = trim($_POST['nom']       ?? '');
$email     = trim($_POST['email']     ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$ville     = trim($_POST['ville']     ?? '');

// Validations
if (empty($prenom) || empty($nom) || empty($email)) {
    $_SESSION['profil_error'] = "Prénom, nom et email sont obligatoires.";
    redirect('profil.php?section=parametres');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['profil_error'] = "Adresse email invalide.";
    redirect('profil.php?section=parametres');
}

// Vérifier que l'email n'est pas déjà pris par un autre utilisateur
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->fetch()) {
    $_SESSION['profil_error'] = "Cette adresse email est déjà utilisée par un autre compte.";
    redirect('profil.php?section=parametres');
}

// Mettre à jour
$stmt = $pdo->prepare("
    UPDATE utilisateurs
    SET prenom = ?, nom = ?, email = ?, telephone = ?, ville = ?
    WHERE id = ?
");

if ($stmt->execute([$prenom, $nom, $email, $telephone, $ville, $user_id])) {
    $_SESSION['user_pseudo'] = $_SESSION['user_pseudo']; // inchangé
    $_SESSION['user_nom']    = "$prenom $nom";
    $_SESSION['profil_success'] = "Votre profil a été mis à jour avec succès.";
} else {
    $_SESSION['profil_error'] = "Erreur lors de la mise à jour. Réessayez.";
}

redirect('profil.php?section=parametres');
?>
