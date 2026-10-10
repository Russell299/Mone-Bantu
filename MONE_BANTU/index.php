<?php
// ============================================================
//  MONË BANTU — Page d'accueil principale
// ============================================================
$base_path = '';
require_once 'php/config.php';

function tt(string $cle, string $defaut): string {
    return function_exists('t') ? t($cle) : $defaut;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MONË BANTU — Achat et vente entre particuliers au Cameroun</title>
    <meta name="description" content="MONË BANTU, la marketplace camerounaise.">
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        .navbar .container {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: nowrap;
        }
        .navbar .logo { flex-shrink: 0; }
        .navbar .search-bar { flex: 1; max-width: 420px; margin: 0 auto; }

        .nav-toggle-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            flex-shrink: 0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .nav-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(233,69,96,0.4);
        }
        .nav-toggle-btn i { font-size: 1rem; }

        .nav-panel {
            position: fixed;
            top: 0;
            right: -340px;
            width: 320px;
            max-width: 85vw;
            height: 100vh;
            background: white;
            box-shadow: -4px 0 24px rgba(0,0,0,0.15);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            transition: right 0.3s ease;
            overflow-y: auto;
        }
        .nav-panel.open { right: 0; }

        .nav-panel-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 2;
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
        .nav-panel-list a i {
            width: 22px; text-align: center; font-size: 1rem;
            color: var(--primary); flex-shrink: 0;
        }
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

        .recent-articles { padding: 60px 0; background: var(--light-bg); }
        .view-more { text-align: center; margin-top: 35px; }
        .view-more a {
            display: inline-block; padding: 13px 35px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white; border-radius: 50px; font-weight: 600;
            transition: all 0.3s; text-decoration: none;
        }
        .view-more a:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(233,69,96,0.4); }
        .no-articles { text-align:center; color:#888; grid-column:1/-1; padding: 40px; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="index.php">
                    <h1>MONË <span>BANTU</span></h1>
                    <p class="slogan"><?php echo tt('slogan', 'Achetez & vendez en toute confiance au Cameroun'); ?></p>
                </a>
            </div>

            <div class="search-bar">
                <form action="php/acheter.php" method="GET">
                    <input type="text" name="search" id="searchInput"
                           placeholder="<?php echo tt('recherche_placeholder', 'Que recherchez-vous ?'); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>

            <button class="nav-toggle-btn" onclick="ouvrirNavPanel()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </button>
        </div>
    </nav>

    <div class="reels-bar-wrap">
        <div class="container">
            <div class="reels-bar" id="reelsBar"></div>
        </div>
    </div>

    <section class="hero">
        <div class="container">
            <h2><?php echo tt('hero_titre', 'Bienvenue sur MONË BANTU'); ?></h2>
            <p><?php echo tt('hero_soustitre', 'La marketplace camerounaise — achetez et vendez facilement entre particuliers'); ?></p>
            <div class="hero-buttons">
                <a href="php/acheter.php" class="btn-primary">
                    <i class="fas fa-search"></i> <?php echo tt('hero_btn_parcourir', 'Parcourir les articles'); ?>
                </a>
                <a href="<?php echo (function_exists('estConnecte') && estConnecte()) ? 'php/vendre.php' : 'php/register.php'; ?>" class="btn-secondary">
                    <i class="fas fa-plus-circle"></i> <?php echo tt('hero_btn_vendre', 'Mettre en vente'); ?>
                </a>
            </div>
        </div>
    </section>

    <section class="categories">
        <div class="container">
            <h2 class="section-title">Catégories populaires</h2>
            <div class="categories-grid">
                <a href="php/acheter.php?categorie=Homme" class="category-card">
                    <i class="fas fa-tshirt"></i><h3>Mode Homme</h3><p>Vêtements, chaussures</p>
                </a>
                <a href="php/acheter.php?categorie=Femme" class="category-card">
                    <i class="fas fa-female"></i><h3>Mode Femme</h3><p>Vêtements, sacs</p>
                </a>
                <a href="php/acheter.php?categorie=Enfant" class="category-card">
                    <i class="fas fa-child"></i><h3>Enfants</h3><p>Vêtements, jouets</p>
                </a>
                <a href="php/acheter.php?categorie=%C3%89lectronique" class="category-card">
                    <i class="fas fa-mobile-alt"></i><h3>Électronique</h3><p>Téléphones, ordinateurs</p>
                </a>
                <a href="php/acheter.php?categorie=Maison" class="category-card">
                    <i class="fas fa-home"></i><h3>Maison</h3><p>Décoration, électroménager</p>
                </a>
                <a href="php/acheter.php?categorie=Sport" class="category-card">
                    <i class="fas fa-running"></i><h3>Sport</h3><p>Équipements et vêtements</p>
                </a>
            </div>
        </div>
    </section>

    <section class="recent-articles">
        <div class="container">
            <h2 class="section-title">Annonces récentes</h2>
            <div class="articles-grid" id="articles-container">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#e94560;"></i>
                    <p style="margin-top:10px;color:#888;">Chargement…</p>
                </div>
            </div>
            <div class="view-more">
                <a href="php/acheter.php">Voir tous les articles <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </section>

    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">Comment ça marche ?</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <i class="fas fa-camera"></i>
                    <h3>Photo &amp; description</h3>
                    <p>Photographie ton article, décris-le et fixe ton prix</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <i class="fas fa-upload"></i>
                    <h3>Publication</h3>
                    <p>Ton annonce est en ligne en quelques secondes</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <i class="fas fa-comments"></i>
                    <h3>Négociation</h3>
                    <p>Discute directement avec l'acheteur via la messagerie</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <i class="fas fa-handshake"></i>
                    <h3>Transaction</h3>
                    <p>Rencontrez-vous et finalisez la vente</p>
                </div>
            </div>
        </div>
    </section>

    <section class="payment-methods">
        <div class="container">
            <h2 class="section-title">Moyens de paiement acceptés</h2>
            <div class="payment-grid">
                <div class="payment-item"><i class="fas fa-mobile-alt"></i><p>Orange Money</p></div>
                <div class="payment-item"><i class="fas fa-mobile-alt"></i><p>MTN Mobile Money</p></div>
                <div class="payment-item"><i class="fas fa-university"></i><p>Express Union</p></div>
                <div class="payment-item"><i class="fas fa-money-bill-wave"></i><p>Espèces</p></div>
            </div>
            <p class="payment-note">
                <i class="fas fa-info-circle"></i>
                L'acheteur et le vendeur s'arrangent directement pour le paiement et la remise de l'article.
            </p>
        </div>
    </section>

    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">Ils nous font confiance</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-avatar">M</div>
                    <p class="testimonial-text">J'ai vendu mon téléphone en 2 jours. Paiement rapide via Orange Money !</p>
                    <p class="testimonial-author">— Marc de Douala</p>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-avatar">F</div>
                    <p class="testimonial-text">J'ai trouvé une belle robe pour ma fille. Le vendeur était super sympa.</p>
                    <p class="testimonial-author">— Fatima de Yaoundé</p>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-avatar">P</div>
                    <p class="testimonial-text">Plateforme simple et efficace. Je recommande vivement !</p>
                    <p class="testimonial-author">— Paul de Garoua</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <h2>Prêt à vendre ou acheter ?</h2>
            <p>Rejoins des milliers de Camerounais qui utilisent déjà MONË BANTU</p>
            <div class="cta-buttons">
                <?php if (!function_exists('estConnecte') || !estConnecte()): ?>
                    <a href="php/register.php" class="btn-primary">Créer un compte</a>
                <?php endif; ?>
                <a href="php/acheter.php" class="btn-secondary">Explorer les annonces</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>MONË BANTU</h3>
                    <p><?php echo tt('footer_texte', 'Achetez et vendez en toute confiance au Cameroun'); ?></p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-telegram"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Navigation</h4>
                    <ul>
                        <li><a href="index.php"><?php echo tt('footer_accueil', 'Accueil'); ?></a></li>
                        <li><a href="php/acheter.php"><?php echo tt('nav_acheter', 'Acheter'); ?></a></li>
                        <li><a href="php/vendre.php"><?php echo tt('nav_vendre', 'Vendre'); ?></a></li>
                        <li><a href="php/messagerie.php"><?php echo tt('nav_messages', 'Messages'); ?></a></li>
                        <li><a href="php/profil.php"><?php echo tt('nav_profil', 'Mon profil'); ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Informations</h4>
                    <ul>
                        <li><a href="#">Comment acheter</a></li>
                        <li><a href="#">Comment vendre</a></li>
                        <li><a href="#">Sécurité</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="php/cgu.php">Conditions d'utilisation</a></li>
                        <li><a href="php/politique_confidentialite.php">Confidentialité</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><i class="fas fa-phone"></i> <?php echo defined('SITE_TEL') ? SITE_TEL : '+237 600 000 000'; ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo defined('SITE_EMAIL') ? SITE_EMAIL : 'contact@monebantu.cm'; ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MONË BANTU — <?php echo tt('footer_droits', 'Tous droits réservés'); ?></p>
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
            <li><a href="php/acheter.php"><i class="fas fa-store"></i> Acheter</a></li>
            <?php if (function_exists('estConnecte') && estConnecte()): ?>
                <li><a href="php/actualites.php"><i class="fas fa-newspaper"></i> Actualités</a></li>
                <li><a href="php/vendre.php"><i class="fas fa-plus-circle"></i> Vendre</a></li>
                <li><a href="php/boutique.php"><i class="fas fa-shop"></i> Boutique</a></li>
                <li><a href="php/reels.php"><i class="fas fa-clapperboard"></i> Réels</a></li>
                <li><a href="php/panier.php"><i class="fas fa-cart-shopping"></i> Panier</a></li>
                <li><a href="php/mes_achats.php"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
                <li><a href="php/mes_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                <li>
                    <a href="php/messagerie.php">
                        <i class="fas fa-envelope"></i> Messages
                        <?php
                        if (isset($pdo) && function_exists('userId')) {
                            try {
                                $nbStmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id=? AND lu=FALSE");
                                $nbStmt->execute([userId()]);
                                $nbMsg = (int)$nbStmt->fetchColumn();
                                if ($nbMsg > 0) echo '<span class="badge">' . $nbMsg . '</span>';
                            } catch (Throwable $e) {}
                        }
                        ?>
                    </a>
                </li>
                <li><a href="php/profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
            <?php else: ?>
                <li><a href="php/login.php"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
                <li><a href="php/register.php"><i class="fas fa-user-plus"></i> Inscription</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-panel-langue">
            <span><i class="fas fa-globe"></i> Langue :</span>
            <a href="php/definir_langue.php?langue=fr" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'fr' ? 'actif' : ''; ?>">FR</a>
            <a href="php/definir_langue.php?langue=en" class="<?php echo ($GLOBALS['LANGUE_ACTIVE'] ?? 'fr') === 'en' ? 'actif' : ''; ?>">EN</a>
        </div>

        <?php if (function_exists('estConnecte') && estConnecte()): ?>
        <div class="nav-panel-logout">
            <a href="php/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
        <?php endif; ?>
    </aside>

    <script src="js/script.js"></script>
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

    fetch('php/get_articles.php?limit=8')
        .then(r => r.json())
        .then(articles => {
            const container = document.getElementById('articles-container');
            if (!articles.length) {
                container.innerHTML = '<p class="no-articles">Aucun article pour le moment. Soyez le premier à vendre !</p>';
                return;
            }
            container.innerHTML = articles.map(a => {
                const photo = a.photo_principale || 'uploads/articles/default.jpg';
                return `
                    <div class="article-card">
                        <div class="article-image">
                            <img src="${photo}" alt="${a.titre}" loading="lazy">
                            <span class="article-etat">${a.etat}</span>
                        </div>
                        <div class="article-info">
                            <h3 class="article-title">${a.titre}</h3>
                            <p class="article-price">${a.prix_formate || (new Intl.NumberFormat('fr-FR').format(a.prix) + ' FCFA')}</p>
                            <div class="article-seller">
                                <div class="seller-avatar">${(a.vendeur_pseudo || '').substring(0,2).toUpperCase()}</div>
                                <span>${a.vendeur_pseudo || ''}</span>
                            </div>
                            <p class="article-location">
                                <i class="fas fa-map-marker-alt"></i> ${a.ville || 'Cameroun'}
                            </p>
                            <a href="php/article.php?id=${a.id}" class="btn-detail">
                                <i class="fas fa-eye"></i> Voir le détail
                            </a>
                        </div>
                    </div>
                `;
            }).join('');
        })
        .catch(() => {
            document.getElementById('articles-container').innerHTML =
                '<p class="no-articles">Erreur de chargement. Veuillez rafraîchir.</p>';
        });
    </script>

    <?php if (empty($GLOBALS['LANGUE_DEFINIE'])): ?>
    <div class="langue-modal" id="langueModal">
        <div class="langue-modal-box">
            <h3><?php echo tt('langue_choix_titre', 'Choisissez votre langue'); ?></h3>
            <p class="langue-modal-sub"><?php echo tt('langue_choix_sous', 'Choose your language'); ?></p>
            <div class="langue-modal-options">
                <a href="php/definir_langue.php?langue=fr" class="langue-option">
                    <span class="langue-drapeau">🇫🇷</span> Français
                </a>
                <a href="php/definir_langue.php?langue=en" class="langue-option">
                    <span class="langue-drapeau">🇬🇧</span> English
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>