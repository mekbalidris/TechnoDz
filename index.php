<?php
// Public shop landing / product listing.
//
// Supports two optional GET filters:
//   - q             : free-text search matched against product name and description (LIKE)
//   - category_id   : restricts results to a single category
//
// Both filters compose: when both are set, only products matching both are returned.
// When the result set is empty, a "No products found" message is rendered instead of the grid.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

// Read filter inputs. Trim the query and coerce category_id to int so we can
// safely treat 0 as "no category filter".
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Build the SELECT dynamically. Every variable piece is bound through a
// parameter - we never concatenate user input into SQL.
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
?>

<?php if ($q === '' && $category_id === 0): ?>
    <section class="hero">
        <h1>Build your dream rig.</h1>
        <p>Top-tier GPUs, CPUs, peripherals and more. Browse the catalog, drop what you love into the cart, and check out in seconds.</p>
    </section>
<?php else: ?>
    <h1>Products</h1>
<?php endif; ?>

<?php if ($q !== '' || $category_id > 0): ?>
    <p class="active-filters">
        Showing
        <?php if ($q !== ''): ?>results for &ldquo;<?= h($q) ?>&rdquo;<?php endif; ?>
        <?php if ($q !== '' && $category_id > 0): ?> in <?php endif; ?>
        <?php if ($category_id > 0):
            // Look up the selected category's name with a prepared statement
            // so we can show it in the active-filter line.
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
                // Short description for the card (full description shows on the detail page).
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
                <a class="btn btn-primary" href="<?= h(BASE_URL) ?>/product.php?id=<?= (int)$p['id'] ?>">View</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
