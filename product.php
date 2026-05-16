<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

$id = (int)($_GET['id'] ?? 0);

$product = null;
if ($id > 0) {
    $sql = 'SELECT p.id, p.name, p.description, p.price, p.image, '
         . 'c.name AS category_name '
         . 'FROM products p '
         . 'LEFT JOIN categories c ON p.category_id = c.id '
         . 'WHERE p.id = ? '
         . 'LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $product = $res->fetch_assoc() ?: null;
    $stmt->close();
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($product === null): ?>
    <div class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> Product not found</div>
    <p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Back to shop</a></p>
<?php else: ?>
    <article class="product-detail">
        <div class="product-detail-image">
            <img
                src="<?= h(product_image_url($product['image'])) ?>"
                alt="<?= h($product['name']) ?>">
        </div>
        <div class="product-detail-body">
            <h1><?= h($product['name']) ?></h1>
            <?php if (!empty($product['category_name'])): ?>
                <p class="product-category"><i class="bi bi-tag-fill"></i> <?= h($product['category_name']) ?></p>
            <?php endif; ?>
            <p class="product-price"><?= h(money($product['price'])) ?></p>
            <p class="product-description"><?= nl2br(h($product['description'])) ?></p>

            <form method="post" action="<?= h(BASE_URL) ?>/cart_action.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= h($product['id']) ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Add to cart</button>
            </form>

            <p style="margin-top:1rem"><a href="<?= h(BASE_URL) ?>/index.php"><i class="bi bi-arrow-left"></i> Back to shop</a></p>
        </div>
    </article>
<?php endif; ?>

<?php
include __DIR__ . '/includes/footer.php';
