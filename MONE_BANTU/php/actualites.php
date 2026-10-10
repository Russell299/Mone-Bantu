<?php
// ============================================================
//  MONË BANTU — Actualités (fil d'actualité style réseau social)
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

$publications = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.titre, a.prix, a.photos, a.description, a.etat,
            a.categorie, a.ville, a.vues, a.date_publication,
            u.id AS auteur_id, u.pseudo, u.photo_profil, u.ville AS auteur_ville,
            (SELECT COUNT(*) FROM likes      WHERE article_id = a.id)                          AS nb_likes,
            (SELECT COUNT(*) FROM commentaires WHERE article_id = a.id)                        AS nb_commentaires,
            (SELECT COUNT(*) FROM likes      WHERE article_id = a.id AND utilisateur_id = :uid) AS deja_like
        FROM articles a
        JOIN utilisateurs u ON a.vendeur_id = u.id
        WHERE a.vendeur_id != :uid2 AND a.statut = 'disponible'
        ORDER BY a.date_publication DESC
        LIMIT 30
    ");
    $stmt->execute([':uid' => $user_id, ':uid2' => $user_id]);
    $publications = $stmt->fetchAll();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .footer { display: none; }

        /* NAV */
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

        /* ACTUALITÉS */
        .actualites-container { max-width: 620px; margin: 20px auto; padding: 0 15px; }

        .actualites-header {
            background: white; padding: 18px 20px; border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 20px;
            display: flex; align-items: center; gap: 15px;
        }
        .actualites-header i { font-size: 1.5rem; color: #e94560; }
        .actualites-header h1 { font-size: 1.3rem; color: #1a1a2e; }
        .actualites-header p { color: #666; font-size: 0.85rem; }

        .post-card {
            background: white; border-radius: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            margin-bottom: 20px; overflow: hidden;
        }
        .post-header { display: flex; align-items: center; padding: 14px 16px; }
        .post-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, #e94560, #0f3460);
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 700; margin-right: 12px; flex-shrink: 0;
        }
        .post-user-info h3 { font-size: 1rem; color: #1a1a2e; }
        .post-user-info .post-meta {
            font-size: 0.78rem; color: #888;
            display: flex; gap: 10px; margin-top: 2px;
        }
        .post-user-info .post-meta i { margin-right: 3px; }

        .post-image-wrap { position: relative; background: #f5f5f5; cursor: pointer; }
        .post-image-wrap img {
            width: 100%; max-height: 500px; object-fit: contain;
            display: block; background: #f5f5f5;
        }
        .post-image-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.25);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.3s;
        }
        .post-image-wrap:hover .post-image-overlay { opacity: 1; }
        .post-image-overlay span {
            background: white; padding: 8px 18px; border-radius: 50px;
            font-weight: 600; font-size: 0.9rem;
        }

        .post-thumbnails {
            display: flex; gap: 3px; padding: 3px;
            background: #f5f5f5; overflow-x: auto;
        }
        .post-thumbnail {
            width: 70px; height: 70px; object-fit: cover;
            border-radius: 6px; cursor: pointer; flex-shrink: 0;
        }

        .post-content { padding: 14px 16px 6px; }
        .post-title { font-size: 1.1rem; font-weight: 600; color: #1a1a2e; margin-bottom: 6px; }
        .post-price { font-size: 1.4rem; font-weight: 700; color: #e94560; margin-bottom: 8px; }
        .post-desc  { color: #555; line-height: 1.55; font-size: 0.92rem; margin-bottom: 10px; }
        .post-tags  { display: flex; gap: 8px; flex-wrap: wrap; }
        .post-tag {
            background: #f0f2f5; color: #555;
            padding: 4px 10px; border-radius: 50px; font-size: 0.78rem;
        }
        .post-tag i { margin-right: 4px; color: #e94560; }

        .post-stats {
            display: flex; gap: 15px; padding: 8px 16px;
            font-size: 0.85rem; color: #888;
            border-bottom: 1px solid #f0f0f0;
        }
        .post-stats span { display: flex; align-items: center; gap: 5px; }

        .post-actions { display: flex; border-bottom: 1px solid #f0f2f5; }
        .post-action-btn {
            flex: 1; display: flex; align-items: center; justify-content: center;
            gap: 7px; padding: 11px 5px;
            background: none; border: none; color: #555;
            font-size: 0.92rem; font-family: 'Poppins', sans-serif;
            cursor: pointer; text-decoration: none;
        }
        .post-action-btn:hover { background: #f0f2f5; color: #e94560; }
        .post-action-btn.liked { color: #e94560; }
        .post-action-btn i { font-size: 1.1rem; }

        .post-comments { padding: 12px 16px; }
        .comment-item { display: flex; gap: 10px; margin-bottom: 12px; }
        .comment-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, #e94560, #0f3460);
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 0.8rem; font-weight: 700; flex-shrink: 0;
        }
        .comment-bubble { flex: 1; background: #f0f2f5; padding: 9px 13px; border-radius: 12px; }
        .comment-bubble strong { display: block; font-size: 0.85rem; color: #1a1a2e; }
        .comment-bubble p { font-size: 0.88rem; color: #444; margin-top: 2px; }
        .comment-bubble .comment-time { font-size: 0.72rem; color: #aaa; margin-top: 4px; }

        .comment-input-row { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
        .comment-input-row .mini-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, #e94560, #0f3460);
            color: white; display: flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 700; flex-shrink: 0;
        }
        .comment-input-row input {
            flex: 1; padding: 9px 14px; border: 1px solid #ddd;
            border-radius: 20px; font-size: 0.9rem;
            font-family: 'Poppins', sans-serif; background: #f0f2f5; outline: none;
        }
        .comment-input-row input:focus { border-color: #e94560; background: white; }
        .comment-input-row button {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: #e94560; color: white; cursor: pointer;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .comment-input-row button:hover { background: #c73652; }

        .comments-loading { text-align: center; color: #aaa; font-size: 0.85rem; padding: 10px; }

        .no-posts {
            text-align: center; padding: 60px 20px;
            background: white; border-radius: 12px;
        }
        .no-posts i { font-size: 3.5rem; color: #ddd; margin-bottom: 15px; display: block; }
        .no-posts h3 { color: #333; margin-bottom: 8px; }
        .no-posts p  { color: #888; margin-bottom: 20px; }
        .btn-primary {
            display: inline-block; padding: 11px 28px;
            background: linear-gradient(135deg, #e94560, #0f3460);
            color: white; text-decoration: none; border-radius: 50px;
            font-weight: 600; transition: transform 0.2s;
        }
        .btn-primary:hover { transform: translateY(-2px); }

        .img-modal {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.92); z-index: 9999;
            align-items: center; justify-content: center;
        }
        .img-modal.open { display: flex; }
        .img-modal img { max-width: 92vw; max-height: 90vh; object-fit: contain; border-radius: 8px; }
        .img-modal-close {
            position: absolute; top: 15px; right: 15px;
            background: white; border: none;
            width: 42px; height: 42px; border-radius: 50%;
            font-size: 1.3rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }

        @media (max-width: 640px) {
            .post-action-btn span { display: none; }
            .actualites-container { padding: 0 8px; }
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
            <button class="nav-toggle-btn" onclick="ouvrirNavPanel()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        </div>
    </nav>

<div class="actualites-container">
    <div class="actualites-header">
        <i class="fas fa-newspaper"></i>
        <div>
            <h1>Actualités</h1>
            <p>Découvrez les dernières publications des membres</p>
        </div>
    </div>

    <?php if (empty($publications)): ?>
        <div class="no-posts">
            <i class="fas fa-box-open"></i>
            <h3>Aucune publication pour le moment</h3>
            <p>Les autres membres n'ont pas encore publié d'articles.</p>
            <a href="acheter.php" class="btn-primary">Explorer les articles</a>
        </div>
    <?php else: ?>
        <?php foreach ($publications as $post):
            $photos     = array_filter(array_map('trim', explode(',', $post['photos'] ?? '')));
            $photosList = array_values($photos);
            $mainPhoto  = !empty($photosList) ? $base_path . reset($photosList) : $base_path . 'uploads/articles/default.jpg';
            $dejaLike   = (int)$post['deja_like'] > 0;
        ?>
        <div class="post-card" id="post-<?php echo $post['id']; ?>">

            <div class="post-header">
                <div class="post-avatar"><?php echo function_exists('initiales') ? initiales($post['pseudo']) : strtoupper(substr($post['pseudo'],0,2)); ?></div>
                <div class="post-user-info">
                    <h3><?php echo function_exists('esc') ? esc($post['pseudo']) : htmlspecialchars($post['pseudo']); ?></h3>
                    <div class="post-meta">
                        <span><i class="far fa-clock"></i><?php echo function_exists('tempsRelatif') ? tempsRelatif($post['date_publication']) : date('d/m/Y', strtotime($post['date_publication'])); ?></span>
                        <span><i class="fas fa-map-marker-alt"></i><?php echo function_exists('esc') ? esc($post['auteur_ville'] ?: 'Cameroun') : 'Cameroun'; ?></span>
                    </div>
                </div>
            </div>

            <div class="post-image-wrap" onclick="openModal('<?php echo htmlspecialchars($mainPhoto, ENT_QUOTES); ?>')">
                <img src="<?php echo htmlspecialchars($mainPhoto, ENT_QUOTES); ?>"
                     alt="<?php echo function_exists('esc') ? esc($post['titre']) : htmlspecialchars($post['titre']); ?>"
                     loading="lazy">
                <div class="post-image-overlay">
                    <span><i class="fas fa-expand-alt"></i> Agrandir</span>
                </div>
            </div>

            <?php if (count($photosList) > 1): ?>
            <div class="post-thumbnails">
                <?php foreach (array_slice($photosList, 1, 5) as $ph): ?>
                    <img src="<?php echo htmlspecialchars($base_path . $ph); ?>"
                         alt="" class="post-thumbnail" loading="lazy"
                         onclick="openModal('<?php echo htmlspecialchars($base_path . $ph, ENT_QUOTES); ?>')">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="post-content">
                <h2 class="post-title"><?php echo function_exists('esc') ? esc($post['titre']) : htmlspecialchars($post['titre']); ?></h2>
                <div class="post-price"><?php echo function_exists('formatPrix') ? formatPrix((float)$post['prix']) : $post['prix'] . ' FCFA'; ?></div>
                <?php if (!empty($post['description'])): ?>
                    <p class="post-desc">
                        <?php
                        $desc = $post['description'];
                        $txt = mb_strlen($desc) > 160 ? mb_substr($desc, 0, 160) . '…' : $desc;
                        echo function_exists('esc') ? esc($txt) : htmlspecialchars($txt);
                        ?>
                    </p>
                <?php endif; ?>
                <div class="post-tags">
                    <?php if ($post['categorie']): ?>
                        <span class="post-tag"><i class="fas fa-tag"></i><?php echo function_exists('esc') ? esc($post['categorie']) : htmlspecialchars($post['categorie']); ?></span>
                    <?php endif; ?>
                    <span class="post-tag"><i class="fas fa-star"></i><?php echo function_exists('esc') ? esc($post['etat']) : htmlspecialchars($post['etat']); ?></span>
                </div>
            </div>

            <div class="post-stats">
                <span id="likes-count-<?php echo $post['id']; ?>">
                    <i class="fas fa-heart" style="color:#e94560"></i>
                    <?php echo $post['nb_likes']; ?> j'aime
                </span>
                <span id="comments-count-<?php echo $post['id']; ?>">
                    <i class="fas fa-comment" style="color:#0f3460"></i>
                    <?php echo $post['nb_commentaires']; ?> commentaire<?php echo $post['nb_commentaires'] != 1 ? 's' : ''; ?>
                </span>
                <span>
                    <i class="fas fa-eye" style="color:#aaa"></i>
                    <?php echo $post['vues']; ?> vues
                </span>
            </div>

            <div class="post-actions">
                <button class="post-action-btn <?php echo $dejaLike ? 'liked' : ''; ?>"
                        id="like-btn-<?php echo $post['id']; ?>"
                        onclick="toggleLike(<?php echo $post['id']; ?>)">
                    <i class="<?php echo $dejaLike ? 'fas' : 'far'; ?> fa-heart"></i>
                    <span>J'aime</span>
                </button>
                <button class="post-action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                    <i class="far fa-comment"></i>
                    <span>Commenter</span>
                </button>
                <a href="article.php?id=<?php echo $post['id']; ?>" class="post-action-btn">
                    <i class="far fa-eye"></i>
                    <span>Voir détail</span>
                </a>
            </div>

            <div class="post-comments" id="comments-<?php echo $post['id']; ?>" style="display:none">
                <div class="comments-list" id="comments-list-<?php echo $post['id']; ?>">
                    <div class="comments-loading">Chargement…</div>
                </div>
                <div class="comment-input-row">
                    <div class="mini-avatar"><?php echo function_exists('initiales') ? initiales($_SESSION['user_pseudo'] ?? 'U') : 'U'; ?></div>
                    <input type="text" id="comment-input-<?php echo $post['id']; ?>"
                           placeholder="Écrire un commentaire…"
                           onkeydown="if(event.key==='Enter') envoyerCommentaire(<?php echo $post['id']; ?>)">
                    <button onclick="envoyerCommentaire(<?php echo $post['id']; ?>)">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="img-modal" id="imgModal" onclick="closeModal()">
    <button class="img-modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
    <img src="" id="modalImg" alt="" onclick="event.stopPropagation()">
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
        <li><a href="actualites.php" class="active"><i class="fas fa-newspaper"></i> Actualités</a></li>
        <li><a href="vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
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
    // ============================================================
    //  MONË BANTU — Actualités JS
    // ============================================================

    const BASE = '<?php echo $base_path; ?>php/';

    /* ---- Menu panel ---- */
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

    /* ---- Modal image ---- */
    function openModal(src) {
        document.getElementById('modalImg').src = src;
        document.getElementById('imgModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('imgModal').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeModal(); fermerNavPanel(); }
    });

    /* ---- Like / Unlike ---- */
    function toggleLike(articleId) {
        const btn     = document.getElementById('like-btn-' + articleId);
        const icon    = btn.querySelector('i');
        const countEl = document.getElementById('likes-count-' + articleId);

        fetch(BASE + 'like_articles.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'article_id=' + articleId
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!data.success) return;
            if (data.action === 'liked') {
                btn.classList.add('liked');
                icon.classList.replace('far', 'fas');
            } else {
                btn.classList.remove('liked');
                icon.classList.replace('fas', 'far');
            }
            countEl.innerHTML = '<i class="fas fa-heart" style="color:#e94560"></i> ' + data.nb_likes + " j'aime";
        })
        .catch(console.error);
    }

    /* ---- Commentaires ---- */
    const loadedComments = {};

    function toggleComments(articleId) {
        const zone = document.getElementById('comments-' + articleId);
        const open = zone.style.display === 'none' || zone.style.display === '';
        zone.style.display = open ? 'block' : 'none';
        if (open && !loadedComments[articleId]) {
            chargerCommentaires(articleId);
        }
    }

    function chargerCommentaires(articleId) {
        const list = document.getElementById('comments-list-' + articleId);
        list.innerHTML = '<div class="comments-loading"><i class="fas fa-spinner fa-spin"></i> Chargement…</div>';
        fetch(BASE + 'get_commentaires.php?article_id=' + articleId)
        .then(function(r){ return r.json(); })
        .then(function(data){
            loadedComments[articleId] = true;
            afficherCommentaires(articleId, data);
        })
        .catch(function(){
            list.innerHTML = '<div class="comments-loading">Erreur de chargement.</div>';
        });
    }

    function afficherCommentaires(articleId, commentaires) {
        const list = document.getElementById('comments-list-' + articleId);
        if (!commentaires.length) {
            list.innerHTML = '<div class="comments-loading">Aucun commentaire. Soyez le premier !</div>';
            return;
        }
        list.innerHTML = commentaires.map(function(c){
            return '<div class="comment-item">' +
                '<div class="comment-avatar">' + c.initiales + '</div>' +
                '<div class="comment-bubble">' +
                    '<strong>' + c.pseudo + '</strong>' +
                    '<p>' + escHtml(c.commentaire) + '</p>' +
                    '<div class="comment-time">' + c.date + '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    function envoyerCommentaire(articleId) {
        const input = document.getElementById('comment-input-' + articleId);
        const texte = input.value.trim();
        if (!texte) return;
        input.disabled = true;

        fetch(BASE + 'commenter.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    'article_id=' + articleId + '&commentaire=' + encodeURIComponent(texte)
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            input.disabled = false;
            if (data.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!data.success) { alert(data.message || 'Erreur.'); return; }

            input.value = '';
            const list = document.getElementById('comments-list-' + articleId);
            const c    = data.commentaire;

            const emptyMsg = list.querySelector('.comments-loading');
            if (emptyMsg) emptyMsg.remove();

            const item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = '<div class="comment-avatar">' + c.initiales + '</div>' +
                '<div class="comment-bubble">' +
                    '<strong>' + c.pseudo + '</strong>' +
                    '<p>' + escHtml(c.commentaire) + '</p>' +
                    '<div class="comment-time">' + c.date + '</div>' +
                '</div>';
            list.appendChild(item);
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            const countEl = document.getElementById('comments-count-' + articleId);
            const current = parseInt(countEl.textContent) || 0;
            countEl.innerHTML = '<i class="fas fa-comment" style="color:#0f3460"></i> ' + (current + 1) + ' commentaire' + (current + 1 !== 1 ? 's' : '');
            loadedComments[articleId] = true;
        })
        .catch(function(err){
            input.disabled = false;
            console.error(err);
            alert('Erreur lors de l\'envoi.');
        });
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    </script>
</body>
</html>