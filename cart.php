<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

// empty cart message
if (empty($cart)) {
    include __DIR__ . '/includes/header.php';
    ?>
    <h1><i class="bi bi-cart3"></i> Your Cart</h1>
    <p class="empty-state">
        <i class="bi bi-cart-x"></i>
        Your cart is empty.
    </p>
    <p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Continue shopping</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// get all products in the cart in one query
$pids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$types = str_repeat('i', count($pids));

$sql  = 'SELECT id, name, price, image FROM products WHERE id IN (' . $placeholders . ')';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$pids);
$stmt->execute();
$res = $stmt->get_result();

// store products by id so we can look them up easily
$products = [];
while ($row = $res->fetch_assoc()) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

$total = 0.0;

include __DIR__ . '/includes/header.php';
?>

<h1><i class="bi bi-cart3"></i> Your Cart</h1>

<table class="cart-table">
    <thead>
        <tr>
            <th>Image</th>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Quantity</th>
            <th>Line Total</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($cart as $pid => $qty):
        $pid = (int)$pid;
        $qty = (int)$qty;
        // skip if the product was deleted
        if (!isset($products[$pid])) {
            continue;
        }
        $product    = $products[$pid];
        $unit_price = (float)$product['price'];
        $line_total = $unit_price * $qty;
        $total     += $line_total;
    ?>
        <tr data-cart-row data-pid="<?= h($pid) ?>">
            <td>
                <img
                    class="cart-thumb"
                    src="<?= h(product_image_url($product['image'])) ?>"
                    alt="<?= h($product['name']) ?>">
            </td>
            <td>
                <a href="<?= h(BASE_URL) ?>/product.php?id=<?= h($pid) ?>">
                    <?= h($product['name']) ?>
                </a>
            </td>
            <td><?= h(money($unit_price)) ?></td>
            <td>
                <form method="post" action="<?= h(BASE_URL) ?>/cart_action.php" class="cart-qty-form js-cart-qty-form">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" value="<?= h($pid) ?>">
                    <input
                        type="number"
                        name="quantity"
                        min="1"
                        value="<?= h($qty) ?>"
                        class="cart-qty-input">
                    <button type="submit" class="btn"><i class="bi bi-arrow-repeat"></i> Update</button>
                </form>
            </td>
            <td data-line-total="<?= h($pid) ?>"><?= h(money($line_total)) ?></td>
            <td>
                <form method="post" action="<?= h(BASE_URL) ?>/cart_action.php" class="cart-remove-form js-cart-remove-form">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="product_id" value="<?= h($pid) ?>">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Remove</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="cart-total" data-cart-total><strong>Total: <span data-cart-total-value><?= h(money($total)) ?></span></strong></p>

<p>
    <a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Continue shopping</a>
    <a href="<?= h(BASE_URL) ?>/checkout.php" class="btn btn-primary"><i class="bi bi-credit-card"></i> Proceed to checkout</a>
</p>

<?php
include __DIR__ . '/includes/footer.php';
