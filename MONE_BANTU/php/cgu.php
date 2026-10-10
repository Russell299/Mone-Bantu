<?php
// ============================================================
//  MONË BANTU — Conditions Générales d'Utilisation
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conditions Générales d'Utilisation — MONË BANTU</title>
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .legal-wrap { max-width: 760px; margin: 0 auto; padding: 30px 20px 60px; }
        .legal-wrap h1 { margin-bottom: 6px; }
        .legal-maj { color: var(--text-muted); font-size: 0.85rem; margin-bottom: 30px; }
        .legal-wrap h2 { font-size: 1.15rem; margin: 32px 0 12px; color: var(--secondary); }
        .legal-wrap p, .legal-wrap li { color: var(--text); line-height: 1.7; font-size: 0.95rem; }
        .legal-wrap ul { padding-left: 20px; margin-bottom: 14px; }
        .legal-wrap li { margin-bottom: 6px; }
        .legal-note { background: var(--light-bg); border-left: 3px solid var(--primary); padding: 12px 16px; border-radius: 8px; font-size: 0.88rem; margin: 16px 0; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <a href="<?php echo $base_path; ?>index.php"><h1>MONË <span>BANTU</span></h1></a>
            </div>
        </div>
    </nav>

    <div class="legal-wrap">
        <h1>Conditions Générales d'Utilisation</h1>
        <p class="legal-maj">Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>

        <p>Les présentes Conditions Générales d'Utilisation ("CGU") régissent l'accès et l'utilisation de la plateforme Monë Bantu, marketplace camerounaise de vente entre particuliers et boutiques. En créant un compte, vous acceptez ces conditions dans leur intégralité.</p>

        <h2>1. Inscription et compte</h2>
        <p>L'inscription nécessite un email, un numéro de téléphone valide, et une localisation (région et ville au minimum). Vos informations personnelles (nom, prénom, email, téléphone, ville) ne sont modifiables qu'à partir de 7 mois après la création du compte, pour des raisons de sécurité et de lutte contre la fraude.</p>

        <h2>2. Localisation obligatoire</h2>
        <p>L'accès à la plateforme requiert une localisation à jour (automatique via GPS si disponible, ou renseignée manuellement). Cette information s'affiche sur vos publications pour renforcer la confiance entre utilisateurs.</p>

        <h2>3. Achats, ventes et frais</h2>
        <ul>
            <li>Les articles vendus par un particulier sont soumis à des frais de service de <strong>20%</strong>, à la charge de l'acheteur.</li>
            <li>Les articles vendus par une boutique sont soumis à des frais de service de <strong>15%</strong>, à la charge de l'acheteur.</li>
            <li>Ces frais rémunèrent le fonctionnement de la plateforme, y compris le système de paiement sécurisé.</li>
        </ul>

        <h2>4. Paiement sécurisé</h2>
        <p>Par défaut, le paiement d'un acheteur est retenu par Monë Bantu jusqu'à confirmation de réception de l'article, ou automatiquement libéré au vendeur après 3 jours sans confirmation ni signalement. Un mode de "virement direct" (règlement immédiat du vendeur) peut être débloqué entre deux utilisateurs ayant un historique de transactions réussies.</p>

        <h2>5. Litiges</h2>
        <p>En cas de problème (article non conforme, non-présentation d'une des parties, etc.), l'acheteur peut signaler le problème avant confirmation. Un membre de l'équipe Monë Bantu examine alors la situation et tranche entre le paiement du vendeur ou l'annulation de la transaction.</p>

        <h2>6. Comportement des utilisateurs et sanctions</h2>
        <p>Toute forme d'escroquerie, de fraude ou de comportement abusif est strictement interdite. Selon la gravité, un compte peut faire l'objet d'un avertissement, d'une suspension temporaire, ou d'un bannissement définitif. Les signalements appuyés par des preuves peuvent entraîner une sanction immédiate.</p>
        <div class="legal-note">
            <i class="fas fa-circle-info"></i> Un signalement resté sans traitement par un administrateur pendant plus de 3 heures peut déclencher une sanction automatique, selon des règles fondées sur la gravité déclarée et le nombre de signalements reçus.
        </div>

        <h2>7. Contenu publié</h2>
        <p>Vous êtes seul responsable des annonces, messages, photos, vidéos ("Réels") et commentaires que vous publiez. Monë Bantu se réserve le droit de retirer tout contenu contraire à ces CGU ou à la loi camerounaise.</p>

        <h2>8. Résiliation</h2>
        <p>Vous pouvez cesser d'utiliser la plateforme à tout moment. Monë Bantu peut suspendre ou résilier un compte en cas de violation des présentes CGU.</p>

        <h2>9. Contact</h2>
        <p>Pour toute question relative à ces conditions : <?php echo SITE_EMAIL; ?></p>

        <p style="margin-top:30px;"><a href="politique_confidentialite.php" style="color:var(--primary);font-weight:600;">Voir aussi notre politique de confidentialité →</a></p>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> MONË BANTU — Tous droits réservés</p>
            </div>
        </div>
    </footer>
</body>
</html>
