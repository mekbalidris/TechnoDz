<?php
require_once __DIR__ . '/config.php';

// Session hardening: must be set BEFORE session_start().
// - cookie_httponly: prevents JavaScript from reading the session cookie (mitigates XSS session theft).
// - use_strict_mode: PHP rejects uninitialized session IDs supplied by the client (mitigates session fixation).
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');

// Open a single mysqli connection reused by every page that includes this file.
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    // Generic message - avoids leaking DB host/user/error details to the browser.
    die('Database unavailable. Please try again later.');
}
$conn->set_charset('utf8mb4');

// Start the session for every page that includes db.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
