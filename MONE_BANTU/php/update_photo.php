<?php
// ============================================================
//  MONË BANTU — Mise à jour photo de profil / couverture
//  POST : type ('profil' ou 'couverture'), fichier image
// ============================================================
require_once 'config.php';

if (!estConnecte()) redirect('../index.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('profil.php');

$user_id = userId();
$type    = $_POST['type'] ?? '';

if (!in_array($type, ['profil', 'couverture'], true)) {
    $_SESSION['profil_error'] = "Requête invalide.";
    redirect('profil.php?section=parametres');
}

$champ_bdd  = $type === 'profil' ? 'photo_profil' : 'photo_couverture';
$sous_dossier = $type === 'profil' ? 'profils' : 'couvertures';
$file_field = 'photo_' . $type;

if (empty($_FILES[$file_field]['tmp_name']) || $_FILES[$file_field]['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['profil_error'] = "Aucune image sélectionnée.";
    redirect('profil.php?section=parametres');
}

$types_autorises = ['image/jpeg', 'image/png', 'image/webp'];
$max_size         = 5 * 1024 * 1024; // 5 Mo

$tmp      = $_FILES[$file_field]['tmp_name'];
$size     = $_FILES[$file_field]['size'];
$mimetype = mime_content_type($tmp);

if ($size > $max_size) {
    $_SESSION['profil_error'] = "L'image ne doit pas dépasser 5 Mo.";
    redirect('profil.php?section=parametres');
}

if (!in_array($mimetype, $types_autorises, true)) {
    $_SESSION['profil_error'] = "Format non autorisé. Utilisez JPEG, PNG ou WebP.";
    redirect('profil.php?section=parametres');
}

$upload_dir = '../uploads/' . $sous_dossier . '/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename_jpg = $type . '_' . $user_id . '_' . uniqid() . '.jpg';
$dest_jpg     = $upload_dir . $filename_jpg;
$dimension_max = $type === 'profil' ? 600 : 1600; // photo de profil plus petite qu'une couverture

if (compresserImage($tmp, $dest_jpg, $dimension_max)) {
    $filename = $filename_jpg;
    $dest     = $dest_jpg;
} else {
    $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext      = $ext_map[$mimetype] ?? 'jpg';
    $filename = $type . '_' . $user_id . '_' . uniqid() . '.' . $ext;
    $dest     = $upload_dir . $filename;
    move_uploaded_file($tmp, $dest);
}

if (file_exists($dest)) {
    // Supprime l'ancienne image si elle existe
    $stmt = $pdo->prepare("SELECT $champ_bdd FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $ancienne = $stmt->fetchColumn();
    if (!empty($ancienne) && file_exists('../' . $ancienne)) {
        @unlink('../' . $ancienne);
    }

    $chemin = 'uploads/' . $sous_dossier . '/' . $filename;
    $stmt   = $pdo->prepare("UPDATE utilisateurs SET $champ_bdd = ? WHERE id = ?");

    if ($stmt->execute([$chemin, $user_id])) {
        $_SESSION['profil_success'] = $type === 'profil'
            ? "Photo de profil mise à jour."
            : "Photo de couverture mise à jour.";
    } else {
        $_SESSION['profil_error'] = "Erreur lors de l'enregistrement.";
    }
} else {
    $_SESSION['profil_error'] = "Erreur lors de l'envoi du fichier.";
}

redirect('profil.php?section=parametres');
?>
