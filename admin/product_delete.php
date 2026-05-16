<?php
// Admin product delete (POST-only).
//
// Receives a POST from the delete button on admin/products.php and removes
// the matching row from the products table using a prepared DELETE
// (Req 9.3, 9.5). The list page's button is wrapped in a form with
// class="js-confirm-delete" so assets/js/app.js prompts the admin via
// window.confirm() before the form actually submits. A non-POST request
// is bounced straight back to the list page so the URL can't be used to
// delete via GET.
//
// Access is gated by require_admin() before any work happens, so an
// unauthenticated visitor is redirected to /admin/login.php (Req 5.2).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/products.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare('DELETE FROM products WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ' . BASE_URL . '/admin/products.php');
exit;
