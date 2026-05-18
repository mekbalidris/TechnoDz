<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

// form values, kept on validation failure
$name        = '';
$description = '';
$price       = '';
$category_id = 0;
$error       = '';

// allowed image types
$allowed_mime = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];

// load categories for the dropdown
$cat_stmt = $conn->prepare('SELECT id, name FROM categories ORDER BY name');
$cat_stmt->execute();
$cat_res = $cat_stmt->get_result();
$categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row;
}
$cat_stmt->close();

// quick lookup for validating the picked category
$category_ids = [];
foreach ($categories as $c) {
    $category_ids[(int)$c['id']] = $c['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price       = trim((string)($_POST['price'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);

    // validate text fields
    if ($name === '') {
        $error = 'Name is required';
    } elseif ($description === '') {
        $error = 'Description is required';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        $error = 'Price must be a positive number';
    } elseif ($category_id <= 0 || !isset($category_ids[$category_id])) {
        $error = 'Please choose a valid category';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Product image is required';
    } else {
        // check the uploaded image type
        $tmp_path = $_FILES['image']['tmp_name'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $tmp_path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime === false || !isset($allowed_mime[$mime])) {
            $error = 'Image must be a PNG, JPEG, or WEBP file';
        } else {
            $ext = $allowed_mime[$mime];

            // make a unique filename so uploads don't overwrite each other
            $base     = str_replace('.', '_', uniqid('prod_', true));
            $filename = $base . '.' . $ext;
            $dest     = PRODUCT_IMG_DIR . '/' . $filename;

            if (!@move_uploaded_file($tmp_path, $dest)) {
                $error = 'Failed to save uploaded image';
            } else {
                // insert the product
                $price_val = (float)$price;
                $ins = $conn->prepare(
                    'INSERT INTO products (name, description, price, image, category_id) '
                  . 'VALUES (?, ?, ?, ?, ?)'
                );
                $ins->bind_param(
                    'ssdsi',
                    $name,
                    $description,
                    $price_val,
                    $filename,
                    $category_id
                );
                $ins->execute();
                $ins->close();

                redirect('/admin/products.php');
            }
        }
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<h1><i class="bi bi-plus-circle"></i> Add Product</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></p>
<?php endif; ?>

<form method="post"
      action="<?= h(BASE_URL) ?>/admin/product_add.php"
      enctype="multipart/form-data"
      class="auth-form">

    <label>
        Name
        <input type="text" name="name" value="<?= h($name) ?>" required maxlength="150">
    </label>

    <label>
        Description
        <textarea name="description" rows="5" required><?= h($description) ?></textarea>
    </label>

    <label>
        Price
        <input type="number" name="price" value="<?= h($price) ?>" step="0.01" min="0" required>
    </label>

    <label>
        Category
        <select name="category_id" required>
            <option value="">-- Select a category --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= h($cat['id']) ?>"
                        <?= ((int)$cat['id'] === $category_id) ? 'selected' : '' ?>>
                    <?= h($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        Image (PNG, JPEG, or WEBP)
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
    </label>

    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Product</button>
    <a href="<?= h(BASE_URL) ?>/admin/products.php" class="btn"><i class="bi bi-x-lg"></i> Cancel</a>
</form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
