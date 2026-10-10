<?php
// ============================================================
//  MONË BANTU — Confirmation de localisation (obligatoire)
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (!estConnecte()) redirect($base_path . 'php/login.php');

// Déjà bon pour cette session ?
if (!empty($_SESSION['localisation_confirmee'])) {
    redirect($base_path . 'index.php');
}

$user_id = userId();
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $region         = trim($_POST['region'] ?? '');
    $departement    = trim($_POST['departement'] ?? '');
    $arrondissement = trim($_POST['arrondissement'] ?? '');
    $ville          = trim($_POST['ville'] ?? '');
    $profession     = trim($_POST['profession'] ?? '');
    $latitude       = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $longitude      = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $localisation_manuelle = empty($latitude) ? 1 : 0;

    if (empty($region) || !in_array($region, REGIONS_CAMEROUN, true) || empty($ville)) {
        $error = "Merci de renseigner au moins la région et la ville.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE utilisateurs
            SET region = ?, departement = ?, arrondissement = ?, ville = ?, profession = ?,
                latitude = ?, longitude = ?, localisation_manuelle = ?, derniere_localisation = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$region, $departement, $arrondissement, $ville, $profession, $latitude, $longitude, $localisation_manuelle, $user_id]);

        $_SESSION['localisation_confirmee'] = true;
        redirect($base_path . 'index.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localisation — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="localisation-gate">
        <div class="localisation-box">
            <i class="fas fa-location-dot" style="font-size:2.4rem;color:var(--primary);margin-bottom:12px;"></i>
            <h2>Confirmez votre localisation</h2>
            <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:22px;">
                Monë Bantu exige une localisation à jour pour continuer à utiliser le site —
                cela permet d'afficher une position fiable sur vos publications.
            </p>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom:16px;"><i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?></div>
            <?php endif; ?>

            <div class="geoloc-statut" id="geolocStatut">
                <i class="fas fa-spinner fa-spin"></i> Localisation de votre appareil en cours...
            </div>

            <form method="POST" style="text-align:left;">
                <input type="hidden" name="latitude" id="loc_latitude" value="">
                <input type="hidden" name="longitude" id="loc_longitude" value="">

                <div class="form-group">
                    <label>Profession</label>
                    <input type="text" name="profession" value="<?php echo esc($user['profession'] ?? ''); ?>" placeholder="Ex : Commerçant, Étudiant...">
                </div>
                <div class="form-group">
                    <label>Région *</label>
                    <select name="region" id="loc_region" required>
                        <option value="">Choisir...</option>
                        <?php foreach (REGIONS_CAMEROUN as $r): ?>
                            <option value="<?php echo esc($r); ?>" <?php echo ($user['region'] ?? '') === $r ? 'selected' : ''; ?>><?php echo esc($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Département <small style="color:#aaa;">(détecté auto si possible)</small></label>
                    <input type="text" name="departement" id="loc_departement" value="<?php echo esc($user['departement'] ?? ''); ?>" placeholder="Ex : Mfoundi">
                </div>
                <div class="form-group">
                    <label>Arrondissement <small style="color:#aaa;">(détecté auto si possible)</small></label>
                    <input type="text" name="arrondissement" id="loc_arrondissement" value="<?php echo esc($user['arrondissement'] ?? ''); ?>" placeholder="Ex : Yaoundé 1er">
                </div>
                <div class="form-group">
                    <label>Ville *</label>
                    <input type="text" name="ville" id="loc_ville" required value="<?php echo esc($user['ville'] ?? ''); ?>" placeholder="Ex : Yaoundé">
                </div>

                <button type="submit" class="btn-primary-full">
                    <i class="fas fa-check"></i> Confirmer et continuer
                </button>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const statutEl = document.getElementById('geolocStatut');
        function succes(pos) {
            const lat = pos.coords.latitude, lon = pos.coords.longitude;
            document.getElementById('loc_latitude').value  = lat;
            document.getElementById('loc_longitude').value = lon;
            statutEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Identification de votre région...';

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1&accept-language=fr`)
                .then(r => r.json())
                .then(data => {
                    const a = data.address || {};
                    const region = a.state || '';
                    const dept   = a.state_district || a.county || '';
                    const arr    = a.county || a.municipality || a.suburb || '';
                    const ville  = a.city || a.town || a.village || '';

                    const selRegion = document.getElementById('loc_region');
                    if (region) {
                        [...selRegion.options].forEach(o => { if (o.value.toLowerCase() === region.toLowerCase()) selRegion.value = o.value; });
                    }
                    if (dept)  document.getElementById('loc_departement').value = dept;
                    if (arr)   document.getElementById('loc_arrondissement').value = arr;
                    if (ville && !document.getElementById('loc_ville').value) document.getElementById('loc_ville').value = ville;

                    statutEl.innerHTML = '<i class="fas fa-check-circle" style="color:#00a86b"></i> Position détectée — vérifiez et corrigez si besoin.';
                })
                .catch(() => {
                    statutEl.innerHTML = '<i class="fas fa-check-circle" style="color:#00a86b"></i> Position GPS détectée — complétez les champs manuellement.';
                });
        }
        function echec() {
            statutEl.innerHTML = '<i class="fas fa-triangle-exclamation" style="color:#f9a826"></i> Localisation indisponible ou refusée — renseignez au moins région et ville manuellement.';
        }
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(succes, echec, { timeout: 8000 });
        } else {
            echec();
        }
    })();
    </script>
</body>
</html>
