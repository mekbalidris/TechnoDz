<?php
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
        cart_add($pid, 1);
        break;
    case 'update':
        cart_set_qty($pid, $qty);
        break;
    case 'remove':
        cart_remove($pid);
        break;
}

// check if this came from ajax
$is_ajax =
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'], 'XMLHttpRequest') === 0)
    || !empty($_POST['ajax']);

if ($is_ajax) {
    // build a small json response with the new cart numbers
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];

    // total items for the badge
    $count = 0;
    foreach ($cart as $q) {
        $count += (int)$q;
    }

    $line_totals = [];
    $line_qtys   = [];
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
        'count'        => $count,
        'line_qtys'    => $line_qtys,
        'line_totals'  => $line_totals,
        'total'        => round($total, 2),
        'total_money'  => money($total),
    ]);
    exit;
}

// classic form submit: go back to where we came from
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '') {
    header('Location: ' . $ref);
    exit;
}
redirect('/cart.php');
