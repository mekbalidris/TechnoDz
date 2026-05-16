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
    <link rel="stylesheet" href="<?= h(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container site-header-inner">
        <a class="brand" href="<?= h(BASE_URL) ?>/index.php">Nexus Shop</a>

        <nav class="site-nav">
            <a href="<?= h(BASE_URL) ?>/index.php">Home</a>
            <a href="<?= h(BASE_URL) ?>/cart.php">
                Cart<?php if ($cart_count > 0): ?> (<?= h($cart_count) ?>)<?php endif; ?>
            </a>
            <?php if (is_logged_in()): ?>
                <span class="nav-user">Hello <?= h(header_current_username()) ?></span>
                <a href="<?= h(BASE_URL) ?>/logout.php">Logout</a>
            <?php else: ?>
                <a href="<?= h(BASE_URL) ?>/login.php">Login</a>
                <a href="<?= h(BASE_URL) ?>/register.php">Register</a>
            <?php endif; ?>
        </nav>

        <form class="search-form" method="get" action="<?= h(BASE_URL) ?>/index.php">
            <input
                type="text"
                name="q"
                placeholder="Search products"
                value="<?= h($search_q) ?>">
            <select name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option
                        value="<?= h($cat['id']) ?>"
                        <?= ((int)$cat['id'] === $selected_category_id) ? 'selected' : '' ?>>
                        <?= h($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
        </form>
    </div>
</header>

<div class="container">
