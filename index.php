<?php
/**
 * NEXUS SHOP — Page d'accueil / Boutique
 * Affiche la liste des produits avec filtres dynamiques
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$pdo = getPDO();

// ── Paramètres de filtrage (GET) ─────────────────────────────────────────────
$recherche = trim($_GET['q']        ?? '');
$categSlug = trim($_GET['categorie'] ?? '');
$tri       = $_GET['tri']            ?? 'nom';
$minPrix   = (float)($_GET['min_prix'] ?? 0);
$maxPrix   = (float)($_GET['max_prix'] ?? 9999);
$page      = max(1, (int)($_GET['page'] ?? 1));
$parPage   = 9;

// Valeurs autorisées pour le tri (whitelist contre injection)
$trisAutorises = ['nom', 'prix_asc', 'prix_desc', 'nouveau'];
if (!in_array($tri, $trisAutorises, true)) $tri = 'nom';

$orderClause = match($tri) {
    'prix_asc'  => 'p.prix ASC',
    'prix_desc' => 'p.prix DESC',
    'nouveau'   => 'p.cree_le DESC',
    default     => 'p.nom ASC',
};

// ── Construction de la requête ───────────────────────────────────────────────
$conditions = ['p.stock > 0'];
$params     = [];

if ($recherche !== '') {
    $conditions[] = 'MATCH(p.nom, p.description) AGAINST(:recherche IN BOOLEAN MODE)';
    $params[':recherche'] = $recherche . '*';
}

if ($categSlug !== '') {
    $conditions[] = 'c.slug = :slug';
    $params[':slug'] = $categSlug;
}

if ($minPrix > 0) {
    $conditions[] = 'p.prix >= :min_prix';
    $params[':min_prix'] = $minPrix;
}
if ($maxPrix < 9999) {
    $conditions[] = 'p.prix <= :max_prix';
    $params[':max_prix'] = $maxPrix;
}

$whereSQL = 'WHERE ' . implode(' AND ', $conditions);

// Comptage total pour la pagination
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    $whereSQL
");
$stmtCount->execute($params);
$totalProduits = (int)$stmtCount->fetchColumn();
$totalPages    = (int)ceil($totalProduits / $parPage);
$offset        = ($page - 1) * $parPage;

// Requête principale avec pagination
$params[':limit']  = $parPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare("
    SELECT
        p.id, p.nom, p.slug, p.prix, p.image, p.est_vedette, p.stock,
        p.description,
        c.nom AS categorie_nom, c.slug AS categorie_slug
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    $whereSQL
    ORDER BY $orderClause
    LIMIT :limit OFFSET :offset
");
// PDO exige bindValue pour les entiers dans LIMIT/OFFSET
$stmt->bindValue(':limit',  $parPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
foreach ($params as $key => $val) {
    if (!in_array($key, [':limit', ':offset'])) {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$produits = $stmt->fetchAll();

// ── Récupération des catégories pour le filtre ───────────────────────────────
$categories = $pdo->query('SELECT id, nom, slug FROM categories ORDER BY nom')->fetchAll();

// ── Prix min/max globaux ─────────────────────────────────────────────────────
$prixRange = $pdo->query('SELECT MIN(prix), MAX(prix) FROM produits WHERE stock > 0')->fetch(PDO::FETCH_NUM);
$prixMin   = (float)$prixRange[0];
$prixMax   = (float)$prixRange[1];

// ── Produits vedettes (sidebar ou hero) ──────────────────────────────────────
$vedettes = $pdo->query('
    SELECT p.id, p.nom, p.prix, p.image, c.nom AS categorie_nom
    FROM produits p JOIN categories c ON c.id = p.categorie_id
    WHERE p.est_vedette = 1 ORDER BY RAND() LIMIT 4
')->fetchAll();

// ── Page ─────────────────────────────────────────────────────────────────────
$pageTitle = APP_NAME . ' — Boutique Matériel PC & Gaming';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ═══════════════════════════════ HERO ════════════════════════════════════ -->
<section class="hero" aria-labelledby="hero-title">
    <div class="hero-bg" aria-hidden="true">
        <div class="hero-grid"></div>
        <div class="hero-glow"></div>
    </div>
    <div class="container hero-content">
        <p class="hero-eyebrow">🔥 Nouveautés 2025</p>
        <h1 id="hero-title">Équipez-vous.<br><em>Dominez le jeu.</em></h1>
        <p class="hero-sub">Processeurs, GPU, RAM, périphériques — tout le matériel pour assembler votre machine ultime.</p>
        <div class="hero-ctas">
            <a href="#shop" class="btn btn-primary">Explorer la boutique</a>
            <a href="<?= BASE_URL ?>/index.php?categorie=cartes-graphiques" class="btn btn-ghost">Voir les GPU →</a>
        </div>
    </div>

    <!-- Produits vedettes dans le hero -->
    <?php if ($vedettes): ?>
    <div class="hero-featured" aria-label="Produits vedettes">
        <?php foreach (array_slice($vedettes, 0, 2) as $v): ?>
        <a href="<?= BASE_URL ?>/product.php?id=<?= $v['id'] ?>" class="featured-card">
            <img src="<?= BASE_URL ?>/assets/images/products/<?= e($v['image']) ?>"
                 alt="<?= e($v['nom']) ?>"
                 width="120" height="120"
                 loading="lazy"
                 onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'">
            <div>
                <span class="tag"><?= e($v['categorie_nom']) ?></span>
                <p><?= e($v['nom']) ?></p>
                <strong><?= formatPrix($v['prix']) ?></strong>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<!-- ═══════════════════════════════ BOUTIQUE ════════════════════════════════ -->
<section class="shop-section container" id="shop">

    <!-- Titre + Compteur -->
    <header class="shop-header">
        <div>
            <h2>
                <?php if ($recherche): ?>
                    Résultats pour «&nbsp;<em><?= e($recherche) ?></em>&nbsp;»
                <?php elseif ($categSlug): ?>
                    <?= e(ucfirst(str_replace('-', ' ', $categSlug))) ?>
                <?php else: ?>
                    Tous les produits
                <?php endif; ?>
            </h2>
            <p class="product-count">
                <span id="product-count"><?= $totalProduits ?></span> article<?= $totalProduits > 1 ? 's' : '' ?> trouvé<?= $totalProduits > 1 ? 's' : '' ?>
            </p>
        </div>

        <!-- Tri rapide -->
        <div class="sort-bar">
            <label for="sort-select">Trier par :</label>
            <select id="sort-select" name="tri" onchange="updateFilter('tri', this.value)">
                <option value="nom"       <?= $tri === 'nom'       ? 'selected' : '' ?>>Nom A→Z</option>
                <option value="prix_asc"  <?= $tri === 'prix_asc'  ? 'selected' : '' ?>>Prix croissant</option>
                <option value="prix_desc" <?= $tri === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                <option value="nouveau"   <?= $tri === 'nouveau'   ? 'selected' : '' ?>>Nouveautés</option>
            </select>
        </div>
    </header>

    <div class="shop-layout">

        <!-- ── Sidebar filtres ── -->
        <aside class="filters-sidebar" aria-label="Filtres produits">
            <div class="filter-panel">
                <h3>Catégories</h3>
                <ul class="category-list" role="list">
                    <li>
                        <a href="<?= BASE_URL ?>/index.php<?= $recherche ? '?q=' . urlencode($recherche) : '' ?>"
                           class="<?= $categSlug === '' ? 'active' : '' ?>">
                            Tout voir
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="?categorie=<?= urlencode($cat['slug']) ?>"
                           class="<?= $categSlug === $cat['slug'] ? 'active' : '' ?>">
                            <?= e($cat['nom']) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Filtre prix -->
            <div class="filter-panel">
                <h3>Fourchette de prix</h3>
                <div class="price-range">
                    <div class="price-inputs">
                        <label>
                            Min
                            <input type="number" id="price-min" min="0" max="<?= (int)$prixMax ?>"
                                   value="<?= $minPrix > 0 ? (int)$minPrix : '' ?>"
                                   placeholder="<?= (int)$prixMin ?>">
                        </label>
                        <span>—</span>
                        <label>
                            Max
                            <input type="number" id="price-max" min="0" max="<?= ceil($prixMax) ?>"
                                   value="<?= $maxPrix < 9999 ? (int)$maxPrix : '' ?>"
                                   placeholder="<?= (int)$prixMax ?>">
                        </label>
                    </div>
                    <button class="btn btn-outline btn-sm" onclick="applyPriceFilter()">Appliquer</button>
                </div>
            </div>

            <!-- Réinitialiser -->
            <?php if ($recherche || $categSlug || $minPrix || $maxPrix < 9999): ?>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm reset-btn">✕ Réinitialiser les filtres</a>
            <?php endif; ?>
        </aside>

        <!-- ── Grille produits ── -->
        <div class="products-area">

            <!-- Loader AJAX -->
            <div id="products-loader" class="loader-overlay" hidden aria-live="polite" aria-label="Chargement…">
                <div class="spinner"></div>
            </div>

            <!-- Grille -->
            <div class="products-grid" id="products-grid">
                <?php if (empty($produits)): ?>
                <div class="empty-state" role="status">
                    <span aria-hidden="true">🔍</span>
                    <p>Aucun produit ne correspond à votre recherche.</p>
                    <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary">Voir tout le catalogue</a>
                </div>

                <?php else: foreach ($produits as $produit): ?>

                <!-- Carte produit avec microdonnées Schema.org -->
                <article class="product-card" itemscope itemtype="https://schema.org/Product">
                    <?php if ($produit['est_vedette']): ?>
                    <span class="badge badge--vedette" aria-label="Produit vedette">⭐ Vedette</span>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/product.php?id=<?= $produit['id'] ?>" class="product-img-link">
                        <img
                            src="<?= BASE_URL ?>/assets/images/products/<?= e($produit['image']) ?>"
                            alt="<?= e($produit['nom']) ?>"
                            width="280" height="200"
                            loading="lazy"
                            itemprop="image"
                            onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'"
                        >
                    </a>

                    <div class="product-info">
                        <span class="product-category" itemprop="category"><?= e($produit['categorie_nom']) ?></span>
                        <h2 class="product-name" itemprop="name">
                            <a href="<?= BASE_URL ?>/product.php?id=<?= $produit['id'] ?>"><?= e($produit['nom']) ?></a>
                        </h2>
                        <p class="product-desc" itemprop="description"><?= e(mb_strimwidth($produit['description'], 0, 90, '…')) ?></p>

                        <!-- Microdonnée Offer -->
                        <div itemprop="offers" itemscope itemtype="https://schema.org/Offer">
                            <meta itemprop="priceCurrency" content="EUR">
                            <meta itemprop="price" content="<?= $produit['prix'] ?>">
                            <meta itemprop="availability" content="<?= $produit['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' ?>">

                            <div class="product-footer">
                                <span class="product-price"><?= formatPrix($produit['prix']) ?></span>

                                <form action="<?= BASE_URL ?>/cart.php" method="POST" class="add-to-cart-form">
                                    <input type="hidden" name="action"     value="add">
                                    <input type="hidden" name="produit_id" value="<?= $produit['id'] ?>">
                                    <input type="hidden" name="redirect"   value="<?= e($_SERVER['REQUEST_URI']) ?>">
                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-add"
                                        <?= !estConnecte() ? 'onclick="return confirmLogin()"' : '' ?>
                                        aria-label="Ajouter <?= e($produit['nom']) ?> au panier"
                                    >
                                        + Panier
                                    </button>
                                </form>
                            </div>
                        </div>

                        <p class="product-stock <?= $produit['stock'] <= 5 ? 'stock--low' : '' ?>">
                            <?php if ($produit['stock'] <= 5): ?>
                                ⚠ Plus que <?= $produit['stock'] ?> en stock
                            <?php else: ?>
                                ✓ En stock (<?= $produit['stock'] ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                </article>

                <?php endforeach; endif; ?>
            </div><!-- /#products-grid -->

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                   class="btn btn-ghost" aria-label="Page précédente">← Préc.</a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="btn <?= $i === $page ? 'btn-primary' : 'btn-ghost' ?>"
                   aria-current="<?= $i === $page ? 'page' : 'false' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                   class="btn btn-ghost" aria-label="Page suivante">Suiv. →</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        </div><!-- /.products-area -->
    </div><!-- /.shop-layout -->
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
