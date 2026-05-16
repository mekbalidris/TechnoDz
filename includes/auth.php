<?php
require_once __DIR__ . '/config.php';

function current_user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function is_logged_in() {
    return current_user_id() > 0;
}

function require_user() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function current_admin_id() {
    return isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
}

function is_admin() {
    return current_admin_id() > 0;
}

function require_admin() {
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}
