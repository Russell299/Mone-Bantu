<?php
// ============================================================
//  MONË BANTU — Réinitialiser le mot de passe
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (estConnecte()) redirect($base_path . 'index.php');

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$succes = false;

$stmt = $pdo->prepare("SELECT id, reset_token_expire FROM utilisateurs WHERE reset_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch();

$token_valide = $user && new DateTime() < new DateTime($user['reset_token_expire']);

if (!$token) {
    $error = "Lien invalide.";
} elseif (!$token_valide) {
    $error = "Ce lien a expiré ou n'est plus valide. Merci de refaire une demande.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valide) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("
            UPDATE utilisateurs SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ?
        ")->execute([$hashed, $user['id']]);
        $succes = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe — MONË BANTU</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif; min-height: 100vh; display: flex;
            align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 20px; position: relative; overflow: hidden;
        }
        body::before, body::after { content: ''; position: absolute; border-radius: 50%; opacity: 0.15; animation: float 6s ease-in-out infinite; }
        body::before { width: 300px; height: 300px; background: #e94560; top: -100px; left: -100px; }
        body::after { width: 200px; height: 200px; background: #0f3460; bottom: -80px; right: -60px; animation-delay: 3s; }
        @keyframes float { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-20px) scale(1.05); } }
        .auth-card {
            background: rgba(255,255,255,0.97); border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            width: 100%; max-width: 420px; padding: 45px 40px; position: relative; z-index: 1; animation: slideUp 0.4s ease;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .brand { text-align: center; margin-bottom: 30px; }
        .brand h1 { font-size: 2rem; font-weight: 700; background: linear-gradient(135deg, #e94560, #0f3460); -webkit-background-clip: text; -webkit-text-fill-color: transparent; letter-spacing: 2px; }
        .brand p { color: #666; font-size: 0.9rem; margin-top: 5px; }
        .form-title { font-size: 1.3rem; color: #333; margin-bottom: 22px; font-weight: 600; }
        .alert { background: #fff0f0; color: #c00; border-left: 4px solid #e94560; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .alert-succes { background: #f0fff4; color: #00a86b; border-left: 4px solid #00a86b; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-size: 0.9rem; font-weight: 500; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.95rem; }
        .input-wrap input { width: 100%; padding: 12px 14px 12px 38px; border: 2px solid #e8e8e8; border-radius: 10px; font-size: 1rem; font-family: 'Poppins', sans-serif; background: #fafafa; }
        .input-wrap input:focus { outline: none; border-color: #e94560; box-shadow: 0 0 0 3px rgba(233,69,96,0.1); background: white; }
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #e94560, #c73652); color: white; border: none; border-radius: 10px; font-size: 1rem; font-family: 'Poppins', sans-serif; font-weight: 600; cursor: pointer; margin-top: 5px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233,69,96,0.4); }
        .auth-links { text-align: center; margin-top: 20px; }
        .auth-links a { color: #e94560; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .auth-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <h1>MONË BANTU</h1>
            <p>Achetez &amp; vendez en toute confiance au Cameroun</p>
        </div>

        <h2 class="form-title">Nouveau mot de passe</h2>

        <?php if ($succes): ?>
            <div class="alert alert-succes"><i class="fas fa-check-circle"></i> Mot de passe mis à jour avec succès !</div>
            <div class="auth-links"><p><a href="login.php">Se connecter maintenant →</a></p></div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?></div>
            <?php endif; ?>

            <?php if ($token_valide): ?>
            <form method="POST" action="">
                <input type="hidden" name="token" value="<?php echo esc($token); ?>">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe <small style="color:#aaa;">(min. 6 car.)</small></label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••">
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="••••••••">
                    </div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-check"></i> Réinitialiser</button>
            </form>
            <?php else: ?>
                <div class="auth-links"><p><a href="mot_de_passe_oublie.php">Refaire une demande →</a></p></div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="auth-links">
            <p><a href="login.php"><i class="fas fa-arrow-left"></i> Retour à la connexion</a></p>
        </div>
    </div>
</body>
</html>
