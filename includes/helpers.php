<?php
require_once __DIR__ . '/config.php';

/**
 * Escape a value for safe HTML output.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a numeric amount as a money string, e.g. "$1,234.56".
 */
function money($n) {
    return '$' . number_format((float)$n, 2);
}

/**
 * Send an HTTP redirect to a path under BASE_URL and stop execution.
 */
function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Resolve a product image URL.
 *
 * Returns the URL to the file inside PRODUCT_IMG_DIR when it exists,
 * otherwise falls back to default.png.
 */
function product_image_url($filename) {
    $filename = (string)$filename;
    if ($filename === '' || !is_file(PRODUCT_IMG_DIR . '/' . $filename)) {
        $filename = 'default.png';
    }
    return PRODUCT_IMG_URL . '/' . $filename;
}
