<?php
// Admin product list.
//
// Shows every product in a single table joined to its category, with edit
// and delete action buttons per row. Access is gated through require_admin()
// so unauthenticated visitors get bounced to /admin/login.php before any
// HTML is sent (Req 5.2). The SELECT below has no parameters but still uses
// prepare/execute for consistency with the rest of the admin CRUD path
// (Req 9.5).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

$sql = 'SELECT p.id, p.name, p.price, p.image, p.category_id, '
     . 'c.name AS category_name '
     . 'FROM products p '
     . 'LEFT JOIN categories c ON p.category_id = c.id '
     . 'ORDER BY p.id DESC';

$stmt = $conn->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();
$products = [];
while ($row = $res->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

include __DIR__ . '/includes/admin_header.php';
?>

<h1><i class="bi bi-box-seam"></i> Products</h1>

<p>
    <a href="<?= h(BASE_URL) ?>/admin/product_add.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Add product</a>
</p>

<?php if (empty($products)): ?>
    <p>No products yet.</p>
<?php else: ?>
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><?= h($p['id']) ?></td>
                    <td>
                        <img
                            src="<?= h(product_image_url($p['image'])) ?>"
                            alt="<?= h($p['name']) ?>"
                            class="admin-thumb"
                            width="60">
                    </td>
                    <td><?= h($p['name']) ?></td>
                    <td><?= $p['category_name'] !== null ? h($p['category_name']) : '&mdash;' ?></td>
                    <td><?= h(money($p['price'])) ?></td>
                    <td>
                        <a href="<?= h(BASE_URL) ?>/admin/product_edit.php?id=<?= h($p['id']) ?>" class="btn"><i class="bi bi-pencil-square"></i> Edit</a>
                        <form
                            method="post"
                            action="<?= h(BASE_URL) ?>/admin/product_delete.php"
                            class="js-confirm-delete"
                            style="display:inline"
                            onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
include __DIR__ . '/includes/admin_footer.php';
