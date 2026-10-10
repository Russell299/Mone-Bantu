<?php
// ============================================================
//  MONË BANTU — Basculer mode boutique / personnel
// ============================================================
require_once 'config.php';

if (!estConnecte()) redirect('login.php');

$user_id = userId();

if (estBoutique($pdo, $user_id)) {
    $_SESSION['mode_actif'] = (modeActif() === 'boutique') ? 'personnel' : 'boutique';
}

$retour = $_SERVER['HTTP_REFERER'] ?? 'profil.php';
redirect($retour);
?>
