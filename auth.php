<?php
/**
 * NEXUS SHOP — Authentification
 * Gère : connexion, inscription, déconnexion
 * Utilise : $_SESSION, $_COOKIE, PDO + requêtes préparées, password_hash/verify
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$pdo    = getPDO();
$action = $_GET['action'] ?? '';
$mode   = $_GET['mode']   ?? 'login'; // 'login' | 'register'
$errors = [];

// ── DÉCONNEXION ──────────────────────────────────────────────────────────────
if ($action === 'logout') {
    // Effacer le token du cookie en BDD
    if (estConnecte()) {
        $stmt = $pdo->prepare('UPDATE utilisateurs SET token_cookie = NULL WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
    }
    // Supprimer le cookie de reconnexion
    setcookie(COOKIE_NAME, '', time() - 3600, '/', '', false, true);
    // Détruire la session
    $_SESSION = [];
    session_destroy();
    setFlash('success', 'Vous avez été déconnecté avec succès.');
    redirect(BASE_URL . '/index.php');
}

// ── TRAITEMENT DES FORMULAIRES (POST) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── INSCRIPTION ──────────────────────────────────────────────────────────
    if (isset($_POST['register'])) {
        $nom       = trim($_POST['nom']       ?? '');
        $email     = trim($_POST['email']     ?? '');
        $mdp       = $_POST['mot_de_passe']   ?? '';
        $mdpConf   = $_POST['mdp_confirm']    ?? '';

        // Validation
        if (empty($nom) || strlen($nom) < 2)
            $errors[] = 'Le nom doit contenir au moins 2 caractères.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Adresse email invalide.';
        if (strlen($mdp) < 8)
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if (!preg_match('/[A-Z]/', $mdp) || !preg_match('/[0-9]/', $mdp))
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule et un chiffre.';
        if ($mdp !== $mdpConf)
            $errors[] = 'Les mots de passe ne correspondent pas.';

        // Vérifier doublon email
        if (empty($errors)) {
            $check = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
            $check->execute([$email]);
            if ($check->fetch()) $errors[] = 'Cette adresse email est déjà utilisée.';
        }

        if (empty($errors)) {
            $hash = password_hash($mdp, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe) VALUES (?, ?, ?)');
            $stmt->execute([$nom, $email, $hash]);

            setFlash('success', 'Compte créé avec succès ! Vous pouvez vous connecter.');
            redirect(BASE_URL . '/auth.php');
        }
    }

    // ── CONNEXION ────────────────────────────────────────────────────────────
    if (isset($_POST['login'])) {
        $email  = trim($_POST['email']        ?? '');
        $mdp    = $_POST['mot_de_passe']      ?? '';
        $souvenir = isset($_POST['remember']); // $_COOKIE reconnexion

        if (empty($email) || empty($mdp)) {
            $errors[] = 'Veuillez remplir tous les champs.';
        } else {
            $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, role FROM utilisateurs WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
                $errors[] = 'Email ou mot de passe incorrect.';
                // Sécurité : délai pour contrer les attaques par force brute
                usleep(500_000);
            } else {
                // Connexion réussie : populer la session
                session_regenerate_id(true); // Prévention session fixation
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_nom']   = $user['nom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];

                // Synchroniser le panier session → base
                if (!empty($_SESSION['panier'])) {
                    syncPanierVersBDD($pdo, $user['id']);
                }

                // Cookie "Se souvenir de moi"
                if ($souvenir) {
                    $token = bin2hex(random_bytes(32));
                    $stmtT = $pdo->prepare('UPDATE utilisateurs SET token_cookie = ? WHERE id = ?');
                    $stmtT->execute([$token, $user['id']]);
                    setcookie(
                        COOKIE_NAME,
                        $token,
                        time() + COOKIE_EXPIRE,
                        '/',
                        '',
                        false, // true en HTTPS
                        true   // httponly
                    );
                }

                setFlash('success', 'Bienvenue, ' . $user['nom'] . ' !');
                $redirect = $_POST['redirect'] ?? BASE_URL . '/index.php';
                redirect(filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : BASE_URL . '/index.php');
            }
        }
    }
}

/**
 * Synchronise le panier session vers la BDD après connexion
 */
function syncPanierVersBDD(PDO $pdo, int $userId): void {
    foreach ($_SESSION['panier'] ?? [] as $produitId => $item) {
        $stmt = $pdo->prepare('
            INSERT INTO panier (utilisateur_id, produit_id, quantite)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE quantite = quantite + VALUES(quantite)
        ');
        $stmt->execute([$userId, $produitId, $item['quantite']]);
    }
}

// Rediriger si déjà connecté
if (estConnecte() && $action !== 'logout') {
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Connexion / Inscription — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page container">

    <!-- Onglets Login / Register -->
    <div class="auth-tabs" role="tablist">
        <button
            role="tab"
            class="auth-tab <?= $mode !== 'register' ? 'active' : '' ?>"
            data-target="tab-login"
            aria-selected="<?= $mode !== 'register' ? 'true' : 'false' ?>"
            onclick="switchTab('login')">
            Connexion
        </button>
        <button
            role="tab"
            class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>"
            data-target="tab-register"
            aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>"
            onclick="switchTab('register')">
            Créer un compte
        </button>
    </div>

    <!-- Affichage des erreurs -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert--error" role="alert">
        <ul>
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ── Formulaire Connexion ── -->
    <div id="tab-login" class="auth-panel <?= $mode !== 'register' ? 'active' : '' ?>" role="tabpanel" aria-label="Connexion">
        <form method="POST" action="<?= BASE_URL ?>/auth.php" class="auth-form" novalidate>
            <input type="hidden" name="redirect" value="<?= e($_GET['redirect'] ?? BASE_URL . '/index.php') ?>">

            <h2>Connexion à votre compte</h2>

            <div class="form-group">
                <label for="login-email">Adresse email</label>
                <input type="email" id="login-email" name="email"
                       placeholder="votremail@exemple.com"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="login-mdp">Mot de passe</label>
                <div class="input-password">
                    <input type="password" id="login-mdp" name="mot_de_passe"
                           placeholder="••••••••"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-pwd" data-target="login-mdp" aria-label="Afficher le mot de passe">👁</button>
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember">Se souvenir de moi (30 jours)</label>
            </div>

            <button type="submit" name="login" class="btn btn-primary btn-lg btn-full">
                Se connecter
            </button>

            <p class="auth-switch">
                Pas encore de compte ?
                <button type="button" class="link-btn" onclick="switchTab('register')">Créer un compte</button>
            </p>
        </form>
    </div>

    <!-- ── Formulaire Inscription ── -->
    <div id="tab-register" class="auth-panel <?= $mode === 'register' ? 'active' : '' ?>" role="tabpanel" aria-label="Inscription">
        <form method="POST" action="<?= BASE_URL ?>/auth.php?mode=register" class="auth-form" novalidate id="register-form">

            <h2>Créer un compte</h2>

            <div class="form-group">
                <label for="reg-nom">Nom complet</label>
                <input type="text" id="reg-nom" name="nom"
                       placeholder="John Doe"
                       value="<?= e($_POST['nom'] ?? '') ?>"
                       autocomplete="name" minlength="2" required>
            </div>

            <div class="form-group">
                <label for="reg-email">Adresse email</label>
                <input type="email" id="reg-email" name="email"
                       placeholder="votremail@exemple.com"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="reg-mdp">Mot de passe</label>
                <div class="input-password">
                    <input type="password" id="reg-mdp" name="mot_de_passe"
                           placeholder="Min. 8 caractères, 1 majuscule, 1 chiffre"
                           autocomplete="new-password" minlength="8" required
                           oninput="checkPasswordStrength(this.value)">
                    <button type="button" class="toggle-pwd" data-target="reg-mdp" aria-label="Afficher le mot de passe">👁</button>
                </div>
                <div class="password-strength" id="pwd-strength">
                    <div class="strength-bar" id="strength-bar"></div>
                    <span id="strength-label"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="reg-confirm">Confirmer le mot de passe</label>
                <input type="password" id="reg-confirm" name="mdp_confirm"
                       placeholder="••••••••"
                       autocomplete="new-password" required>
                <p class="field-error" id="confirm-error" hidden>Les mots de passe ne correspondent pas.</p>
            </div>

            <button type="submit" name="register" class="btn btn-primary btn-lg btn-full">
                Créer mon compte
            </button>

            <p class="auth-switch">
                Déjà un compte ?
                <button type="button" class="link-btn" onclick="switchTab('login')">Se connecter</button>
            </p>
        </form>
    </div>

</div><!-- /.auth-page -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
