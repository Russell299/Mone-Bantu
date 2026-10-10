<?php
// ============================================================
//  MONË BANTU — Mes notifications
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

$user_id = userId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tout_marquer_lu'])) {
    try {
        $pdo->prepare("UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?")->execute([$user_id]);
    } catch (Throwable $e) {}
    redirect('mes_notifications.php');
}

$notifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT n.*, a.titre AS article_titre, a.photos AS article_photos
        FROM notifications n
        LEFT JOIN articles a ON a.id = n.article_id
        WHERE n.utilisateur_id = ?
        ORDER BY n.date_creation DESC
        LIMIT 100
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (Throwable $e) {}

$nb_non_lues = 0;
foreach ($notifications as $n) if (!$n['lu']) $nb_non_lues++;

$icones_type = [
    'like'         => ['fa-heart', '#e94560'],
    'commentaire'  => ['fa-comment', '#0f3460'],
    'partage'      => ['fa-share-nodes', '#00a86b'],
    'modification' => ['fa-pen', '#f9a826'],
    'vente'        => ['fa-bag-shopping', '#00a86b'],
    'suppression'  => ['fa-trash', '#999'],
    'sanction'     => ['fa-gavel', '#c00'],
    'avis'         => ['fa-star', '#f9a826'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes notifications — MONË BANTU</title>
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

        /* Page notifications */
        .container { padding-bottom: 60px; }
        .notif-header {
            display: flex; justify-content: space-between; align-items: center;
            margin: 25px 0 20px; flex-wrap: wrap; gap: 10px;
        }
        .notif-carte {
            display: flex; gap: 12px; align-items: flex-start;
            background: white; border-radius: var(--radius-md);
            padding: 14px 16px; margin-bottom: 10px; box-shadow: var(--shadow-sm);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .notif-carte:hover { transform: translateX(2px); box-shadow: var(--shadow-md); }
        .notif-carte.non-lue { border-left: 3px solid var(--primary); background: rgba(233,69,96,0.03); }
        .notif-icone {
            width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; flex-shrink: 0; font-size: 0.9rem;
        }
        .notif-corps { flex: 1; }
        .notif-corps p { font-size: 0.9rem; color: var(--text); margin-bottom: 3px; }
        .notif-corps small { color: var(--text-muted); font-size: 0.75rem; }
        .btn-tout-lire {
            background: white; border: 1px solid var(--border);
            padding: 8px 16px; border-radius: 50px;
            font-family: 'Poppins', sans-serif; font-size: 0.85rem;
            color: var(--text); cursor: pointer; transition: all 0.2s;
        }
        .btn-tout-lire:hover { border-color: var(--primary); color: var(--primary); }
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
        <div class="notif-header">
            <h1 class="page-title" style="margin:0;">
                <i class="fas fa-bell" style="color:var(--primary)"></i> Mes notifications
            </h1>
            <?php if ($nb_non_lues > 0): ?>
                <form method="POST" style="margin:0;">
                    <button type="submit" name="tout_marquer_lu" class="btn-tout-lire">
                        <i class="fas fa-check-double"></i> Tout marquer comme lu
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="etat-vide">
                <i class="fas fa-bell-slash"></i>
                <p>Aucune notification pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n):
                [$icone, $couleur] = $icones_type[$n['type']] ?? ['fa-bell', '#999'];
                $lien = $n['article_id'] ? 'article.php?id=' . $n['article_id'] : '#';
            ?>
            <a href="<?php echo $lien; ?>" style="text-decoration:none;color:inherit;">
                <div class="notif-carte <?php echo $n['lu'] ? '' : 'non-lue'; ?>">
                    <div class="notif-icone" style="background:<?php echo $couleur; ?>;">
                        <i class="fas <?php echo $icone; ?>"></i>
                    </div>
                    <div class="notif-corps">
                        <p><?php echo function_exists('esc') ? esc($n['message']) : htmlspecialchars($n['message']); ?></p>
                        <small><?php echo function_exists('tempsRelatif') ? tempsRelatif($n['date_creation']) : date('d/m/Y H:i', strtotime($n['date_creation'])); ?></small>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
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
            <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
            <li><a href="boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
            <li><a href="reels.php"><i class="fas fa-clapperboard"></i> Réels</a></li>
            <li><a href="panier.php"><i class="fas fa-cart-shopping"></i> Panier</a></li>
            <li><a href="mes_achats.php"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
            <li>
                <a href="mes_notifications.php" class="active">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($nb_non_lues > 0): ?><span class="badge"><?php echo $nb_non_lues; ?></span><?php endif; ?>
                </a>
            </li>
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
    </script>
</body>
</html>