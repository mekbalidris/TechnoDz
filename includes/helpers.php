<?php
require_once __DIR__ . '/config.php';

/**
 * Escape a value for safe HTML output.
 */
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a numeric amount as a DZD price string.
 *
 * The catalog stores prices in USD; we convert to Algerian Dinar by
 * multiplying by 260 and formatting with thousands separators.
 * Example: 1999.99 -> "519 997 DZD".
 */
function money($n) {
    $dzd = (float)$n * 260;
    // Round to whole dinars - DZD is rarely displayed with subunits.
    return number_format($dzd, 0, '.', ',') . ' DZD';
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
