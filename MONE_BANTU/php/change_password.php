<?php
// ============================================================
//  MONË BANTU — Changement de mot de passe
//  POST : old_password, new_password, confirm_password
// ============================================================
require_once 'config.php';

if (!estConnecte()) redirect('../index.php');
exigerLocalisation($pdo, '../');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('profil.php');

$user_id         = userId();
$old_password    = $_POST['old_password']    ?? '';
$new_password    = $_POST['new_password']    ?? '';
$confirm_password= $_POST['confirm_password']?? '';

// Validations
if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['profil_error'] = "Tous les champs sont obligatoires.";
    redirect('profil.php?section=parametres');
}

if ($new_password !== $confirm_password) {
    $_SESSION['profil_error'] = "Les nouveaux mots de passe ne correspondent pas.";
    redirect('profil.php?section=parametres');
}

if (strlen($new_password) < 6) {
    $_SESSION['profil_error'] = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    redirect('profil.php?section=parametres');
}

// Récupérer le mot de passe actuel
$stmt = $pdo->prepare("SELECT password FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($old_password, $user['password'])) {
    $_SESSION['profil_error'] = "L'ancien mot de passe est incorrect.";
    redirect('profil.php?section=parametres');
}

// Mettre à jour
$hashed = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE utilisateurs SET password = ? WHERE id = ?");

if ($stmt->execute([$hashed, $user_id])) {
    $_SESSION['profil_success'] = "Mot de passe modifié avec succès.";
} else {
    $_SESSION['profil_error'] = "Erreur lors du changement de mot de passe.";
}

redirect('profil.php?section=parametres');
?>
