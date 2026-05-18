<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

// guests must log in before placing an order
if (!is_logged_in()) {
    redirect('/login.php');
}

$user_id = current_user_id();
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

// empty cart -> show message and stop
if (empty($cart)) {
    include __DIR__ . '/includes/header.php';
    ?>
    <h1><i class="bi bi-cart3"></i> Checkout</h1>
    <p class="empty-state"><i class="bi bi-cart-x"></i> Your cart is empty.</p>
    <p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Continue shopping</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// load all products in the cart
$pids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$types = str_repeat('i', count($pids));

$sql  = 'SELECT id, name, price FROM products WHERE id IN (' . $placeholders . ')';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$pids);
$stmt->execute();
$res = $stmt->get_result();

$products = [];
while ($row = $res->fetch_assoc()) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

// build the order lines and total
$lines = [];
$total = 0.0;
foreach ($cart as $pid => $qty) {
    $pid = (int)$pid;
    $qty = (int)$qty;
    if ($pid <= 0 || $qty <= 0 || !isset($products[$pid])) {
        continue;
    }
    $product = $products[$pid];
    $unit_price = (float)$product['price'];
    $lines[] = [
        'product_id'   => $pid,
        'product_name' => (string)$product['name'],
        'unit_price'   => $unit_price,
        'quantity'     => $qty,
    ];
    $total += $unit_price * $qty;
}

// if all products were deleted -> treat like empty cart
if (empty($lines)) {
    include __DIR__ . '/includes/header.php';
    ?>
    <h1><i class="bi bi-cart3"></i> Checkout</h1>
    <p class="empty-state"><i class="bi bi-cart-x"></i> Your cart is empty.</p>
    <p><a href="<?= h(BASE_URL) ?>/index.php" class="btn"><i class="bi bi-arrow-left"></i> Continue shopping</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// place the order on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        // insert the order
        $ins_order = $conn->prepare(
            'INSERT INTO orders (user_id, total) VALUES (?, ?)'
        );
        $ins_order->bind_param('id', $user_id, $total);
        $ins_order->execute();
        $order_id = (int)$conn->insert_id;
        $ins_order->close();

        // insert one line per product (snapshot name + price so the order stays the same later)
        $ins_item = $conn->prepare(
            'INSERT INTO order_items
                 (order_id, product_id, product_name, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($lines as $line) {
            $ins_item->bind_param(
                'iisdi',
                $order_id,
                $line['product_id'],
                $line['product_name'],
                $line['unit_price'],
                $line['quantity']
            );
            $ins_item->execute();
        }
        $ins_item->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        die('Could not place your order. Please try again.');
    }

    // empty the cart and go to the confirmation page
    cart_save_to_db($user_id, []);
    unset($_SESSION['cart']);

    redirect('/order_confirm.php?order_id=' . $order_id);
}

include __DIR__ . '/includes/header.php';
?>

<h1><i class="bi bi-credit-card"></i> Checkout</h1>

<table class="cart-table">
    <thead>
        <tr>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Quantity</th>
            <th>Line Total</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $line):
        $line_total = $line['unit_price'] * $line['quantity'];
    ?>
        <tr>
            <td><?= h($line['product_name']) ?></td>
            <td><?= h(money($line['unit_price'])) ?></td>
            <td><?= h($line['quantity']) ?></td>
            <td><?= h(money($line_total)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p class="cart-total"><strong>Total: <?= h(money($total)) ?></strong></p>

<form method="post" action="<?= h(BASE_URL) ?>/checkout.php" style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <button type="submit" class="btn btn-primary"><i class="bi bi-bag-check-fill"></i> Place order</button>
    <a href="<?= h(BASE_URL) ?>/cart.php" class="btn"><i class="bi bi-arrow-left"></i> Back to cart</a>
</form>

<?php
include __DIR__ . '/includes/footer.php';
