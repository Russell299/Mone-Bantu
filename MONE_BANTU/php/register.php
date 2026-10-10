<?php
// ============================================================
//  MONË BANTU — Inscription
// ============================================================

$base_path = '../';
require_once $base_path . 'php/config.php';

if (estConnecte()) {
    redirect($base_path . 'index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom              = trim($_POST['nom'] ?? '');
    $prenom           = trim($_POST['prenom'] ?? '');
    $pseudo           = trim($_POST['pseudo'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $telephone        = trim($_POST['telephone'] ?? '');
    $ville            = trim($_POST['ville'] ?? '');
    $profession       = trim($_POST['profession'] ?? '');
    $region           = trim($_POST['region'] ?? '');
    $departement      = trim($_POST['departement'] ?? '');
    $arrondissement   = trim($_POST['arrondissement'] ?? '');
    $latitude         = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $longitude        = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $localisation_manuelle = empty($latitude) ? 1 : 0;
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validations
    if (empty($nom) || empty($prenom) || empty($pseudo) || empty($email) || empty($telephone) || empty($password)) {
        $error = "Tous les champs obligatoires (*) doivent être remplis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "L'adresse email n'est pas valide.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $pseudo)) {
        $error = "Le pseudo ne peut contenir que des lettres, chiffres, - et _ (3 à 30 caractères).";
    } elseif (empty($region) || !in_array($region, REGIONS_CAMEROUN, true) || empty($ville)) {
        $error = "La région et la ville sont obligatoires pour utiliser Monë Bantu.";
    } else {
        // Vérifier unicité email et pseudo
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? OR pseudo = ? LIMIT 1");
        $stmt->execute([$email, $pseudo]);

        if ($stmt->fetch()) {
            $error = "Cet email ou ce pseudo est déjà utilisé.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO utilisateurs
                    (nom, prenom, pseudo, email, telephone, ville, profession, region, departement, arrondissement,
                     latitude, longitude, localisation_manuelle, derniere_localisation, password, date_inscription)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
            ");

            if ($stmt->execute([$nom, $prenom, $pseudo, $email, $telephone, $ville, $profession, $region, $departement, $arrondissement, $latitude, $longitude, $localisation_manuelle, $hashed])) {
                // Connexion automatique après inscription
                $new_id = $pdo->lastInsertId();
                session_regenerate_id(true);
                $_SESSION['user_id']              = $new_id;
                $_SESSION['localisation_confirmee'] = true;
                $_SESSION['user_pseudo']= $pseudo;
                $_SESSION['user_nom']   = "$prenom $nom";
                redirect($base_path . 'index.php');
            } else {
                $error = "Une erreur est survenue lors de l'inscription. Réessayez.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register_titre'); ?> — MONË BANTU</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 30px 20px;
        }

        .auth-card {
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 520px;
            padding: 40px;
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand {
            text-align: center;
            margin-bottom: 25px;
        }
        .brand h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #e94560, #0f3460);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }
        .brand p { color: #666; font-size: 0.9rem; margin-top: 5px; }

        .form-title { font-size: 1.2rem; color: #333; margin-bottom: 20px; font-weight: 600; }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert.error   { background: #fff0f0; color: #c00; border-left: 4px solid #e94560; }
        .alert.success { background: #f0fff0; color: #080; border-left: 4px solid #00a86b; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .input-wrap { position: relative; }
        .input-wrap i.icon-left {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 0.9rem;
        }
        .input-wrap input,
        .input-wrap select {
            width: 100%;
            padding: 11px 13px 11px 36px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #fafafa;
        }
        .input-wrap input:focus,
        .input-wrap select:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 3px rgba(233,69,96,0.1);
            background: white;
        }
        .toggle-password {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #aaa;
        }
        .toggle-password:hover { color: #e94560; }

        .password-strength {
            height: 4px;
            border-radius: 2px;
            background: #eee;
            margin-top: 6px;
            overflow: hidden;
        }
        .strength-bar {
            height: 100%;
            width: 0;
            border-radius: 2px;
            transition: width 0.3s, background 0.3s;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #e94560, #c73652);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 5px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233,69,96,0.4);
        }

        .auth-links {
            text-align: center;
            margin-top: 18px;
        }
        .auth-links p { color: #666; font-size: 0.9rem; margin-bottom: 6px; }
        .auth-links a { color: #e94560; text-decoration: none; font-weight: 600; }
        .auth-links a:hover { text-decoration: underline; }

        @media (max-width: 480px) {
            .form-row { grid-template-columns: 1fr; }
            .auth-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <h1>MONË BANTU</h1>
            <p>Rejoignez la communauté camerounaise</p>
        </div>

        <h2 class="form-title"><?php echo t('register_titre'); ?></h2>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?php echo esc($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('register_prenom'); ?> *</label>
                    <div class="input-wrap">
                        <i class="fas fa-user icon-left"></i>
                        <input type="text" name="prenom" required
                               value="<?php echo isset($_POST['prenom']) ? esc($_POST['prenom']) : ''; ?>"
                               placeholder="Jean">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('register_nom'); ?> *</label>
                    <div class="input-wrap">
                        <i class="fas fa-user icon-left"></i>
                        <input type="text" name="nom" required
                               value="<?php echo isset($_POST['nom']) ? esc($_POST['nom']) : ''; ?>"
                               placeholder="Dupont">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo t('register_pseudo'); ?> * <small style="color:#aaa"><?php echo t('register_pseudo_hint'); ?></small></label>
                <div class="input-wrap">
                    <i class="fas fa-at icon-left"></i>
                    <input type="text" name="pseudo" required minlength="3" maxlength="30"
                           value="<?php echo isset($_POST['pseudo']) ? esc($_POST['pseudo']) : ''; ?>"
                           placeholder="mon_pseudo">
                </div>
            </div>

            <div class="form-group">
                <label><?php echo t('register_email'); ?> *</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope icon-left"></i>
                    <input type="email" name="email" required autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? esc($_POST['email']) : ''; ?>"
                           placeholder="votre@email.com">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('register_telephone'); ?> *</label>
                    <div class="input-wrap">
                        <i class="fas fa-phone icon-left"></i>
                        <input type="tel" name="telephone" required
                               value="<?php echo isset($_POST['telephone']) ? esc($_POST['telephone']) : ''; ?>"
                               placeholder="6XXXXXXXX">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('register_ville'); ?></label>
                    <div class="input-wrap">
                        <i class="fas fa-map-marker-alt icon-left"></i>
                        <select name="ville" required>
                            <option value="">Choisir...</option>
                            <?php foreach ($villes as $v): ?>
                                <option value="<?php echo esc($v); ?>"
                                    <?php echo (isset($_POST['ville']) && $_POST['ville'] === $v) ? 'selected' : ''; ?>>
                                    <?php echo esc($v); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('register_profession'); ?></label>
                    <div class="input-wrap">
                        <i class="fas fa-briefcase icon-left"></i>
                        <input type="text" name="profession"
                               value="<?php echo isset($_POST['profession']) ? esc($_POST['profession']) : ''; ?>"
                               placeholder="Ex : Commerçant, Étudiant...">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('register_region'); ?> *</label>
                    <div class="input-wrap">
                        <i class="fas fa-map icon-left"></i>
                        <select name="region" id="reg_region" required>
                            <option value="">Choisir...</option>
                            <?php foreach (REGIONS_CAMEROUN as $r): ?>
                                <option value="<?php echo esc($r); ?>"
                                    <?php echo (isset($_POST['region']) && $_POST['region'] === $r) ? 'selected' : ''; ?>>
                                    <?php echo esc($r); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('register_departement'); ?> <small style="color:#aaa;"><?php echo t('register_departement_hint'); ?></small></label>
                    <div class="input-wrap">
                        <i class="fas fa-map-pin icon-left"></i>
                        <input type="text" name="departement" id="reg_departement"
                               value="<?php echo isset($_POST['departement']) ? esc($_POST['departement']) : ''; ?>"
                               placeholder="Ex : Mfoundi">
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('register_arrondissement'); ?> <small style="color:#aaa;"><?php echo t('register_arrondissement_hint'); ?></small></label>
                    <div class="input-wrap">
                        <i class="fas fa-location-dot icon-left"></i>
                        <input type="text" name="arrondissement" id="reg_arrondissement"
                               value="<?php echo isset($_POST['arrondissement']) ? esc($_POST['arrondissement']) : ''; ?>"
                               placeholder="Ex : Yaoundé 1er">
                    </div>
                </div>
            </div>

            <div class="geoloc-statut" id="geolocStatut">
                <i class="fas fa-spinner fa-spin"></i> Localisation de votre appareil en cours...
            </div>
            <p style="font-size:0.7rem;color:#bbb;margin:-8px 0 12px;">Données de localisation © OpenStreetMap contributors</p>
            <input type="hidden" name="latitude" id="reg_latitude" value="">
            <input type="hidden" name="longitude" id="reg_longitude" value="">

            <div class="form-row">
                <div class="form-group">
                    <label><?php echo t('register_password'); ?> * <small style="color:#aaa"><?php echo t('register_password_hint'); ?></small></label>
                    <div class="input-wrap">
                        <i class="fas fa-lock icon-left"></i>
                        <input type="password" id="password" name="password" required minlength="6"
                               placeholder="••••••••" oninput="checkStrength(this.value)">
                        <button type="button" class="toggle-password" onclick="togglePwd('password','eye1')">
                            <i class="fas fa-eye" id="eye1"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo t('register_confirm'); ?> *</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock icon-left"></i>
                        <input type="password" id="confirm" name="confirm_password" required
                               placeholder="••••••••">
                        <button type="button" class="toggle-password" onclick="togglePwd('confirm','eye2')">
                            <i class="fas fa-eye" id="eye2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <p style="text-align:center;font-size:0.78rem;color:#999;margin-bottom:14px;">
                <?php echo t('register_cgu_intro'); ?>
                <a href="cgu.php" style="color:var(--primary,#e94560);"><?php echo t('register_cgu_lien'); ?></a> <?php echo t('register_cgu_et'); ?>
                <a href="politique_confidentialite.php" style="color:var(--primary,#e94560);"><?php echo t('register_confidentialite_lien'); ?></a>.
            </p>
            <button type="submit" class="btn-submit">
                <i class="fas fa-user-plus"></i> <?php echo t('register_btn'); ?>
            </button>
        </form>

        <div class="auth-links">
            <p><?php echo t('register_deja_compte'); ?> <a href="login.php"><?php echo t('register_se_connecter'); ?></a></p>
            <p><a href="<?php echo $base_path; ?>index.php"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a></p>
        </div>
    </div>

    <script>
    // ---- Localisation automatique (GPS + géocodage inverse OpenStreetMap) ----
    // Détection au mieux : toujours modifiable manuellement par l'utilisateur.
    (function () {
        const statutEl = document.getElementById('geolocStatut');

        function succes(pos) {
            const lat = pos.coords.latitude, lon = pos.coords.longitude;
            document.getElementById('reg_latitude').value  = lat;
            document.getElementById('reg_longitude').value = lon;
            statutEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Identification de votre région...';

            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&addressdetails=1&accept-language=fr`)
                .then(r => r.json())
                .then(data => {
                    const a = data.address || {};
                    const region = a.state || '';
                    const dept   = a.state_district || a.county || '';
                    const arr    = a.county || a.municipality || a.suburb || '';
                    const ville  = a.city || a.town || a.village || '';

                    const selRegion = document.getElementById('reg_region');
                    if (region) {
                        [...selRegion.options].forEach(o => { if (o.value.toLowerCase() === region.toLowerCase()) selRegion.value = o.value; });
                    }
                    if (dept)  document.getElementById('reg_departement').value = dept;
                    if (arr)   document.getElementById('reg_arrondissement').value = arr;

                    statutEl.innerHTML = '<i class="fas fa-check-circle" style="color:#00a86b"></i> Position détectée — vérifiez et corrigez si besoin.';
                })
                .catch(() => {
                    statutEl.innerHTML = '<i class="fas fa-check-circle" style="color:#00a86b"></i> Position GPS détectée — complétez les champs manuellement.';
                });
        }
        function echec() {
            statutEl.innerHTML = '<i class="fas fa-triangle-exclamation" style="color:#f9a826"></i> Localisation indisponible ou refusée — renseignez au moins votre région et votre ville.';
        }
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(succes, echec, { timeout: 8000 });
        } else {
            echec();
        }
    })();

    function togglePwd(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }

    function checkStrength(pwd) {
        const bar = document.getElementById('strengthBar');
        let score = 0;
        if (pwd.length >= 6)  score++;
        if (pwd.length >= 10) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;

        const colors = ['#e94560','#e94560','#ff9800','#4caf50','#00a86b'];
        const widths  = ['20%','40%','60%','80%','100%'];
        bar.style.width      = widths[score - 1] || '0';
        bar.style.background = colors[score - 1] || '#eee';
    }
    </script>
</body>
</html>
