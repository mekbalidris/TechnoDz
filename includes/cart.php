<?php
require_once __DIR__ . '/auth.php';

const CART_COOKIE = 'nexus_cart';
const CART_COOKIE_TTL = 60 * 60 * 24 * 30;

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

function cart_save_cookie(array $cart) {
    setcookie(CART_COOKIE, json_encode($cart), time() + CART_COOKIE_TTL, '/');
}

function cart_clear_cookie() {
    setcookie(CART_COOKIE, '', time() - 3600, '/');
    unset($_COOKIE[CART_COOKIE]);
}

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

function cart_save_to_db($user_id, array $cart) {
    global $conn;
    $user_id = (int)$user_id;

    $conn->begin_transaction();

    // delete old rows then re-insert the current cart
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

function cart_load() {
    // logged-in user reads from db, guest reads from cookie
    if (is_logged_in()) {
        $_SESSION['cart'] = cart_load_from_db(current_user_id());
        return;
    }
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = cart_load_from_cookie();
    }
}

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

function cart_set_qty($product_id, $qty) {
    $product_id = (int)$product_id;
    $qty = (int)$qty;
    if ($product_id <= 0) {
        return;
    }
    if ($qty < 1) {
        // qty 0 means remove the line
        cart_remove($product_id);
        return;
    }
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$product_id] = $qty;
    cart_persist();
}

function cart_remove($product_id) {
    $product_id = (int)$product_id;
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    unset($_SESSION['cart'][$product_id]);
    cart_persist();
}

function cart_persist() {
    $cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
    if (is_logged_in()) {
        cart_save_to_db(current_user_id(), $cart);
    } else {
        cart_save_cookie($cart);
    }
}

function cart_merge_on_login($user_id) {
    // merge guest cart into the user's saved cart
    $guest = $_SESSION['cart'] ?? cart_load_from_cookie();
    $userCart = cart_load_from_db($user_id);
    foreach ($guest as $pid => $qty) {
        $userCart[$pid] = (int)($userCart[$pid] ?? 0) + (int)$qty;
    }
    cart_save_to_db($user_id, $userCart);
    $_SESSION['cart'] = $userCart;
    cart_clear_cookie();
}
