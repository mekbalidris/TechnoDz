<?php
require_once __DIR__ . '/config.php';

// session security flags must be set before session_start
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

// connect to mysql
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database unavailable. Please try again later.');
}
$conn->set_charset('utf8mb4');

// start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
