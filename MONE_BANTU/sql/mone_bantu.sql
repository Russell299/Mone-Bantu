-- ============================================================
--  MONË BANTU — Base de données COMPLÈTE v3
--  À importer dans phpMyAdmin (tout remplacer)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Créer et sélectionner la base
CREATE DATABASE IF NOT EXISTS mone_bantu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE mone_bantu;

-- Désactiver les FK pour éviter les erreurs d'ordre
SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer les tables si elles existent (ordre inverse des FK)
DROP TABLE IF EXISTS message_reactions;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS sanctions;
DROP TABLE IF EXISTS signalements;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS commentaires;
DROP TABLE IF EXISTS likes;
DROP TABLE IF EXISTS favoris;
DROP TABLE IF EXISTS evaluations;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS commandes;
DROP TABLE IF EXISTS paniers;
DROP TABLE IF EXISTS soldes_vendeurs;
DROP TABLE IF EXISTS boutiques;
DROP TABLE IF EXISTS reels;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS utilisateurs;

-- ============================================================
-- TABLE : utilisateurs
-- ============================================================
CREATE TABLE utilisateurs (
    id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    prenom           VARCHAR(100) NOT NULL,
    pseudo           VARCHAR(50)  NOT NULL,
    email            VARCHAR(100) NOT NULL,
    telephone        VARCHAR(20)  NOT NULL,
    password         VARCHAR(255) NOT NULL,
    ville            VARCHAR(100) DEFAULT NULL,
    quartier         VARCHAR(100) DEFAULT NULL,
    region           VARCHAR(100) DEFAULT NULL,
    departement      VARCHAR(100) DEFAULT NULL,
    arrondissement   VARCHAR(100) DEFAULT NULL,
    profession       VARCHAR(100) DEFAULT NULL,
    latitude         DECIMAL(10,7) DEFAULT NULL,
    longitude        DECIMAL(10,7) DEFAULT NULL,
    localisation_manuelle TINYINT(1) DEFAULT 0,   -- 1 = saisie à la main (pas de GPS sur l'appareil)
    derniere_localisation DATETIME DEFAULT NULL,
    photo_profil     VARCHAR(255) DEFAULT NULL,
    photo_couverture VARCHAR(255) DEFAULT NULL,
    note_moyenne     DECIMAL(3,2) DEFAULT 0.00,
    nombre_ventes    INT          DEFAULT 0,
    nombre_achats    INT          DEFAULT 0,
    date_inscription DATETIME     DEFAULT CURRENT_TIMESTAMP,
    role             ENUM('user','admin') DEFAULT 'user',
    statut_compte    ENUM('actif','suspendu','banni') DEFAULT 'actif',
    reset_token          VARCHAR(64)  DEFAULT NULL,
    reset_token_expire   DATETIME     DEFAULT NULL,
    suspension_fin   DATETIME DEFAULT NULL,   -- date de fin, uniquement si statut_compte = 'suspendu'
    UNIQUE KEY uk_pseudo (pseudo),
    UNIQUE KEY uk_email  (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : articles
-- ============================================================
CREATE TABLE articles (
    id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    vendeur_id       INT          NOT NULL,
    titre            VARCHAR(200) NOT NULL,
    description      TEXT         DEFAULT NULL,
    categorie        VARCHAR(50)  DEFAULT NULL,
    etat             ENUM('Neuf','Comme neuf','Bon état','État correct','À réparer') NOT NULL DEFAULT 'Bon état',
    prix             INT          NOT NULL DEFAULT 0,
    ville            VARCHAR(100) DEFAULT NULL,
    region           VARCHAR(100) DEFAULT NULL,   -- figée à la publication (position du vendeur à ce moment-là)
    quartier         VARCHAR(100) DEFAULT NULL,
    photos           TEXT         DEFAULT NULL,
    mode_vente       ENUM('boutique','personnel') NOT NULL DEFAULT 'personnel',
    statut           ENUM('disponible','vendu','reserve') DEFAULT 'disponible',
    date_publication DATETIME     DEFAULT CURRENT_TIMESTAMP,
    vues             INT          DEFAULT 0,
    favoris          INT          DEFAULT 0,
    partages         INT          DEFAULT 0,
    FOREIGN KEY (vendeur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : reels
-- ============================================================
CREATE TABLE reels (
    id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id   INT          NOT NULL,
    video_url        VARCHAR(255) NOT NULL,
    legende          VARCHAR(255) DEFAULT NULL,
    mode_publication ENUM('boutique','personnel') NOT NULL DEFAULT 'personnel',
    vues             INT          DEFAULT 0,
    date_publication DATETIME     DEFAULT CURRENT_TIMESTAMP,
    date_expiration  DATETIME     NOT NULL,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : boutiques
-- ============================================================
CREATE TABLE boutiques (
    id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id   INT          NOT NULL,
    nom_boutique     VARCHAR(100) NOT NULL,
    description      TEXT,
    piece_identite   VARCHAR(255) NOT NULL,
    telephone        VARCHAR(20)  NOT NULL,
    adresse          VARCHAR(255) DEFAULT NULL,
    date_creation    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_utilisateur (utilisateur_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : paniers (boutique uniquement)
-- ============================================================
CREATE TABLE paniers (
    id             INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT      NOT NULL,
    article_id     INT      NOT NULL,
    date_ajout     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_panier_article (utilisateur_id, article_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id)     REFERENCES articles(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : commandes (regroupe les articles boutique payés ensemble)
-- ============================================================
CREATE TABLE commandes (
    id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    acheteur_id      INT NOT NULL,
    montant_articles INT NOT NULL,
    frais            INT NOT NULL DEFAULT 0,
    montant_total    INT NOT NULL,
    statut           ENUM('en_attente','payee','annulee') DEFAULT 'en_attente',
    date_commande    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (acheteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : soldes_vendeurs (solde comptable à reverser à chaque vendeur)
-- ============================================================
CREATE TABLE soldes_vendeurs (
    utilisateur_id    INT NOT NULL PRIMARY KEY,
    solde             INT NOT NULL DEFAULT 0,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : favoris
-- ============================================================
CREATE TABLE favoris (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT      NOT NULL,
    article_id      INT      NOT NULL,
    date_ajout      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_favori (utilisateur_id, article_id),
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id)     REFERENCES articles(id)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : likes
-- ============================================================
CREATE TABLE likes (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    article_id      INT      NOT NULL,
    utilisateur_id  INT      NOT NULL,
    date_like       DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_like (article_id, utilisateur_id),
    FOREIGN KEY (article_id)     REFERENCES articles(id)     ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : commentaires
-- ============================================================
CREATE TABLE commentaires (
    id               INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    article_id       INT      NOT NULL,
    utilisateur_id   INT      NOT NULL,
    commentaire      TEXT     NOT NULL,
    nom_affiche      VARCHAR(100) DEFAULT NULL,
    type_affiche     ENUM('boutique','personnel') NOT NULL DEFAULT 'personnel',
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id)     REFERENCES articles(id)     ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : messages (avec colonnes media dès le départ)
-- ============================================================
CREATE TABLE messages (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    expediteur_id   INT      NOT NULL,
    destinataire_id INT      NOT NULL,
    article_id      INT      DEFAULT NULL,
    reel_id         INT      DEFAULT NULL,
    contenu         TEXT     NOT NULL,
    type            ENUM('texte','image','video','audio','fichier') NOT NULL DEFAULT 'texte',
    expediteur_nom_affiche VARCHAR(100) DEFAULT NULL,
    expediteur_type        ENUM('boutique','personnel') NOT NULL DEFAULT 'personnel',
    fichier_url     VARCHAR(500) DEFAULT NULL,
    fichier_nom     VARCHAR(255) DEFAULT NULL,
    fichier_taille  INT          DEFAULT NULL,
    date_envoi      DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu              TINYINT(1) DEFAULT 0,
    epingle_jusqu   DATETIME DEFAULT NULL,   -- NULL = pas épinglé ; sinon date d'expiration de l'épingle
    FOREIGN KEY (expediteur_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (destinataire_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id)      REFERENCES articles(id)     ON DELETE SET NULL,
    FOREIGN KEY (reel_id)         REFERENCES reels(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : message_reactions
-- ============================================================
CREATE TABLE message_reactions (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    message_id      INT      NOT NULL,
    utilisateur_id  INT      NOT NULL,
    emoji           VARCHAR(20) NOT NULL,
    date_reaction   DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reaction (message_id, utilisateur_id),
    FOREIGN KEY (message_id)     REFERENCES messages(id)     ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : signalements (rapports d'utilisateurs, en attente de traitement admin)
-- ============================================================
CREATE TABLE signalements (
    id                    INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    signalant_id          INT      NOT NULL,
    utilisateur_signale_id INT     NOT NULL,
    motif                 TEXT     NOT NULL,
    gravite               ENUM('leger','moyen','grave') NOT NULL DEFAULT 'moyen',
    preuve_url            VARCHAR(255) DEFAULT NULL,   -- capture d'écran / photo jointe par le signalant
    contexte              ENUM('article','message','autre') NOT NULL DEFAULT 'autre',
    article_id            INT      DEFAULT NULL,
    statut                ENUM('en_attente','traite','rejete') DEFAULT 'en_attente',
    admin_id              INT      DEFAULT NULL,
    date_creation         DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_traitement       DATETIME DEFAULT NULL,
    FOREIGN KEY (signalant_id)           REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (utilisateur_signale_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id)             REFERENCES articles(id)     ON DELETE SET NULL,
    FOREIGN KEY (admin_id)               REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : sanctions (historique des avertissements/suspensions/bannissements)
-- ============================================================
CREATE TABLE sanctions (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT      NOT NULL,
    admin_id        INT      DEFAULT NULL,   -- NULL = sanction automatique (système, pas d'admin)
    type            ENUM('avertissement','suspension','bannissement','levee') NOT NULL,
    motif           TEXT     NOT NULL,
    duree_jours     INT      DEFAULT NULL,   -- uniquement pour 'suspension'
    date_creation   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)       REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : notifications
-- ============================================================
CREATE TABLE notifications (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT      NOT NULL,   -- destinataire
    article_id      INT      DEFAULT NULL,
    type            ENUM('like','commentaire','partage','modification','vente','suppression','sanction','avis') NOT NULL,
    message         VARCHAR(255) NOT NULL,
    lu              TINYINT(1) DEFAULT 0,
    date_creation   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (article_id)     REFERENCES articles(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : transactions
-- ============================================================
CREATE TABLE transactions (
    id                INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    commande_id       INT      DEFAULT NULL,
    article_id        INT      NOT NULL,
    acheteur_id       INT      NOT NULL,
    vendeur_id        INT      NOT NULL,
    prix_accepte      INT      NOT NULL,
    commission        INT      NOT NULL DEFAULT 0,   -- montant des frais (15% ou 20%), figé à l'achat
    montant_total     INT      NOT NULL DEFAULT 0,   -- prix_accepte + commission
    statut            ENUM('en_attente_paiement','payee','terminee','litige','annulee') DEFAULT 'en_attente_paiement',
    mode_virement     ENUM('securise','direct') NOT NULL DEFAULT 'securise',
    mode_paiement     VARCHAR(50) DEFAULT NULL,
    date_transaction  DATETIME    DEFAULT CURRENT_TIMESTAMP,
    date_paiement     DATETIME    DEFAULT NULL,   -- paiement Mobile Money confirmé (argent retenu par la plateforme)
    date_limite       DATETIME    DEFAULT NULL,   -- date_paiement + 3 jours : libération auto si pas de confirmation/litige
    date_confirmation DATETIME    DEFAULT NULL,   -- réception confirmée (par le client ou automatiquement)
    litige_motif      TEXT        DEFAULT NULL,
    date_litige       DATETIME    DEFAULT NULL,
    resolution_admin  ENUM('vendeur','acheteur') DEFAULT NULL,
    date_resolution   DATETIME    DEFAULT NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id)     ON DELETE SET NULL,
    FOREIGN KEY (article_id)  REFERENCES articles(id)     ON DELETE CASCADE,
    FOREIGN KEY (acheteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (vendeur_id)  REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE : evaluations
-- ============================================================
CREATE TABLE evaluations (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    transaction_id  INT      NOT NULL,
    evaluateur_id   INT      NOT NULL,
    evalue_id       INT      NOT NULL,
    note            TINYINT  NOT NULL CHECK (note BETWEEN 1 AND 5),
    commentaire     TEXT     DEFAULT NULL,
    date_evaluation DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_transaction_evaluateur (transaction_id, evaluateur_id),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id)  ON DELETE CASCADE,
    FOREIGN KEY (evaluateur_id)  REFERENCES utilisateurs(id)  ON DELETE CASCADE,
    FOREIGN KEY (evalue_id)      REFERENCES utilisateurs(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réactiver les FK
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Compte admin par défaut
-- Mot de passe : admin123
-- ============================================================
INSERT INTO utilisateurs (nom, prenom, pseudo, email, telephone, password, role)
VALUES ('Admin', 'MONË BANTU', 'admin', 'admin@monebantu.cm', '600000000',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
