<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// only clear the admin session (don't touch the user session)
unset($_SESSION['admin_id']);

header('Location: ' . BASE_URL . '/admin/login.php');
exit;
