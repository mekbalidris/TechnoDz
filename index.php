<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

// read filter inputs from the url
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// build the query with optional search and category filter
$sql = 'SELECT p.id, p.name, p.description, p.price, p.image, p.category_id, '
     . '       c.name AS category_name '
     . 'FROM products p '
     . 'LEFT JOIN categories c ON p.category_id = c.id '
     . 'WHERE 1=1';

$types  = '';
$params = [];

if ($q !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $like = '%' . $q . '%';
    $types  .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

if ($category_id > 0) {
    $sql .= ' AND p.category_id = ?';
    $types  .= 'i';
    $params[] = $category_id;
}

$sql .= ' ORDER BY p.id DESC';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

include __DIR__ . '/includes/header.php';

// load categories for the filter chips
$filter_categories = [];
$cat_q = $conn->query('SELECT id, name FROM categories ORDER BY name');
if ($cat_q) {
    while ($row = $cat_q->fetch_assoc()) {
        $filter_categories[] = $row;
    }
    $cat_q->free();
}
?>

<?php if ($q === '' && $category_id === 0): ?>
    <section class="hero hero-split">
        <div class="hero-copy">
            <span class="hero-eyebrow"><i class="bi bi-lightning-charge-fill"></i> Build smarter</span>
            <h1>Power your next build at <span class="hero-accent">TechnoDz</span></h1>
            <p>GPUs, CPUs, peripherals and more from the brands you trust. Discover today's deals, drop them into your cart, and check out in seconds.</p>
            <div class="hero-actions">
                <a href="#products" class="btn btn-primary"><i class="bi bi-grid-fill"></i> Shop now</a>
                <a href="<?= h(BASE_URL) ?>/cart.php" class="btn btn-light"><i class="bi bi-cart3"></i> View cart</a>
            </div>
        </div>
        <div class="hero-art">
            <img src="<?= h(product_image_url('rtx5090.png')) ?>" alt="Featured GPU">
        </div>
    </section>
<?php else: ?>
    <h1 id="products"><i class="bi bi-grid-fill"></i> Products</h1>
<?php endif; ?>

<section class="filter-strip" id="products">
    <div class="filter-strip-label">
        <i class="bi bi-funnel-fill"></i> <span>Categories</span>
    </div>
    <div class="filter-chips">
        <a class="chip <?= $category_id === 0 ? 'is-active' : '' ?>"
           href="<?= h(BASE_URL) ?>/index.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">
            All
        </a>
        <?php foreach ($filter_categories as $fc):
            $url = BASE_URL . '/index.php?category_id=' . (int)$fc['id'];
            if ($q !== '') { $url .= '&q=' . urlencode($q); }
        ?>
            <a class="chip <?= $category_id === (int)$fc['id'] ? 'is-active' : '' ?>"
               href="<?= h($url) ?>">
                <?= h($fc['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($q !== '' || $category_id > 0): ?>
    <p class="active-filters">
        Showing
        <?php if ($q !== ''): ?>results for &ldquo;<?= h($q) ?>&rdquo;<?php endif; ?>
        <?php if ($q !== '' && $category_id > 0): ?> in <?php endif; ?>
        <?php if ($category_id > 0):
            // get the selected category name to show in the banner
            $cat_stmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
            $cat_stmt->bind_param('i', $category_id);
            $cat_stmt->execute();
            $cat_stmt->bind_result($cat_name);
            $cat_label = $cat_stmt->fetch() ? (string)$cat_name : '';
            $cat_stmt->close();
            if ($cat_label !== ''): ?>
                category &ldquo;<?= h($cat_label) ?>&rdquo;
            <?php endif;
        endif; ?>
        &middot; <a href="<?= h(BASE_URL) ?>/index.php">Clear filters</a>
    </p>
<?php endif; ?>

<?php if (empty($products)): ?>
    <p class="flash">No products found.</p>
<?php else: ?>
    <div class="grid">
        <?php foreach ($products as $p): ?>
            <?php
                // shorter description for the card
                $desc = (string)$p['description'];
                if (function_exists('mb_strimwidth')) {
                    $short_desc = mb_strimwidth($desc, 0, 140, '…', 'UTF-8');
                } else {
                    $short_desc = strlen($desc) > 140 ? substr($desc, 0, 140) . '…' : $desc;
                }
            ?>
            <div class="card">
                <a href="<?= h(BASE_URL) ?>/product.php?id=<?= (int)$p['id'] ?>">
                    <img src="<?= h(product_image_url($p['image'])) ?>" alt="<?= h($p['name']) ?>">
                </a>
                <h3><?= h($p['name']) ?></h3>
                <?php if (!empty($p['category_name'])): ?>
                    <p class="category"><?= h($p['category_name']) ?></p>
                <?php endif; ?>
                <p class="desc"><?= h($short_desc) ?></p>
                <p class="price"><?= h(money($p['price'])) ?></p>
                <a class="btn btn-primary" href="<?= h(BASE_URL) ?>/product.php?id=<?= (int)$p['id'] ?>"><i class="bi bi-eye"></i> View</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
