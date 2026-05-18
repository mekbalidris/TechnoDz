<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_name'])) {
        // add a new category
        $name = trim((string)$_POST['add_name']);

        if ($name === '') {
            $error = 'Category name is required';
        } else {
            // check if the name already exists
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
        // delete a category (products that used it become uncategorized)
        $id = (int)$_POST['delete_id'];

        $del = $conn->prepare('DELETE FROM categories WHERE id = ?');
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();

        $success = 'Category deleted';
    }
}

// load all categories for the table
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

<h1><i class="bi bi-tags"></i> Categories</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></p>
<?php endif; ?>
<?php if ($success !== ''): ?>
    <p class="flash ok"><i class="bi bi-check-circle-fill"></i> <?= h($success) ?></p>
<?php endif; ?>

<h2><i class="bi bi-plus-circle"></i> Add category</h2>
<form method="post" action="<?= BASE_URL ?>/admin/categories.php" class="form-inline">
    <label for="add_name">Name</label>
    <input type="text" id="add_name" name="add_name" required maxlength="80">
    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add</button>
</form>

<h2><i class="bi bi-list-ul"></i> Existing categories</h2>
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
                            <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
