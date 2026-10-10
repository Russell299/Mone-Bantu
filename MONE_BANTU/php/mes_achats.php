<?php
// ============================================================
//  MONË BANTU — Mes achats
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

if (function_exists('liberationPaiementsExpires')) {
    try { liberationPaiementsExpires($pdo); } catch (Throwable $e) {}
}

$stmt = $pdo->prepare("
    SELECT t.*, a.titre, a.photos, u.pseudo AS vendeur_pseudo, b.nom_boutique,
           ev.id AS evaluation_id
    FROM transactions t
    JOIN articles a ON a.id = t.article_id
    JOIN utilisateurs u ON u.id = t.vendeur_id
    LEFT JOIN boutiques b ON b.utilisateur_id = t.vendeur_id
    LEFT JOIN evaluations ev ON ev.transaction_id = t.id AND ev.evaluateur_id = t.acheteur_id
    WHERE t.acheteur_id = ?
    ORDER BY t.date_transaction DESC
");
$stmt->execute([$user_id]);
$achats = $stmt->fetchAll();

$libelles_statut = [
    'en_attente_paiement' => ['En attente de paiement', 'statut-attente', 'fa-clock'],
    'payee'                => ['Payé — en attente de réception', 'statut-payee', 'fa-box'],
    'terminee'             => ['Terminé', 'statut-termine', 'fa-check-circle'],
    'litige'               => ['Litige en cours d\'examen', 'statut-litige', 'fa-triangle-exclamation'],
    'annulee'              => ['Annulé', 'statut-annule', 'fa-ban'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE'] ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes achats — MONË BANTU</title>
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

        /* Page mes achats */
        .container { padding-bottom: 60px; }
        .achat-carte {
            display: flex; gap: 14px; background: white; border-radius: var(--radius-md);
            padding: 14px; margin-bottom: 14px; box-shadow: var(--shadow-sm);
        }
        .achat-carte img { width: 70px; height: 70px; border-radius: 8px; object-fit: cover; }
        .achat-carte-info { flex: 1; }
        .achat-carte-info h4 { font-size: 0.95rem; margin-bottom: 3px; }
        .achat-carte-info span { font-size: 0.8rem; color: var(--text-muted); display: block; }
        .achat-statut {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 50px; font-size: 0.78rem; font-weight: 600; margin-top: 6px;
        }
        .statut-attente { background: #f0f0f0; color: #777; }
        .statut-payee   { background: rgba(249,168,38,0.15); color: #b8790f; }
        .statut-termine { background: rgba(0,168,107,0.12); color: #00a86b; }
        .statut-litige  { background: rgba(233,69,96,0.12); color: var(--primary); }
        .statut-annule  { background: #f0f0f0; color: #999; }
        .achat-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .achat-actions button {
            font-size: 0.8rem; padding: 7px 14px; border-radius: 50px; border: none; cursor: pointer;
        }
        .btn-confirmer-reception { background: #00a86b; color: white; }
        .btn-signaler { background: none; border: 1px solid var(--border) !important; color: var(--text-muted); }
        .delai-info { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }

        .alert {
            padding: 13px 16px; border-radius: var(--radius-sm);
            margin-bottom: 18px; display: flex; align-items: center;
            gap: 10px; font-size: 0.92rem;
        }
        .alert-error { background: #fff0f0; color: #c00; border-left: 4px solid var(--primary); }
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
            <i class="fas fa-bag-shopping" style="color:var(--primary)"></i> Mes achats
        </h1>

        <?php if (empty($achats)): ?>
            <div class="etat-vide">
                <i class="fas fa-bag-shopping"></i>
                <p>Aucun achat pour le moment.</p>
                <a href="acheter.php" class="btn-action" style="text-decoration:none;">
                    <i class="fas fa-store"></i> Parcourir les articles
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($achats as $t):
                $phs = array_filter(array_map('trim', explode(',', $t['photos'] ?? '')));
                $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
                [$libelle, $classe, $icone] = $libelles_statut[$t['statut']] ?? ['Inconnu', 'statut-attente', 'fa-question'];
            ?>
            <div class="achat-carte">
                <img src="<?php echo htmlspecialchars($ph); ?>" alt="">
                <div class="achat-carte-info">
                    <h4><?php echo function_exists('esc') ? esc($t['titre']) : htmlspecialchars($t['titre']); ?></h4>
                    <span>
                        <i class="fas fa-<?php echo $t['nom_boutique'] ? 'shop' : 'user'; ?>"></i>
                        <?php echo function_exists('esc') ? esc($t['nom_boutique'] ?: $t['vendeur_pseudo']) : htmlspecialchars($t['nom_boutique'] ?: $t['vendeur_pseudo']); ?>
                    </span>
                    <span>Total payé : <strong><?php echo function_exists('formatPrix') ? formatPrix($t['montant_total']) : $t['montant_total'] . ' FCFA'; ?></strong></span>
                    <span class="achat-statut <?php echo $classe; ?>">
                        <i class="fas <?php echo $icone; ?>"></i> <?php echo $libelle; ?>
                    </span>

                    <?php if ($t['statut'] === 'payee' && !empty($t['date_limite'])): ?>
                        <div class="delai-info">
                            <i class="fas fa-hourglass-half"></i> Libération automatique le
                            <?php echo (new DateTime($t['date_limite']))->format('d/m/Y à H:i'); ?> si aucune action.
                        </div>
                        <div class="achat-actions">
                            <button class="btn-confirmer-reception" onclick="confirmerReception(<?php echo $t['id']; ?>, this)">
                                <i class="fas fa-check"></i> J'ai bien reçu l'article
                            </button>
                            <button class="btn-signaler" onclick="ouvrirSignalement(<?php echo $t['id']; ?>)">
                                <i class="fas fa-triangle-exclamation"></i> Signaler un problème
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($t['statut'] === 'terminee'): ?>
                        <div class="achat-actions">
                            <a href="recu.php?id=<?php echo $t['id']; ?>" class="btn-signaler" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                <i class="fas fa-receipt"></i> Voir le reçu
                            </a>
                            <?php if ($t['evaluation_id']): ?>
                                <span class="btn-signaler" style="opacity:0.6;cursor:default;">
                                    <i class="fas fa-star"></i> Avis déjà laissé
                                </span>
                            <?php else: ?>
                                <button class="btn-confirmer-reception" onclick="ouvrirEvaluation(<?php echo $t['id']; ?>)">
                                    <i class="fas fa-star"></i> Laisser un avis
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($t['statut'] === 'litige'): ?>
                        <div class="delai-info">
                            <i class="fas fa-comment-dots"></i> Motif signalé : « <?php echo function_exists('esc') ? esc($t['litige_motif'] ?? '') : htmlspecialchars($t['litige_motif'] ?? ''); ?> »
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===== MODAL SIGNALEMENT ===== -->
    <div class="modal-reel" id="modalSignalement">
        <div class="modal-reel-box">
            <button class="modal-reel-fermer" onclick="fermerSignalement()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-triangle-exclamation"></i> Signaler un problème</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">
                Décris ce qui ne va pas — l'équipe Monë Bantu va examiner et trancher.
            </p>
            <form id="formSignalement">
                <div class="form-group">
                    <textarea id="motif_litige" rows="4" maxlength="500" placeholder="Ex : article non conforme à la photo, vendeur absent au rendez-vous..." required></textarea>
                </div>
                <button type="submit" class="btn-primary-full" id="btnSignaler">
                    <i class="fas fa-paper-plane"></i> Envoyer le signalement
                </button>
            </form>
        </div>
    </div>

    <!-- ===== MODAL ÉVALUATION ===== -->
    <div class="modal-reel" id="modalEvaluation">
        <div class="modal-reel-box">
            <button class="modal-reel-fermer" onclick="fermerEvaluation()"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-star"></i> Laisser un avis</h3>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-bottom:16px;">Comment s'est passée cette transaction ?</p>
            <div class="etoiles-choix" id="etoilesChoix">
                <i class="far fa-star" data-note="1"></i>
                <i class="far fa-star" data-note="2"></i>
                <i class="far fa-star" data-note="3"></i>
                <i class="far fa-star" data-note="4"></i>
                <i class="far fa-star" data-note="5"></i>
            </div>
            <form id="formEvaluation">
                <div class="form-group">
                    <textarea id="commentaire_evaluation" rows="3" maxlength="500" placeholder="Un commentaire (optionnel)..."></textarea>
                </div>
                <button type="submit" class="btn-primary-full" id="btnEvaluer">
                    <i class="fas fa-paper-plane"></i> Envoyer l'avis
                </button>
            </form>
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
            <li><a href="mes_achats.php" class="active"><i class="fas fa-bag-shopping"></i> Mes achats</a></li>
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

    let transactionSignalee = null;

    function confirmerReception(id, btn) {
        if (!confirm("Confirmer que vous avez bien reçu l'article ? Le vendeur sera payé immédiatement.")) return;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirmation...';
        fetch('confirmer_reception.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'transaction_id=' + id
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { showToast(d.error || 'Erreur', 'error'); btn.disabled = false; return; }
            showToast('Réception confirmée, merci !', 'success');
            setTimeout(() => location.reload(), 900);
        });
    }

    function ouvrirSignalement(id) {
        transactionSignalee = id;
        document.getElementById('modalSignalement').classList.add('open');
    }
    function fermerSignalement() {
        document.getElementById('modalSignalement').classList.remove('open');
    }

    document.getElementById('formSignalement')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const motif = document.getElementById('motif_litige').value.trim();
        if (!motif) return;
        const btn = document.getElementById('btnSignaler');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        fetch('signaler_probleme.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'transaction_id=' + transactionSignalee + '&motif=' + encodeURIComponent(motif)
        })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le signalement';
            if (!d.success) { showToast(d.error || 'Erreur', 'error'); return; }
            showToast('Signalement envoyé.', 'success');
            fermerSignalement();
            setTimeout(() => location.reload(), 900);
        });
    });

    let transactionAEvaluer = null;
    let noteChoisie = 0;

    function ouvrirEvaluation(id) {
        transactionAEvaluer = id;
        noteChoisie = 0;
        document.querySelectorAll('#etoilesChoix i').forEach(e => e.className = 'far fa-star');
        document.getElementById('commentaire_evaluation').value = '';
        document.getElementById('modalEvaluation').classList.add('open');
    }
    function fermerEvaluation() {
        document.getElementById('modalEvaluation').classList.remove('open');
    }
    document.querySelectorAll('#etoilesChoix i').forEach(etoile => {
        etoile.addEventListener('click', function() {
            noteChoisie = parseInt(this.dataset.note);
            document.querySelectorAll('#etoilesChoix i').forEach(e => {
                e.className = parseInt(e.dataset.note) <= noteChoisie ? 'fas fa-star' : 'far fa-star';
            });
        });
    });
    document.getElementById('formEvaluation')?.addEventListener('submit', function(e) {
        e.preventDefault();
        if (noteChoisie < 1) { showToast('Choisissez une note.', 'error'); return; }
        const btn = document.getElementById('btnEvaluer');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi...';
        fetch('evaluer_transaction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'transaction_id=' + transactionAEvaluer + '&note=' + noteChoisie + '&commentaire=' + encodeURIComponent(document.getElementById('commentaire_evaluation').value.trim())
        })
        .then(r => r.json())
        .then(d => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer l\'avis';
            if (!d.success) { showToast(d.error || 'Erreur', 'error'); return; }
            showToast('Merci pour votre avis !', 'success');
            fermerEvaluation();
            setTimeout(() => location.reload(), 900);
        });
    });
    </script>
</body>
</html>