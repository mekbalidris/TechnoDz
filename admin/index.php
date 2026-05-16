<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Req 5.2: any unauthenticated visitor is bounced to admin/login.php
// before any HTML is sent.
require_admin();

/**
 * Count rows in a single table using a prepared statement.
 *
 * Even though there are no parameters, the design (Req 11.2) requires every
 * query to go through prepare()/execute(), so we use a prepared statement
 * here as well for consistency.
 */
function admin_count_rows(mysqli $conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

$product_count  = admin_count_rows($conn, 'SELECT COUNT(*) FROM products');
$category_count = admin_count_rows($conn, 'SELECT COUNT(*) FROM categories');
$order_count    = admin_count_rows($conn, 'SELECT COUNT(*) FROM orders');

require __DIR__ . '/includes/admin_header.php';
?>

<h1>Admin Dashboard</h1>

<div class="grid">
    <div class="card">
        <h3>Products</h3>
        <p class="price"><?= h($product_count) ?></p>
    </div>
    <div class="card">
        <h3>Categories</h3>
        <p class="price"><?= h($category_count) ?></p>
    </div>
    <div class="card">
        <h3>Orders</h3>
        <p class="price"><?= h($order_count) ?></p>
    </div>
</div>

<h2>Quick Links</h2>
<p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/products.php">Manage Products</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/product_add.php">Add Product</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/categories.php">Manage Categories</a>
</p>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
