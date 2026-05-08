-- ============================================================
-- NEXUS SHOP — Script SQL complet
-- Base de données : boutique de matériel informatique & PC Gaming
-- Encodage : UTF-8 | Moteur : InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS nexus_shop
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE nexus_shop;

-- ------------------------------------------------------------
-- TABLE : utilisateurs
-- Stocke les comptes clients et administrateurs
-- ------------------------------------------------------------
CREATE TABLE utilisateurs (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(80)     NOT NULL,
    email       VARCHAR(180)    NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255)   NOT NULL,              -- Hash bcrypt
    role        ENUM('client','admin') NOT NULL DEFAULT 'client',
    token_cookie VARCHAR(64)    DEFAULT NULL,          -- Pour $_COOKIE reconnexion
    cree_le     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TABLE : categories
-- Hiérarchie des catégories de produits
-- ------------------------------------------------------------
CREATE TABLE categories (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nom         VARCHAR(100)    NOT NULL,
    slug        VARCHAR(100)    NOT NULL UNIQUE,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TABLE : produits
-- Catalogue des articles en vente
-- ------------------------------------------------------------
CREATE TABLE produits (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    categorie_id    INT UNSIGNED    NOT NULL,
    nom             VARCHAR(200)    NOT NULL,
    slug            VARCHAR(200)    NOT NULL UNIQUE,
    description     TEXT            NOT NULL,
    prix            DECIMAL(10,2)   NOT NULL,
    stock           INT UNSIGNED    NOT NULL DEFAULT 0,
    image           VARCHAR(255)    NOT NULL DEFAULT 'default.png',
    marque          VARCHAR(100)    DEFAULT NULL,
    specifications  JSON            DEFAULT NULL,      -- Specs techniques (JSON)
    est_vedette     TINYINT(1)      NOT NULL DEFAULT 0,
    cree_le         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_categorie (categorie_id),
    INDEX idx_prix      (prix),
    FULLTEXT idx_recherche (nom, description)          -- Recherche full-text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- TABLE : panier
-- Panier persistant en base (complète $_SESSION)
-- ------------------------------------------------------------
CREATE TABLE panier (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    utilisateur_id  INT UNSIGNED    NOT NULL,
    produit_id      INT UNSIGNED    NOT NULL,
    quantite        INT UNSIGNED    NOT NULL DEFAULT 1,
    ajoute_le       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_produit (utilisateur_id, produit_id),   -- 1 ligne par produit/user
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id)     REFERENCES produits(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DONNÉES DE TEST — Catégories
-- ============================================================
INSERT INTO categories (nom, slug) VALUES
  ('Processeurs',      'processeurs'),
  ('Cartes Graphiques','cartes-graphiques'),
  ('RAM & Mémoire',    'ram-memoire'),
  ('Stockage',         'stockage'),
  ('Périphériques',    'peripheriques'),
  ('Boîtiers & Refroidissement', 'boitiers-refroidissement');

-- ============================================================
-- DONNÉES DE TEST — Produits
-- ============================================================
INSERT INTO produits (categorie_id, nom, slug, description, prix, stock, image, marque, specifications, est_vedette) VALUES
(2, 'NVIDIA GeForce RTX 5090',
 'nvidia-rtx-5090',
 'La carte graphique ultime pour le gaming 8K et le ray tracing en temps réel. Architecture Ada Lovelace de nouvelle génération avec 32 Go de VRAM GDDR7.',
 2199.99, 12, 'rtx5090.png', 'NVIDIA',
 '{"VRAM":"32 Go GDDR7","TDP":"600W","Ports":"3x DisplayPort 2.1, 1x HDMI 2.1","Longueur":"340mm"}', 1),

(1, 'AMD Ryzen 9 9950X',
 'amd-ryzen-9950x',
 'Processeur 16 cœurs / 32 threads pour stations de travail et gaming haute performance. Fréquence boost jusqu\'à 5.7 GHz.',
 749.99, 30, 'ryzen9950x.png', 'AMD',
 '{"Coeurs":"16","Threads":"32","Freq. Base":"4.3 GHz","Freq. Boost":"5.7 GHz","TDP":"170W","Socket":"AM5"}', 1),

(2, 'AMD Radeon RX 9070 XT',
 'amd-rx-9070xt',
 'Carte graphique milieu-haut de gamme avec 16 Go de VRAM GDDR6. Idéale pour le 1440p et 4K à haut framerate.',
 699.99, 25, 'rx9070xt.png', 'AMD',
 '{"VRAM":"16 Go GDDR6","TDP":"304W","Ports":"2x DisplayPort 2.1, 1x HDMI 2.1"}', 1),

(3, 'G.Skill Trident Z5 RGB DDR5 64 Go',
 'gskill-trident-z5-ddr5-64go',
 'Kit de 2x32 Go DDR5-6400 MHz avec profils XMP 3.0. Dissipateurs thermiques RGB ultra-fins. Idéal pour les workstations et gaming intensif.',
 289.99, 45, 'gskill-ddr5.png', 'G.Skill',
 '{"Capacite":"64 Go (2x32)","Type":"DDR5","Freq":"6400 MHz","Latence":"CL32","Voltage":"1.4V"}', 0),

(4, 'Samsung 990 Pro 2 To NVMe',
 'samsung-990pro-2to',
 'SSD NVMe PCIe 4.0 ultra-rapide. Vitesses de lecture séquentielle jusqu\'à 7 450 Mo/s. Parfait pour les jeux AAA et l\'édition vidéo.',
 189.99, 60, 'samsung990pro.png', 'Samsung',
 '{"Capacite":"2 To","Interface":"PCIe 4.0 NVMe M.2","Lecture":"7450 Mo/s","Ecriture":"6900 Mo/s","TBW":"1200 TBW"}', 0),

(5, 'Logitech G Pro X Superlight 2',
 'logitech-gpro-superlight2',
 'Souris gaming sans-fil ultra-légère (60g). Capteur HERO 2 25K DPI. Autonomie 95h. Le choix des pros de l\'esport.',
 159.99, 80, 'gpro-superlight.png', 'Logitech',
 '{"Poids":"60g","Capteur":"HERO 2 25K","DPI":"100-25600","Batterie":"95h","Connexion":"Lightspeed 2.4GHz"}', 1),

(6, 'Corsair iCUE H150i ELITE CAPELLIX XT',
 'corsair-h150i-elite',
 'Watercooling AIO 360mm avec pompe à débit amélioré et 3 ventilateurs LL120 RGB. Compatible LGA1851 et AM5.',
 199.99, 20, 'corsair-h150i.png', 'Corsair',
 '{"Radiateur":"360mm","Ventilateurs":"3x 120mm RGB","Compatibilite":"LGA1851/1700, AM5/AM4","Niveau sonore":"< 30 dBA"}', 0),

(1, 'Intel Core Ultra 9 285K',
 'intel-core-ultra9-285k',
 'Processeur phare d\'Intel avec architecture hybride P-core / E-core. 24 cœurs, boost à 5.7 GHz. Socket LGA1851.',
 599.99, 18, 'i9-285k.png', 'Intel',
 '{"Coeurs":"24 (8P+16E)","Threads":"24","Freq. Boost":"5.7 GHz","TDP":"125W (PBP)","Socket":"LGA1851"}', 0),

(5, 'SteelSeries Arctis Nova Pro Wireless',
 'steelseries-arctis-nova-pro',
 'Casque gaming haut de gamme avec double connexion (2.4GHz + Bluetooth). Son Hi-Res certifié, réduction de bruit active.',
 349.99, 15, 'arctis-nova.png', 'SteelSeries',
 '{"Type":"Circum-aural fermé","Freq":"10-40000 Hz","ANC":"Oui","Autonomie":"22h","Connexion":"2.4GHz + BT5.0"}', 1),

(4, 'WD Black SN850X 4 To',
 'wd-black-sn850x-4to',
 'SSD gaming avec cache intelligent GameMode 2.0. PCIe Gen4 NVMe pour des chargements quasi-instantanés dans tous les jeux.',
 349.99, 35, 'wd-sn850x.png', 'Western Digital',
 '{"Capacite":"4 To","Interface":"PCIe 4.0 NVMe M.2","Lecture":"7300 Mo/s","Ecriture":"6600 Mo/s"}', 0);

-- ============================================================
-- COMPTE ADMIN PAR DÉFAUT
-- Email: admin@nexus.shop | Mot de passe: Admin@1234
-- Hash bcrypt généré avec password_hash('Admin@1234', PASSWORD_BCRYPT)
-- ============================================================
INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES
  ('Administrateur', 'admin@nexus.shop',
   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ============================================================
-- VUE UTILITAIRE — Panier enrichi avec infos produit
-- ============================================================
CREATE OR REPLACE VIEW vue_panier AS
SELECT
    p.id            AS panier_id,
    p.utilisateur_id,
    p.quantite,
    pr.id           AS produit_id,
    pr.nom          AS produit_nom,
    pr.prix         AS prix_unitaire,
    pr.image,
    pr.stock,
    (p.quantite * pr.prix) AS sous_total
FROM panier p
JOIN produits pr ON pr.id = p.produit_id;
