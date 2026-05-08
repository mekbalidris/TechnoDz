<?php
/**
 * NEXUS SHOP — Header HTML commun
 * Inclure en haut de chaque page : require_once 'includes/header.php';
 *
 * @var string $pageTitle   Titre de la page courante
 * @var string $pageDesc    Meta-description (optionnel)
 */
$pageTitle ??= APP_NAME . ' — Matériel Informatique & PC Gaming';
$pageDesc  ??= 'Découvrez notre sélection de processeurs, cartes graphiques, RAM et périphériques gaming haut de gamme.';
$cartCount  = nombreArticlesPanier();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"    content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDesc) ?>">
    <meta name="theme-color" content="#0F172A">

    <!-- Open Graph -->
    <meta property="og:title"       content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDesc) ?>">
    <meta property="og:type"        content="website">

    <!-- Schema.org — Organisation -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "OnlineStore",
      "name": "<?= APP_NAME ?>",
      "url": "<?= BASE_URL ?>",
      "description": "<?= e($pageDesc) ?>",
      "currenciesAccepted": "EUR"
    }
    </script>

    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,600;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- ═══════════════════════════════ HEADER ═══════════════════════════════════ -->
<header class="site-header" role="banner">
    <div class="header-inner container">

        <!-- Logo -->
        <a href="<?= BASE_URL ?>/index.php" class="logo" aria-label="<?= APP_NAME ?> – Accueil">
            <span class="logo-icon" aria-hidden="true">⬡</span>
            <span class="logo-text">NEXUS<em>SHOP</em></span>
        </a>

        <!-- Barre de recherche desktop -->
        <form class="search-bar" action="<?= BASE_URL ?>/index.php" method="GET" role="search">
            <input
                type="search"
                name="q"
                id="search-input"
                placeholder="Rechercher un produit, une marque…"
                value="<?= e($_GET['q'] ?? '') ?>"
                autocomplete="off"
                aria-label="Rechercher un produit"
            >
            <button type="submit" aria-label="Lancer la recherche">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </button>
        </form>

        <!-- Navigation principale -->
        <nav class="main-nav" aria-label="Navigation principale">
            <ul role="list">
                <li><a href="<?= BASE_URL ?>/index.php">Boutique</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=cartes-graphiques">GPU</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=processeurs">CPU</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=peripheriques">Périphériques</a></li>
            </ul>
        </nav>

        <!-- Actions utilisateur -->
        <div class="header-actions">
            <!-- Panier -->
            <a href="<?= BASE_URL ?>/cart.php" class="btn-icon cart-btn" aria-label="Panier (<?= $cartCount ?> articles)">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <?php if ($cartCount > 0): ?>
                <span class="cart-badge" aria-live="polite"><?= $cartCount ?></span>
                <?php endif; ?>
            </a>

            <?php if (estConnecte()): ?>
                <!-- Menu utilisateur connecté -->
                <div class="user-menu">
                    <button class="btn-icon user-btn" aria-haspopup="true" aria-expanded="false" id="user-menu-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span><?= e($_SESSION['user_nom']) ?></span>
                    </button>
                    <ul class="dropdown-menu" role="menu" aria-labelledby="user-menu-btn">
                        <?php if (estAdmin()): ?>
                        <li role="menuitem"><a href="<?= BASE_URL ?>/admin.php">⚙ Admin</a></li>
                        <?php endif; ?>
                        <li role="menuitem"><a href="<?= BASE_URL ?>/auth.php?action=logout">⏻ Déconnexion</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/auth.php" class="btn btn-outline btn-sm">Connexion</a>
            <?php endif; ?>

            <!-- Burger mobile -->
            <button class="burger-btn" id="burger-btn" aria-label="Ouvrir le menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Message Flash -->
<?php $flash = getFlash(); if ($flash): ?>
<div class="flash flash--<?= e($flash['type']) ?>" role="alert" aria-live="assertive">
    <span><?= e($flash['message']) ?></span>
    <button class="flash-close" aria-label="Fermer">✕</button>
</div>
<?php endif; ?>

<!-- Contenu principal -->
<main id="main-content">
