<?php
// ============================================================
//  MONË BANTU — Configuration principale (sans troncature)
// ============================================================

define('SITE_NAME', 'MONË BANTU');
define('SITE_EMAIL', 'contact@monebantu.cm');
define('SITE_TEL', '+237 658 389 221');
define('SITE_VERSION', '2.0');

define('TAUX_COMMISSION_BOUTIQUE', 0.15);
define('TAUX_COMMISSION_PARTICULIER', 0.20);

define('DB_HOST', 'localhost');
define('DB_NAME', 'mone_bantu');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Connexion PDO ----
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Erreur DB : " . $e->getMessage());
    http_response_code(500);
    die('Erreur de connexion à la base de données.');
}

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Vérification de sanction (protégée) ----
if (isset($_SESSION['user_id'])) {
    try {
        $stmtSanction = $pdo->prepare("SELECT statut_compte, suspension_fin FROM utilisateurs WHERE id = ?");
        $stmtSanction->execute([$_SESSION['user_id']]);
        $sanctionRow = $stmtSanction->fetch();

        if ($sanctionRow) {
            $toujoursSuspendu = $sanctionRow['statut_compte'] === 'suspendu'
                && $sanctionRow['suspension_fin'] !== null
                && new DateTime() < new DateTime($sanctionRow['suspension_fin']);

            if ($sanctionRow['statut_compte'] === 'banni' || $toujoursSuspendu) {
                $_SESSION = [];
                session_destroy();
                session_start();
                $_SESSION['erreur_sanction'] = $sanctionRow['statut_compte'] === 'banni'
                    ? "Ce compte a été banni définitivement de Monë Bantu."
                    : "Ce compte est temporairement suspendu.";
                $redirection = (strpos($_SERVER['PHP_SELF'], '/php/') !== false ? '' : 'php/') . 'login.php';
                header('Location: ' . $redirection);
                exit();
            }
        }
    } catch (Throwable $e) {
        error_log("Verif sanction : " . $e->getMessage());
    }
}

// ============================================================
//  Langue (protégée contre les fichiers manquants)
// ============================================================
define('LANGUES_DISPONIBLES', ['fr', 'en']);

$GLOBALS['LANGUE_DEFINIE'] = isset($_SESSION['langue']);
$_LANGUE_ACTIVE = $_SESSION['langue'] ?? 'fr';
if (!in_array($_LANGUE_ACTIVE, LANGUES_DISPONIBLES, true)) $_LANGUE_ACTIVE = 'fr';

$GLOBALS['LANGUE_ACTIVE'] = $_LANGUE_ACTIVE;
$GLOBALS['TRADUCTIONS']   = [];

$fichier_langue = __DIR__ . '/../lang/' . $_LANGUE_ACTIVE . '.php';
if (file_exists($fichier_langue)) {
    $traductions = require $fichier_langue;
    if (is_array($traductions)) $GLOBALS['TRADUCTIONS'] = $traductions;
}
if (empty($GLOBALS['TRADUCTIONS'])) {
    $fichier_fr = __DIR__ . '/../lang/fr.php';
    if (file_exists($fichier_fr)) {
        $traductions = require $fichier_fr;
        if (is_array($traductions)) $GLOBALS['TRADUCTIONS'] = $traductions;
    }
}

function t(string $cle): string {
    return $GLOBALS['TRADUCTIONS'][$cle] ?? $cle;
}

// ============================================================
//  Fonctions utilitaires
// ============================================================

function estConnecte(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit();
}

function userId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function esc(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatPrix(float $prix): string {
    return number_format($prix, 0, ',', ' ') . ' FCFA';
}

function compresserImage(string $chemin_source, string $chemin_dest, int $dimension_max = 1280, int $qualite = 78): bool {
    if (!function_exists('imagecreatetruecolor')) return false;
    $info = @getimagesize($chemin_source);
    if (!$info) return false;
    [$largeur, $hauteur] = $info;

    switch ($info['mime']) {
        case 'image/jpeg': $source_img = @imagecreatefromjpeg($chemin_source); break;
        case 'image/png':  $source_img = @imagecreatefrompng($chemin_source); break;
        case 'image/webp': $source_img = @imagecreatefromwebp($chemin_source); break;
        default: return false;
    }
    if (!$source_img) return false;

    $ratio        = min(1, $dimension_max / max($largeur, $hauteur));
    $nouv_largeur = max(1, (int)round($largeur * $ratio));
    $nouv_hauteur = max(1, (int)round($hauteur * $ratio));

    $image_finale = imagecreatetruecolor($nouv_largeur, $nouv_hauteur);
    $blanc = imagecolorallocate($image_finale, 255, 255, 255);
    imagefill($image_finale, 0, 0, $blanc);
    imagecopyresampled($image_finale, $source_img, 0, 0, 0, 0, $nouv_largeur, $nouv_hauteur, $largeur, $hauteur);

    $ok = imagejpeg($image_finale, $chemin_dest, $qualite);
    imagedestroy($source_img);
    imagedestroy($image_finale);
    return $ok;
}

function tempsRelatif(string $dateStr): string {
    $date = new DateTime($dateStr);
    $now  = new DateTime();
    $diff = $now->diff($date);

    if ($diff->days === 0) {
        if ($diff->h === 0) {
            return $diff->i <= 1 ? "À l'instant" : "Il y a {$diff->i} min";
        }
        return "Il y a {$diff->h} h";
    }
    if ($diff->days === 1) return "Hier";
    if ($diff->days < 7)  return "Il y a {$diff->days} jours";
    return $date->format('d/m/Y');
}

function initiales(string $pseudo): string {
    return strtoupper(mb_substr($pseudo, 0, 2, 'UTF-8'));
}

function estBoutique(PDO $pdo, int $utilisateur_id): bool {
    $stmt = $pdo->prepare("SELECT id FROM boutiques WHERE utilisateur_id = ?");
    $stmt->execute([$utilisateur_id]);
    return (bool)$stmt->fetch();
}

define('SEUIL_VIREMENT_DIRECT', 5);

function transactionsReussiesEntre(PDO $pdo, int $userA, int $userB): int {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM transactions
        WHERE statut = 'terminee'
        AND ((acheteur_id = ? AND vendeur_id = ?) OR (acheteur_id = ? AND vendeur_id = ?))
    ");
    $stmt->execute([$userA, $userB, $userB, $userA]);
    return (int)$stmt->fetchColumn();
}

function virementDirectDisponible(PDO $pdo, int $acheteur_id, int $vendeur_id): bool {
    return transactionsReussiesEntre($pdo, $acheteur_id, $vendeur_id) >= SEUIL_VIREMENT_DIRECT;
}

// ============================================================
//  Notifications
// ============================================================

function utilisateursInteresses(PDO $pdo, int $article_id, ?int $exclure_id = null): array {
    $ids = [];
    $stmt = $pdo->prepare("SELECT vendeur_id FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    if ($v = $stmt->fetchColumn()) $ids[] = (int)$v;

    foreach (['favoris', 'likes', 'commentaires'] as $table) {
        $stmt = $pdo->prepare("SELECT DISTINCT utilisateur_id FROM $table WHERE article_id = ?");
        $stmt->execute([$article_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) $ids[] = (int)$uid;
    }

    $ids = array_unique($ids);
    if ($exclure_id !== null) $ids = array_filter($ids, fn($id) => $id !== $exclure_id);
    return array_values($ids);
}

function creerNotification(PDO $pdo, int $destinataire_id, ?int $article_id, string $type, string $message): void {
    $pdo->prepare("
        INSERT INTO notifications (utilisateur_id, article_id, type, message, date_creation)
        VALUES (?, ?, ?, ?, NOW())
    ")->execute([$destinataire_id, $article_id, $type, $message]);
}

function notifierInteresses(PDO $pdo, int $article_id, string $type, string $message, ?int $exclure_id = null): void {
    foreach (utilisateursInteresses($pdo, $article_id, $exclure_id) as $uid) {
        creerNotification($pdo, $uid, $article_id, $type, $message);
    }
}

/**
 * Escalade les signalements non traités (fonction disponible mais NON appelée
 * automatiquement pour éviter la troncature si la table manque).
 */
function escaladerSignalementsExpires(PDO $pdo): void {
    // Désactivée temporairement — à réactiver une fois la base complète.
    return;
}

// ============================================================
//  Localisation obligatoire
// ============================================================
const REGIONS_CAMEROUN = [
    'Adamaoua', 'Centre', 'Est', 'Extrême-Nord', 'Littoral',
    'Nord', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Ouest',
];

function localisationDefinie(PDO $pdo, int $utilisateur_id): bool {
    $stmt = $pdo->prepare("SELECT region, latitude, localisation_manuelle FROM utilisateurs WHERE id = ?");
    $stmt->execute([$utilisateur_id]);
    $u = $stmt->fetch();
    if (!$u) return false;
    return !empty($u['region']) && (!empty($u['latitude']) || (int)$u['localisation_manuelle'] === 1);
}

function exigerLocalisation(PDO $pdo, string $base_path): void {
    if (!empty($_SESSION['localisation_confirmee'])) return;
    if (localisationDefinie($pdo, userId())) {
        $_SESSION['localisation_confirmee'] = true;
        return;
    }
    redirect($base_path . 'php/localisation.php');
}

function estAdmin(PDO $pdo, int $utilisateur_id): bool {
    $stmt = $pdo->prepare("SELECT role FROM utilisateurs WHERE id = ?");
    $stmt->execute([$utilisateur_id]);
    return $stmt->fetchColumn() === 'admin';
}

function modeActif(): string {
    return ($_SESSION['mode_actif'] ?? 'personnel') === 'boutique' ? 'boutique' : 'personnel';
}

function nomAffiche(PDO $pdo, int $utilisateur_id, string $mode): string {
    if ($mode === 'boutique') {
        $stmt = $pdo->prepare("SELECT nom_boutique FROM boutiques WHERE utilisateur_id = ?");
        $stmt->execute([$utilisateur_id]);
        $nom = $stmt->fetchColumn();
        if ($nom) return $nom;
    }
    $stmt = $pdo->prepare("SELECT pseudo FROM utilisateurs WHERE id = ?");
    $stmt->execute([$utilisateur_id]);
    return $stmt->fetchColumn() ?: 'Utilisateur';
}

define('DELAI_MODIFICATION_PROFIL_MOIS', 7);

function statutModificationProfil(PDO $pdo, int $utilisateur_id): array {
    $stmt = $pdo->prepare("SELECT date_inscription FROM utilisateurs WHERE id = ?");
    $stmt->execute([$utilisateur_id]);
    $date_inscription = new DateTime($stmt->fetchColumn() ?: 'now');
    $date_limite = (clone $date_inscription)->modify('+' . DELAI_MODIFICATION_PROFIL_MOIS . ' months');
    return [
        'autorise'    => new DateTime() >= $date_limite,
        'date_limite' => $date_limite,
    ];
}

/**
 * Libère les paiements expirés (fonction disponible mais NON appelée
 * automatiquement pour éviter la troncature si la table manque).
 */
function liberationPaiementsExpires(PDO $pdo): void {
    // Désactivée temporairement — à réactiver une fois la base complète.
    return;
}

// ============================================================
//  ⚠️ AUCUN APPEL SYSTÈME ICI
//  (c'était la cause de la troncature — escaladerSignalementsExpires
//  était appelé à chaque chargement de page et plantait si la table
//  signalements n'existait pas encore.)
// ============================================================

// ============================================================
//  Données de référence
// ============================================================

$categories = [
    'Homme'       => ['Vêtements', 'Chaussures', 'Accessoires'],
    'Femme'       => ['Vêtements', 'Chaussures', 'Accessoires', 'Sacs'],
    'Enfant'      => ['Vêtements', 'Jouets', 'Chaussures'],
    'Électronique'=> ['Téléphones', 'Ordinateurs', 'Accessoires tech'],
    'Maison'      => ['Décoration', 'Électroménager', 'Meubles'],
    'Sport'       => ['Vêtements de sport', 'Équipement'],
    'Autres'      => ['Livres', 'CD/DVD', 'Divers'],
];

$villes = [
    'Yaoundé', 'Douala', 'Garoua', 'Bamenda',
    'Maroua', 'Ngaoundéré', 'Bafoussam', 'Bertoua',
    'Ebolowa', 'Kumba',
];

$moyens_paiement = [
    'Orange Money', 'MTN Mobile Money', 'Express Union',
    'Espèces', 'Transfert bancaire',
];

$etats_article = [
    'Neuf', 'Comme neuf', 'Bon état', 'État correct', 'À réparer',
];