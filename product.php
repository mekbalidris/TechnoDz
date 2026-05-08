<?php
/**
 * NEXUS SHOP — Page de détails produit
 * URL : product.php?id=1
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$pdo = getPDO();

// ── Récupération du produit ──────────────────────────────────────────────────
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('error', 'Produit introuvable.');
    redirect(BASE_URL . '/index.php');
}

$stmt = $pdo->prepare('
    SELECT p.*, c.nom AS categorie_nom, c.slug AS categorie_slug
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    WHERE p.id = ?
    LIMIT 1
');
$stmt->execute([$id]);
$produit = $stmt->fetch();

if (!$produit) {
    setFlash('error', 'Ce produit n\'existe pas.');
    redirect(BASE_URL . '/index.php');
}

// ── Produits similaires (même catégorie) ─────────────────────────────────────
$similaires = $pdo->prepare('
    SELECT id, nom, prix, image
    FROM produits
    WHERE categorie_id = (SELECT categorie_id FROM produits WHERE id = ?)
      AND id != ?
      AND stock > 0
    ORDER BY RAND()
    LIMIT 4
');
$similaires->execute([$id, $id]);
$similaires = $similaires->fetchAll();

// ── Décodage des spécifications JSON ─────────────────────────────────────────
$specs = [];
if ($produit['specifications']) {
    $specs = json_decode($produit['specifications'], true) ?? [];
}

// ── Meta ─────────────────────────────────────────────────────────────────────
$pageTitle = e($produit['nom']) . ' — ' . APP_NAME;
$pageDesc  = mb_strimwidth($produit['description'], 0, 155, '…');
require_once __DIR__ . '/includes/header.php';
?>

<!-- Fil d'Ariane -->
<nav class="breadcrumb container" aria-label="Fil d'Ariane">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a itemprop="item" href="<?= BASE_URL ?>/index.php"><span itemprop="name">Boutique</span></a>
            <meta itemprop="position" content="1">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a itemprop="item" href="<?= BASE_URL ?>/index.php?categorie=<?= e($produit['categorie_slug']) ?>">
                <span itemprop="name"><?= e($produit['categorie_nom']) ?></span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <span itemprop="name"><?= e($produit['nom']) ?></span>
            <meta itemprop="position" content="3">
        </li>
    </ol>
</nav>

<!-- ── Fiche produit ── -->
<section class="product-detail container" itemscope itemtype="https://schema.org/Product">
    <meta itemprop="name"  content="<?= e($produit['nom']) ?>">
    <meta itemprop="brand" content="<?= e($produit['marque'] ?? '') ?>">

    <!-- JSON-LD enrichi pour le SEO -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org/",
      "@type": "Product",
      "name": "<?= e($produit['nom']) ?>",
      "image": "<?= BASE_URL ?>/assets/images/products/<?= e($produit['image']) ?>",
      "description": "<?= e($produit['description']) ?>",
      "brand": {"@type": "Brand", "name": "<?= e($produit['marque'] ?? 'Inconnu') ?>"},
      "offers": {
        "@type": "Offer",
        "url": "<?= BASE_URL ?>/product.php?id=<?= $produit['id'] ?>",
        "priceCurrency": "EUR",
        "price": "<?= $produit['prix'] ?>",
        "availability": "<?= $produit['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>"
      }
    }
    </script>

    <div class="product-detail-grid">

        <!-- Galerie image -->
        <div class="product-gallery">
            <div class="main-image">
                <img
                    src="<?= BASE_URL ?>/assets/images/products/<?= e($produit['image']) ?>"
                    alt="<?= e($produit['nom']) ?>"
                    id="main-product-img"
                    width="500" height="400"
                    itemprop="image"
                    onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'"
                >
                <?php if ($produit['est_vedette']): ?>
                <span class="badge badge--vedette">⭐ Produit vedette</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Infos & Achat -->
        <div class="product-purchase">
            <p class="product-category-tag">
                <a href="<?= BASE_URL ?>/index.php?categorie=<?= e($produit['categorie_slug']) ?>">
                    <?= e($produit['categorie_nom']) ?>
                </a>
            </p>

            <h1 class="product-detail-name"><?= e($produit['nom']) ?></h1>

            <?php if ($produit['marque']): ?>
            <p class="product-brand" itemprop="brand">Par <strong><?= e($produit['marque']) ?></strong></p>
            <?php endif; ?>

            <div class="product-detail-price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                <meta itemprop="priceCurrency" content="EUR">
                <span class="price-main" itemprop="price"><?= formatPrix($produit['prix']) ?></span>
                <span class="price-tva">TTC · Livraison offerte dès 99 €</span>
            </div>

            <p class="product-desc-full" itemprop="description"><?= nl2br(e($produit['description'])) ?></p>

            <!-- Stock -->
            <div class="stock-indicator <?= $produit['stock'] <= 5 ? 'stock--low' : 'stock--ok' ?>">
                <?php if ($produit['stock'] === 0): ?>
                    <span>✗ Rupture de stock</span>
                <?php elseif ($produit['stock'] <= 5): ?>
                    <span>⚠ Plus que <?= $produit['stock'] ?> en stock — commandez vite !</span>
                <?php else: ?>
                    <span>✓ En stock (<?= $produit['stock'] ?> disponibles)</span>
                <?php endif; ?>
            </div>

            <!-- Formulaire d'ajout au panier -->
            <?php if ($produit['stock'] > 0): ?>
            <form action="<?= BASE_URL ?>/cart.php" method="POST" class="buy-form">
                <input type="hidden" name="action"     value="add">
                <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                <input type="hidden" name="redirect"   value="<?= e($_SERVER['REQUEST_URI']) ?>">

                <div class="qty-row">
                    <label for="qty">Quantité :</label>
                    <div class="qty-control">
                        <button type="button" class="qty-btn" id="qty-minus" aria-label="Réduire">−</button>
                        <input type="number" name="quantite" id="qty" value="1" min="1" max="<?= $produit['stock'] ?>" aria-label="Quantité">
                        <button type="button" class="qty-btn" id="qty-plus" aria-label="Augmenter">+</button>
                    </div>
                </div>

                <div class="buy-buttons">
                    <button type="submit" class="btn btn-primary btn-lg"
                        <?= !estConnecte() ? 'onclick="return confirmLogin()"' : '' ?>>
                        🛒 Ajouter au panier
                    </button>
                    <button type="button" class="btn btn-ghost btn-lg" onclick="window.history.back()">
                        ← Retour
                    </button>
                </div>
            </form>
            <?php else: ?>
            <button class="btn btn-disabled btn-lg" disabled>Rupture de stock</button>
            <?php endif; ?>

        </div><!-- /.product-purchase -->
    </div><!-- /.product-detail-grid -->

    <!-- Spécifications techniques -->
    <?php if (!empty($specs)): ?>
    <div class="specs-section">
        <h2>Caractéristiques techniques</h2>
        <div class="specs-grid">
            <?php foreach ($specs as $cle => $valeur): ?>
            <div class="spec-item">
                <span class="spec-key"><?= e($cle) ?></span>
                <span class="spec-val"><?= e($valeur) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</section><!-- /.product-detail -->

<!-- Produits similaires -->
<?php if (!empty($similaires)): ?>
<section class="similar-products container" aria-labelledby="similar-title">
    <h2 id="similar-title">Produits similaires</h2>
    <div class="products-grid products-grid--4">
        <?php foreach ($similaires as $s): ?>
        <article class="product-card product-card--sm">
            <a href="<?= BASE_URL ?>/product.php?id=<?= $s['id'] ?>" class="product-img-link">
                <img src="<?= BASE_URL ?>/assets/images/products/<?= e($s['image']) ?>"
                     alt="<?= e($s['nom']) ?>"
                     width="200" height="150" loading="lazy"
                     onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'">
            </a>
            <div class="product-info">
                <h3 class="product-name"><a href="<?= BASE_URL ?>/product.php?id=<?= $s['id'] ?>"><?= e($s['nom']) ?></a></h3>
                <div class="product-footer">
                    <span class="product-price"><?= formatPrix($s['prix']) ?></span>
                    <a href="<?= BASE_URL ?>/product.php?id=<?= $s['id'] ?>" class="btn btn-outline btn-sm">Voir</a>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
