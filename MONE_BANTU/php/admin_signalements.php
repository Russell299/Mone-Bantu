<?php
// ============================================================
//  MONË BANTU — Administration des signalements
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');
if (!estAdmin($pdo, userId())) redirect($base_path . 'index.php');

// Traiter une action rapide (rejeter)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rejeter_id'])) {
    $pdo->prepare("UPDATE signalements SET statut = 'rejete', admin_id = ?, date_traitement = NOW() WHERE id = ?")
        ->execute([userId(), (int)$_POST['rejeter_id']]);
    redirect('admin_signalements.php');
}

$stmt = $pdo->query("
    SELECT sg.*,
           us.pseudo AS signalant_pseudo,
           ur.pseudo AS signale_pseudo, ur.id AS signale_id, ur.statut_compte,
           a.titre AS article_titre
    FROM signalements sg
    JOIN utilisateurs us ON us.id = sg.signalant_id
    JOIN utilisateurs ur ON ur.id = sg.utilisateur_signale_id
    LEFT JOIN articles a ON a.id = sg.article_id
    WHERE sg.statut = 'en_attente'
    ORDER BY sg.date_creation ASC
");
$signalements = $stmt->fetchAll();

// Regrouper par utilisateur signalé, pour repérer les cas répétés
$compte_par_signale = [];
foreach ($signalements as $s) {
    $compte_par_signale[$s['signale_id']] = ($compte_par_signale[$s['signale_id']] ?? 0) + 1;
}

$libelles_contexte = ['article' => 'Fiche article', 'message' => 'Messagerie', 'autre' => 'Autre'];
$libelles_gravite   = ['leger' => ['Léger', '#f9a826'], 'moyen' => ['Moyen', '#e94560'], 'grave' => ['Grave', '#c00']];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signalements — Administration MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sig-carte {
            background: white; border-radius: var(--radius-md); padding: 16px 18px;
            margin-bottom: 14px; box-shadow: var(--shadow-sm); border-left: 4px solid #f9a826;
        }
        .sig-repete { border-left-color: var(--primary); }
        .sig-tete { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
        .sig-repete-badge { background:rgba(233,69,96,0.12); color:var(--primary); font-size:0.72rem; font-weight:600; padding:3px 10px; border-radius:50px; }
        .sig-contexte { font-size:0.75rem; color:var(--text-muted); background:var(--light-bg); padding:2px 9px; border-radius:50px; }
        .sig-motif { background:var(--light-bg); border-left:3px solid #f9a826; padding:10px 14px; border-radius:6px; font-size:0.88rem; margin:10px 0; font-style:italic; }
        .sig-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
        .btn-traiter { background:var(--primary); color:white; border:none; padding:9px 18px; border-radius:50px; font-size:0.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
        .btn-rejeter { background:none; border:1px solid var(--border); color:var(--text-muted); padding:9px 18px; border-radius:50px; font-size:0.85rem; cursor:pointer; }
        .btn-sanction-immediate { background:#c00; color:white; border:none; padding:9px 18px; border-radius:50px; font-size:0.85rem; cursor:pointer; }
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
                <li><a href="admin_litiges.php"><i class="fas fa-scale-balanced"></i> Litiges</a></li>
                <li><a href="admin_signalements.php" class="active">
                    <i class="fas fa-flag"></i> Signalements
                    <?php if (count($signalements)): ?><span class="badge"><?php echo count($signalements); ?></span><?php endif; ?>
                </a></li>
                <li><a href="admin_utilisateurs.php"><i class="fas fa-users"></i> Utilisateurs</a></li>
                <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
                <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i></a></li>
            </ul>
            <div class="hamburger"><span></span><span></span><span></span></div>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title" style="margin:25px 0 20px;">
            <i class="fas fa-flag" style="color:var(--primary)"></i> Signalements en attente
            <?php if (count($signalements)): ?><span style="font-size:0.9rem;color:var(--text-muted);">(<?php echo count($signalements); ?>)</span><?php endif; ?>
        </h1>

        <?php if (empty($signalements)): ?>
            <div class="etat-vide">
                <i class="fas fa-circle-check"></i>
                <p>Aucun signalement en attente.</p>
            </div>
        <?php else: ?>
            <?php foreach ($signalements as $s):
                $repete = $compte_par_signale[$s['signale_id']] > 1;
            ?>
            <div class="sig-carte <?php echo $repete ? 'sig-repete' : ''; ?>">
                <div class="sig-tete">
                    <div>
                        <strong><?php echo esc($s['signale_pseudo']); ?></strong>
                        <span style="font-size:0.8rem;color:var(--text-muted);"> signalé par <?php echo esc($s['signalant_pseudo']); ?></span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <?php if ($repete): ?>
                            <span class="sig-repete-badge"><i class="fas fa-triangle-exclamation"></i> <?php echo $compte_par_signale[$s['signale_id']]; ?> signalements</span>
                        <?php endif; ?>
                        <span class="sig-contexte"><?php echo $libelles_contexte[$s['contexte']]; ?></span>
                        <?php [$lib_g, $coul_g] = $libelles_gravite[$s['gravite']] ?? ['Moyen', '#e94560']; ?>
                        <span class="sig-contexte" style="color:<?php echo $coul_g; ?>;"><i class="fas fa-circle-exclamation"></i> <?php echo $lib_g; ?></span>
                    </div>
                </div>

                <div class="sig-motif"><i class="fas fa-quote-left"></i> <?php echo nl2br(esc($s['motif'])); ?></div>

                <?php if (!empty($s['preuve_url'])): ?>
                    <a href="<?php echo $base_path . esc($s['preuve_url']); ?>" target="_blank" style="display:inline-block;margin-bottom:10px;">
                        <img src="<?php echo $base_path . esc($s['preuve_url']); ?>" alt="Preuve jointe"
                             style="max-width:220px;max-height:160px;border-radius:8px;border:1px solid var(--border);object-fit:cover;">
                    </a>
                <?php endif; ?>

                <div style="font-size:0.8rem;color:var(--text-muted);">
                    <?php if ($s['article_titre']): ?>À propos de : <strong><?php echo esc($s['article_titre']); ?></strong> — <?php endif; ?>
                    Le <?php echo (new DateTime($s['date_creation']))->format('d/m/Y à H:i'); ?>
                    — Statut actuel : <strong><?php echo ucfirst($s['statut_compte']); ?></strong>
                </div>

                <div class="sig-actions">
                    <a href="admin_utilisateurs.php?q=<?php echo urlencode($s['signale_pseudo']); ?>&user=<?php echo $s['signale_id']; ?>" class="btn-traiter">
                        <i class="fas fa-gavel"></i> Examiner / sanctionner
                    </a>
                    <button type="button" class="btn-sanction-immediate" onclick="confirmerSanctionImmediate(<?php echo $s['signale_id']; ?>, <?php echo (int)$s['id']; ?>, '<?php echo esc($s['signale_pseudo']); ?>')">
                        <i class="fas fa-ban"></i> Sanction immédiate (bannissement)
                    </button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="rejeter_id" value="<?php echo $s['id']; ?>">
                        <button type="submit" class="btn-rejeter"><i class="fas fa-xmark"></i> Rejeter</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
        function confirmerSanctionImmediate(userId, signalementId, pseudo) {
            if (!confirm(`Bannir définitivement ${pseudo} immédiatement, sans attendre ? Cette action est basée sur une preuve jugée suffisante.`)) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'sanctionner_utilisateur.php';
            form.innerHTML = `
                <input type="hidden" name="utilisateur_id" value="${userId}">
                <input type="hidden" name="type" value="bannissement">
                <input type="hidden" name="motif" value="Bannissement immédiat suite à signalement #${signalementId} appuyé par une preuve jugée solide.">
                <input type="hidden" name="retour" value="admin_signalements.php">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        document.querySelector('.hamburger')?.addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
    </script>
</body>
</html>
