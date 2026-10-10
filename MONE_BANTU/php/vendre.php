<?php
// ============================================================
//  MONË BANTU — Vendre (publier / modifier une annonce)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

if (!function_exists('estConnecte') || !estConnecte()) {
    redirect($base_path . 'php/login.php');
}

$error   = '';
$success = '';
$article_publie_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie   = trim($_POST['categorie']   ?? '');
    $etat        = trim($_POST['etat']        ?? 'Bon état');
    $prix        = (int)($_POST['prix']       ?? 0);
    $ville       = trim($_POST['ville']       ?? '');

    if (empty($titre)) {
        $error = "Le titre est obligatoire.";
    } elseif (mb_strlen($titre) < 5) {
        $error = "Le titre doit contenir au moins 5 caractères.";
    } elseif ($prix <= 0) {
        $error = "Le prix doit être supérieur à 0.";
    } elseif (isset($etats_article) && !in_array($etat, $etats_article)) {
        $error = "État invalide.";
    } else {
        $photos     = [];
        $upload_dir = dirname(__DIR__) . '/uploads/articles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $types_autorises = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $max_size        = 5 * 1024 * 1024;
        $max_photos      = 5;

        if (!empty($_FILES['photos']['tmp_name'][0])) {
            foreach ($_FILES['photos']['tmp_name'] as $i => $tmp) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                if (count($photos) >= $max_photos) break;

                $size     = $_FILES['photos']['size'][$i];
                $mimetype = mime_content_type($tmp);

                if ($size > $max_size) { $error = "Chaque photo ne doit pas dépasser 5 Mo."; break; }
                if (!in_array($mimetype, $types_autorises)) { $error = "Format non autorisé."; break; }

                $filename_jpg = 'article_' . userId() . '_' . uniqid() . '.jpg';
                $dest_jpg     = $upload_dir . $filename_jpg;

                if (function_exists('compresserImage') && compresserImage($tmp, $dest_jpg)) {
                    $photos[] = 'uploads/articles/' . $filename_jpg;
                } else {
                    $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                    $ext      = $ext_map[$mimetype] ?? 'jpg';
                    $filename = 'article_' . userId() . '_' . uniqid() . '.' . $ext;
                    $dest     = $upload_dir . $filename;
                    if (move_uploaded_file($tmp, $dest)) $photos[] = 'uploads/articles/' . $filename;
                }
            }
        }

        if (empty($error)) {
            $mode_vente = 'personnel';
            if (function_exists('modeActif') && function_exists('estBoutique')) {
                if (modeActif() === 'boutique' && estBoutique($pdo, userId())) {
                    $mode_vente = 'boutique';
                }
            }

            $edit_id = (int)($_POST['edit_id'] ?? 0);

            if ($edit_id > 0) {
                $stmtCheck = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND vendeur_id = ?");
                $stmtCheck->execute([$edit_id, userId()]);
                $article_existant = $stmtCheck->fetch();

                if (!$article_existant) {
                    $error = "Cette annonce n'existe pas ou ne vous appartient pas.";
                } else {
                    $photos_finales = !empty($photos) ? implode(',', $photos) : $article_existant['photos'];
                    $stmt = $pdo->prepare("
                        UPDATE articles
                        SET titre = ?, description = ?, categorie = ?, etat = ?, prix = ?, ville = ?, photos = ?
                        WHERE id = ? AND vendeur_id = ?
                    ");
                    if ($stmt->execute([$titre, $description, $categorie, $etat, $prix, $ville, $photos_finales, $edit_id, userId()])) {
                        if (function_exists('notifierInteresses')) {
                            notifierInteresses($pdo, $edit_id, 'modification', 'Une annonce "' . $titre . '" qui vous intéresse a été modifiée.', userId());
                        }
                        $article_publie_id = $edit_id;
                        $success = "Votre annonce a été mise à jour avec succès !";
                        $_POST = [];
                    } else {
                        $error = "Erreur lors de la mise à jour.";
                    }
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO articles (vendeur_id, titre, description, categorie, etat, prix, ville, photos, mode_vente, statut, date_publication)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible', NOW())
                ");
                if ($stmt->execute([userId(), $titre, $description, $categorie, $etat, $prix, $ville, implode(',', $photos), $mode_vente])) {
                    $article_publie_id = (int)$pdo->lastInsertId();
                    $success = "Votre annonce a été publiée avec succès !";
                    $_POST = [];
                } else {
                    $error = "Erreur lors de la publication.";
                }
            }
        }
    }
}

// ---- Mode édition ----
$article_edit = null;
$edit_id_get  = (int)($_GET['edit'] ?? 0);
if ($edit_id_get > 0 && empty($success)) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND vendeur_id = ?");
    $stmt->execute([$edit_id_get, userId()]);
    $article_edit = $stmt->fetch();
}

// ---- Valeurs préparées pour le formulaire ----
$val_titre = $_POST['titre']       ?? ($article_edit['titre']       ?? '');
$val_desc  = $_POST['description'] ?? ($article_edit['description'] ?? '');
$val_cat   = $_POST['categorie']   ?? ($article_edit['categorie']   ?? '');
$val_etat  = $_POST['etat']        ?? ($article_edit['etat']        ?? '');
$val_ville = $_POST['ville']       ?? ($article_edit['ville']       ?? '');
$val_prix  = isset($_POST['prix']) ? (int)$_POST['prix'] : (int)($article_edit['prix'] ?? 0);

$esc_titre = function_exists('esc') ? esc($val_titre) : htmlspecialchars($val_titre);
$esc_desc  = function_exists('esc') ? esc($val_desc)  : htmlspecialchars($val_desc);
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $article_edit ? tt('vendre_titre_modifier', 'Modifier mon annonce') : tt('vendre_titre_creer', 'Publier une annonce'); ?> — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar .container { display: flex; align-items: center; gap: 15px; flex-wrap: nowrap; }
        .navbar .logo { flex-shrink: 0; }
        .navbar .search-bar { flex: 1; max-width: 420px; margin: 0 auto; }
        .nav-toggle-btn {
            display: flex; align-items: center; gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border: none; border-radius: 50px;
            font-family: 'Poppins', sans-serif; font-size: 0.9rem;
            font-weight: 600; cursor: pointer; flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .nav-toggle-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(233,69,96,0.4); }
        .nav-panel {
            position: fixed; top: 0; right: -340px;
            width: 320px; max-width: 85vw; height: 100vh;
            background: white; box-shadow: -4px 0 24px rgba(0,0,0,0.15);
            z-index: 99999; display: flex; flex-direction: column;
            transition: right 0.3s ease; overflow-y: auto;
        }
        .nav-panel.open { right: 0; }
        .nav-panel-header {
            padding: 18px 20px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; background: white; z-index: 2;
        }
        .nav-panel-header h3 { font-size: 1.05rem; color: var(--text); margin: 0; }
        .nav-panel-close {
            background: none; border: none; font-size: 1.3rem;
            color: var(--text-muted); cursor: pointer;
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s, transform 0.2s;
        }
        .nav-panel-close:hover { background: #f0f0f0; transform: rotate(90deg); }
        .nav-panel-user {
            padding: 14px 20px; background: #f9f9fc;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 12px;
        }
        .nav-panel-user-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 700; flex-shrink: 0;
        }
        .nav-panel-user-info strong { display: block; font-size: 0.92rem; }
        .nav-panel-user-info small { color: var(--text-muted); font-size: 0.78rem; }
        .nav-panel-list { list-style: none; padding: 8px 0; margin: 0; flex: 1; }
        .nav-panel-list a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: var(--text);
            text-decoration: none; font-size: 0.92rem;
            transition: background 0.15s, color 0.15s;
        }
        .nav-panel-list a:hover { background: rgba(233,69,96,0.06); color: var(--primary); }
        .nav-panel-list a.active { background: rgba(233,69,96,0.1); color: var(--primary); font-weight: 600; }
        .nav-panel-list a i { width: 22px; text-align: center; font-size: 1rem; color: var(--primary); flex-shrink: 0; }
        .nav-panel-langue {
            padding: 14px 20px; border-top: 1px solid var(--border);
            display: flex; gap: 10px; align-items: center; font-size: 0.85rem;
        }
        .nav-panel-langue span { color: var(--text-muted); }
        .nav-panel-langue a {
            padding: 5px 12px; border-radius: 50px; text-decoration: none;
            color: var(--text-muted); font-weight: 600; transition: all 0.2s;
        }
        .nav-panel-langue a.actif { background: rgba(233,69,96,0.12); color: var(--primary); }
        .nav-panel-logout { padding: 14px 20px; border-top: 1px solid var(--border); }
        .nav-panel-logout a {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 11px; background: #f0f0f0; color: #c0392b;
            border-radius: 8px; text-decoration: none;
            font-weight: 600; font-size: 0.9rem; transition: background 0.2s;
        }
        .nav-panel-logout a:hover { background: #ffe5e5; }
        .nav-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            z-index: 99998; opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .nav-overlay.open { opacity: 1; pointer-events: auto; }
        .nav-menu { display: none !important; }
        .hamburger { display: none !important; }
        @media (max-width: 700px) {
            .nav-toggle-btn span { display: none; }
            .nav-toggle-btn { padding: 10px 12px; }
            .nav-panel { width: 90vw; right: -100vw; }
        }
        .upload-zone {
            border: 2px dashed var(--border); border-radius: var(--radius-md);
            padding: 30px 20px; text-align: center; cursor: pointer;
            transition: all var(--transition); background: var(--light-bg);
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--primary); background: rgba(233,69,96,0.04); }
        .upload-zone i { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; display: block; }
        .upload-zone p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .upload-zone input[type="file"] { display: none; }
        #preview { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .preview-item {
            position: relative; width: 100px; height: 100px;
            border-radius: var(--radius-sm); overflow: hidden;
            border: 2px solid var(--border);
        }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .preview-item .remove-photo {
            position: absolute; top: 3px; right: 3px;
            background: rgba(233,69,96,0.9); color: white;
            border: none; border-radius: 50%;
            width: 22px; height: 22px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; cursor: pointer;
        }
        .preview-item.principale::after {
            content: 'Principale'; position: absolute; bottom: 0; left: 0; right: 0;
            background: rgba(233,69,96,0.85); color: white;
            font-size: 0.65rem; text-align: center; padding: 2px;
        }
        .prix-input-wrap { position: relative; }
        .prix-input-wrap input { padding-right: 55px; }
        .prix-suffix {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            color: #aaa; font-size: 0.85rem; pointer-events: none;
        }
        .success-card {
            background: white; border-radius: var(--radius-md);
            padding: 45px 30px; text-align: center;
            box-shadow: var(--shadow-md); max-width: 500px; margin: 40px auto;
        }
        .success-card i { font-size: 4rem; color: #00a86b; display: block; margin-bottom: 20px; }
        .success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="<?php echo $base_path; ?>index.php"><h1>MONË <span>BANTU</span></h1></a>
            </div>
            <div class="search-bar">
                <form action="acheter.php" method="GET">
                    <input type="text" name="search" placeholder="<?php echo tt('acheter_search_placeholder', 'Rechercher un article...'); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <button class="nav-toggle-btn" onclick="ouvrirNavPanel()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title" style="margin:25px 0 0;">
            <i class="fas fa-plus-circle" style="color:var(--primary)"></i>
            <?php echo $article_edit ? tt('vendre_titre_modifier', 'Modifier mon annonce') : tt('vendre_titre_creer', 'Publier une annonce'); ?>
        </h1>

        <?php if ($success && $article_publie_id): ?>
        <div class="success-card">
            <i class="fas fa-check-circle"></i>
            <h2><?php echo tt('vendre_succes_titre', 'Annonce publiée !'); ?></h2>
            <p><?php echo function_exists('esc') ? esc($success) : htmlspecialchars($success); ?><br><?php echo tt('vendre_succes_suite', 'Votre article est maintenant visible par tous les membres.'); ?></p>
            <div class="success-actions">
                <a href="article.php?id=<?php echo $article_publie_id; ?>" class="btn-detail" style="padding:11px 25px;">
                    <i class="fas fa-eye"></i> <?php echo tt('vendre_voir_annonce', 'Voir mon annonce'); ?>
                </a>
                <a href="vendre.php" class="btn-detail" style="padding:11px 25px;background:var(--secondary);">
                    <i class="fas fa-plus"></i> <?php echo tt('vendre_publier_autre', 'Nouvelle annonce'); ?>
                </a>
                <a href="profil.php?section=mes-articles" class="btn-detail" style="padding:11px 25px;background:#555;">
                    <i class="fas fa-list"></i> <?php echo tt('vendre_mes_annonces', 'Mes annonces'); ?>
                </a>
            </div>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
            <div class="alert alert-error" style="max-width:750px;margin:15px auto;">
                <i class="fas fa-exclamation-circle"></i> <?php echo function_exists('esc') ? esc($error) : htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="vendre-form" id="venteForm">
            <?php if ($article_edit): ?>
                <input type="hidden" name="edit_id" value="<?php echo (int)$article_edit['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="titre"><?php echo tt('vendre_label_titre', "Titre de l'annonce"); ?> <span style="color:var(--primary)">*</span></label>
                <input type="text" id="titre" name="titre" required minlength="5" maxlength="200"
                       placeholder="<?php echo tt('vendre_titre_placeholder', 'Ex : Samsung Galaxy A12'); ?>"
                       value="<?php echo $esc_titre; ?>">
                <small id="titre-count">0 / 200 caractères</small>
            </div>

            <div class="form-group">
                <label for="description"><?php echo tt('vendre_label_description', 'Description'); ?></label>
                <textarea id="description" name="description" rows="5" maxlength="2000"
                          placeholder="<?php echo tt('vendre_description_placeholder', 'Décrivez votre article...'); ?>"><?php echo $esc_desc; ?></textarea>
                <small id="desc-count">0 / 2000 caractères</small>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                <div class="form-group">
                    <label for="categorie"><?php echo tt('vendre_label_categorie', 'Catégorie'); ?></label>
                    <select id="categorie" name="categorie">
                        <option value=""><?php echo tt('vendre_selectionner', 'Sélectionner…'); ?></option>
                        <?php if (isset($categories)): foreach (array_keys($categories) as $cat): ?>
                            <option value="<?php echo function_exists('esc') ? esc($cat) : htmlspecialchars($cat); ?>" <?php echo $val_cat === $cat ? 'selected' : ''; ?>>
                                <?php echo function_exists('esc') ? esc($cat) : htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="etat"><?php echo tt('vendre_label_etat', 'État'); ?> <span style="color:var(--primary)">*</span></label>
                    <select id="etat" name="etat" required>
                        <?php if (isset($etats_article)): foreach ($etats_article as $e): ?>
                            <option value="<?php echo function_exists('esc') ? esc($e) : htmlspecialchars($e); ?>" <?php echo $val_etat === $e ? 'selected' : ''; ?>>
                                <?php echo function_exists('esc') ? esc($e) : htmlspecialchars($e); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                <div class="form-group">
                    <label for="prix"><?php echo tt('vendre_label_prix', 'Prix'); ?> <span style="color:var(--primary)">*</span></label>
                    <div class="prix-input-wrap">
                        <input type="number" id="prix" name="prix" required min="1" placeholder="5000"
                               value="<?php echo $val_prix ?: ''; ?>">
                        <span class="prix-suffix">FCFA</span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="ville"><?php echo tt('vendre_label_ville', 'Ville'); ?></label>
                    <select id="ville" name="ville">
                        <option value=""><?php echo tt('vendre_selectionner', 'Sélectionner…'); ?></option>
                        <?php if (isset($villes)): foreach ($villes as $v): ?>
                            <option value="<?php echo function_exists('esc') ? esc($v) : htmlspecialchars($v); ?>" <?php echo $val_ville === $v ? 'selected' : ''; ?>>
                                <?php echo function_exists('esc') ? esc($v) : htmlspecialchars($v); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo tt('vendre_label_photos', 'Photos'); ?> <small style="color:#aaa;">(max 5 — JPG, PNG, WebP — 5 Mo/photo)</small></label>
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('photos').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><?php echo tt('vendre_photos_zone', 'Cliquez ou glissez vos photos ici'); ?></p>
                    <p style="margin-top:5px;font-size:0.8rem;"><?php echo tt('vendre_photos_principale', 'La première photo sera la photo principale'); ?></p>
                    <input type="file" id="photos" name="photos[]" multiple accept="image/*">
                </div>
                <div id="preview"></div>
                <small id="photo-count" style="color:var(--primary);display:none;"></small>
            </div>

            <div class="info-importante">
                <i class="fas fa-info-circle"></i>
                <p><?php echo tt('vendre_info_paiement', "Le paiement passe par Monë Bantu : des frais de service s'appliquent à l'acheteur, et le vendeur est réglé une fois la transaction confirmée."); ?></p>
            </div>

            <button type="submit" class="btn-primary-full" id="submitBtn">
                <i class="fas fa-paper-plane"></i> <?php echo $article_edit ? tt('vendre_btn_modifier', 'Mettre à jour') : tt('vendre_btn_publier', "Publier l'annonce"); ?>
            </button>
        </form>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>MONË BANTU</h3>
                    <p>Achetez et vendez en toute confiance au Cameroun</p>
                </div>
                <div class="footer-section">
                    <h4>Liens rapides</h4>
                    <ul>
                        <li><a href="<?php echo $base_path; ?>index.php">Accueil</a></li>
                        <li><a href="acheter.php">Acheter</a></li>
                        <li><a href="vendre.php">Vendre</a></li>
                        <li><a href="messagerie.php">Messages</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> <?php echo defined('SITE_TEL') ? SITE_TEL : '+237 600 000 000'; ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo defined('SITE_EMAIL') ? SITE_EMAIL : 'contact@monebantu.cm'; ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MONË BANTU — Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <div class="nav-overlay" id="navOverlay" onclick="fermerNavPanel()"></div>
    <aside class="nav-panel" id="navPanel">
        <div class="nav-panel-header">
            <h3><i class="fas fa-bars" style="color:var(--primary)"></i> Menu</h3>
            <button class="nav-panel-close" onclick="fermerNavPanel()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <?php if (function_exists('estConnecte') && estConnecte()): ?>
        <div class="nav-panel-user">
            <div class="nav-panel-user-avatar">
                <?php echo function_exists('initiales') ? initiales($_SESSION['user_pseudo'] ?? 'U') : strtoupper(substr($_SESSION['user_pseudo'] ?? 'U', 0, 2)); ?>
            </div>
            <div class="nav-panel-user-info">
                <strong><?php echo function_exists('esc') ? esc($_SESSION['user_pseudo'] ?? 'Utilisateur') : htmlspecialchars($_SESSION['user_pseudo'] ?? 'Utilisateur'); ?></strong>
                <small>Monë Bantu</small>
            </div>
        </div>
        <?php endif; ?>

        <ul class="nav-panel-list">
            <li><a href="acheter.php"><i class="fas fa-store"></i> Acheter</a></li>
            <li><a href="actualites.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
            <li><a href="vendre.php" class="active"><i class="fas fa-plus-circle"></i> Vendre</a></li>
            <li><a href="boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
            <li><a href="reels.php"><i class="fas fa-clapperboard"></i> Réels</a></li>
            <li><a href="panier.php"><i class="fas fa-cart-shopping"></i> Panier</a></li>
            <li><a href="mes_achats.php"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
            <li><a href="mes_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
        </ul>

        <div class="nav-panel-langue">
            <span><i class="fas fa-globe"></i> Langue :</span>
            <a href="definir_langue.php?langue=fr" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'fr' ? 'actif' : ''; ?>">FR</a>
            <a href="definir_langue.php?langue=en" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'en' ? 'actif' : ''; ?>">EN</a>
        </div>

        <div class="nav-panel-logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </aside>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
    function ouvrirNavPanel() {
        document.getElementById('navPanel').classList.add('open');
        document.getElementById('navOverlay').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function fermerNavPanel() {
        document.getElementById('navPanel').classList.remove('open');
        document.getElementById('navOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fermerNavPanel(); });

    document.getElementById('titre')?.addEventListener('input', function() {
        document.getElementById('titre-count').textContent = this.value.length + ' / 200 caractères';
    });
    document.getElementById('description')?.addEventListener('input', function() {
        document.getElementById('desc-count').textContent = this.value.length + ' / 2000 caractères';
    });

    const uploadZone = document.getElementById('uploadZone');
    const photosInput = document.getElementById('photos');
    const preview    = document.getElementById('preview');
    const photoCount = document.getElementById('photo-count');
    let   fichiers   = [];

    uploadZone?.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
    uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
    uploadZone?.addEventListener('drop', e => { e.preventDefault(); uploadZone.classList.remove('dragover'); ajouterFichiers(e.dataTransfer.files); });
    photosInput?.addEventListener('change', function() { ajouterFichiers(this.files); });

    function ajouterFichiers(files) {
        for (const file of files) {
            if (fichiers.length >= 5) break;
            if (!file.type.startsWith('image/')) continue;
            if (file.size > 5 * 1024 * 1024) { alert(file.name + ' dépasse 5 Mo.'); continue; }
            fichiers.push(file);
        }
        mettreAJourPreview();
        syncInput();
    }

    function mettreAJourPreview() {
        preview.innerHTML = '';
        fichiers.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = e => {
                const item = document.createElement('div');
                item.className = 'preview-item' + (idx === 0 ? ' principale' : '');
                item.innerHTML = '<img src="' + e.target.result + '" alt=""><button type="button" class="remove-photo" onclick="supprimerPhoto(' + idx + ')"><i class="fas fa-times"></i></button>';
                preview.appendChild(item);
            };
            reader.readAsDataURL(file);
        });
        photoCount.style.display = fichiers.length ? 'block' : 'none';
        photoCount.textContent = fichiers.length + ' photo' + (fichiers.length > 1 ? 's' : '') + ' sélectionnée' + (fichiers.length > 1 ? 's' : '');
    }

    function supprimerPhoto(idx) { fichiers.splice(idx, 1); mettreAJourPreview(); syncInput(); }

    function syncInput() {
        try {
            const dt = new DataTransfer();
            fichiers.forEach(f => dt.items.add(f));
            photosInput.files = dt.files;
        } catch(e) {}
    }

    document.getElementById('venteForm')?.addEventListener('submit', function(e) {
        const titre = document.getElementById('titre').value.trim();
        const prix  = parseInt(document.getElementById('prix').value);
        if (titre.length < 5) { e.preventDefault(); alert('Le titre doit contenir au moins 5 caractères.'); return; }
        if (!prix || prix <= 0) { e.preventDefault(); alert('Veuillez saisir un prix valide.'); return; }
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publication en cours…';
    });
    </script>
</body>
</html>