<?php
// ============================================================
//  MONË BANTU — Politique de confidentialité
// ============================================================
$base_path = '../';
require_once $base_path . 'php/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politique de confidentialité — MONË BANTU</title>
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
        .legal-table { width:100%; border-collapse:collapse; margin:14px 0; font-size:0.88rem; }
        .legal-table th, .legal-table td { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border); }
        .legal-table th { color:var(--text-muted); font-weight:600; }
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
        <h1>Politique de confidentialité</h1>
        <p class="legal-maj">Dernière mise à jour : <?php echo date('d/m/Y'); ?></p>

        <p>Monë Bantu traite des données à caractère personnel dans le respect de la loi camerounaise n° 2024/017 relative à la protection des données à caractère personnel. Cette page explique quelles données sont collectées, pourquoi, et comment elles sont utilisées.</p>

        <h2>1. Données collectées</h2>
        <table class="legal-table">
            <tr><th>Donnée</th><th>Pourquoi</th></tr>
            <tr><td>Nom, prénom, pseudo</td><td>Identification sur la plateforme</td></tr>
            <tr><td>Email, téléphone</td><td>Connexion, contact, récupération de compte</td></tr>
            <tr><td>Profession</td><td>Affichée sur vos annonces (confiance entre utilisateurs)</td></tr>
            <tr><td>Localisation (région, département, arrondissement, ville, coordonnées GPS)</td><td>Affichage sur vos annonces, lutte contre les fausses localisations</td></tr>
            <tr><td>Photo de profil / couverture</td><td>Personnalisation du profil</td></tr>
            <tr><td>Messages, articles, Réels, commentaires</td><td>Fonctionnement du service</td></tr>
            <tr><td>Historique de transactions</td><td>Paiement sécurisé, litiges, calcul du niveau de confiance</td></tr>
            <tr><td>Pièce d'identité (boutiques uniquement)</td><td>Vérification de l'identité du vendeur boutique</td></tr>
        </table>

        <h2>2. Localisation</h2>
        <p>La localisation est obligatoire pour utiliser Monë Bantu. Elle est collectée automatiquement via le GPS de votre appareil lorsque c'est possible, ou renseignée manuellement sinon. Elle est actualisée à chaque nouvelle publication pour refléter votre position réelle.</p>

        <h2>3. Qui a accès à vos données</h2>
        <ul>
            <li>Les autres utilisateurs voient : pseudo, nom/prénom, profession, localisation, photo de profil, note moyenne, et le contenu que vous publiez publiquement.</li>
            <li>L'équipe Monë Bantu accède aux données nécessaires pour traiter les litiges, signalements et demandes de support.</li>
            <li>Aucune donnée n'est vendue à des tiers.</li>
        </ul>

        <h2>4. Paiement</h2>
        <p>Les informations liées aux paiements Mobile Money sont traitées via notre prestataire de paiement agréé. Monë Bantu ne stocke pas les identifiants de connexion à vos comptes Orange Money / MTN MoMo.</p>

        <h2>5. Conservation des données</h2>
        <p>Vos données sont conservées tant que votre compte est actif. En cas de suppression de compte, les données sont supprimées, à l'exception des informations nécessaires au respect d'obligations légales (ex : historique de transactions à des fins comptables).</p>

        <h2>6. Vos droits</h2>
        <p>Conformément à la loi n° 2024/017, vous disposez d'un droit d'accès, de rectification et de suppression de vos données personnelles. Pour exercer ces droits, contactez-nous à <?php echo SITE_EMAIL; ?>.</p>

        <div class="legal-note">
            <i class="fas fa-circle-info"></i> Certaines informations (comme votre historique de transactions ou vos sanctions actives) ne peuvent pas être supprimées tant qu'elles sont nécessaires au bon fonctionnement de la plateforme ou à la résolution d'un litige en cours.
        </div>

        <h2>7. Cookies et session</h2>
        <p>Monë Bantu utilise des cookies de session strictement nécessaires au fonctionnement du site (rester connecté, mémoriser votre langue). Aucun cookie publicitaire ou de suivi tiers n'est utilisé.</p>

        <h2>8. Contact</h2>
        <p>Pour toute question relative à cette politique : <?php echo SITE_EMAIL; ?></p>

        <p style="margin-top:30px;"><a href="cgu.php" style="color:var(--primary);font-weight:600;">← Voir aussi nos Conditions Générales d'Utilisation</a></p>
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
