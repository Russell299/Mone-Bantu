<?php
// ============================================================
//  MONË BANTU — Réels
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!function_exists('estConnecte') || !estConnecte()) {
    redirect($base_path . 'php/login.php');
}
if (function_exists('exigerLocalisation')) {
    exigerLocalisation($pdo, $base_path);
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réels — MONË BANTU</title>
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

        .container { padding-bottom: 60px; }
        .page-title { margin: 25px 0 8px !important; }
        .reels-intro { color: var(--text-muted); margin-bottom: 22px; max-width: 620px; }
        .reels-bar-wrap { background: white; padding: 18px; margin-bottom: 20px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); }
        .reels-bar { display: flex; gap: 16px; overflow-x: auto; padding: 4px 2px 8px; }
        .reels-bar:empty { display: none; }
        .reel-story { flex: 0 0 auto; width: 78px; text-align: center; cursor: pointer; }
        .reel-story-avatar {
            width: 66px; height: 66px; border-radius: 50%; padding: 3px;
            background: linear-gradient(135deg, #e94560, #f9a826, #0f3460);
            margin: 0 auto 6px;
            display: flex; align-items: center; justify-content: center;
        }
        .reel-story-avatar-inner {
            width: 100%; height: 100%; border-radius: 50%;
            border: 2px solid white;
            background: var(--light-bg) center/cover no-repeat;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: var(--secondary); overflow: hidden;
            font-size: 1rem;
        }
        .reel-story-avatar-inner img { width: 100%; height: 100%; object-fit: cover; }
        .reel-story-nom { font-size: 0.72rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .reel-story-add .reel-story-avatar { background: var(--border); }
        .reel-story-add .reel-story-avatar-inner { background: white; color: var(--primary); font-size: 1.4rem; }

        .modal-reel {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 99999;
            align-items: center; justify-content: center; padding: 20px;
        }
        .modal-reel.open { display: flex; }
        .modal-reel-box {
            background: white; border-radius: var(--radius-md);
            padding: 28px; max-width: 420px; width: 100%; position: relative;
        }
        .modal-reel-box h3 { margin-bottom: 6px; }
        .modal-reel-fermer {
            position: absolute; top: 14px; right: 14px;
            background: none; border: none; font-size: 1.1rem;
            color: var(--text-muted); cursor: pointer;
        }

        .reel-viewer {
            display: none; position: fixed; inset: 0;
            background: #000; z-index: 999999;
            align-items: center; justify-content: center;
        }
        .reel-viewer.open { display: flex; }
        .reel-viewer video { max-width: 100%; max-height: 100%; width: auto; height: 100%; object-fit: contain; }
        .reel-viewer-progress {
            position: absolute; top: 10px; left: 10px; right: 10px;
            display: flex; gap: 4px; z-index: 3;
        }
        .reel-viewer-progress span { flex: 1; height: 3px; border-radius: 3px; background: rgba(255,255,255,0.3); overflow: hidden; }
        .reel-viewer-progress span i { display: block; height: 100%; width: 0%; background: white; }
        .reel-viewer-fermer {
            position: absolute; top: 26px; right: 14px; z-index: 3;
            background: rgba(0,0,0,0.4); border: none; color: white;
            width: 34px; height: 34px; border-radius: 50%; font-size: 1rem; cursor: pointer;
        }
        .reel-viewer-user {
            position: absolute; top: 26px; left: 14px; z-index: 3;
            color: white; font-size: 0.9rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
            text-shadow: 0 1px 4px rgba(0,0,0,0.5);
        }
        .reel-viewer-legende {
            position: absolute; bottom: 70px; left: 16px; right: 16px; z-index: 3;
            color: white; font-size: 0.92rem; text-shadow: 0 1px 4px rgba(0,0,0,0.6);
        }
        .reel-viewer-nav { position: absolute; inset: 0; display: flex; z-index: 2; }
        .reel-viewer-prev, .reel-viewer-next { flex: 1; cursor: pointer; }
        .reel-viewer-reply {
            position: absolute; bottom: 14px; left: 14px; right: 14px; z-index: 3;
            display: flex; gap: 8px;
        }
        .reel-viewer-reply input {
            flex: 1; padding: 10px 16px; border-radius: 50px; border: none;
            background: rgba(255,255,255,0.15); color: white; outline: none;
        }
        .reel-viewer-reply input::placeholder { color: rgba(255,255,255,0.7); }
        .reel-viewer-reply button {
            width: 42px; height: 42px; border-radius: 50%; border: none;
            background: var(--primary); color: white; cursor: pointer;
        }

        .upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
            background: var(--light-bg);
        }
        .upload-zone:hover { border-color: var(--primary); background: rgba(233,69,96,0.04); }
        .upload-zone i { font-size: 2.5rem; color: var(--primary); margin-bottom: 10px; display: block; }
        .upload-zone p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
        .upload-zone input[type="file"] { display: none; }
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
            <button class="nav-toggle-btn" onclick="ouvrirNavPanel()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-clapperboard" style="color:var(--primary)"></i> Réels
        </h1>
        <p class="reels-intro">
            De courtes vidéos publicitaires, visibles 24h.
        </p>

        <div class="reels-bar-wrap">
            <div class="reels-bar" id="reelsBar"></div>
        </div>

        <div id="reelsEtatVide" class="etat-vide" style="display:none;">
            <i class="fas fa-clapperboard"></i>
            <p>Aucun Réel actif pour le moment.</p>
            <button class="btn-action" onclick="ouvrirPublicationReel()" style="border:none;cursor:pointer;">
                <i class="fas fa-plus"></i> Publier le premier Réel
            </button>
        </div>
    </div>

    <div class="modal-reel" id="modalPublierReel">
        <div class="modal-reel-box">
            <button class="modal-reel-fermer" onclick="fermerPublicationReel()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-clapperboard"></i> Nouveau Réel</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Vidéo MP4/WebM/MOV, 25 Mo max.
            </p>
            <form id="formPublierReel">
                <div class="upload-zone" onclick="document.getElementById('reel_video').click()">
                    <i class="fas fa-video"></i>
                    <p id="reelVideoLabel"><strong>Cliquez</strong> pour choisir une vidéo</p>
                    <input type="file" id="reel_video" name="video" accept="video/mp4,video/webm,video/quicktime,video/3gpp" style="display:none;">
                </div>
                <div class="form-group" style="margin-top:14px;">
                    <label for="reel_legende">Légende (optionnel)</label>
                    <input type="text" id="reel_legende" name="legende" maxlength="255" placeholder="Ex : Nouvelle collection !">
                </div>
                <button type="submit" class="btn-primary-full" id="btnPublierReel">
                    <i class="fas fa-paper-plane"></i> Publier
                </button>
            </form>
        </div>
    </div>

    <div class="reel-viewer" id="reelViewer">
        <div class="reel-viewer-progress" id="reelProgress"></div>
        <button class="reel-viewer-fermer" onclick="fermerVisionneuseReel()"><i class="fas fa-times"></i></button>
        <div class="reel-viewer-user" id="reelViewerUser"></div>
        <video id="reelViewerVideo" playsinline autoplay></video>
        <div class="reel-viewer-legende" id="reelViewerLegende"></div>
        <div class="reel-viewer-nav">
            <div class="reel-viewer-prev" onclick="reelPrecedent()"></div>
            <div class="reel-viewer-next" onclick="reelSuivant()"></div>
        </div>
        <form class="reel-viewer-reply" id="formReplyReel">
            <input type="text" id="reelReplyInput" placeholder="Répondre au Réel..." maxlength="500" autocomplete="off">
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>

    <div class="nav-overlay" id="navOverlay" onclick="fermerNavPanel()"></div>
    <aside class="nav-panel" id="navPanel">
        <div class="nav-panel-header">
            <h3><i class="fas fa-bars" style="color:var(--primary)"></i> Menu</h3>
            <button class="nav-panel-close" onclick="fermerNavPanel()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="nav-panel-user">
            <div class="nav-panel-user-avatar">
                <?php echo function_exists('initiales') ? initiales($_SESSION['user_pseudo'] ?? 'U') : strtoupper(substr($_SESSION['user_pseudo'] ?? 'U', 0, 2)); ?>
            </div>
            <div class="nav-panel-user-info">
                <strong><?php echo function_exists('esc') ? esc($_SESSION['user_pseudo'] ?? 'Utilisateur') : htmlspecialchars($_SESSION['user_pseudo'] ?? 'Utilisateur'); ?></strong>
                <small>Monë Bantu</small>
            </div>
        </div>
        <ul class="nav-panel-list">
            <li><a href="acheter.php"><i class="fas fa-store"></i> Acheter</a></li>
            <li><a href="actualites.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
            <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
            <li><a href="boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
            <li><a href="reels.php" class="active"><i class="fas fa-clapperboard"></i> Réels</a></li>
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
    // Panneau de navigation (les fonctions des Réels sont déjà dans js/script.js :
    // ne JAMAIS redéclarer ROOT, PHP, reelsGroupes, etc. ici)
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