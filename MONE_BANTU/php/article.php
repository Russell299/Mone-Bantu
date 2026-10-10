<?php
// ============================================================
//  MONË BANTU — Détail d'un article
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

$article_id = (int)($_GET['id'] ?? 0);
if ($article_id <= 0) redirect($base_path . 'index.php');

$stmt = $pdo->prepare("
    SELECT a.*, u.id AS vendeur_uid, u.pseudo, u.note_moyenne, u.nombre_ventes,
           u.prenom AS vendeur_prenom, u.nom AS vendeur_nom, u.profession AS vendeur_profession,
           u.region AS vendeur_region, u.departement AS vendeur_departement,
           u.arrondissement AS vendeur_arrondissement,
           u.ville AS vendeur_ville, u.photo_profil
    FROM articles a
    JOIN utilisateurs u ON a.vendeur_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$article_id]);
$article = $stmt->fetch();
if (!$article) redirect($base_path . 'index.php');

$user_id = function_exists('userId') ? userId() : 0;

$vendeur_est_boutique = ($article['mode_vente'] === 'boutique');
$taux_commission      = $vendeur_est_boutique ? (defined('TAUX_COMMISSION_BOUTIQUE') ? TAUX_COMMISSION_BOUTIQUE : 0.15) : (defined('TAUX_COMMISSION_PARTICULIER') ? TAUX_COMMISSION_PARTICULIER : 0.20);
$commission_prevue    = (int)round((int)$article['prix'] * $taux_commission);
$total_prevu          = (int)$article['prix'] + $commission_prevue;

$virement_direct_ok = false;
if (function_exists('estConnecte') && estConnecte() && !$vendeur_est_boutique && $user_id != $article['vendeur_uid']) {
    if (function_exists('virementDirectDisponible')) {
        $virement_direct_ok = virementDirectDisponible($pdo, $user_id, (int)$article['vendeur_uid']);
    }
}

if (empty($_SESSION['vus'][$article_id])) {
    try {
        $pdo->prepare("UPDATE articles SET vues = vues + 1 WHERE id = ?")->execute([$article_id]);
        $_SESSION['vus'][$article_id] = true;
    } catch (Throwable $e) {}
}

$est_favori = false; $deja_like = false;
if (function_exists('estConnecte') && estConnecte()) {
    try {
        $stmt2 = $pdo->prepare("SELECT id FROM favoris WHERE utilisateur_id=? AND article_id=?");
        $stmt2->execute([$user_id, $article_id]);
        $est_favori = (bool)$stmt2->fetch();
        $stmt3 = $pdo->prepare("SELECT id FROM likes WHERE utilisateur_id=? AND article_id=?");
        $stmt3->execute([$user_id, $article_id]);
        $deja_like = (bool)$stmt3->fetch();
    } catch (Throwable $e) {}
}

$nb_likes = 0; $nb_comments = 0;
try {
    $stmt_nb = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE article_id=?");
    $stmt_nb->execute([$article_id]);
    $nb_likes = (int)$stmt_nb->fetchColumn();
    $stmt_nc = $pdo->prepare("SELECT COUNT(*) FROM commentaires WHERE article_id=?");
    $stmt_nc->execute([$article_id]);
    $nb_comments = (int)$stmt_nc->fetchColumn();
} catch (Throwable $e) {}

$autres_articles = [];
try {
    $autres = $pdo->prepare("SELECT * FROM articles WHERE vendeur_id=? AND id!=? AND statut='disponible' ORDER BY date_publication DESC LIMIT 4");
    $autres->execute([$article['vendeur_uid'], $article_id]);
    $autres_articles = $autres->fetchAll();
} catch (Throwable $e) {}

$photos = array_filter(array_map('trim', explode(',', $article['photos'] ?? '')));
$photos = array_values($photos);
$photo_principale = !empty($photos) ? $base_path . $photos[0] : $base_path . 'uploads/articles/default.jpg';
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo function_exists('esc') ? esc($article['titre']) : htmlspecialchars($article['titre']); ?> — MONË BANTU</title>
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
    <nav style="padding:15px 0;font-size:0.85rem;color:#888;">
        <a href="<?php echo $base_path; ?>index.php" style="color:#e94560">Accueil</a> &rsaquo;
        <a href="acheter.php" style="color:#e94560">Acheter</a> &rsaquo;
        <?php echo function_exists('esc') ? esc($article['titre']) : htmlspecialchars($article['titre']); ?>
    </nav>

    <div class="article-detail">
        <div class="article-images">
            <img src="<?php echo htmlspecialchars($photo_principale); ?>"
                 alt="<?php echo function_exists('esc') ? esc($article['titre']) : htmlspecialchars($article['titre']); ?>"
                 class="main-image" id="mainImage">

            <?php if (count($photos) > 1): ?>
            <div class="image-thumbnails">
                <?php foreach ($photos as $i => $ph): ?>
                    <img src="<?php echo htmlspecialchars($base_path . $ph); ?>"
                         alt=""
                         class="thumbnail <?php echo $i === 0 ? 'active' : ''; ?>"
                         onclick="changeMainImage('<?php echo htmlspecialchars($base_path . $ph, ENT_QUOTES); ?>', this)"
                         loading="lazy">
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="display:flex;gap:18px;margin-top:14px;color:#666;font-size:0.9rem;">
                <span id="like-count-detail"><i class="fas fa-heart" style="color:#e94560"></i> <?php echo $nb_likes; ?> j'aime</span>
                <span><i class="fas fa-comment" style="color:#0f3460"></i> <?php echo $nb_comments; ?> commentaires</span>
                <span><i class="fas fa-eye" style="color:#aaa"></i> <?php echo (int)$article['vues']; ?> vues</span>
            </div>
        </div>

        <div class="article-info">
            <h1><?php echo function_exists('esc') ? esc($article['titre']) : htmlspecialchars($article['titre']); ?></h1>
            <div class="article-price-large"><?php echo function_exists('formatPrix') ? formatPrix((float)$article['prix']) : number_format($article['prix'], 0, ',', ' ') . ' FCFA'; ?></div>

            <div class="article-meta-detail">
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-star"></i> État</span>
                    <span class="meta-value"><?php echo function_exists('esc') ? esc($article['etat']) : htmlspecialchars($article['etat']); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-tag"></i> Catégorie</span>
                    <span class="meta-value"><?php echo function_exists('esc') ? esc($article['categorie'] ?: 'Non spécifiée') : htmlspecialchars($article['categorie'] ?: 'Non spécifiée'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-map-marker-alt"></i> Ville</span>
                    <span class="meta-value"><?php echo function_exists('esc') ? esc($article['ville'] ?: 'Cameroun') : htmlspecialchars($article['ville'] ?: 'Cameroun'); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><i class="far fa-calendar-alt"></i> Publié</span>
                    <span class="meta-value"><?php echo function_exists('tempsRelatif') ? tempsRelatif($article['date_publication']) : date('d/m/Y', strtotime($article['date_publication'])); ?></span>
                </div>
                <div class="meta-item">
                    <span class="meta-label"><i class="fas fa-check-circle"></i> Statut</span>
                    <span class="meta-value" style="color:<?php echo $article['statut']==='disponible' ? '#00a86b' : '#e94560'; ?>">
                        <?php echo ucfirst(function_exists('esc') ? esc($article['statut']) : htmlspecialchars($article['statut'])); ?>
                    </span>
                </div>
            </div>

            <div class="vendeur-card">
                <h3>À propos du vendeur</h3>
                <div class="vendeur-info">
                    <div class="vendeur-avatar-large"><?php echo function_exists('initiales') ? initiales($article['pseudo']) : strtoupper(substr($article['pseudo'], 0, 2)); ?></div>
                    <div class="vendeur-details">
                        <p><strong><?php echo function_exists('esc') ? esc($article['pseudo']) : htmlspecialchars($article['pseudo']); ?></strong></p>
                        <p style="color:var(--text-muted);font-size:0.85rem;">
                            <?php echo function_exists('esc') ? esc(trim($article['vendeur_prenom'] . ' ' . $article['vendeur_nom'])) : htmlspecialchars(trim($article['vendeur_prenom'] . ' ' . $article['vendeur_nom'])); ?>
                            <?php if (!empty($article['vendeur_profession'])): ?>
                                — <?php echo function_exists('esc') ? esc($article['vendeur_profession']) : htmlspecialchars($article['vendeur_profession']); ?>
                            <?php endif; ?>
                        </p>
                        <p class="vendeur-note">
                            <?php $note = (float)($article['note_moyenne'] ?? 0);
                            for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color:<?php echo $i <= $note ? '#f1c40f' : '#ddd'; ?>"></i>
                            <?php endfor; ?>
                            <small style="margin-left:5px;">(<?php echo (int)($article['nombre_ventes'] ?? 0); ?> vente<?php echo $article['nombre_ventes'] != 1 ? 's' : ''; ?>)</small>
                        </p>
                        <p><i class="fas fa-map-marker-alt"></i>
                            <?php
                            $loc_parts = array_filter([$article['ville'] ?: null, $article['region'] ?: null]);
                            echo function_exists('esc') ? esc(!empty($loc_parts) ? implode(', ', $loc_parts) : 'Cameroun') : htmlspecialchars(!empty($loc_parts) ? implode(', ', $loc_parts) : 'Cameroun');
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if (function_exists('estConnecte') && estConnecte()): ?>
                <?php if ($user_id != $article['vendeur_uid']): ?>
                    <?php if ($article['statut'] === 'disponible'): ?>
                        <div class="achat-recap-mini">
                            <span>Prix : <strong><?php echo function_exists('formatPrix') ? formatPrix($article['prix']) : $article['prix']; ?></strong></span>
                            <span>Frais (<?php echo (int)($taux_commission * 100); ?>%) : <strong><?php echo function_exists('formatPrix') ? formatPrix($commission_prevue) : $commission_prevue; ?></strong></span>
                            <span class="achat-recap-total">Total à payer : <strong><?php echo function_exists('formatPrix') ? formatPrix($total_prevu) : $total_prevu; ?></strong></span>
                        </div>
                        <?php if ($vendeur_est_boutique): ?>
                            <button class="btn-primary-full" id="btnAjouterPanier" onclick="ajouterAuPanierArticle(<?php echo $article_id; ?>, this)">
                                <i class="fas fa-cart-plus"></i> Ajouter au panier
                            </button>
                        <?php else: ?>
                            <?php if ($virement_direct_ok): ?>
                                <div class="mode-virement-choix">
                                    <label class="mode-virement-opt">
                                        <input type="radio" name="mode_virement" value="securise" checked> Paiement sécurisé (recommandé)
                                    </label>
                                    <label class="mode-virement-opt">
                                        <input type="radio" name="mode_virement" value="direct"> Virement direct (vendeur payé immédiatement)
                                    </label>
                                </div>
                            <?php endif; ?>
                            <button class="btn-primary-full" id="btnAcheterDirect" onclick="acheterDirect(<?php echo $article_id; ?>, this)">
                                <i class="fas fa-credit-card"></i> Acheter maintenant
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <a href="messagerie.php?user=<?php echo $article['vendeur_uid']; ?>" class="btn-contact">
                        <i class="fas fa-envelope"></i> Contacter le vendeur
                    </a>

                    <button class="btn-favori-detail <?php echo $est_favori ? 'active' : ''; ?>"
                            id="favori-btn" onclick="toggleFavori(<?php echo $article_id; ?>)">
                        <i class="<?php echo $est_favori ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span id="favori-label"><?php echo $est_favori ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?></span>
                    </button>

                    <button class="btn-favori-detail <?php echo $deja_like ? 'active' : ''; ?>"
                            id="like-btn-detail" onclick="toggleLike(<?php echo $article_id; ?>)"
                            style="margin-top:6px;">
                        <i class="<?php echo $deja_like ? 'fas' : 'far'; ?> fa-thumbs-up"></i>
                        <span>J'aime</span>
                    </button>

                    <button class="btn-favori-detail" id="btn-partager"
                            onclick="partagerArticle(<?php echo $article_id; ?>, this)"
                            style="margin-top:6px;">
                        <i class="fas fa-share-nodes"></i>
                        <span>Partager</span>
                    </button>

                    <button class="btn-favori-detail" id="btn-signaler-vendeur"
                            onclick="ouvrirSignalementUtilisateur(<?php echo $article['vendeur_uid']; ?>, 'article', <?php echo $article_id; ?>)"
                            style="margin-top:6px;color:var(--text-muted);">
                        <i class="fas fa-flag"></i>
                        <span>Signaler ce vendeur</span>
                    </button>
                <?php else: ?>
                    <a href="vendre.php?edit=<?php echo $article_id; ?>" class="btn-contact">
                        <i class="fas fa-edit"></i> Modifier mon annonce
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="login.php" class="btn-contact">
                    <i class="fas fa-sign-in-alt"></i> Connectez-vous pour contacter le vendeur
                </a>
            <?php endif; ?>

            <?php if ($article['description']): ?>
            <div class="description">
                <h3>Description</h3>
                <p><?php echo nl2br(function_exists('esc') ? esc($article['description']) : htmlspecialchars($article['description'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="background:white;border-radius:12px;padding:28px;margin:25px 0;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
        <h2 style="margin-bottom:20px;font-size:1.2rem;">
            <i class="fas fa-comments" style="color:#e94560"></i>
            Commentaires (<span id="total-comments"><?php echo $nb_comments; ?></span>)
        </h2>
        <div id="comments-list" style="margin-bottom:18px;">
            <div style="text-align:center;color:#aaa;padding:20px;">
                <i class="fas fa-spinner fa-spin"></i> Chargement…
            </div>
        </div>

        <?php if (function_exists('estConnecte') && estConnecte()): ?>
        <div style="display:flex;gap:10px;align-items:center;border-top:1px solid #f0f0f0;padding-top:16px;">
            <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#e94560,#0f3460);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
                <?php echo function_exists('initiales') ? initiales($_SESSION['user_pseudo'] ?? 'U') : 'U'; ?>
            </div>
            <input type="text" id="comment-input" placeholder="Écrire un commentaire…"
                   style="flex:1;padding:10px 15px;border:2px solid #eee;border-radius:50px;font-family:'Poppins',sans-serif;font-size:0.9rem;"
                   onkeydown="if(event.key==='Enter') envoyerComment()">
            <button onclick="envoyerComment()"
                    style="width:40px;height:40px;border-radius:50%;background:#e94560;border:none;color:white;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <?php else: ?>
        <p style="text-align:center;color:#888;border-top:1px solid #f0f0f0;padding-top:14px;">
            <a href="login.php" style="color:#e94560;font-weight:600;">Connectez-vous</a> pour laisser un commentaire.
        </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($autres_articles)): ?>
    <div class="autres-articles">
        <h2>Autres articles de <strong><?php echo function_exists('esc') ? esc($article['pseudo']) : htmlspecialchars($article['pseudo']); ?></strong></h2>
        <div class="articles-grid">
            <?php foreach ($autres_articles as $a):
                $phs = array_filter(array_map('trim', explode(',', $a['photos'] ?? '')));
                $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
            ?>
            <a href="article.php?id=<?php echo $a['id']; ?>" class="article-card" style="text-decoration:none;color:inherit;">
                <div class="article-image" style="height:150px;">
                    <img src="<?php echo htmlspecialchars($ph); ?>" alt="" loading="lazy">
                    <span class="article-etat"><?php echo function_exists('esc') ? esc($a['etat']) : htmlspecialchars($a['etat']); ?></span>
                </div>
                <div class="article-info">
                    <h4 style="font-size:0.95rem;margin-bottom:5px;"><?php echo function_exists('esc') ? esc($a['titre']) : htmlspecialchars($a['titre']); ?></h4>
                    <p class="article-price" style="font-size:1rem;"><?php echo function_exists('formatPrix') ? formatPrix((float)$a['prix']) : $a['prix']; ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
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

<div class="modal-reel" id="modalSignalementUser">
    <div class="modal-reel-box">
        <button class="modal-reel-fermer" onclick="fermerSignalementUtilisateur()"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-flag"></i> Signaler cet utilisateur</h3>
        <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
            Décris le problème — l'équipe Monë Bantu va l'examiner.
        </p>
        <form id="formSignalementUser">
            <div class="form-group">
                <label style="font-size:0.85rem;">Gravité</label>
                <div class="mode-virement-choix">
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="leger"> Léger — désaccord, retard...</label>
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="moyen" checked> Moyen — comportement suspect</label>
                    <label class="mode-virement-opt"><input type="radio" name="gravite_signalement" value="grave"> Grave — escroquerie avérée</label>
                </div>
            </div>
            <div class="form-group">
                <textarea id="motif_signalement_user" rows="4" maxlength="500" placeholder="Décris ce qui s'est passé..." required></textarea>
            </div>
            <div class="form-group">
                <label style="font-size:0.85rem;">Preuve (optionnel)</label>
                <div class="upload-zone" onclick="document.getElementById('preuve_signalement').click()">
                    <i class="fas fa-camera"></i>
                    <p id="preuveLabel"><strong>Cliquez</strong> pour joindre une preuve</p>
                    <input type="file" id="preuve_signalement" accept="image/jpeg,image/png,image/webp" style="display:none;">
                </div>
            </div>
            <button type="submit" class="btn-primary-full" id="btnEnvoyerSignalementUser">
                <i class="fas fa-paper-plane"></i> Envoyer le signalement
            </button>
        </form>
    </div>
</div>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
    const BASE = '<?php echo $base_path; ?>php/';
    const ARTICLE_ID = <?php echo $article_id; ?>;

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

    function changeMainImage(src, el) {
        document.getElementById('mainImage').src = src;
        document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }

    function toggleLike(id) {
        fetch(BASE + 'like_articles.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + id
        })
        .then(r => r.json())
        .then(d => {
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) return;
            const btn  = document.getElementById('like-btn-detail');
            const icon = btn.querySelector('i');
            btn.classList.toggle('active', d.action === 'liked');
            icon.className = d.action === 'liked' ? 'fas fa-thumbs-up' : 'far fa-thumbs-up';
            document.getElementById('like-count-detail').innerHTML =
                '<i class="fas fa-heart" style="color:#e94560"></i> ' + d.nb_likes + " j'aime";
        });
    }

    function toggleFavori(id) {
        fetch(BASE + 'toggle_favor.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + id
        })
        .then(r => r.json())
        .then(d => {
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) return;
            const btn   = document.getElementById('favori-btn');
            const icon  = btn.querySelector('i');
            const label = document.getElementById('favori-label');
            btn.classList.toggle('active', d.added);
            icon.className = d.added ? 'fas fa-heart' : 'far fa-heart';
            label.textContent = d.added ? 'Retirer des favoris' : 'Ajouter aux favoris';
        });
    }

    function acheterDirect(id, btn) {
        if (!confirm("Confirmer l'achat de cet article ?")) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
        const modeChoisi = document.querySelector('input[name="mode_virement"]:checked')?.value || 'securise';
        fetch(BASE + 'acheter_article.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + id + '&mode_virement=' + modeChoisi
        })
        .then(r => r.json())
        .then(d => {
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) {
                showToast(d.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-credit-card"></i> Acheter maintenant';
                return;
            }
            showToast(d.message || 'Commande enregistrée.', 'success');
            btn.innerHTML = '<i class="fas fa-check"></i> Commande enregistrée';
        });
    }

    function ajouterAuPanierArticle(id, btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
        fetch(BASE + 'gerer_panier.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + id + '&action=ajouter'
        })
        .then(r => r.json())
        .then(d => {
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) {
                showToast(d.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter au panier';
                return;
            }
            showToast('Ajouté au panier', 'success');
            btn.innerHTML = '<i class="fas fa-check"></i> Dans le panier';
        });
    }

    function partagerArticle(id, btn) {
        const url = window.location.href;
        const titre = document.title;
        fetch(BASE + 'partager_article.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + id
        }).catch(() => {});
        if (navigator.share) {
            navigator.share({ title: titre, url: url }).catch(() => {});
        } else {
            navigator.clipboard.writeText(url).then(() => {
                showToast('Lien copié !', 'success');
            }).catch(() => { showToast(url, 'success'); });
        }
    }

    function escHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function chargerCommentaires() {
        fetch(BASE + 'get_commentaires.php?article_id=' + ARTICLE_ID)
        .then(r => r.json())
        .then(list => {
            const el = document.getElementById('comments-list');
            if (!list.length) {
                el.innerHTML = '<p style="text-align:center;color:#aaa;padding:20px;">Aucun commentaire. Soyez le premier !</p>';
                return;
            }
            el.innerHTML = list.map(c => `
                <div style="display:flex;gap:10px;margin-bottom:14px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#e94560,#0f3460);color:white;display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;flex-shrink:0;">${c.initiales}</div>
                    <div style="flex:1;background:#f0f2f5;padding:10px 14px;border-radius:12px;">
                        <strong style="font-size:0.85rem;">${escHtml(c.pseudo)}</strong>
                        <p style="font-size:0.88rem;color:#444;margin:3px 0;">${escHtml(c.commentaire)}</p>
                        <small style="color:#aaa;">${c.date}</small>
                    </div>
                </div>
            `).join('');
        });
    }

    function envoyerComment() {
        const input = document.getElementById('comment-input');
        const texte = input.value.trim();
        if (!texte) return;
        input.disabled = true;
        fetch(BASE + 'commenter.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'article_id=' + ARTICLE_ID + '&commentaire=' + encodeURIComponent(texte)
        })
        .then(r => r.json())
        .then(d => {
            input.disabled = false;
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) { alert(d.message || 'Erreur'); return; }
            input.value = '';
            chargerCommentaires();
            const total = document.getElementById('total-comments');
            total.textContent = parseInt(total.textContent) + 1;
        })
        .catch(() => { input.disabled = false; });
    }

    chargerCommentaires();

    let signalementCible = null;
    function ouvrirSignalementUtilisateur(userId, contexte, articleId) {
        signalementCible = { userId, contexte, articleId };
        document.getElementById('modalSignalementUser').classList.add('open');
    }
    function fermerSignalementUtilisateur() {
        document.getElementById('modalSignalementUser').classList.remove('open');
    }

    document.getElementById('formSignalementUser')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const motif = document.getElementById('motif_signalement_user').value.trim();
        if (motif.length < 5 || !signalementCible) return;
        const btn = document.getElementById('btnEnvoyerSignalementUser');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        const fd = new FormData();
        fd.append('utilisateur_signale_id', signalementCible.userId);
        fd.append('motif', motif);
        fd.append('contexte', signalementCible.contexte);
        fd.append('article_id', signalementCible.articleId || '');
        fd.append('gravite', document.querySelector('input[name="gravite_signalement"]:checked')?.value || 'moyen');
        const fichierPreuve = document.getElementById('preuve_signalement').files[0];
        if (fichierPreuve) fd.append('preuve', fichierPreuve);
        fetch(BASE + 'signaler_utilisateur.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le signalement';
            if (d.require_login) { window.location.href = BASE + 'login.php'; return; }
            if (!d.success) { showToast(d.error || 'Erreur', 'error'); return; }
            showToast('Signalement envoyé, merci.', 'success');
            fermerSignalementUtilisateur();
        });
    });
    </script>
</body>
</html>