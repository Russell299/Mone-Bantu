<?php
// ============================================================
//  MONË BANTU — Définir la langue active
//  GET/POST : langue ('fr' ou 'en')
// ============================================================
require_once 'config.php';

$langue = $_REQUEST['langue'] ?? 'fr';
if (in_array($langue, LANGUES_DISPONIBLES, true)) {
    $_SESSION['langue'] = $langue;
}

$retour = $_SERVER['HTTP_REFERER'] ?? '../index.php';
redirect($retour);
?>
