<?php
// ============================================================
//  MONË BANTU — Mon profil
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');
exigerLocalisation($pdo, $base_path);

$user_id = userId();

// Données utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$a_une_boutique = estBoutique($pdo, $user_id);
$mode_courant   = modeActif();
$statut_modif   = statutModificationProfil($pdo, $user_id);

// Sécurité : utilisateur introuvable
if (!$user) {
    session_destroy();
    redirect($base_path . 'php/login.php');
}

// Mes articles
$stmt = $pdo->prepare("SELECT * FROM articles WHERE vendeur_id = ? ORDER BY date_publication DESC");
$stmt->execute([$user_id]);
$mes_articles = $stmt->fetchAll();

// Mes favoris
$stmt = $pdo->prepare("
    SELECT a.* FROM favoris f
    JOIN articles a ON f.article_id = a.id
    WHERE f.utilisateur_id = ?
    ORDER BY f.date_ajout DESC
");
$stmt->execute([$user_id]);
$favoris = $stmt->fetchAll();

// Mes évaluations
$stmt = $pdo->prepare("
    SELECT e.*, u.pseudo AS evaluateur_pseudo
    FROM evaluations e
    JOIN utilisateurs u ON e.evaluateur_id = u.id
    WHERE e.evalue_id = ?
    ORDER BY e.date_evaluation DESC
");
$stmt->execute([$user_id]);
$evaluations = $stmt->fetchAll();

// Messages session
$success = $_SESSION['profil_success'] ?? '';
$error   = $_SESSION['profil_error']   ?? '';
unset($_SESSION['profil_success'], $_SESSION['profil_error']);

// Section active
$sections_valides = ['mes-articles', 'favoris', 'evaluations', 'parametres'];
$section_active   = in_array($_GET['section'] ?? '', $sections_valides)
                    ? $_GET['section']
                    : 'mes-articles';

// Initiales correctes : prenom (1 lettre) + nom (1 lettre)
$init_profil = strtoupper(
    mb_substr($user['prenom'] ?? 'U', 0, 1, 'UTF-8') .
    mb_substr($user['nom']    ?? '',  0, 1, 'UTF-8')
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ===== PROFIL SPÉCIFIQUE ===== */
        body { background: #f0f2f5; }

        .profil-wrap {
            max-width: 860px;
            margin: 25px auto;
            padding: 0 15px 40px;
        }

        /* Carte en-tête */
        .profil-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 30px 25px 20px;
            text-align: center;
            margin-bottom: 18px;
        }
        .profil-cover {
            height: 150px;
            border-radius: 14px 14px 0 0;
            background: linear-gradient(135deg, #e94560, #0f3460);
            background-size: cover;
            background-position: center;
            position: relative;
        }
        .cover-edit-btn {
            position: absolute; bottom: 12px; right: 14px;
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(0,0,0,0.55); color: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.85rem;
            transition: background 0.2s;
        }
        .cover-edit-btn:hover { background: rgba(0,0,0,0.75); }

        .profil-card { border-radius: 0 0 14px 14px; }

        .profil-av-wrap {
            position: relative;
            width: 85px;
            margin: -45px auto 14px;
            z-index: 2;
        }
        .profil-av {
            width: 85px; height: 85px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e94560, #0f3460);
            color: white;
            font-size: 1.8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            border: 4px solid white;
            box-shadow: 0 4px 14px rgba(233,69,96,0.3);
            overflow: hidden;
        }
        .profil-av img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-edit-btn {
            position: absolute; bottom: 0; right: -2px;
            width: 28px; height: 28px; border-radius: 50%;
            background: #e94560; color: white;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 0.7rem;
            border: 2px solid white;
        }
        .profil-card h2 { font-size: 1.3rem; margin-bottom: 3px; }
        .profil-pseudo  { color: #888; font-size: 0.9rem; margin-bottom: 4px; }
        .profil-ville   { color: #aaa; font-size: 0.82rem; }
        .profil-depuis  { color: #bbb; font-size: 0.75rem; margin-top: 6px; }

        .profil-stats {
            display: flex; justify-content: center;
            gap: 30px; margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }
        .stat-item { text-align: center; }
        .stat-val { font-size: 1.4rem; font-weight: 700; color: #e94560; display: block; }
        .stat-lbl { font-size: 0.75rem; color: #aaa; }

        /* Menu navigation sections */
        .profil-nav {
            background: white;
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 18px;
            display: flex;
            flex-wrap: wrap;
        }
        .nav-tab {
            flex: 1; min-width: 120px;
            display: flex; align-items: center; justify-content: center;
            gap: 7px; padding: 14px 10px;
            cursor: pointer; text-decoration: none; color: #555;
            font-size: 0.85rem; font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            background: none; border-top: none; border-left: none; border-right: none;
            font-family: 'Poppins', sans-serif;
        }
        .nav-tab:hover { color: #e94560; background: #fff8f9; }
        .nav-tab.active {
            color: #e94560;
            border-bottom-color: #e94560;
            background: #fff8f9;
            font-weight: 600;
        }
        .nav-tab i { font-size: 0.9rem; }
        .nav-tab .count {
            background: #f0f0f0; color: #666;
            font-size: 0.68rem; font-weight: 700;
            padding: 1px 6px; border-radius: 9px;
        }
        .nav-tab.active .count { background: #ffd4db; color: #e94560; }

        /* Sections */
        .profil-section {
            background: white;
            border-radius: 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            padding: 24px;
            display: none;
        }
        .profil-section.active { display: block; }
        .profil-section h3 { font-size: 1.05rem; margin-bottom: 18px; color: #1a1a2e; }

        /* Grille articles profil */
        .profil-articles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
        }
        .profil-article-card {
            background: #fafafa;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #eee;
            transition: box-shadow 0.2s;
        }
        .profil-article-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.1); }
        .pa-img {
            height: 130px; overflow: hidden;
            background: #f0f0f0; position: relative;
        }
        .pa-img img { width: 100%; height: 100%; object-fit: cover; }
        .pa-statut {
            position: absolute; top: 6px; right: 6px;
            padding: 2px 8px; border-radius: 4px;
            font-size: 0.68rem; font-weight: 700; color: white;
        }
        .pa-body { padding: 10px 12px; }
        .pa-titre { font-size: 0.82rem; font-weight: 600; margin-bottom: 3px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .pa-prix  { font-size: 0.95rem; font-weight: 700; color: #e94560; margin-bottom: 6px; }
        .pa-meta  { font-size: 0.7rem; color: #aaa; margin-bottom: 8px; }
        .pa-actions { display: flex; gap: 5px; }
        .pa-btn {
            flex: 1; padding: 5px 8px; text-align: center;
            border-radius: 6px; font-size: 0.75rem; font-weight: 600;
            text-decoration: none; color: white; border: none;
            cursor: pointer; font-family: 'Poppins', sans-serif;
            transition: opacity 0.2s;
        }
        .pa-btn:hover { opacity: 0.85; }
        .pa-btn-voir    { background: #0f3460; }
        .pa-btn-supp    { background: #e94560; }

        /* Évaluations */
        .eval-card {
            background: #fafafa; border-radius: 10px;
            padding: 14px 16px; margin-bottom: 12px;
            border-left: 4px solid #e94560;
        }
        .eval-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .eval-stars i { color: #f1c40f; font-size: 0.85rem; margin: 0 1px; }
        .eval-stars .vide { color: #ddd; }

        /* Formulaires paramètres */
        .param-section { max-width: 460px; }
        .param-section .form-group { margin-bottom: 16px; }
        .param-section label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 5px; color: #444; }
        .param-section input,
        .param-section select {
            width: 100%; padding: 10px 13px;
            border: 1.5px solid #e8e8e8; border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: 0.9rem;
            background: #fafafa; outline: none;
            transition: border-color 0.2s;
        }
        .param-section input:focus,
        .param-section select:focus { border-color: #e94560; background: white; }

        .btn-sauv {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #e94560, #c73652);
            color: white; border: none; border-radius: 8px;
            font-size: 0.95rem; font-weight: 600;
            font-family: 'Poppins', sans-serif; cursor: pointer;
            transition: all 0.2s; margin-top: 4px;
        }
        .btn-sauv:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(233,69,96,0.4); }

        /* Vide état */
        .etat-vide { text-align: center; padding: 40px 20px; color: #aaa; }
        .etat-vide i { font-size: 2.5rem; color: #e8e8e8; display: block; margin-bottom: 12px; }
        .etat-vide p { margin-bottom: 16px; }
        .btn-action {
            display: inline-block; padding: 10px 24px;
            background: linear-gradient(135deg, #e94560, #c73652);
            color: white; text-decoration: none; border-radius: 8px;
            font-weight: 600; font-size: 0.88rem; transition: all 0.2s;
        }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(233,69,96,0.4); }

        .divider { border: none; border-top: 1px solid #eee; margin: 24px 0; }

        /* Alertes */
        .alerte {
            padding: 12px 16px; border-radius: 8px;
            margin-bottom: 16px; display: flex; align-items: center; gap: 9px;
            font-size: 0.88rem;
        }
        .alerte-ok  { background: #f0fff0; color: #080; border-left: 4px solid #00a86b; }
        .alerte-err { background: #fff0f0; color: #c00; border-left: 4px solid #e94560; }

        /* Modal confirmation suppression */
        .modal-supp {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.5); z-index: 9999;
            align-items: center; justify-content: center;
        }
        .modal-supp.open { display: flex; }
        .modal-supp-box {
            background: white; border-radius: 14px;
            padding: 28px; max-width: 360px; width: 90%;
            text-align: center;
        }
        .modal-supp-box i { font-size: 2.5rem; color: #e94560; display: block; margin-bottom: 14px; }
        .modal-supp-box h3 { margin-bottom: 8px; }
        .modal-supp-box p  { color: #666; font-size: 0.88rem; margin-bottom: 20px; }
        .modal-btns { display: flex; gap: 10px; justify-content: center; }
        .btn-annuler { padding: 9px 22px; background: #f0f0f0; color: #555; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; cursor: pointer; font-weight: 600; }
        .btn-confirmer { padding: 9px 22px; background: #e94560; color: white; border: none; border-radius: 8px; font-family: 'Poppins', sans-serif; cursor: pointer; font-weight: 600; }

        @media(max-width:580px) {
            .profil-stats { gap: 15px; }
            .nav-tab span { display: none; }
            .profil-articles-grid { grid-template-columns: repeat(2, 1fr); }
        }
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
                    <input type="text" name="search" placeholder="Rechercher un article...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <ul class="nav-menu">
                <li><a href="acheter.php"><i class="fas fa-store"></i> <?php echo t('nav_acheter'); ?></a></li>
                <li><a href="actualites.php"><i class="fas fa-newspaper"></i> <?php echo t('nav_actualites'); ?></a></li>
                <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> <?php echo t('nav_vendre'); ?></a></li>
                <li><a href="boutique.php"><i class="fas fa-shop"></i> <?php echo t('nav_boutique'); ?></a></li>
                <li><a href="reels.php"><i class="fas fa-clapperboard"></i> <?php echo t('nav_reels'); ?></a></li>
                <li><a href="panier.php"><i class="fas fa-cart-shopping"></i> <?php echo t('nav_panier'); ?></a></li>
                <li><a href="mes_achats.php"><i class="fas fa-bag-shopping"></i> <?php echo t('nav_mes_achats'); ?></a></li>
                <li><a href="mes_notifications.php"><i class="fas fa-bell"></i> <?php echo t('nav_notifications'); ?></a></li>
                <li><a href="messagerie.php"><i class="fas fa-envelope"></i> <?php echo t('nav_messages'); ?></a></li>
                <li><a href="profil.php" class="active"><i class="fas fa-user"></i> <?php echo t('nav_profil'); ?></a></li>
                <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i></a></li>
                <li class="lang-switch">
                    <a href="definir_langue.php?langue=fr" class="<?php echo $GLOBALS['LANGUE_ACTIVE'] === 'fr' ? 'actif' : ''; ?>">FR</a>
                    <span>/</span>
                    <a href="definir_langue.php?langue=en" class="<?php echo $GLOBALS['LANGUE_ACTIVE'] === 'en' ? 'actif' : ''; ?>">EN</a>
                </li>
            </ul>
            <div class="hamburger"><span></span><span></span><span></span></div>
        </div>
    </nav>

    <div class="profil-wrap">

        <?php if ($success): ?>
            <div class="alerte alerte-ok"><i class="fas fa-check-circle"></i> <?php echo esc($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alerte alerte-err"><i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?></div>
        <?php endif; ?>

        <!-- ===== EN-TÊTE PROFIL ===== -->
        <div class="profil-cover" <?php echo !empty($user['photo_couverture']) ? 'style="background-image:url(\'' . $base_path . esc($user['photo_couverture']) . '\')"' : ''; ?>>
            <form method="POST" action="update_photo.php" enctype="multipart/form-data" id="coverForm">
                <input type="hidden" name="type" value="couverture">
                <label for="couverture_input" class="cover-edit-btn" title="Changer la couverture">
                    <i class="fas fa-camera"></i>
                </label>
                <input type="file" id="couverture_input" name="photo_couverture" accept="image/*" style="display:none;">
            </form>
        </div>
        <div class="profil-card">
            <div class="profil-av-wrap">
                <div class="profil-av">
                    <?php if (!empty($user['photo_profil'])): ?>
                        <img src="<?php echo $base_path . esc($user['photo_profil']); ?>" alt="Photo de profil">
                    <?php else: ?>
                        <?php echo $init_profil; ?>
                    <?php endif; ?>
                </div>
                <form method="POST" action="update_photo.php" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="type" value="profil">
                    <label for="avatar_input" class="avatar-edit-btn" title="Changer la photo de profil">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="avatar_input" name="photo_profil" accept="image/*" style="display:none;">
                </form>
            </div>
            <h2><?php echo esc(trim($user['prenom'] . ' ' . $user['nom'])); ?></h2>
            <p class="profil-pseudo">@<?php echo esc($user['pseudo']); ?></p>

            <?php if ($a_une_boutique): ?>
            <form method="POST" action="basculer_mode.php" class="mode-toggle-form">
                <button type="submit" class="mode-toggle-btn <?php echo $mode_courant; ?>">
                    <span class="mode-toggle-option <?php echo $mode_courant === 'personnel' ? 'actif' : ''; ?>">
                        <i class="fas fa-user"></i> Personnel
                    </span>
                    <span class="mode-toggle-option <?php echo $mode_courant === 'boutique' ? 'actif' : ''; ?>">
                        <i class="fas fa-shop"></i> Boutique
                    </span>
                </button>
            </form>
            <p class="mode-toggle-info">
                Mode actif : <strong><?php echo $mode_courant === 'boutique' ? 'Boutique' : 'Personnel'; ?></strong>
                — vos prochains articles, messages et Réels seront publiés sous ce nom.
            </p>
            <?php endif; ?>

            <p class="profil-ville"><i class="fas fa-map-marker-alt"></i> <?php echo esc($user['ville'] ?: 'Cameroun'); ?></p>
            <p class="profil-depuis">Membre depuis le <?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></p>

            <div class="profil-stats">
                <div class="stat-item">
                    <span class="stat-val">
                        <?php echo $user['note_moyenne'] > 0 ? number_format((float)$user['note_moyenne'], 1) : '—'; ?>
                    </span>
                    <span class="stat-lbl"><i class="fas fa-star" style="color:#f1c40f;"></i> Note</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val"><?php echo count($mes_articles); ?></span>
                    <span class="stat-lbl">Annonces</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val"><?php echo (int)($user['nombre_ventes'] ?? 0); ?></span>
                    <span class="stat-lbl">Ventes</span>
                </div>
                <div class="stat-item">
                    <span class="stat-val"><?php echo count($favoris); ?></span>
                    <span class="stat-lbl">Favoris</span>
                </div>
            </div>
        </div>

        <!-- ===== NAVIGATION TABS ===== -->
        <div class="profil-nav">
            <button class="nav-tab <?php echo $section_active === 'mes-articles' ? 'active' : ''; ?>"
                    onclick="showSec('mes-articles')">
                <i class="fas fa-box"></i>
                <span>Mes annonces</span>
                <span class="count"><?php echo count($mes_articles); ?></span>
            </button>
            <button class="nav-tab <?php echo $section_active === 'favoris' ? 'active' : ''; ?>"
                    onclick="showSec('favoris')">
                <i class="fas fa-heart"></i>
                <span>Favoris</span>
                <span class="count"><?php echo count($favoris); ?></span>
            </button>
            <button class="nav-tab <?php echo $section_active === 'evaluations' ? 'active' : ''; ?>"
                    onclick="showSec('evaluations')">
                <i class="fas fa-star"></i>
                <span>Évaluations</span>
                <span class="count"><?php echo count($evaluations); ?></span>
            </button>
            <button class="nav-tab <?php echo $section_active === 'parametres' ? 'active' : ''; ?>"
                    onclick="showSec('parametres')">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </button>
        </div>

        <!-- ===== MES ARTICLES ===== -->
        <div id="sec-mes-articles" class="profil-section <?php echo $section_active === 'mes-articles' ? 'active' : ''; ?>">
            <h3><i class="fas fa-box" style="color:#e94560;margin-right:7px;"></i>Mes annonces</h3>

            <?php if (empty($mes_articles)): ?>
                <div class="etat-vide">
                    <i class="fas fa-box-open"></i>
                    <p>Vous n'avez aucune annonce en ligne.</p>
                    <a href="vendre.php" class="btn-action"><i class="fas fa-plus"></i> Publier une annonce</a>
                </div>
            <?php else: ?>
                <div class="profil-articles-grid">
                    <?php foreach ($mes_articles as $a):
                        $phs = array_filter(array_map('trim', explode(',', $a['photos'] ?? '')));
                        $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
                        $color_statut = $a['statut'] === 'disponible' ? '#00a86b' : ($a['statut'] === 'vendu' ? '#e94560' : '#ff9800');
                    ?>
                    <div class="profil-article-card">
                        <div class="pa-img">
                            <img src="<?php echo htmlspecialchars($ph); ?>" alt="<?php echo esc($a['titre']); ?>" loading="lazy">
                            <span class="pa-statut" style="background:<?php echo $color_statut; ?>">
                                <?php echo ucfirst(esc($a['statut'])); ?>
                            </span>
                        </div>
                        <div class="pa-body">
                            <div class="pa-titre"><?php echo esc($a['titre']); ?></div>
                            <div class="pa-prix"><?php echo formatPrix((float)$a['prix']); ?></div>
                            <div class="pa-meta">
                                <i class="fas fa-eye"></i> <?php echo (int)$a['vues']; ?> &nbsp;
                                <?php echo tempsRelatif($a['date_publication']); ?>
                            </div>
                            <div class="pa-actions">
                                <a href="article.php?id=<?php echo $a['id']; ?>" class="pa-btn pa-btn-voir">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                                <button class="pa-btn pa-btn-supp"
                                        onclick="confirmerSupp(<?php echo $a['id']; ?>, '<?php echo addslashes(esc($a['titre'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:16px;">
                    <a href="vendre.php" class="btn-action">
                        <i class="fas fa-plus"></i> Nouvelle annonce
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== FAVORIS ===== -->
        <div id="sec-favoris" class="profil-section <?php echo $section_active === 'favoris' ? 'active' : ''; ?>">
            <h3><i class="fas fa-heart" style="color:#e94560;margin-right:7px;"></i>Mes favoris</h3>

            <?php if (empty($favoris)): ?>
                <div class="etat-vide">
                    <i class="fas fa-heart-broken"></i>
                    <p>Aucun article en favori.</p>
                    <a href="acheter.php" class="btn-action"><i class="fas fa-search"></i> Explorer les articles</a>
                </div>
            <?php else: ?>
                <div class="profil-articles-grid">
                    <?php foreach ($favoris as $a):
                        $phs = array_filter(array_map('trim', explode(',', $a['photos'] ?? '')));
                        $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
                    ?>
                    <div class="profil-article-card">
                        <div class="pa-img">
                            <img src="<?php echo htmlspecialchars($ph); ?>" alt="<?php echo esc($a['titre']); ?>" loading="lazy">
                            <span class="pa-statut" style="background:#555;"><?php echo esc($a['etat']); ?></span>
                        </div>
                        <div class="pa-body">
                            <div class="pa-titre"><?php echo esc($a['titre']); ?></div>
                            <div class="pa-prix"><?php echo formatPrix((float)$a['prix']); ?></div>
                            <div class="pa-actions">
                                <a href="article.php?id=<?php echo $a['id']; ?>" class="pa-btn pa-btn-voir" style="background:#e94560;">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== ÉVALUATIONS ===== -->
        <div id="sec-evaluations" class="profil-section <?php echo $section_active === 'evaluations' ? 'active' : ''; ?>">
            <h3><i class="fas fa-star" style="color:#f1c40f;margin-right:7px;"></i>Mes évaluations</h3>

            <?php if (empty($evaluations)): ?>
                <div class="etat-vide">
                    <i class="fas fa-star"></i>
                    <p>Aucune évaluation reçue pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($evaluations as $e): ?>
                <div class="eval-card">
                    <div class="eval-top">
                        <strong><?php echo esc($e['evaluateur_pseudo']); ?></strong>
                        <div class="eval-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i > $e['note'] ? 'vide' : ''; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if (!empty($e['commentaire'])): ?>
                        <p style="color:#555;font-size:0.88rem;margin-bottom:6px;"><?php echo nl2br(esc($e['commentaire'])); ?></p>
                    <?php endif; ?>
                    <small style="color:#bbb;"><?php echo date('d/m/Y', strtotime($e['date_evaluation'])); ?></small>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ===== PARAMÈTRES ===== -->
        <div id="sec-parametres" class="profil-section <?php echo $section_active === 'parametres' ? 'active' : ''; ?>">
            <h3><i class="fas fa-cog" style="color:#e94560;margin-right:7px;"></i>Paramètres du compte</h3>

            <div class="param-section">
                <?php if (!$statut_modif['autorise']): ?>
                    <div class="alert" style="background:#fff8e6;color:#8a6100;border-left:4px solid #f9a826;margin-bottom:16px;">
                        <i class="fas fa-lock"></i>
                        Pour la sécurité du compte, vos informations personnelles ne sont modifiables qu'à partir du
                        <strong><?php echo $statut_modif['date_limite']->format('d/m/Y'); ?></strong>
                        (7 mois après votre inscription).
                    </div>
                <?php endif; ?>
                <form method="POST" action="update_profil.php">
                    <?php $verrouille = !$statut_modif['autorise'] ? 'disabled' : ''; ?>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required value="<?php echo esc($user['prenom']); ?>" <?php echo $verrouille; ?>>
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required value="<?php echo esc($user['nom']); ?>" <?php echo $verrouille; ?>>
                    </div>
                    <div class="form-group">
                        <label>Adresse email *</label>
                        <input type="email" name="email" required value="<?php echo esc($user['email']); ?>" <?php echo $verrouille; ?>>
                    </div>
                    <div class="form-group">
                        <label>Téléphone *</label>
                        <input type="tel" name="telephone" required value="<?php echo esc($user['telephone']); ?>" <?php echo $verrouille; ?>>
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <select name="ville" <?php echo $verrouille; ?>>
                            <option value="">Choisir…</option>
                            <?php foreach ($villes as $v): ?>
                                <option value="<?php echo esc($v); ?>"
                                    <?php echo ($user['ville'] ?? '') === $v ? 'selected' : ''; ?>>
                                    <?php echo esc($v); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-sauv" <?php echo $verrouille; ?>>
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </form>

                <hr class="divider">

                <h4 style="margin-bottom:16px;font-size:1rem;">
                    <i class="fas fa-lock" style="color:#e94560;margin-right:6px;"></i>
                    Changer le mot de passe
                </h4>
                <form method="POST" action="change_password.php">
                    <div class="form-group">
                        <label>Mot de passe actuel *</label>
                        <input type="password" name="old_password" required placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe * <small style="color:#aaa;">(min. 6 car.)</small></label>
                        <input type="password" name="new_password" required minlength="6" placeholder="••••••••">
                    </div>
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe *</label>
                        <input type="password" name="confirm_password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-sauv" style="background:linear-gradient(135deg,#0f3460,#1a1a2e);">
                        <i class="fas fa-lock"></i> Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

    </div><!-- /.profil-wrap -->

    <!-- Modal confirmation suppression -->
    <div class="modal-supp" id="modalSupp">
        <div class="modal-supp-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Supprimer l'annonce ?</h3>
            <p id="modalSuppTitre">Cette action est irréversible.</p>
            <div class="modal-btns">
                <button class="btn-annuler" onclick="fermerModalSupp()">Annuler</button>
                <button class="btn-confirmer" id="btnConfirmerSupp" onclick="supprimerArticle()">Supprimer</button>
            </div>
        </div>
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
                    <p><i class="fas fa-phone"></i> <?php echo SITE_TEL; ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo SITE_EMAIL; ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MONË BANTU — Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
    const SECTIONS = ['mes-articles','favoris','evaluations','parametres'];
    let articleASupprimer = 0;

    function showSec(id) {
        // Cacher toutes les sections
        SECTIONS.forEach(s => {
            const el = document.getElementById('sec-' + s);
            if (el) el.classList.remove('active');
        });
        // Afficher la section demandée
        const cible = document.getElementById('sec-' + id);
        if (cible) cible.classList.add('active');

        // Mettre à jour les tabs
        document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
        const tabs = document.querySelectorAll('.nav-tab');
        const index = SECTIONS.indexOf(id);
        if (index >= 0 && tabs[index]) tabs[index].classList.add('active');

        // Mettre à jour l'URL sans rechargement
        history.replaceState(null, '', '?section=' + id);
    }

    // Suppression d'article
    function confirmerSupp(id, titre) {
        articleASupprimer = id;
        document.getElementById('modalSuppTitre').textContent =
            'Voulez-vous vraiment supprimer "' + titre + '" ? Cette action est irréversible.';
        document.getElementById('modalSupp').classList.add('open');
    }

    function fermerModalSupp() {
        document.getElementById('modalSupp').classList.remove('open');
        articleASupprimer = 0;
    }

    async function supprimerArticle() {
        if (!articleASupprimer) return;
        const btn = document.getElementById('btnConfirmerSupp');
        btn.disabled = true;
        btn.textContent = 'Suppression…';

        try {
            const fd = new FormData();
            fd.append('article_id', articleASupprimer);
            const r = await fetch('supprimer_article.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                fermerModalSupp();
                showToast('Annonce supprimée', 'success');
                setTimeout(() => window.location.reload(), 1000);
            } else {
                showToast(d.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.textContent = 'Supprimer';
            }
        } catch(e) {
            showToast('Erreur réseau', 'error');
            btn.disabled = false;
            btn.textContent = 'Supprimer';
        }
    }

    // Fermer modal si clic extérieur
    document.getElementById('modalSupp').addEventListener('click', function(e) {
        if (e.target === this) fermerModalSupp();
    });

    // Menu mobile
    document.querySelector('.hamburger')?.addEventListener('click', function() {
        document.querySelector('.nav-menu').classList.toggle('active');
        this.classList.toggle('active');
    });

    // Envoi automatique dès qu'une photo est sélectionnée
    document.getElementById('avatar_input')?.addEventListener('change', function() {
        if (this.files[0]) document.getElementById('avatarForm').submit();
    });
    document.getElementById('couverture_input')?.addEventListener('change', function() {
        if (this.files[0]) document.getElementById('coverForm').submit();
    });
    </script>
</body>
</html>
