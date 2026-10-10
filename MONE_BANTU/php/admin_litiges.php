<?php
// ============================================================
//  MONË BANTU — Administration des litiges
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');
if (!estAdmin($pdo, userId())) redirect($base_path . 'index.php');

$stmt = $pdo->query("
    SELECT t.*, a.titre, a.photos,
           ua.pseudo AS acheteur_pseudo, ua.telephone AS acheteur_tel,
           uv.pseudo AS vendeur_pseudo, uv.telephone AS vendeur_tel,
           b.nom_boutique
    FROM transactions t
    JOIN articles a ON a.id = t.article_id
    JOIN utilisateurs ua ON ua.id = t.acheteur_id
    JOIN utilisateurs uv ON uv.id = t.vendeur_id
    LEFT JOIN boutiques b ON b.utilisateur_id = t.vendeur_id
    WHERE t.statut = 'litige'
    ORDER BY t.date_litige ASC
");
$litiges = $stmt->fetchAll();

// Historique des litiges déjà résolus (les 20 derniers)
$stmt = $pdo->query("
    SELECT t.*, a.titre, ua.pseudo AS acheteur_pseudo, uv.pseudo AS vendeur_pseudo
    FROM transactions t
    JOIN articles a ON a.id = t.article_id
    JOIN utilisateurs ua ON ua.id = t.acheteur_id
    JOIN utilisateurs uv ON uv.id = t.vendeur_id
    WHERE t.resolution_admin IS NOT NULL
    ORDER BY t.date_resolution DESC
    LIMIT 20
");
$historique = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Litiges — Administration MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .litige-carte {
            background: white; border-radius: var(--radius-md); padding: 18px;
            margin-bottom: 16px; box-shadow: var(--shadow-md); border-left: 4px solid var(--primary);
        }
        .litige-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 12px 0; }
        @media (max-width: 600px) { .litige-parties { grid-template-columns: 1fr; } }
        .litige-partie { background: var(--light-bg); border-radius: 8px; padding: 12px; font-size: 0.85rem; }
        .litige-partie strong { display: block; margin-bottom: 4px; }
        .litige-motif {
            background: rgba(233,69,96,0.06); border-left: 3px solid var(--primary);
            padding: 10px 14px; border-radius: 6px; font-size: 0.88rem; margin: 12px 0; font-style: italic;
        }
        .litige-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .btn-payer-vendeur { background: #00a86b; color: white; border: none; padding: 9px 18px; border-radius: 50px; cursor: pointer; font-size: 0.85rem; }
        .btn-rembourser { background: var(--primary); color: white; border: none; padding: 9px 18px; border-radius: 50px; cursor: pointer; font-size: 0.85rem; }
        .histo-ligne { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .histo-badge { padding: 3px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 600; }
        .histo-vendeur { background: rgba(0,168,107,0.12); color: #00a86b; }
        .histo-acheteur { background: rgba(233,69,96,0.12); color: var(--primary); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="<?php echo $base_path; ?>index.php"><h1>MONË <span>BANTU</span></h1></a>
            </div>
            <ul class="nav-menu">
                <li><a href="<?php echo $base_path; ?>index.php"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="admin_litiges.php" class="active"><i class="fas fa-scale-balanced"></i> Litiges</a></li>
                <li><a href="admin_utilisateurs.php"><i class="fas fa-users"></i> Utilisateurs</a></li>
                <li><a href="admin_signalements.php"><i class="fas fa-flag"></i> Signalements</a></li>
                <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
                <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i></a></li>
            </ul>
            <div class="hamburger"><span></span><span></span><span></span></div>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title" style="margin:25px 0 20px;">
            <i class="fas fa-scale-balanced" style="color:var(--primary)"></i> Litiges à examiner
            <?php if (count($litiges)): ?><span style="font-size:0.9rem;color:var(--text-muted);">(<?php echo count($litiges); ?>)</span><?php endif; ?>
        </h1>

        <?php if (empty($litiges)): ?>
            <div class="etat-vide">
                <i class="fas fa-circle-check"></i>
                <p>Aucun litige en attente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($litiges as $l):
                $phs = array_filter(array_map('trim', explode(',', $l['photos'] ?? '')));
                $ph  = !empty($phs) ? $base_path . reset($phs) : $base_path . 'uploads/articles/default.jpg';
            ?>
            <div class="litige-carte" data-transaction="<?php echo $l['id']; ?>">
                <div style="display:flex;gap:12px;align-items:center;">
                    <img src="<?php echo esc($ph); ?>" alt="" style="width:56px;height:56px;border-radius:8px;object-fit:cover;">
                    <div>
                        <h4 style="margin-bottom:2px;"><?php echo esc($l['titre']); ?></h4>
                        <span style="font-size:0.8rem;color:var(--text-muted);">Signalé le <?php echo (new DateTime($l['date_litige']))->format('d/m/Y à H:i'); ?></span>
                    </div>
                </div>

                <div class="litige-motif"><i class="fas fa-quote-left"></i> <?php echo nl2br(esc($l['litige_motif'])); ?></div>

                <div class="litige-parties">
                    <div class="litige-partie">
                        <strong><i class="fas fa-user"></i> Acheteur</strong>
                        <?php echo esc($l['acheteur_pseudo']); ?><br>
                        <?php echo esc($l['acheteur_tel'] ?: '—'); ?>
                    </div>
                    <div class="litige-partie">
                        <strong><i class="fas <?php echo $l['nom_boutique'] ? 'fa-shop' : 'fa-user'; ?>"></i> Vendeur</strong>
                        <?php echo esc($l['nom_boutique'] ?: $l['vendeur_pseudo']); ?><br>
                        <?php echo esc($l['vendeur_tel'] ?: '—'); ?>
                    </div>
                </div>

                <div style="font-size:0.85rem;color:var(--text-muted);">
                    Article : <strong><?php echo formatPrix($l['prix_accepte']); ?></strong> —
                    Total payé par l'acheteur : <strong><?php echo formatPrix($l['montant_total']); ?></strong>
                </div>

                <div class="litige-actions">
                    <button class="btn-payer-vendeur" onclick="resoudreLitige(<?php echo $l['id']; ?>, 'vendeur', this)">
                        <i class="fas fa-check"></i> Payer le vendeur
                    </button>
                    <button class="btn-rembourser" onclick="resoudreLitige(<?php echo $l['id']; ?>, 'acheteur', this)">
                        <i class="fas fa-rotate-left"></i> Rembourser l'acheteur
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($historique)): ?>
        <h2 style="font-size:1.1rem;margin:30px 0 12px;">Historique des résolutions</h2>
        <div style="background:white;border-radius:var(--radius-md);padding:16px 18px;box-shadow:var(--shadow-sm);">
            <?php foreach ($historique as $h): ?>
            <div class="histo-ligne">
                <span><?php echo esc($h['titre']); ?> — <?php echo esc($h['acheteur_pseudo']); ?> / <?php echo esc($h['vendeur_pseudo']); ?></span>
                <span class="histo-badge <?php echo $h['resolution_admin'] === 'vendeur' ? 'histo-vendeur' : 'histo-acheteur'; ?>">
                    <?php echo $h['resolution_admin'] === 'vendeur' ? 'Vendeur payé' : 'Acheteur remboursé'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
        function resoudreLitige(id, decision, btn) {
            const msg = decision === 'vendeur'
                ? 'Confirmer : le vendeur sera payé.'
                : "Confirmer : l'acheteur sera remboursé (hors plateforme, manuellement).";
            if (!confirm(msg)) return;

            const carte = btn.closest('.litige-carte');
            carte.querySelectorAll('button').forEach(b => b.disabled = true);

            fetch('resoudre_litige.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'transaction_id=' + id + '&decision=' + decision
            })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { showToast(d.error || 'Erreur', 'error'); return; }
                showToast('Litige résolu.', 'success');
                carte.style.opacity = '0.4';
                setTimeout(() => location.reload(), 900);
            });
        }
        document.querySelector('.hamburger')?.addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
    </script>
</body>
</html>
