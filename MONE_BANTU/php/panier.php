<?php
// ============================================================
//  MONË BANTU — Panier (achats boutique)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}

if (!function_exists('estConnecte') || !estConnecte()) {
    redirect($base_path . 'php/login.php');
}

$user_id = userId();
$erreur  = '';

// ---- Validation de la commande ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valider_commande'])) {
    $stmt = $pdo->prepare("
        SELECT p.article_id, a.prix, a.statut, a.vendeur_id
        FROM paniers p
        JOIN articles a ON a.id = p.article_id
        WHERE p.utilisateur_id = ?
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll();

    $items = array_filter($items, fn($i) => $i['statut'] === 'disponible');

    if (empty($items)) {
        $erreur = "Votre panier est vide ou ne contient plus d'articles disponibles.";
    } else {
        $montant_articles = array_sum(array_column($items, 'prix'));
        $taux_boutique    = defined('TAUX_COMMISSION_BOUTIQUE') ? TAUX_COMMISSION_BOUTIQUE : 0.15;
        $frais            = (int)round($montant_articles * $taux_boutique);
        $montant_total    = $montant_articles + $frais;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO commandes (acheteur_id, montant_articles, frais, montant_total, statut, date_commande)
                VALUES (?, ?, ?, ?, 'en_attente', NOW())
            ");
            $stmt->execute([$user_id, $montant_articles, $frais, $montant_total]);
            $commande_id = $pdo->lastInsertId();

            foreach ($items as $item) {
                $prix_item  = (int)$item['prix'];
                $part_frais = (int)round($prix_item * $taux_boutique);
                $total_item = $prix_item + $part_frais;

                $direct_ok = !empty($_POST['virement_direct'])
                    && function_exists('virementDirectDisponible')
                    && virementDirectDisponible($pdo, $user_id, (int)$item['vendeur_id']);

                if ($direct_ok) {
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (commande_id, article_id, acheteur_id, vendeur_id, prix_accepte, commission, montant_total, statut, mode_virement, date_paiement, date_confirmation, date_transaction)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'terminee', 'direct', NOW(), NOW(), NOW())
                    ");
                    $stmt->execute([$commande_id, $item['article_id'], $user_id, $item['vendeur_id'], $prix_item, $part_frais, $total_item]);

                    $pdo->prepare("INSERT IGNORE INTO soldes_vendeurs (utilisateur_id, solde) VALUES (?, 0)")
                        ->execute([$item['vendeur_id']]);
                    $pdo->prepare("UPDATE soldes_vendeurs SET solde = solde + ? WHERE utilisateur_id = ?")
                        ->execute([$prix_item, $item['vendeur_id']]);
                    $pdo->prepare("UPDATE articles SET statut = 'vendu' WHERE id = ?")->execute([$item['article_id']]);
                    $pdo->prepare("UPDATE utilisateurs SET nombre_ventes = nombre_ventes + 1 WHERE id = ?")->execute([$item['vendeur_id']]);
                    if (function_exists('notifierInteresses')) {
                        notifierInteresses($pdo, (int)$item['article_id'], 'vente', 'Une annonce qui vous intéresse vient d\'être vendue.', $user_id);
                    }
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (commande_id, article_id, acheteur_id, vendeur_id, prix_accepte, commission, montant_total, statut, mode_virement, date_transaction)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente_paiement', 'securise', NOW())
                    ");
                    $stmt->execute([$commande_id, $item['article_id'], $user_id, $item['vendeur_id'], $prix_item, $part_frais, $total_item]);

                    $pdo->prepare("UPDATE articles SET statut = 'reserve' WHERE id = ?")->execute([$item['article_id']]);
                }
            }

            $pdo->prepare("DELETE FROM paniers WHERE utilisateur_id = ?")->execute([$user_id]);
            $pdo->commit();
            redirect('panier.php?commande=' . $commande_id);
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreur = "Erreur lors de la création de la commande.";
        }
    }
}

// ---- Commande créée ? ----
$commande_confirmee = null;
if (isset($_GET['commande'])) {
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ? AND acheteur_id = ?");
    $stmt->execute([(int)$_GET['commande'], $user_id]);
    $commande_confirmee = $stmt->fetch();
}

// ---- Contenu du panier ----
$stmt = $pdo->prepare("
    SELECT p.id AS panier_id, a.id AS article_id, a.titre, a.prix, a.photos, a.statut, a.vendeur_id,
           u.pseudo AS vendeur_pseudo, b.nom_boutique
    FROM paniers p
    JOIN articles a ON a.id = p.article_id
    JOIN utilisateurs u ON u.id = a.vendeur_id
    LEFT JOIN boutiques b ON b.utilisateur_id = a.vendeur_id
    WHERE p.utilisateur_id = ?
    ORDER BY p.date_ajout DESC
");
$stmt->execute([$user_id]);
$panier = $stmt->fetchAll();

$virement_direct_dispo = false;
foreach ($panier as $item) {
    if (function_exists('virementDirectDisponible') && virementDirectDisponible($pdo, $user_id, (int)$item['vendeur_id'])) {
        $virement_direct_dispo = true;
        break;
    }
}

$taux_boutique    = defined('TAUX_COMMISSION_BOUTIQUE') ? TAUX_COMMISSION_BOUTIQUE : 0.15;
$montant_articles = array_sum(array_column($panier, 'prix'));
$frais            = (int)round($montant_articles * $taux_boutique);
$montant_total    = $montant_articles + $frais;
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon panier — MONË BANTU</title>
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

        /* Panier */
        .panier-layout { display: grid; grid-template-columns: 1fr 320px; gap: 20px; align-items: start; padding: 22px 0; }
        @media (max-width: 800px) { .panier-layout { grid-template-columns: 1fr; } }
        .panier-item {
            display: flex; gap: 14px; align-items: center;
            background: white; border-radius: var(--radius-md);
            padding: 12px; margin-bottom: 12px; box-shadow: var(--shadow-sm);
        }
        .panier-item img { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; }
        .panier-item-info { flex: 1; }
        .panier-item-info h4 { font-size: 0.95rem; margin-bottom: 3px; }
        .panier-item-info span { font-size: 0.8rem; color: var(--text-muted); }
        .panier-item-prix { font-weight: 700; color: var(--primary); white-space: nowrap; }
        .panier-item-suppr { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem; }
        .panier-item-suppr:hover { color: var(--primary); }
        .panier-recap {
            background: white; border-radius: var(--radius-md);
            padding: 22px; box-shadow: var(--shadow-md); position: sticky; top: 90px;
        }
        .panier-recap h3 { margin-bottom: 14px; }
        .panier-recap-ligne { display: flex; justify-content: space-between; margin-bottom: 10px; color: var(--text-muted); font-size: 0.92rem; }
        .panier-recap-total { display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; margin: 14px 0; padding-top: 14px; border-top: 1px solid var(--border); }
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
        <h1 class="page-title" style="margin:25px 0 20px;">
            <i class="fas fa-cart-shopping" style="color:var(--primary)"></i> Mon panier
        </h1>

        <?php if ($commande_confirmee): ?>
            <div class="alert alert-success" style="margin-bottom:20px;">
                <i class="fas fa-check-circle"></i>
                Commande #<?php echo $commande_confirmee['id']; ?> créée —
                <?php echo function_exists('formatPrix') ? formatPrix($commande_confirmee['montant_total']) : $commande_confirmee['montant_total'] . ' FCFA'; ?> à régler.
                Le paiement Mobile Money sera bientôt disponible.
            </div>
        <?php endif; ?>

        <?php if ($erreur): ?>
            <div class="alert alert-error" style="margin-bottom:20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo function_exists('esc') ? esc($erreur) : htmlspecialchars($erreur); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($panier)): ?>
            <div class="etat-vide">
                <i class="fas fa-cart-shopping"></i>
                <p>Votre panier est vide.</p>
                <a href="acheter.php" class="btn-action" style="text-decoration:none;">
                    <i class="fas fa-store"></i> Parcourir les boutiques
                </a>
            </div>
        <?php else: ?>
        <div class="panier-layout">
            <div>
                <?php foreach ($panier as $item):
                    $phs = array_filter(array_map('trim', explode(',', $item['photos'] ?? '')));
                    $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
                ?>
                <div class="panier-item" data-article="<?php echo $item['article_id']; ?>">
                    <img src="<?php echo htmlspecialchars($ph); ?>" alt="">
                    <div class="panier-item-info">
                        <h4><?php echo function_exists('esc') ? esc($item['titre']) : htmlspecialchars($item['titre']); ?></h4>
                        <span><i class="fas fa-shop"></i> <?php echo function_exists('esc') ? esc($item['nom_boutique'] ?: $item['vendeur_pseudo']) : htmlspecialchars($item['nom_boutique'] ?: $item['vendeur_pseudo']); ?></span>
                    </div>
                    <div class="panier-item-prix">
                        <?php echo function_exists('formatPrix') ? formatPrix($item['prix']) : $item['prix'] . ' FCFA'; ?>
                    </div>
                    <button class="panier-item-suppr" onclick="retirerDuPanier(<?php echo $item['article_id']; ?>, this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="panier-recap">
                <h3>Récapitulatif</h3>
                <div class="panier-recap-ligne">
                    <span>Articles</span>
                    <span><?php echo function_exists('formatPrix') ? formatPrix($montant_articles) : $montant_articles . ' FCFA'; ?></span>
                </div>
                <div class="panier-recap-ligne">
                    <span>Frais de service (<?php echo (int)($taux_boutique * 100); ?>%)</span>
                    <span><?php echo function_exists('formatPrix') ? formatPrix($frais) : $frais . ' FCFA'; ?></span>
                </div>
                <div class="panier-recap-total">
                    <span>Total</span>
                    <span><?php echo function_exists('formatPrix') ? formatPrix($montant_total) : $montant_total . ' FCFA'; ?></span>
                </div>
                <form method="POST">
                    <?php if ($virement_direct_dispo): ?>
                        <label class="mode-virement-opt" style="margin-bottom:12px;">
                            <input type="checkbox" name="virement_direct" value="1">
                            Virement direct pour les vendeurs de confiance
                        </label>
                    <?php endif; ?>
                    <button type="submit" name="valider_commande" class="btn-primary-full">
                        <i class="fas fa-credit-card"></i> Valider la commande
                    </button>
                </form>
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
            <li><a href="panier.php" class="active"><i class="fas fa-cart-shopping"></i> Panier</a></li>
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

    function retirerDuPanier(articleId, btn) {
        fetch('gerer_panier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'article_id=' + articleId + '&action=retirer'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast(data.error || 'Erreur', 'error');
            }
        });
    }
    </script>
</body>
</html>