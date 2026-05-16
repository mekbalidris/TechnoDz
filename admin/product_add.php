<?php
// Admin: add a new product.
//
// Flow:
//   GET  -> render the form populated with the categories dropdown.
//   POST -> validate name, description, price (numeric and > 0), category,
//           and the uploaded image (allowed MIME types only). On success,
//           move the upload into assets/images/products/ under a random
//           filename and INSERT the row using a prepared statement.
//
// Access is gated through require_admin() so unauthenticated visitors get
// bounced to /admin/login.php before any HTML or DB write happens
// (Req 5.2). Every DB call uses a prepared statement (Req 9.5, 11.2).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

// State variables - re-rendered into the form on validation failure so the
// admin doesn't have to re-type everything.
$name        = '';
$description = '';
$price       = '';
$category_id = 0;
$error       = '';

// Allowed image upload MIME types and their canonical extensions
// (Req 9.1, design's Security section).
$allowed_mime = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
];

// Load the categories list once - used both to populate the dropdown and to
// validate the submitted category_id. Prepared SELECT for consistency
// (Req 11.2, 10.5).
$cat_stmt = $conn->prepare('SELECT id, name FROM categories ORDER BY name');
$cat_stmt->execute();
$cat_res = $cat_stmt->get_result();
$categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row;
}
$cat_stmt->close();

// Build a lookup for fast category validation: id => name.
$category_ids = [];
foreach ($categories as $c) {
    $category_ids[(int)$c['id']] = $c['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price       = trim((string)($_POST['price'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);

    // ---- Field validation (Req 9.4) ---------------------------------------
    if ($name === '') {
        $error = 'Name is required';
    } elseif ($description === '') {
        $error = 'Description is required';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        // Req 9.4: a non-positive or non-numeric price must be rejected.
        $error = 'Price must be a positive number';
    } elseif ($category_id <= 0 || !isset($category_ids[$category_id])) {
        // Req 10.5: each product must be assigned to exactly one existing
        // category from the list.
        $error = 'Please choose a valid category';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Product image is required';
    } else {
        // ---- Image upload validation (design's Security section) ----------
        $tmp_path = $_FILES['image']['tmp_name'];

        // finfo_file inspects the actual file bytes, not the (spoofable)
        // client-supplied MIME type in $_FILES.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $tmp_path) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime === false || !isset($allowed_mime[$mime])) {
            $error = 'Image must be a PNG, JPEG, or WEBP file';
        } else {
            $ext = $allowed_mime[$mime];

            // uniqid('prod_', true) returns something like
            // "prod_69fe4ada22d9c9.38339246" - the dot is part of the
            // unique value and would confuse the extension on disk, so we
            // replace it before appending the real extension.
            $base     = str_replace('.', '_', uniqid('prod_', true));
            $filename = $base . '.' . $ext;
            $dest     = PRODUCT_IMG_DIR . '/' . $filename;

            if (!@move_uploaded_file($tmp_path, $dest)) {
                $error = 'Failed to save uploaded image';
            } else {
                // ---- Insert the product row (Req 9.1, 9.5, 11.2) ----------
                // Bind types: s name, s description, d price, s image, i category_id
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

<h1>Add Product</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><?= h($error) ?></p>
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

    <button type="submit" class="btn btn-primary">Add Product</button>
    <a href="<?= h(BASE_URL) ?>/admin/products.php" class="btn">Cancel</a>
</form>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
