<?php
// ============================================================
//  MONË BANTU — Connexion
// ============================================================

$base_path = '../';
require_once $base_path . 'php/config.php';

if (estConnecte()) {
    redirect($base_path . 'index.php');
}

$error = '';
if (!empty($_SESSION['erreur_sanction'])) {
    $error = $_SESSION['erreur_sanction'];
    unset($_SESSION['erreur_sanction']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = t('login_erreur_champs');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Auto-réactivation si la suspension est terminée
            if ($user['statut_compte'] === 'suspendu' && $user['suspension_fin'] !== null && new DateTime() >= new DateTime($user['suspension_fin'])) {
                $pdo->prepare("UPDATE utilisateurs SET statut_compte = 'actif', suspension_fin = NULL WHERE id = ?")->execute([$user['id']]);
                $user['statut_compte'] = 'actif';
            }

            if ($user['statut_compte'] === 'banni') {
                $error = t('login_erreur_banni');
            } elseif ($user['statut_compte'] === 'suspendu') {
                $fin = (new DateTime($user['suspension_fin']))->format('d/m/Y à H:i');
                $error = sprintf(t('login_erreur_suspendu'), $fin);
            } else {
                session_regenerate_id(true); // Sécurité : nouvel ID de session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_pseudo']= $user['pseudo'];
                $_SESSION['user_nom']   = trim($user['prenom'] . ' ' . $user['nom']);
                redirect($base_path . 'index.php');
            }
        } else {
            $error = t('login_erreur_identifiants');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $GLOBALS['LANGUE_ACTIVE']; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('login_titre'); ?> — MONË BANTU</title>
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
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Bulles animées en arrière-plan */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.15;
            animation: float 6s ease-in-out infinite;
        }
        body::before {
            width: 300px; height: 300px;
            background: #e94560;
            top: -100px; left: -100px;
        }
        body::after {
            width: 200px; height: 200px;
            background: #0f3460;
            bottom: -80px; right: -60px;
            animation-delay: 3s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(-20px) scale(1.05); }
        }

        .auth-card {
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            padding: 45px 40px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.4s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #e94560, #0f3460);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }
        .brand p {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .form-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 25px;
            font-weight: 600;
        }

        .alert {
            background: #fff0f0;
            color: #c00;
            border-left: 4px solid #e94560;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
            font-size: 0.95rem;
        }
        .input-wrap input {
            width: 100%;
            padding: 12px 14px 12px 38px;
            border: 2px solid #e8e8e8;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: #fafafa;
        }
        .input-wrap input:focus {
            outline: none;
            border-color: #e94560;
            box-shadow: 0 0 0 3px rgba(233,69,96,0.1);
            background: white;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #aaa;
            padding: 0;
        }
        .toggle-password:hover { color: #e94560; }

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
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233,69,96,0.4);
        }
        .btn-submit:active { transform: translateY(0); }

        .auth-links {
            text-align: center;
            margin-top: 20px;
        }
        .auth-links p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .auth-links a {
            color: #e94560;
            text-decoration: none;
            font-weight: 600;
        }
        .auth-links a:hover { text-decoration: underline; }

        .divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
            color: #ccc;
            font-size: 0.85rem;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #eee;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <p style="text-align:right;font-size:0.78rem;margin-bottom:8px;">
            <a href="definir_langue.php?langue=fr" style="color:<?php echo $GLOBALS['LANGUE_ACTIVE']==='fr' ? 'var(--primary,#e94560)' : '#aaa'; ?>;font-weight:600;">FR</a>
            <span style="color:#ddd;"> / </span>
            <a href="definir_langue.php?langue=en" style="color:<?php echo $GLOBALS['LANGUE_ACTIVE']==='en' ? 'var(--primary,#e94560)' : '#aaa'; ?>;font-weight:600;">EN</a>
        </p>
        <div class="brand">
            <h1>MONË BANTU</h1>
            <p><?php echo t('login_slogan'); ?></p>
        </div>

        <h2 class="form-title"><?php echo t('login_titre'); ?></h2>

        <?php if ($error): ?>
            <div class="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo esc($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <div class="form-group">
                <label for="email"><?php echo t('login_email_label'); ?></label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? esc($_POST['email']) : ''; ?>"
                           placeholder="votre@email.com">
                </div>
            </div>

            <div class="form-group">
                <label for="password"><?php echo t('login_password_label'); ?></label>
                <div class="input-wrap">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••">
                    <button type="button" class="toggle-password" onclick="togglePwd()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-sign-in-alt"></i> <?php echo t('login_btn'); ?>
            </button>
        </form>

        <p style="text-align:center;margin-top:12px;">
            <a href="mot_de_passe_oublie.php" style="font-size:0.85rem;color:var(--text-muted);"><?php echo t('login_mdp_oublie'); ?></a>
        </p>

        <div class="divider"><?php echo t('login_ou'); ?></div>

        <div class="auth-links">
            <p><?php echo t('login_pas_de_compte'); ?> <a href="register.php"><?php echo t('login_creer_compte'); ?></a></p>
            <p><a href="<?php echo $base_path; ?>index.php"><i class="fas fa-arrow-left"></i> <?php echo t('login_retour_accueil'); ?></a></p>
        </div>
    </div>

    <script>
    function togglePwd() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    </script>
</body>
</html>
