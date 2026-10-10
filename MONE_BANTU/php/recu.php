<?php
// ============================================================
//  MONË BANTU — Reçu de transaction (imprimable / PDF via navigateur)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');
exigerLocalisation($pdo, $base_path);

$user_id        = userId();
$transaction_id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT t.*, a.titre, a.categorie, a.etat,
           ua.pseudo AS acheteur_pseudo, ua.prenom AS acheteur_prenom, ua.nom AS acheteur_nom, ua.telephone AS acheteur_tel,
           uv.pseudo AS vendeur_pseudo, uv.prenom AS vendeur_prenom, uv.nom AS vendeur_nom, uv.telephone AS vendeur_tel,
           b.nom_boutique
    FROM transactions t
    JOIN articles a ON a.id = t.article_id
    JOIN utilisateurs ua ON ua.id = t.acheteur_id
    JOIN utilisateurs uv ON uv.id = t.vendeur_id
    LEFT JOIN boutiques b ON b.utilisateur_id = t.vendeur_id
    WHERE t.id = ?
");
$stmt->execute([$transaction_id]);
$t = $stmt->fetch();

// Seuls l'acheteur, le vendeur, ou un admin peuvent voir ce reçu
if (!$t || (!in_array($user_id, [(int)$t['acheteur_id'], (int)$t['vendeur_id']]) && !estAdmin($pdo, $user_id))) {
    redirect($base_path . 'index.php');
}
if ($t['statut'] !== 'terminee') {
    redirect($base_path . 'php/mes_achats.php');
}

$nom_vendeur = $t['nom_boutique'] ?: trim($t['vendeur_prenom'] . ' ' . $t['vendeur_nom']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu #<?php echo $t['id']; ?> — MONË BANTU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f4f6; padding: 30px 16px; }
        .recu {
            max-width: 620px; margin: 0 auto; background: white; border-radius: 12px;
            padding: 36px 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .recu-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom: 2px solid #e94560; padding-bottom:18px; margin-bottom:22px; }
        .recu-header h1 { color:#e94560; font-size:1.4rem; }
        .recu-header .num { text-align:right; font-size:0.85rem; color:#777; }
        .recu-header .num strong { display:block; color:#222; font-size:1rem; }
        .recu-section { margin-bottom:20px; }
        .recu-section h3 { font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#999; margin-bottom:8px; }
        .recu-parties { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .recu-partie { background:#f9f9fb; border-radius:8px; padding:12px 14px; font-size:0.88rem; }
        .recu-partie strong { display:block; margin-bottom:2px; }
        .recu-table { width:100%; border-collapse: collapse; font-size:0.9rem; }
        .recu-table td { padding:9px 0; border-bottom:1px solid #eee; }
        .recu-table td:last-child { text-align:right; }
        .recu-total { font-weight:700; font-size:1.05rem; color:#e94560; }
        .recu-badge { display:inline-block; padding:4px 12px; border-radius:50px; font-size:0.78rem; font-weight:600; background:rgba(0,168,107,0.12); color:#00a86b; }
        .recu-footer { text-align:center; margin-top:26px; font-size:0.78rem; color:#999; }
        .btn-imprimer {
            display:block; margin:22px auto 0; background:#e94560; color:white; border:none;
            padding:11px 24px; border-radius:50px; font-size:0.9rem; cursor:pointer;
        }
        @media print { .btn-imprimer { display:none; } body { background:white; padding:0; } .recu { box-shadow:none; } }
    </style>
</head>
<body>
    <div class="recu">
        <div class="recu-header">
            <div>
                <h1>MONË BANTU</h1>
                <p style="font-size:0.8rem;color:#777;margin-top:2px;">Reçu de transaction</p>
            </div>
            <div class="num">
                Reçu <strong>#<?php echo str_pad($t['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                <?php echo (new DateTime($t['date_confirmation'] ?: $t['date_transaction']))->format('d/m/Y à H:i'); ?>
            </div>
        </div>

        <div class="recu-section">
            <span class="recu-badge"><i class="fas fa-check-circle"></i> Transaction terminée</span>
        </div>

        <div class="recu-section">
            <h3>Parties à l'accord</h3>
            <div class="recu-parties">
                <div class="recu-partie">
                    <strong><i class="fas fa-user"></i> Acheteur</strong>
                    <?php echo esc(trim($t['acheteur_prenom'] . ' ' . $t['acheteur_nom'])); ?><br>
                    @<?php echo esc($t['acheteur_pseudo']); ?><br>
                    <?php echo esc($t['acheteur_tel']); ?>
                </div>
                <div class="recu-partie">
                    <strong><i class="fas <?php echo $t['nom_boutique'] ? 'fa-shop' : 'fa-user'; ?>"></i> Vendeur</strong>
                    <?php echo esc($nom_vendeur); ?><br>
                    @<?php echo esc($t['vendeur_pseudo']); ?><br>
                    <?php echo esc($t['vendeur_tel']); ?>
                </div>
            </div>
        </div>

        <div class="recu-section">
            <h3>Article</h3>
            <table class="recu-table">
                <tr><td><?php echo esc($t['titre']); ?> <span style="color:#999;">(<?php echo esc($t['etat']); ?>, <?php echo esc($t['categorie']); ?>)</span></td><td><?php echo formatPrix($t['prix_accepte']); ?></td></tr>
                <tr><td>Frais de service Monë Bantu</td><td><?php echo formatPrix($t['commission']); ?></td></tr>
                <tr><td class="recu-total">Total</td><td class="recu-total"><?php echo formatPrix($t['montant_total']); ?></td></tr>
            </table>
        </div>

        <div class="recu-section">
            <h3>Méthode</h3>
            <p style="font-size:0.88rem;">
                <?php echo $t['mode_virement'] === 'direct' ? 'Virement direct entre utilisateurs' : 'Paiement sécurisé Monë Bantu'; ?>
                — Transaction #<?php echo $t['id']; ?>
            </p>
        </div>

        <button class="btn-imprimer" onclick="window.print()"><i class="fas fa-download"></i> Télécharger / Imprimer en PDF</button>

        <div class="recu-footer">
            Ce reçu fait foi de l'accord entre les deux parties sur Monë Bantu.<br>
            <?php echo SITE_EMAIL; ?> — <?php echo SITE_TEL; ?>
        </div>
    </div>
</body>
</html>
