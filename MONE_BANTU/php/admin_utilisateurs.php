<?php
// ============================================================
//  MONË BANTU — Administration des utilisateurs (sanctions)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');
if (!estAdmin($pdo, userId())) redirect($base_path . 'index.php');

$q = trim($_GET['q'] ?? '');
$resultats = [];
if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM utilisateurs
        WHERE pseudo LIKE ? OR email LIKE ? OR telephone LIKE ? OR CONCAT(prenom,' ',nom) LIKE ?
        ORDER BY date_inscription DESC LIMIT 30
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like, $like]);
    $resultats = $stmt->fetchAll();
}

$user_detail = null;
$historique  = [];
$selected_id = (int)($_GET['user'] ?? 0);
if ($selected_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
    $stmt->execute([$selected_id]);
    $user_detail = $stmt->fetch();

    if ($user_detail) {
        $stmt = $pdo->prepare("
            SELECT s.*, a.pseudo AS admin_pseudo
            FROM sanctions s
            LEFT JOIN utilisateurs a ON a.id = s.admin_id
            WHERE s.utilisateur_id = ?
            ORDER BY s.date_creation DESC
        ");
        $stmt->execute([$selected_id]);
        $historique = $stmt->fetchAll();
    }
}

$libelles_sanction = [
    'avertissement' => ['Avertissement', '#f9a826'],
    'suspension'    => ['Suspension', '#e94560'],
    'bannissement'  => ['Bannissement', '#c00'],
    'levee'         => ['Sanction levée', '#00a86b'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs — Administration MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .u-recherche { display:flex; gap:8px; margin-bottom:20px; }
        .u-recherche input { flex:1; padding:11px 16px; border-radius:50px; border:1px solid var(--border); }
        .u-carte {
            display:flex; align-items:center; gap:12px; background:white; border-radius:var(--radius-md);
            padding:12px 16px; margin-bottom:10px; box-shadow:var(--shadow-sm); text-decoration:none; color:inherit;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .u-carte:hover { transform:translateX(2px); box-shadow:var(--shadow-md); }
        .u-av { width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));color:white;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0; }
        .u-badge { padding:3px 10px; border-radius:50px; font-size:0.72rem; font-weight:600; margin-left:auto; }
        .badge-actif    { background:rgba(0,168,107,0.12); color:#00a86b; }
        .badge-suspendu { background:rgba(249,168,38,0.15); color:#b8790f; }
        .badge-banni    { background:rgba(200,0,0,0.12); color:#c00; }

        .detail-box { background:white; border-radius:var(--radius-md); padding:22px; box-shadow:var(--shadow-md); margin-bottom:20px; }
        .duree-choix { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0 14px; }
        .duree-choix button { padding:7px 14px; border-radius:50px; border:1px solid var(--border); background:white; font-size:0.82rem; cursor:pointer; }
        .duree-choix button.actif { background:var(--primary); color:white; border-color:var(--primary); }

        .histo-ligne { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid var(--border); font-size:0.85rem; }
        .histo-puce { width:8px;height:8px;border-radius:50%;margin-top:6px;flex-shrink:0; }
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
                <li><a href="admin_signalements.php"><i class="fas fa-flag"></i> Signalements</a></li>
                <li><a href="admin_utilisateurs.php" class="active"><i class="fas fa-users"></i> Utilisateurs</a></li>
                <li><a href="profil.php"><i class="fas fa-user"></i> Mon profil</a></li>
                <li><a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i></a></li>
            </ul>
            <div class="hamburger"><span></span><span></span><span></span></div>
        </div>
    </nav>

    <div class="container">
        <h1 class="page-title" style="margin:25px 0 20px;">
            <i class="fas fa-users" style="color:var(--primary)"></i> Gestion des utilisateurs
        </h1>

        <form method="GET" class="u-recherche">
            <input type="text" name="q" value="<?php echo esc($q); ?>" placeholder="Rechercher par pseudo, email, téléphone ou nom...">
            <button type="submit" class="btn-action" style="border:none;"><i class="fas fa-search"></i></button>
        </form>

        <?php if ($q !== '' && empty($resultats)): ?>
            <div class="etat-vide"><i class="fas fa-user-slash"></i><p>Aucun utilisateur trouvé.</p></div>
        <?php endif; ?>

        <?php foreach ($resultats as $r): ?>
            <a href="admin_utilisateurs.php?q=<?php echo urlencode($q); ?>&user=<?php echo $r['id']; ?>" class="u-carte">
                <div class="u-av"><?php echo initiales($r['pseudo']); ?></div>
                <div>
                    <strong><?php echo esc($r['pseudo']); ?></strong><br>
                    <span style="font-size:0.8rem;color:var(--text-muted);"><?php echo esc($r['email']); ?></span>
                </div>
                <span class="u-badge badge-<?php echo $r['statut_compte']; ?>"><?php echo ucfirst($r['statut_compte']); ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($user_detail): ?>
        <div class="detail-box" id="detailBox">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                <div class="u-av" style="width:54px;height:54px;font-size:1.2rem;"><?php echo initiales($user_detail['pseudo']); ?></div>
                <div>
                    <h3 style="margin-bottom:2px;"><?php echo esc(trim($user_detail['prenom'].' '.$user_detail['nom'])); ?> (@<?php echo esc($user_detail['pseudo']); ?>)</h3>
                    <span class="u-badge badge-<?php echo $user_detail['statut_compte']; ?>"><?php echo ucfirst($user_detail['statut_compte']); ?></span>
                    <?php if ($user_detail['statut_compte'] === 'suspendu' && $user_detail['suspension_fin']): ?>
                        <span style="font-size:0.8rem;color:var(--text-muted);"> — jusqu'au <?php echo (new DateTime($user_detail['suspension_fin']))->format('d/m/Y H:i'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:18px;">
                <?php echo esc($user_detail['email']); ?> — <?php echo esc($user_detail['telephone']); ?>
                — Inscrit le <?php echo (new DateTime($user_detail['date_inscription']))->format('d/m/Y'); ?>
            </p>

            <?php if ($user_detail['statut_compte'] !== 'actif'): ?>
                <form method="POST" action="sanctionner_utilisateur.php" style="margin-bottom:18px;">
                    <input type="hidden" name="utilisateur_id" value="<?php echo $user_detail['id']; ?>">
                    <input type="hidden" name="type" value="levee">
                    <input type="hidden" name="motif" value="Sanction levée par l'administrateur.">
                    <input type="hidden" name="retour" value="admin_utilisateurs.php?q=<?php echo urlencode($q); ?>&user=<?php echo $user_detail['id']; ?>">
                    <button type="submit" class="btn-action" style="border:1px solid #00a86b;color:#00a86b;background:none;">
                        <i class="fas fa-unlock"></i> Lever la sanction
                    </button>
                </form>
            <?php endif; ?>

            <h4 style="font-size:0.95rem;margin-bottom:10px;">Appliquer une sanction</h4>
            <form method="POST" action="sanctionner_utilisateur.php" id="formSanction">
                <input type="hidden" name="utilisateur_id" value="<?php echo $user_detail['id']; ?>">
                <input type="hidden" name="retour" value="admin_utilisateurs.php?q=<?php echo urlencode($q); ?>&user=<?php echo $user_detail['id']; ?>">

                <div class="form-group">
                    <label>Type de sanction</label>
                    <select name="type" id="typeSanction" required onchange="document.getElementById('blocDuree').style.display = this.value==='suspension' ? 'block' : 'none';">
                        <option value="avertissement">Avertissement (le compte reste actif)</option>
                        <option value="suspension">Suspension temporaire</option>
                        <option value="bannissement">Bannissement définitif</option>
                    </select>
                </div>

                <div id="blocDuree" style="display:none;">
                    <label style="font-size:0.85rem;">Durée</label>
                    <div class="duree-choix">
                        <button type="button" onclick="choisirDuree(1,this)">1 jour</button>
                        <button type="button" onclick="choisirDuree(3,this)">3 jours</button>
                        <button type="button" onclick="choisirDuree(7,this)">7 jours</button>
                        <button type="button" onclick="choisirDuree(30,this)">30 jours</button>
                    </div>
                    <input type="number" name="duree_jours" id="dureeJours" min="1" placeholder="Ou nombre de jours personnalisé">
                </div>

                <div class="form-group">
                    <label>Motif <span style="color:var(--primary)">*</span></label>
                    <textarea name="motif" rows="3" required placeholder="Ex : escroquerie signalée par plusieurs acheteurs, article jamais livré..."></textarea>
                </div>

                <button type="submit" class="btn-primary-full"><i class="fas fa-gavel"></i> Appliquer la sanction</button>
            </form>

            <?php if (!empty($historique)): ?>
            <h4 style="font-size:0.95rem;margin:24px 0 10px;">Historique</h4>
            <?php foreach ($historique as $h): [$lib, $couleur] = $libelles_sanction[$h['type']]; ?>
                <div class="histo-ligne">
                    <div class="histo-puce" style="background:<?php echo $couleur; ?>;"></div>
                    <div>
                        <strong style="color:<?php echo $couleur; ?>;"><?php echo $lib; ?></strong>
                        <?php if ($h['duree_jours']): ?> (<?php echo $h['duree_jours']; ?> jours)<?php endif; ?>
                        — par <?php echo $h['admin_pseudo'] ? esc($h['admin_pseudo']) : '<em>Système (automatique)</em>'; ?>, le <?php echo (new DateTime($h['date_creation']))->format('d/m/Y H:i'); ?><br>
                        <span style="color:var(--text-muted);"><?php echo esc($h['motif']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo $base_path; ?>js/script.js"></script>
    <script>
        function choisirDuree(jours, btn) {
            document.getElementById('dureeJours').value = jours;
            document.querySelectorAll('.duree-choix button').forEach(b => b.classList.remove('actif'));
            btn.classList.add('actif');
        }
        <?php if ($user_detail): ?>
        document.getElementById('detailBox').scrollIntoView({ behavior: 'smooth', block: 'start' });
        <?php endif; ?>
        document.querySelector('.hamburger')?.addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
    </script>
</body>
</html>
