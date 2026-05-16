<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

// User logout: clear the user session and the guest cart cookie so the next
// visitor on this browser starts as a fresh Guest with an empty cart.
//
// Notes:
// - $_SESSION['admin_id'] is intentionally NOT touched - admin and user
//   sessions are independent (see Requirement 5.3).
// - cart_clear_cookie() removes the nexus_cart cookie so the freshly logged-out
//   browser doesn't immediately rehydrate the previous user's items as a guest.
unset($_SESSION['user_id']);
unset($_SESSION['cart']);
cart_clear_cookie();

redirect('/index.php');
