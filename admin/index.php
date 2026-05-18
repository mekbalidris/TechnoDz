<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

// small helper to count rows in one table
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

<h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>

<div class="grid stats-grid">
    <div class="card stat-card">
        <i class="bi bi-box-seam stat-icon"></i>
        <h3>Products</h3>
        <p class="stat-value"><?= h($product_count) ?></p>
    </div>
    <div class="card stat-card">
        <i class="bi bi-tags stat-icon"></i>
        <h3>Categories</h3>
        <p class="stat-value"><?= h($category_count) ?></p>
    </div>
    <div class="card stat-card">
        <i class="bi bi-bag-check stat-icon"></i>
        <h3>Orders</h3>
        <p class="stat-value"><?= h($order_count) ?></p>
    </div>
</div>

<h2>Quick Links</h2>
<p style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a class="btn btn-primary" href="<?= BASE_URL ?>/admin/products.php"><i class="bi bi-box-seam"></i> Manage Products</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/product_add.php"><i class="bi bi-plus-circle"></i> Add Product</a>
    <a class="btn" href="<?= BASE_URL ?>/admin/categories.php"><i class="bi bi-tags"></i> Manage Categories</a>
</p>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
