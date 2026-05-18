<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();
require_user();

$order_id = (int)($_GET['order_id'] ?? 0);

// load the order and check it belongs to the current user
$stmt = $conn->prepare(
    'SELECT id, user_id, total, created_at
       FROM orders
      WHERE id = ? AND user_id = ?
      LIMIT 1'
);
$user_id = current_user_id();
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> Order not found</div>
    <p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Back to shop</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// load the order items
$items_stmt = $conn->prepare(
    'SELECT product_id, product_name, unit_price, quantity
       FROM order_items
      WHERE order_id = ?'
);
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items_res = $items_stmt->get_result();

$items = [];
while ($row = $items_res->fetch_assoc()) {
    $items[] = $row;
}
$items_stmt->close();

include __DIR__ . '/includes/header.php';
?>

<h1><i class="bi bi-bag-check-fill"></i> Order Confirmation</h1>

<p class="flash ok"><i class="bi bi-check-circle-fill"></i> Thank you! Your order has been placed.</p>

<p><strong>Order ID:</strong> #<?= h($order['id']) ?></p>

<table class="cart-table">
    <thead>
        <tr>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Qty</th>
            <th>Line Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item):
        $unit_price = (float)$item['unit_price'];
        $quantity   = (int)$item['quantity'];
        $line_total = $unit_price * $quantity;
    ?>
        <tr>
            <td><?= h($item['product_name']) ?></td>
            <td><?= h(money($unit_price)) ?></td>
            <td><?= h($quantity) ?></td>
            <td><?= h(money($line_total)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" style="text-align:right;"><strong>Total:</strong></td>
            <td><strong><?= h(money($order['total'])) ?></strong></td>
        </tr>
    </tfoot>
</table>

<p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Back to shop</a></p>

<?php
include __DIR__ . '/includes/footer.php';
