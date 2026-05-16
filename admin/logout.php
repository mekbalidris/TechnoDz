<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Admin logout: clear ONLY the admin session key.
// User session ($_SESSION['user_id']) and the user cart ($_SESSION['cart'])
// are deliberately left untouched - admin and user sessions are independent.
unset($_SESSION['admin_id']);

header('Location: ' . BASE_URL . '/admin/login.php');
exit;
