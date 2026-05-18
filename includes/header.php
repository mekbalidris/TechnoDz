<?php
// Public site header.
//
// Included from public pages (index.php, product.php, cart.php, login.php,
// register.php, checkout.php, order_confirm.php) AFTER the shared layer
// (db.php, auth.php, cart.php, helpers.php) has been required. We rely on:
//   - $conn         (mysqli, from db.php)
//   - is_logged_in(), current_user_id() (auth.php)
//   - h() (helpers.php)
//   - $_SESSION['cart'] hydrated by cart_load() in the caller
//   - BASE_URL (config.php)
//
// The caller is responsible for calling cart_load() before including this
// file so the cart count in the nav is accurate.

// Resolve the logged-in user's display name with a one-shot prepared SELECT.
// Cached in a static so multiple calls (or repeated includes during testing)
// don't re-hit the database for the same request.
if (!function_exists('header_current_username')) {
    function header_current_username() {
        static $cached_uid = null;
        static $cached_name = '';

        $uid = current_user_id();
        if ($uid <= 0) {
            return '';
        }
        if ($cached_uid === $uid) {
            return $cached_name;
        }

        global $conn;
        $stmt = $conn->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->bind_result($username);
        $cached_name = $stmt->fetch() ? (string)$username : '';
        $stmt->close();
        $cached_uid = $uid;
        return $cached_name;
    }
}

// Cart line count (sum of quantities) for the nav badge.
$cart_count = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cart_count += (int)$qty;
    }
}

// Preserve current search inputs so the form re-renders with the active filter.
$search_q = isset($_GET['q']) ? (string)$_GET['q'] : '';
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Load categories for the filter dropdown.
$categories = [];
if (isset($conn) && $conn instanceof mysqli) {
    $cat_res = $conn->query('SELECT id, name FROM categories ORDER BY name');
    if ($cat_res) {
        while ($row = $cat_res->fetch_assoc()) {
            $categories[] = $row;
        }
        $cat_res->free();
    }
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nexus Shop</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container site-header-inner">
        <a class="brand" href="<?= h(BASE_URL) ?>/index.php">Nexus Shop</a>

        <nav class="site-nav">
            <a href="<?= h(BASE_URL) ?>/index.php"><i class="bi bi-house-door"></i> Home</a>
            <a href="<?= h(BASE_URL) ?>/cart.php">
                <i class="bi bi-cart3"></i> Cart<span class="cart-badge" data-cart-badge<?= $cart_count > 0 ? '' : ' hidden' ?>><?= h($cart_count) ?></span>
            </a>
            <?php if (is_logged_in()): ?>
                <span class="nav-user"><i class="bi bi-person-circle"></i> <?= h(header_current_username()) ?></span>
                <a href="<?= h(BASE_URL) ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            <?php else: ?>
                <a href="<?= h(BASE_URL) ?>/login.php"><i class="bi bi-box-arrow-in-right"></i> Login</a>
                <a href="<?= h(BASE_URL) ?>/register.php"><i class="bi bi-person-plus"></i> Register</a>
            <?php endif; ?>
        </nav>

        <form class="search-form" method="get" action="<?= h(BASE_URL) ?>/index.php">
            <div class="search-input-wrap">
                <i class="bi bi-search search-icon"></i>
                <input
                    type="text"
                    name="q"
                    placeholder="Search products"
                    value="<?= h($search_q) ?>">
            </div>
            <?php if ($selected_category_id > 0): ?>
                <input type="hidden" name="category_id" value="<?= h($selected_category_id) ?>">
            <?php endif; ?>
            <button type="submit"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>
</header>

<div class="container">
