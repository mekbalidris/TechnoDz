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
// After the mutation we redirect back to the page the user came from
// (HTTP_REFERER), falling back to /cart.php when no referer is available -
// e.g. when the action was triggered from a programmatic POST.

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
    // Unknown actions are intentionally ignored - we still redirect below
    // so the user lands somewhere sensible.
}

// Bounce back to wherever the form was submitted from. Fall through to
// /cart.php when there's no referer to honor.
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref !== '') {
    header('Location: ' . $ref);
    exit;
}
redirect('/cart.php');
