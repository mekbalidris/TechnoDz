<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Req 5.2: any unauthenticated visitor is bounced to admin/login.php
// before any HTML is sent.
require_admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_name'])) {
        // Req 10.2 / 10.3: add a new category, but reject duplicates.
        $name = trim((string)$_POST['add_name']);

        if ($name === '') {
            $error = 'Category name is required';
        } else {
            // Prepared SELECT to look up an existing row with the same name.
            $stmt = $conn->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();

            if ($exists) {
                $error = 'Category already exists';
            } else {
                $ins = $conn->prepare('INSERT INTO categories (name) VALUES (?)');
                $ins->bind_param('s', $name);
                $ins->execute();
                $ins->close();
                $success = 'Category added';
            }
        }
    } elseif (isset($_POST['delete_id'])) {
        // Req 10.4: delete the category. The FK products.category_id has
        // ON DELETE SET NULL, so any products that referenced this row are
        // automatically unassigned by the database.
        $id = (int)$_POST['delete_id'];

        $del = $conn->prepare('DELETE FROM categories WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $success = 'Category deleted';
    }
}

// Req 10.1: list every category from the categories table.
$list = $conn->prepare('SELECT id, name FROM categories ORDER BY name');
$list->execute();
$res = $list->get_result();
$categories = [];
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}
$list->close();

require __DIR__ . '/includes/admin_header.php';
?>

<h1>Categories</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p class="flash ok"><?= h($success) ?></p>
<?php endif; ?>

<h2>Add category</h2>
<form method="post" action="<?= BASE_URL ?>/admin/categories.php" class="form-inline">
    <label for="add_name">Name</label>
    <input type="text" id="add_name" name="add_name" required maxlength="80">
    <button type="submit" class="btn btn-primary">Add</button>
</form>

<h2>Existing categories</h2>
<?php if (empty($categories)): ?>
    <p>No categories yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><?= h($cat['id']) ?></td>
                    <td><?= h($cat['name']) ?></td>
                    <td>
                        <form method="post"
                              action="<?= BASE_URL ?>/admin/categories.php"
                              class="js-confirm-delete"
                              style="display:inline">
                            <input type="hidden" name="delete_id" value="<?= h($cat['id']) ?>">
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
