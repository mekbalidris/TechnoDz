<?php
// Cart cookie + DB persistence layer.
//
// This file only contains the *persistence* primitives for the cart:
//   - constants for the guest cookie name and lifetime
//   - cookie read / write / clear
//   - DB read / write (for logged-in users)
//
// Session-level operations (cart_load, cart_add, cart_set_qty, cart_remove,
// cart_persist, cart_merge_on_login) live alongside these in tasks 3.2 / 3.3.

// Cart helpers depend on is_logged_in() / current_user_id() from auth.php.
// Loading auth.php here means callers don't have to remember the order of
// includes - cart.php is self-sufficient.
require_once __DIR__ . '/auth.php';

// Name of the cookie used to persist a guest's cart between visits.
const CART_COOKIE = 'nexus_cart';

// Cookie lifetime: 30 days.
const CART_COOKIE_TTL = 60 * 60 * 24 * 30;

/**
 * Read the guest cart from the nexus_cart cookie.
 *
 * Returns an associative array of (int)product_id => (int)quantity.
 * Any malformed JSON, non-array payload, or non-positive id/qty entries
 * are silently dropped so callers always get a clean, well-typed cart.
 *
 * @return array<int,int>
 */
function cart_load_from_cookie() {
    if (empty($_COOKIE[CART_COOKIE])) {
        return [];
    }
    $data = json_decode($_COOKIE[CART_COOKIE], true);
    if (!is_array($data)) {
        return [];
    }
    $clean = [];
    foreach ($data as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;
        if ($pid > 0 && $qty > 0) {
            $clean[$pid] = $qty;
        }
    }
    return $clean;
}

/**
 * Write the cart to the nexus_cart cookie as JSON.
 * Path is '/' so the cookie is sent for every page on the site.
 *
 * @param array<int,int> $cart
 * @return void
 */
function cart_save_cookie(array $cart) {
    setcookie(CART_COOKIE, json_encode($cart), time() + CART_COOKIE_TTL, '/');
}

/**
 * Remove the nexus_cart cookie from the browser and from $_COOKIE for this
 * request. Used on user logout and after merging a guest cart on login.
 *
 * @return void
 */
function cart_clear_cookie() {
    setcookie(CART_COOKIE, '', time() - 3600, '/');
    unset($_COOKIE[CART_COOKIE]);
}

/**
 * Load the persistent cart for a logged-in user from the cart_items table.
 * Returns an associative array of (int)product_id => (int)quantity.
 *
 * Uses a prepared statement with bind_param('i', ...) - never any string
 * concatenation of user input into SQL.
 *
 * @param int $user_id
 * @return array<int,int>
 */
function cart_load_from_db($user_id) {
    global $conn;
    $user_id = (int)$user_id;

    $stmt = $conn->prepare('SELECT product_id, quantity FROM cart_items WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $cart = [];
    while ($row = $res->fetch_assoc()) {
        $cart[(int)$row['product_id']] = (int)$row['quantity'];
    }
    $stmt->close();
    return $cart;
}

/**
 * Persist the user's cart to the cart_items table.
 *
 * Strategy: delete every existing row for this user, then insert one row per
 * non-empty cart line. Wrapped in a transaction so a partial failure can't
 * leave the cart half-written.
 *
 * @param int             $user_id
 * @param array<int,int>  $cart    map of product_id => quantity
 * @return void
 */
function cart_save_to_db($user_id, array $cart) {
    global $conn;
    $user_id = (int)$user_id;

    $conn->begin_transaction();

    $del = $conn->prepare('DELETE FROM cart_items WHERE user_id = ?');
    $del->bind_param('i', $user_id);
    $del->execute();
    $del->close();

    if (!empty($cart)) {
        $ins = $conn->prepare(
            'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)'
        );
        foreach ($cart as $pid => $qty) {
            $pid = (int)$pid;
            $qty = (int)$qty;
            $ins->bind_param('iii', $user_id, $pid, $qty);
            $ins->execute();
        }
        $ins->close();
    }

    $conn->commit();
}

/**
 * Hydrate $_SESSION['cart'] from the right persistent tier for this request.
 *
 * - Logged-in users: always overwrite the session cart with the row in
 *   cart_items, so the session matches the DB on every request.
 * - Guests: only seed the session cart from the cookie when no session cart
 *   exists yet, so in-flight changes during a visit aren't clobbered.
 *
 * Should be called once near the top of every page, right after the
 * shared includes block.
 *
 * @return void
 */
function cart_load() {
    if (is_logged_in()) {
        $_SESSION['cart'] = cart_load_from_db(current_user_id());
        return;
    }
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = cart_load_from_cookie();
    }
}

/**
 * Add a product to the cart, or increment its quantity if already present.
 *
 * Non-positive product ids are ignored. The mutation is immediately
 * persisted to the right tier (DB for users, cookie for guests).
 *
 * @param int $product_id
 * @param int $delta       quantity to add (defaults to 1)
 * @return void
 */
function cart_add($product_id, $delta = 1) {
    $product_id = (int)$product_id;
    if ($product_id <= 0) {
        return;
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $current = isset($_SESSION['cart'][$product_id]) ? (int)$_SESSION['cart'][$product_id] : 0;
    $_SESSION['cart'][$product_id] = $current + (int)$delta;
    cart_persist();
}

/**
 * Set the quantity of a cart line to an exact value.
 *
 * If $qty is less than 1, the line is removed entirely. Otherwise the line
 * is replaced with the new quantity. The mutation is immediately persisted.
 *
 * @param int $product_id
 * @param int $qty
 * @return void
 */
function cart_set_qty($product_id, $qty) {
    $product_id = (int)$product_id;
    $qty = (int)$qty;
    if ($product_id <= 0) {
        return;
    }
    if ($qty < 1) {
        cart_remove($product_id);
        return;
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$product_id] = $qty;
    cart_persist();
}

/**
 * Remove a product line from the cart entirely.
 *
 * No-op when the product isn't in the cart. The mutation is immediately
 * persisted.
 *
 * @param int $product_id
 * @return void
 */
function cart_remove($product_id) {
    $product_id = (int)$product_id;
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    unset($_SESSION['cart'][$product_id]);
    cart_persist();
}

/**
 * Write the current session cart to the right persistent tier.
 *
 * Logged-in users: persist to cart_items via cart_save_to_db().
 * Guests: persist to the nexus_cart cookie via cart_save_cookie().
 *
 * @return void
 */
function cart_persist() {
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
    if (is_logged_in()) {
        cart_save_to_db(current_user_id(), $cart);
    } else {
        cart_save_cookie($cart);
    }
}
/**
 * Merge a guest's cart into a user's persistent cart at login time.
 *
 * Sources the guest cart from the session if present, falling back to the
 * cookie so a visitor who logs in on a fresh request still gets their cart
 * carried over. Quantities are summed per product against the user's
 * existing DB cart, the result is written back to cart_items, mirrored
 * into $_SESSION['cart'], and the guest cookie is cleared so the same
 * items don't merge a second time on the next login.
 *
 * @param int $user_id
 * @return void
 */
function cart_merge_on_login($user_id) {
    $guest = $_SESSION['cart'] ?? cart_load_from_cookie();
    $userCart = cart_load_from_db($user_id);
    foreach ($guest as $pid => $qty) {
        $userCart[$pid] = (int)($userCart[$pid] ?? 0) + (int)$qty;
    }
    cart_save_to_db($user_id, $userCart);
    $_SESSION['cart'] = $userCart;
    cart_clear_cookie();
}
