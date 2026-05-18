<?php
// POST handler for cart mutations.
//
// Accepts a POST with `action` set to one of `add`, `update`, or `remove`,
// along with `product_id` (and `quantity` for `update`). The handler
// dispatches to the matching cart helper, which transparently persists the
// mutation to the right tier (DB for users, cookie for guests). Any other
// HTTP method is bounced straight to /cart.php so this URL is never useful
// to crawlers or accidental GETs.
//
// Two response modes:
//   1. AJAX request (header X-Requested-With: XMLHttpRequest, or
//      `ajax=1` in the POST body) -> respond with a JSON payload containing
//      the new cart count, line totals, and overall total. The page-level
//      JS (assets/js/app.js) consumes this to update the DOM in place
//      without a full reload.
//   2. Classic form submit -> redirect back to the referring page (or
//      /cart.php as a fallback). This keeps the no-JS form fallback fully
//      functional, exactly as before.

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

cart_load();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/cart.php');
}

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$pid    = (int)($_POST['product_id'] ?? 0);
$qty    = (int)($_POST['quantity'] ?? 0);

switch ($action) {
    case 'add':
        // "Add to cart" always increments by 1 (Req 6.1). cart_add() handles
        // the "create line / increment existing" branch internally.
        cart_add($pid, 1);
        break;
    case 'update':
        // qty < 1 is treated as a remove inside cart_set_qty().
        cart_set_qty($pid, $qty);
        break;
    case 'remove':
        cart_remove($pid);
        break;
    // Unknown actions are intentionally ignored - we still respond / redirect
    // below so the caller lands somewhere sensible.
}

/**
 * Detect whether this request was made via AJAX. We accept either the
 * canonical header that fetch()/jQuery sets, or an explicit ajax=1 field
 * in the POST body, so it's easy to test from the browser console.
 */
$is_ajax =
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
    || !empty($_POST['ajax']);

if ($is_ajax) {
    // Build a small JSON snapshot of the cart for the JS layer.
    // The session cart is the source of truth after any mutation.
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

    // Total line count (sum of quantities) for the nav badge.
    $count = 0;
    foreach ($cart as $q) {
        $count += (int)$q;
    }

    // Look up unit prices for any non-empty cart so we can return line totals
    // and an overall total. One prepared SELECT, types padded with 'i'.
    $line_totals = [];   // pid => line subtotal (number, two decimals)
    $line_qtys   = [];   // pid => quantity
    $total       = 0.0;

    if (!empty($cart)) {
        $pids = array_map('intval', array_keys($cart));
        $placeholders = implode(',', array_fill(0, count($pids), '?'));
        $types = str_repeat('i', count($pids));

        $stmt = $conn->prepare(
            'SELECT id, price FROM products WHERE id IN (' . $placeholders . ')'
        );
        $stmt->bind_param($types, ...$pids);
        $stmt->execute();
        $res = $stmt->get_result();

        $prices = [];
        while ($row = $res->fetch_assoc()) {
            $prices[(int)$row['id']] = (float)$row['price'];
        }
        $stmt->close();

        foreach ($cart as $cpid => $cqty) {
            $cpid = (int)$cpid;
            $cqty = (int)$cqty;
            $unit = isset($prices[$cpid]) ? $prices[$cpid] : 0.0;
            $sub  = $unit * $cqty;
            $line_totals[$cpid] = round($sub, 2);
            $line_qtys[$cpid]   = $cqty;
            $total += $sub;
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'           => true,
        'action'       => $action,
        'product_id'   => $pid,
        'count'        => $count,                     // total items across all lines
        'line_qtys'    => $line_qtys,                 // {pid: qty}
        'line_totals'  => $line_totals,               // {pid: subtotal}
        'total'        => round($total, 2),           // numeric overall total
        'total_money'  => money($total),              // pre-formatted "$1,234.56"
    ]);
    exit;
}

// Classic form-submit path: redirect back to wherever the form came from.
// Falls through to /cart.php if no referer is set.
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '') {
    header('Location: ' . $ref);
    exit;
}
redirect('/cart.php');
