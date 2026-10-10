<?php
// ============================================================
//  MONË BANTU — Boutique (inscription / tableau de bord)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

if (!function_exists('estConnecte') || !estConnecte()) {
    redirect($base_path . 'php/login.php');
}
if (function_exists('exigerLocalisation')) {
    exigerLocalisation($pdo, $base_path);
}

$stmt = $pdo->prepare("SELECT * FROM boutiques WHERE utilisateur_id = ?");
$stmt->execute([userId()]);
$ma_boutique = $stmt->fetch();

$error   = '';
$success = '';

if (!$ma_boutique && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_boutique = trim($_POST['nom_boutique'] ?? '');
    $description  = trim($_POST['description']  ?? '');
    $telephone    = trim($_POST['telephone']    ?? '');
    $adresse      = trim($_POST['adresse']      ?? '');

    if (empty($nom_boutique)) {
        $error = "Le nom de la boutique est obligatoire.";
    } elseif (mb_strlen($nom_boutique) < 3) {
        $error = "Le nom de la boutique doit contenir au moins 3 caractères.";
    } elseif (empty($telephone)) {
        $error = "Le numéro de téléphone est obligatoire.";
    } elseif (empty($_FILES['piece_identite']['tmp_name']) || $_FILES['piece_identite']['error'] !== UPLOAD_ERR_OK) {
        $error = "La pièce d'identité est obligatoire.";
    } else {
        $upload_dir = dirname(__DIR__) . '/uploads/boutiques/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $types_autorises = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $max_size         = 5 * 1024 * 1024;

        $tmp      = $_FILES['piece_identite']['tmp_name'];
        $size     = $_FILES['piece_identite']['size'];
        $mimetype = mime_content_type($tmp);

        if ($size > $max_size) {
            $error = "Le fichier ne doit pas dépasser 5 Mo.";
        } elseif (!in_array($mimetype, $types_autorises)) {
            $error = "Format non autorisé. Utilisez JPEG, PNG, WebP ou PDF.";
        } else {
            $ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
            $ext      = $ext_map[$mimetype] ?? 'jpg';
            $filename = 'piece_' . userId() . '_' . uniqid() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $piece_path = 'uploads/boutiques/' . $filename;
                $stmt = $pdo->prepare("
                    INSERT INTO boutiques (utilisateur_id, nom_boutique, description, piece_identite, telephone, adresse, date_creation)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                if ($stmt->execute([userId(), $nom_boutique, $description, $piece_path, $telephone, $adresse])) {
                    $success = "Votre boutique a été créée avec succès !";
                    $stmt = $pdo->prepare("SELECT * FROM boutiques WHERE utilisateur_id = ?");
                    $stmt->execute([userId()]);
                    $ma_boutique = $stmt->fetch();
                } else {
                    $error = "Erreur lors de la création de la boutique.";
                }
            } else {
                $error = "Erreur lors de l'envoi du fichier.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique — MONË BANTU</title>
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

        .page-title { margin: 25px 0 8px !important; }
        .container > p { margin-bottom: 22px; max-width: 620px; }

        #boutiqueForm {
            max-width: 620px;
            background: white;
            border-radius: var(--radius-md);
            padding: 28px 26px 22px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 60px;
        }
        #boutiqueForm .form-group { margin-bottom: 18px; }
        #boutiqueForm .form-group:last-of-type { margin-bottom: 24px; }
        #boutiqueForm .btn-primary-full { margin-top: 6px; margin-bottom: 0; }

        .upload-zone {
            border: 2px dashed var(--border); border-radius: var(--radius-md);
            padding: 30px 20px; text-align: center; cursor: pointer;
            transition: all var(--transition); background: var(--light-bg);
        }
        .upload-zone:hover { border-color: var(--primary); background: rgba(233,69,96,0.04); }
        .upload-zone i { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; display: block; }
        .upload-zone p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .upload-zone input[type="file"] { display: none; }
        #piece-name { display:block; margin-top:10px; color:var(--primary); font-size:0.85rem; }

        .boutique-dashboard {
            background: white; border-radius: var(--radius-md);
            padding: 35px 30px; box-shadow: var(--shadow-md);
            max-width: 620px; margin: 20px auto 60px;
        }
        .boutique-dashboard h2 { margin-bottom: 6px; }
        .boutique-dashboard .statut-badge {
            display: inline-block; background: rgba(0,168,107,0.12); color: #00a86b;
            padding: 4px 14px; border-radius: 50px; font-size: 0.8rem;
            font-weight: 600; margin-bottom: 18px;
        }
        .boutique-dashboard .info-row { margin-bottom: 12px; color: var(--text-muted); }
        .boutique-dashboard .info-row strong { color: var(--text); }

        .alert {
            padding: 13px 16px; border-radius: var(--radius-sm);
            margin-bottom: 18px; display: flex; align-items: center;
            gap: 10px; font-size: 0.92rem;
        }
        .alert-success { background: #f0fff0; color: #080; border-left: 4px solid #00a86b; }
        .alert-error   { background: #fff0f0; color: #c00; border-left: 4px solid var(--primary); }
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

        <?php if ($ma_boutique): ?>
        <h1 class="page-title">
            <i class="fas fa-shop" style="color:var(--primary)"></i> Ma boutique
        </h1>

        <?php if ($success): ?>
            <div class="alert alert-success" style="max-width:620px;margin:15px 0;">
                <i class="fas fa-check-circle"></i> <?php echo function_exists('esc') ? esc($success) : htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="boutique-dashboard">
            <span class="statut-badge"><i class="fas fa-check-circle"></i> Active</span>
            <h2><?php echo function_exists('esc') ? esc($ma_boutique['nom_boutique']) : htmlspecialchars($ma_boutique['nom_boutique']); ?></h2>
            <?php if (!empty($ma_boutique['description'])): ?>
                <p style="color:var(--text-muted);margin-bottom:18px;"><?php echo function_exists('esc') ? esc($ma_boutique['description']) : htmlspecialchars($ma_boutique['description']); ?></p>
            <?php endif; ?>
            <div class="info-row"><i class="fas fa-phone"></i> <strong><?php echo function_exists('esc') ? esc($ma_boutique['telephone']) : htmlspecialchars($ma_boutique['telephone']); ?></strong></div>
            <?php if (!empty($ma_boutique['adresse'])): ?>
                <div class="info-row"><i class="fas fa-location-dot"></i> <strong><?php echo function_exists('esc') ? esc($ma_boutique['adresse']) : htmlspecialchars($ma_boutique['adresse']); ?></strong></div>
            <?php endif; ?>
            <div class="info-row"><i class="fas fa-calendar"></i> Créée le <?php echo (new DateTime($ma_boutique['date_creation']))->format('d/m/Y'); ?></div>

            <a href="vendre.php" class="btn-primary-full" style="margin-top:20px;display:block;text-align:center;text-decoration:none;">
                <i class="fas fa-plus"></i> Publier un article
            </a>
        </div>

        <?php else: ?>
        <h1 class="page-title">
            <i class="fas fa-shop" style="color:var(--primary)"></i> Créer ma boutique
        </h1>
        <p style="color:var(--text-muted);">
            Ouvrez votre boutique sur MONË BANTU pour vendre sous votre propre nom.
            Votre boutique est activée immédiatement après inscription.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error" style="max-width:620px;margin:15px 0;">
                <i class="fas fa-exclamation-circle"></i> <?php echo function_exists('esc') ? esc($error) : htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="boutiqueForm">
            <div class="form-group">
                <label for="nom_boutique">Nom de la boutique <span style="color:var(--primary)">*</span></label>
                <input type="text" id="nom_boutique" name="nom_boutique" required
                       minlength="3" maxlength="100"
                       placeholder="Ex : Boutique Aïcha Mode"
                       value="<?php echo isset($_POST['nom_boutique']) ? (function_exists('esc') ? esc($_POST['nom_boutique']) : htmlspecialchars($_POST['nom_boutique'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="1000"
                          placeholder="Présentez votre boutique en quelques mots…"><?php echo isset($_POST['description']) ? (function_exists('esc') ? esc($_POST['description']) : htmlspecialchars($_POST['description'])) : ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone <span style="color:var(--primary)">*</span></label>
                <input type="tel" id="telephone" name="telephone" required
                       placeholder="6XX XXX XXX"
                       value="<?php echo isset($_POST['telephone']) ? (function_exists('esc') ? esc($_POST['telephone']) : htmlspecialchars($_POST['telephone'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse"
                       placeholder="Quartier, ville"
                       value="<?php echo isset($_POST['adresse']) ? (function_exists('esc') ? esc($_POST['adresse']) : htmlspecialchars($_POST['adresse'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Pièce d'identité <span style="color:var(--primary)">*</span>
                    <small style="color:#aaa;">(CNI, passeport — JPG, PNG ou PDF, 5 Mo max)</small>
                </label>
                <div class="upload-zone" onclick="document.getElementById('piece_identite').click()">
                    <i class="fas fa-id-card"></i>
                    <p><strong>Cliquez</strong> pour sélectionner votre pièce d'identité</p>
                    <input type="file" id="piece_identite" name="piece_identite" accept="image/*,application/pdf" required>
                </div>
                <span id="piece-name"></span>
            </div>

            <button type="submit" class="btn-primary-full" id="submitBtn">
                <i class="fas fa-shop"></i> Créer ma boutique
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
                        <li><a href="boutique.php">Boutique</a></li>
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
            <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
            <li><a href="boutique.php" class="active"><i class="fas fa-shop"></i> Boutique</a></li>
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

    document.getElementById('piece_identite')?.addEventListener('change', function() {
        const nameEl = document.getElementById('piece-name');
        if (nameEl) nameEl.textContent = this.files[0] ? this.files[0].name : '';
    });

    document.getElementById('boutiqueForm')?.addEventListener('submit', function(e) {
        const nom = document.getElementById('nom_boutique').value.trim();
        if (nom.length < 3) {
            e.preventDefault();
            alert('Le nom de la boutique doit contenir au moins 3 caractères.');
            return;
        }
        document.getElementById('submitBtn').disabled = true;
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours…';
    });
    </script>
</body>
</html>