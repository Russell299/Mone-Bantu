<?php
// ============================================================
//  MONË BANTU — Mot de passe oublié
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';

if (estConnecte()) redirect($base_path . 'index.php');

$error = '';
$lien_reset = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Merci d'entrer une adresse email valide.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Message identique que le compte existe ou non (évite de révéler quels emails sont inscrits)
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expire  = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');
            $pdo->prepare("UPDATE utilisateurs SET reset_token = ?, reset_token_expire = ? WHERE id = ?")
                ->execute([$token, $expire, $user['id']]);

            // TODO_EMAIL : envoyer ce lien par email (via Brevo ou autre) une fois configuré.
            // En attendant, il est affiché directement ci-dessous pour permettre les tests.
            $lien_reset = 'reinitialiser_mot_de_passe.php?token=' . $token;
        }

        if (!$lien_reset) {
            // Toujours afficher un message neutre si l'email n'existe pas
            $error = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — MONË BANTU</title>
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
        .form-title { font-size: 1.3rem; color: #333; margin-bottom: 12px; font-weight: 600; }
        .form-sub { color: #777; font-size: 0.85rem; margin-bottom: 22px; line-height: 1.5; }
        .alert { background: #fff0f0; color: #c00; border-left: 4px solid #e94560; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .alert-info { background: #f0f7ff; color: #0f3460; border-left: 4px solid #0f3460; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; color: #555; font-size: 0.9rem; font-weight: 500; }
        .input-wrap { position: relative; }
        .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #aaa; font-size: 0.95rem; }
        .input-wrap input { width: 100%; padding: 12px 14px 12px 38px; border: 2px solid #e8e8e8; border-radius: 10px; font-size: 1rem; font-family: 'Poppins', sans-serif; transition: border-color 0.3s, box-shadow 0.3s; background: #fafafa; }
        .input-wrap input:focus { outline: none; border-color: #e94560; box-shadow: 0 0 0 3px rgba(233,69,96,0.1); background: white; }
        .btn-submit { width: 100%; padding: 14px; background: linear-gradient(135deg, #e94560, #c73652); color: white; border: none; border-radius: 10px; font-size: 1rem; font-family: 'Poppins', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.3s; letter-spacing: 0.5px; margin-top: 5px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233,69,96,0.4); }
        .auth-links { text-align: center; margin-top: 20px; }
        .auth-links a { color: #e94560; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .auth-links a:hover { text-decoration: underline; }
        .lien-box { background: #f0fff4; border: 1px dashed #00a86b; border-radius: 10px; padding: 14px; margin-bottom: 20px; font-size: 0.85rem; word-break: break-all; }
        .lien-box a { color: #00a86b; font-weight: 600; }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="brand">
            <h1>MONË BANTU</h1>
            <p>Achetez &amp; vendez en toute confiance au Cameroun</p>
        </div>

        <h2 class="form-title">Mot de passe oublié</h2>
        <p class="form-sub">Entre ton adresse email — un lien de réinitialisation te sera fourni.</p>

        <?php if ($error): ?>
            <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo esc($error); ?></div>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error): ?>
            <?php if ($lien_reset): ?>
                <div class="lien-box">
                    <i class="fas fa-triangle-exclamation" style="color:#f9a826;"></i>
                    L'envoi automatique par email n'est pas encore configuré — voici ton lien de réinitialisation (valable 1h) :<br><br>
                    <a href="<?php echo esc($lien_reset); ?>"><?php echo esc($lien_reset); ?></a>
                </div>
            <?php else: ?>
                <div class="alert-info" style="padding:12px 15px;border-radius:8px;margin-bottom:20px;font-size:0.9rem;">
                    <i class="fas fa-info-circle"></i> Si un compte existe avec cet email, un lien de réinitialisation a été généré.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required placeholder="votre@email.com"
                           value="<?php echo isset($_POST['email']) ? esc($_POST['email']) : ''; ?>">
                </div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Obtenir le lien</button>
        </form>

        <div class="auth-links">
            <p><a href="login.php"><i class="fas fa-arrow-left"></i> Retour à la connexion</a></p>
        </div>
    </div>
</body>
</html>
