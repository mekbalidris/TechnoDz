<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart.php';
require_once __DIR__ . '/includes/helpers.php';

// clear the user session and the guest cart cookie
unset($_SESSION['user_id']);
unset($_SESSION['cart']);
cart_clear_cookie();

redirect('/index.php');
