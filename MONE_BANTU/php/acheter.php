<?php
// ============================================================
//  MONË BANTU — Page Acheter
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

$search    = trim($_GET['search']    ?? '');
$categorie = trim($_GET['categorie'] ?? '');
$etat      = trim($_GET['etat']      ?? '');
$ville     = trim($_GET['ville']     ?? '');
$prix_min  = (int)($_GET['prix_min'] ?? 0);
$prix_max  = (int)($_GET['prix_max'] ?? 0);
$sort      = $_GET['sort']           ?? 'recent';

$where  = ["a.statut = 'disponible'"];
$params = [];

if ($search !== '') {
    $where[]  = "(a.titre LIKE ? OR a.description LIKE ? OR u.pseudo LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($categorie !== '') { $where[] = "a.categorie = ?";  $params[] = $categorie; }
if ($etat !== '')      { $where[] = "a.etat = ?";       $params[] = $etat; }
if ($ville !== '')     { $where[] = "a.ville = ?";      $params[] = $ville; }
if ($prix_min > 0)     { $where[] = "a.prix >= ?";      $params[] = $prix_min; }
if ($prix_max > 0)     { $where[] = "a.prix <= ?";      $params[] = $prix_max; }

$orderBy = match($sort) {
    'prix_asc'  => 'a.prix ASC',
    'prix_desc' => 'a.prix DESC',
    'vues'      => 'a.vues DESC',
    default     => 'a.date_publication DESC',
};

$whereSQL = implode(' AND ', $where);
$sql = "
    SELECT a.*, u.pseudo AS vendeur_pseudo, u.ville AS vendeur_ville,
           (SELECT COUNT(*) FROM likes WHERE article_id = a.id) AS nb_likes
    FROM articles a
    JOIN utilisateurs u ON a.vendeur_id = u.id
    WHERE $whereSQL
    ORDER BY $orderBy
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$mes_favoris = [];
if (function_exists('estConnecte') && estConnecte()) {
    try {
        $fav = $pdo->prepare("SELECT article_id FROM favoris WHERE utilisateur_id = ?");
        $fav->execute([userId()]);
        $mes_favoris = array_column($fav->fetchAll(), 'article_id');
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo tt('acheter_titre', 'Acheter'); ?> — MONË BANTU</title>
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
        .nav-panel-list li { margin: 0; }
        .nav-panel-list a {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; color: var(--text);
            text-decoration: none; font-size: 0.92rem;
            transition: background 0.15s, color 0.15s;
        }
        .nav-panel-list a:hover { background: rgba(233,69,96,0.06); color: var(--primary); }
        .nav-panel-list a.active { background: rgba(233,69,96,0.1); color: var(--primary); font-weight: 600; }
        .nav-panel-list a i { width: 22px; text-align: center; font-size: 1rem; color: var(--primary); flex-shrink: 0; }
        .nav-panel-list a .badge {
            margin-left: auto; background: var(--primary); color: white;
            font-size: 0.7rem; font-weight: 700; min-width: 20px; height: 20px;
            border-radius: 10px; display: inline-flex; align-items: center;
            justify-content: center; padding: 0 6px;
        }

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
        .nav-panel-langue a:hover { background: rgba(233,69,96,0.08); }

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
            .navbar .slogan { display: none; }
            .nav-toggle-btn span { display: none; }
            .nav-toggle-btn { padding: 10px 12px; }
            .nav-panel { width: 90vw; right: -100vw; }
        }

        .acheter-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 22px;
            align-items: start;
            padding: 22px 0;
        }
        @media(max-width:900px){ .acheter-layout{ grid-template-columns:1fr; } }
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
                    <input type="text" name="search" placeholder="<?php echo tt('acheter_search_placeholder', 'Rechercher un article...'); ?>"
                           value="<?php echo function_exists('esc') ? esc($search) : htmlspecialchars($search); ?>">
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
        <div class="acheter-layout">

            <aside class="filters-sidebar">
                <h3><i class="fas fa-filter"></i> Filtres</h3>
                <form action="acheter.php" method="GET" id="filterForm">
                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?php echo function_exists('esc') ? esc($search) : htmlspecialchars($search); ?>">
                    <?php endif; ?>

                    <div class="filter-group">
                        <label><?php echo tt('acheter_categorie', 'Catégorie'); ?></label>
                        <select name="categorie" onchange="this.form.submit()">
                            <option value=""><?php echo tt('acheter_toutes', 'Toutes'); ?></option>
                            <?php if (isset($categories)): foreach (array_keys($categories) as $cat): ?>
                                <option value="<?php echo function_exists('esc') ? esc($cat) : htmlspecialchars($cat); ?>" <?php echo $categorie === $cat ? 'selected' : ''; ?>>
                                    <?php echo function_exists('esc') ? esc($cat) : htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php echo tt('acheter_etat', 'État'); ?></label>
                        <select name="etat" onchange="this.form.submit()">
                            <option value=""><?php echo tt('acheter_tous_etats', 'Tous les états'); ?></option>
                            <?php if (isset($etats_article)): foreach ($etats_article as $e): ?>
                                <option value="<?php echo function_exists('esc') ? esc($e) : htmlspecialchars($e); ?>" <?php echo $etat === $e ? 'selected' : ''; ?>>
                                    <?php echo function_exists('esc') ? esc($e) : htmlspecialchars($e); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php echo tt('acheter_ville', 'Ville'); ?></label>
                        <select name="ville" onchange="this.form.submit()">
                            <option value=""><?php echo tt('acheter_toutes_villes', 'Toutes les villes'); ?></option>
                            <?php if (isset($villes)): foreach ($villes as $v): ?>
                                <option value="<?php echo function_exists('esc') ? esc($v) : htmlspecialchars($v); ?>" <?php echo $ville === $v ? 'selected' : ''; ?>>
                                    <?php echo function_exists('esc') ? esc($v) : htmlspecialchars($v); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label><?php echo tt('acheter_prix', 'Prix (FCFA)'); ?></label>
                        <div class="price-range">
                            <input type="number" name="prix_min" placeholder="<?php echo tt('acheter_min', 'Min'); ?>" min="0"
                                   value="<?php echo $prix_min ?: ''; ?>">
                            <span>—</span>
                            <input type="number" name="prix_max" placeholder="<?php echo tt('acheter_max', 'Max'); ?>" min="0"
                                   value="<?php echo $prix_max ?: ''; ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> <?php echo tt('acheter_appliquer', 'Appliquer'); ?>
                    </button>

                    <?php if ($search || $categorie || $etat || $ville || $prix_min || $prix_max): ?>
                        <a href="acheter.php" style="display:block;text-align:center;margin-top:10px;color:#e94560;font-size:0.85rem;">
                            <i class="fas fa-times"></i> <?php echo tt('acheter_reinitialiser', 'Réinitialiser'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </aside>

            <div class="articles-zone">
                <div class="articles-header">
                    <span class="articles-count">
                        <strong><?php echo count($articles); ?></strong>
                        <?php echo count($articles) != 1 ? 'articles' : 'article'; ?>
                        <?php if ($search): ?> pour "<em><?php echo function_exists('esc') ? esc($search) : htmlspecialchars($search); ?></em>"<?php endif; ?>
                    </span>
                    <select class="sort-select" onchange="window.location.href='?<?php
                        $params_url = $_GET;
                        unset($params_url['sort']);
                        echo http_build_query($params_url);
                    ?>&sort='+this.value">
                        <option value="recent"    <?php echo $sort==='recent'    ? 'selected' : ''; ?>><?php echo tt('acheter_tri_recent', 'Plus récents'); ?></option>
                        <option value="prix_asc"  <?php echo $sort==='prix_asc'  ? 'selected' : ''; ?>><?php echo tt('acheter_tri_prix_asc', 'Prix croissant'); ?></option>
                        <option value="prix_desc" <?php echo $sort==='prix_desc' ? 'selected' : ''; ?>><?php echo tt('acheter_tri_prix_desc', 'Prix décroissant'); ?></option>
                        <option value="vues"      <?php echo $sort==='vues'      ? 'selected' : ''; ?>><?php echo tt('acheter_tri_vues', 'Plus vus'); ?></option>
                    </select>
                </div>

                <div class="articles-grid">
                    <?php if (empty($articles)): ?>
                        <div class="no-results">
                            <i class="fas fa-box-open"></i>
                            <h3><?php echo tt('acheter_aucun_trouve', 'Aucun article trouvé'); ?></h3>
                            <p><?php echo tt('acheter_modifiez_filtres', 'Modifiez vos filtres ou revenez plus tard.'); ?></p>
                            <a href="acheter.php"><?php echo tt('acheter_voir_tous', 'Voir tous les articles'); ?></a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($articles as $a):
                            $photos  = array_filter(array_map('trim', explode(',', $a['photos'] ?? '')));
                            $photo   = !empty($photos) ? $base_path . reset($photos) : $base_path . 'uploads/articles/default.jpg';
                            $is_fav  = in_array($a['id'], $mes_favoris);
                        ?>
                        <div class="article-card">
                            <div class="article-image">
                                <img src="<?php echo htmlspecialchars($photo); ?>"
                                     alt="<?php echo function_exists('esc') ? esc($a['titre']) : htmlspecialchars($a['titre']); ?>" loading="lazy">
                                <span class="article-etat"><?php echo function_exists('esc') ? esc($a['etat']) : htmlspecialchars($a['etat']); ?></span>
                                <?php if (function_exists('estConnecte') && estConnecte()): ?>
                                    <button class="btn-favori <?php echo $is_fav ? 'active' : ''; ?>"
                                            onclick="toggleFavori(<?php echo $a['id']; ?>, this)"
                                            title="<?php echo $is_fav ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>">
                                        <i class="<?php echo $is_fav ? 'fas' : 'far'; ?> fa-heart"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="article-info">
                                <h3 class="article-title"><?php echo function_exists('esc') ? esc($a['titre']) : htmlspecialchars($a['titre']); ?></h3>
                                <p class="article-price"><?php echo function_exists('formatPrix') ? formatPrix((float)$a['prix']) : number_format($a['prix'], 0, ',', ' ') . ' FCFA'; ?></p>
                                <div class="article-seller">
                                    <div class="seller-avatar"><?php echo function_exists('initiales') ? initiales($a['vendeur_pseudo']) : strtoupper(substr($a['vendeur_pseudo'],0,2)); ?></div>
                                    <span><?php echo function_exists('esc') ? esc($a['vendeur_pseudo']) : htmlspecialchars($a['vendeur_pseudo']); ?></span>
                                </div>
                                <p class="article-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo function_exists('esc') ? esc($a['ville'] ?: 'Cameroun') : htmlspecialchars($a['ville'] ?: 'Cameroun'); ?>
                                </p>
                                <div style="display:flex;gap:6px;align-items:center;margin-bottom:10px;font-size:0.8rem;color:#aaa;">
                                    <span><i class="fas fa-heart" style="color:#e94560"></i> <?php echo $a['nb_likes']; ?></span>
                                    <span><i class="fas fa-eye"></i> <?php echo $a['vues']; ?></span>
                                    <span><?php echo function_exists('tempsRelatif') ? tempsRelatif($a['date_publication']) : date('d/m/Y', strtotime($a['date_publication'])); ?></span>
                                </div>
                                <a href="article.php?id=<?php echo $a['id']; ?>" class="btn-detail">
                                    <i class="fas fa-eye"></i> Voir le détail
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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
                    <p><i class="fas fa-phone"></i> <?php echo defined('SITE_TEL') ? SITE_TEL : '+237 600 000 000'; ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo defined('SITE_EMAIL') ? SITE_EMAIL : 'contact@monebantu.cm'; ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MONË BANTU — Tous droits réservés</p>
            </div>
        </div>
    </footer>

    <!-- ===== PANNEAU MENU ===== -->
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
            <li><a href="acheter.php" class="active"><i class="fas fa-store"></i> Acheter</a></li>
            <?php if (function_exists('estConnecte') && estConnecte()): ?>
                <li><a href="actualites.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
                <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
                <li><a href="boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
                <li><a href="reels.php"><i class="fas fa-clapperboard"></i> Réels</a></li>
                <li><a href="panier.php"><i class="fas fa-cart-shopping"></i> Panier</a></li>
                <li><a href="mes_achats.php"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
                <li><a href="mes_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li><a href="messagerie.php"><i class="fas fa-envelope"></i> Messages</a></li>
                <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
            <?php else: ?>
                <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Inscription</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-panel-langue">
            <span><i class="fas fa-globe"></i> Langue :</span>
            <a href="definir_langue.php?langue=fr" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'fr' ? 'actif' : ''; ?>">FR</a>
            <a href="definir_langue.php?langue=en" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'en' ? 'actif' : ''; ?>">EN</a>
        </div>

        <?php if (function_exists('estConnecte') && estConnecte()): ?>
        <div class="nav-panel-logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
        <?php endif; ?>
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
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fermerNavPanel();
    });
    </script>
</body>
</html>