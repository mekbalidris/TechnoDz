<?php
/**
 * NEXUS SHOP — Configuration centrale
 * Connexion PDO sécurisée + constantes globales
 */
declare(strict_types=1);

// ── Paramètres de connexion ──────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'nexus_shop');
define('DB_USER', 'root');       // ← Modifier en production
define('DB_PASS', '');           // ← Modifier en production
define('DB_CHARSET', 'utf8mb4');

// ── Constantes de l'application ──────────────────────────────────────────────
define('APP_NAME',    'NEXUS SHOP');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/nexus_shop'); // ← Adapter
define('UPLOAD_DIR',  __DIR__ . '/../assets/images/products/');
define('UPLOAD_URL',  BASE_URL . '/assets/images/products/');
define('DEFAULT_IMG', 'default.png');

// ── Cookie de reconnexion ────────────────────────────────────────────────────
define('COOKIE_NAME',    'nexus_remember');
define('COOKIE_EXPIRE',  30 * 24 * 3600); // 30 jours

// ── Démarrage de session sécurisé ────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // true en HTTPS (production)
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// ── Connexion PDO (Singleton) ────────────────────────────────────────────────
function getPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production : logger l'erreur, ne pas l'afficher
            http_response_code(503);
            die(json_encode(['erreur' => 'Service temporairement indisponible.']));
        }
    }
    return $pdo;
}

// ── Helpers globaux ──────────────────────────────────────────────────────────

/** Échappe pour affichage HTML */
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Formate un prix en euros */
function formatPrix(float|int|string $prix): string {
    return number_format((float)$prix, 2, ',', ' ') . ' €';
}

/** Vérifie si l'utilisateur est connecté */
function estConnecte(): bool {
    return isset($_SESSION['user_id']);
}

/** Vérifie si l'utilisateur est admin */
function estAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/** Redirige et arrête l'exécution */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Ajoute un message flash en session */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Récupère et efface le message flash */
function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Nombre d'articles dans le panier (session) */
function nombreArticlesPanier(): int {
    if (!isset($_SESSION['panier'])) return 0;
    return array_sum(array_column($_SESSION['panier'], 'quantite'));
}

// ── Auto-connexion via Cookie ────────────────────────────────────────────────
if (!estConnecte() && isset($_COOKIE[COOKIE_NAME])) {
    $token = $_COOKIE[COOKIE_NAME];
    $pdo   = getPDO();
    $stmt  = $pdo->prepare('SELECT id, nom, email, role FROM utilisateurs WHERE token_cookie = ? LIMIT 1');
    $stmt->execute([$token]);
    $user  = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_nom']   = $user['nom'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
    } else {
        // Token invalide : supprimer le cookie
        setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
    }
}
