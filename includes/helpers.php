<?php
require_once __DIR__ . '/config.php';

// escape html
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// format price in dzd (we store usd and multiply by 260)
function money($n) {
    $dzd = (float)$n * 260;
    return number_format($dzd, 0, '.', ',') . ' DZD';
}

// redirect to a path under BASE_URL
function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

// returns the image url, or default.png if the file is missing
function product_image_url($filename) {
    $filename = (string)$filename;
    if ($filename === '' || !is_file(PRODUCT_IMG_DIR . '/' . $filename)) {
        $filename = 'default.png';
    }
    return PRODUCT_IMG_URL . '/' . $filename;
}
