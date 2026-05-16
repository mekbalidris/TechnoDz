<?php
// Checkout page.
//
// Behavior:
//   - Guests are redirected to /login.php BEFORE any DB write (Req 8.3).
//   - When the cart is empty, we render "Your cart is empty" and never
//     touch the orders / order_items tables (Req 8.4).
//   - On POST with a non-empty cart we look every cart line up in the
//     products table, compute the order total in PHP, and inside a
//     transaction INSERT one orders row plus one order_items row per
//     line, snapshotting product_name and unit_price from the products
//     row - never from client input - so historical orders stay stable
//     even if the catalog is later edited (Req 8.1, 8.2).
//   - After commit we clear the persistent cart (cart_items rows for the
//     user) and the in-memory session cart, then redirect to the order
//     confirmation page.
//   - On GET we just show an order summary and a single "Place order"
//     form that POSTs back to this page.
//
// Every query uses a prepared statement; cart product ids and the order
// id are always bound as integers, never concatenated into SQL.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

// Req 8.3: guests must log in before placing an order. We bail out before
// reading the cart or touching the DB so a guest POST can never create a
// row in `orders`.
if (!is_logged_in()) {
    redirect('/login.php');
}

$user_id = current_user_id();
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Req 8.4: empty cart => render the message and return without touching
// the database. We render inside the public layout so the header/footer
// stay consistent with the rest of the site.
if (empty($cart)) {
    include __DIR__ . '/includes/header.php';
    ?>
    <h1>Checkout</h1>
    <p>Your cart is empty.</p>
    <p><a href="<?= h(BASE_URL) ?>/index.php">&larr; Continue shopping</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// Fetch every product referenced by the cart in a single prepared SELECT.
// Cast keys to int defensively so a tampered session can't smuggle a
// non-integer through bind_param('i', ...).
$pids = array_map('intval', array_keys($cart));
$placeholders = implode(',', array_fill(0, count($pids), '?'));
$types = str_repeat('i', count($pids));

$sql  = 'SELECT id, name, price FROM products WHERE id IN (' . $placeholders . ')';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$pids);
$stmt->execute();
$res = $stmt->get_result();

// Index by id so we can correlate each cart line with its product row
// regardless of the order MySQL returned the rows in.
$products = [];
while ($row = $res->fetch_assoc()) {
    $products[(int)$row['id']] = $row;
}
$stmt->close();

// Build the list of order lines and the running total. Lines whose
// product was deleted from the catalog after being added to the cart are
// silently skipped - we have no trustworthy name or price to snapshot.
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

// If every cart line referenced a missing product the cart is effectively
// empty - treat it the same way as a literal empty cart (Req 8.4).
if (empty($lines)) {
    include __DIR__ . '/includes/header.php';
    ?>
    <h1>Checkout</h1>
    <p>Your cart is empty.</p>
    <p><a href="<?= h(BASE_URL) ?>/index.php">&larr; Continue shopping</a></p>
    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

// POST = "Place order". Everything up to this point is read-only, so a
// guest or empty-cart caller never reaches the write path.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();

    try {
        // Insert the order header. user_id is an INT, total is a DECIMAL
        // bound as a double here ('d') - mysqli converts it to the right
        // textual decimal on the wire.
        $ins_order = $conn->prepare(
            'INSERT INTO orders (user_id, total) VALUES (?, ?)'
        );
        $ins_order->bind_param('id', $user_id, $total);
        $ins_order->execute();
        $order_id = (int)$conn->insert_id;
        $ins_order->close();

        // Insert one order_items row per cart line, snapshotting the
        // product name and unit price as fetched from `products` above
        // (NOT from $_POST or $_SESSION) so historical orders stay stable
        // after later catalog edits (Req 8.2).
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
        // Generic message - avoids leaking SQL details to the browser.
        die('Could not place your order. Please try again.');
    }

    // Clear the persistent user cart (cart_items rows) and the in-memory
    // session cart so the next page load starts fresh (Req 8.1).
    cart_save_to_db($user_id, []);
    unset($_SESSION['cart']);

    redirect('/order_confirm.php?order_id=' . $order_id);
}

// GET: render the order summary + Place order form.
include __DIR__ . '/includes/header.php';
?>

<h1>Checkout</h1>

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

<form method="post" action="<?= h(BASE_URL) ?>/checkout.php">
    <button type="submit" class="btn btn-primary">Place order</button>
    &nbsp;
    <a href="<?= h(BASE_URL) ?>/cart.php">&larr; Back to cart</a>
</form>

<?php
include __DIR__ . '/includes/footer.php';
