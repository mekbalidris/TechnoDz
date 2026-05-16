<?php
// Admin: edit product (form + handler).
//
// GET  ?id=N : fetches the row and renders the form populated with the
//              product values plus a categories dropdown.
// POST       : validates the same fields as product_add.php and runs a
//              prepared UPDATE. The image file is optional on edit; a
//              missing upload keeps the existing filename.
//
// Access is gated through require_admin() so unauthenticated visitors are
// bounced to /admin/login.php before any HTML is sent (Req 5.2 / 9.5).

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

// Pull the id from either the query string (GET form) or the POST body.
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('/admin/products.php');
}

// Fetch the product row with a prepared statement (Req 9.5).
$stmt = $conn->prepare(
    'SELECT id, name, description, price, image, category_id '
    . 'FROM products WHERE id = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$res     = $stmt->get_result();
$product = $res->fetch_assoc();
$stmt->close();

if (!$product) {
    include __DIR__ . '/includes/admin_header.php';
    echo '<h1><i class="bi bi-pencil-square"></i> Edit Product</h1>';
    echo '<p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> Product not found</p>';
    echo '<p><a href="' . h(BASE_URL) . '/admin/products.php" class="btn"><i class="bi bi-arrow-left"></i> Back to products</a></p>';
    include __DIR__ . '/includes/admin_footer.php';
    exit;
}

// Categories for the dropdown (Req 10.5: each product picks one category).
$catStmt = $conn->prepare('SELECT id, name FROM categories ORDER BY name');
$catStmt->execute();
$catRes = $catStmt->get_result();
$categories = [];
while ($row = $catRes->fetch_assoc()) {
    $categories[] = $row;
}
$catStmt->close();

// Form state seeded from the product row. POST overrides these below.
$name        = (string)$product['name'];
$description = (string)$product['description'];
$price       = (string)$product['price'];
$category_id = (int)$product['category_id'];
$image       = (string)$product['image'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read submitted values, trimming the strings.
    $name        = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price       = trim((string)($_POST['price'] ?? ''));
    $category_id = (int)($_POST['category_id'] ?? 0);

    // Validate text fields.
    if ($name === '') {
        $error = 'Name is required';
    } elseif ($description === '') {
        $error = 'Description is required';
    } elseif (!is_numeric($price) || (float)$price <= 0) {
        // Req 9.4: a non-positive or non-numeric price is rejected.
        $error = 'Price must be a positive number';
    } elseif ($category_id <= 0) {
        $error = 'Category is required';
    } else {
        // Confirm the chosen category actually exists.
        $chk = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $chk->bind_param('i', $category_id);
        $chk->execute();
        $chk->store_result();
        $catOk = $chk->num_rows > 0;
        $chk->close();

        if (!$catOk) {
            $error = 'Selected category does not exist';
        }
    }

    // Image handling. The field is optional on edit:
    //   - UPLOAD_ERR_NO_FILE : keep the existing filename
    //   - UPLOAD_ERR_OK      : validate MIME, move the file, use new name
    //   - anything else      : surface as a validation error
    $newImage = $image; // default: keep existing

    if ($error === '' && isset($_FILES['image'])) {
        $uploadErr = (int)$_FILES['image']['error'];

        if ($uploadErr === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['image']['tmp_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);

            $allowed = [
                'image/png'  => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ];

            if (!isset($allowed[$mime])) {
                $error = 'Image must be PNG, JPEG, or WEBP';
            } else {
                $ext      = $allowed[$mime];
                $newImage = uniqid('prod_', true) . '.' . $ext;
                $dest     = PRODUCT_IMG_DIR . '/' . $newImage;

                if (!move_uploaded_file($tmp, $dest)) {
                    $error    = 'Failed to save uploaded image';
                    $newImage = $image; // fall back to existing on failure
                }
            }
        } elseif ($uploadErr !== UPLOAD_ERR_NO_FILE) {
            // Any other upload error counts as a validation failure.
            $error = 'Image upload failed';
        }
    }

    if ($error === '') {
        // Req 9.2 / 9.5: prepared UPDATE for the row.
        $priceFloat = (float)$price;

        $upd = $conn->prepare(
            'UPDATE products '
            . 'SET name = ?, description = ?, price = ?, image = ?, category_id = ? '
            . 'WHERE id = ?'
        );
        $upd->bind_param(
            'ssdsii',
            $name,
            $description,
            $priceFloat,
            $newImage,
            $category_id,
            $id
        );
        $upd->execute();
        $upd->close();

        redirect('/admin/products.php');
    }

    // Validation failed: keep the new image filename in state so the
    // thumbnail reflects whatever is actually persisted right now.
    $image = $newImage;
}

include __DIR__ . '/includes/admin_header.php';
?>

<h1><i class="bi bi-pencil-square"></i> Edit Product</h1>

<?php if ($error !== ''): ?>
    <p class="flash err"><i class="bi bi-exclamation-triangle-fill"></i> <?= h($error) ?></p>
<?php endif; ?>

<form method="post"
      action="<?= h(BASE_URL) ?>/admin/product_edit.php"
      enctype="multipart/form-data">

    <input type="hidden" name="id" value="<?= h($id) ?>">

    <label for="name">Name</label>
    <input type="text" id="name" name="name" maxlength="150"
           value="<?= h($name) ?>" required>

    <label for="description">Description</label>
    <textarea id="description" name="description" required><?= h($description) ?></textarea>

    <label for="price">Price</label>
    <input type="number" id="price" name="price" step="0.01" min="0.01"
           value="<?= h($price) ?>" required>

    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
        <option value="">-- Select a category --</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?= h($cat['id']) ?>"
                <?= ((int)$cat['id'] === $category_id) ? 'selected' : '' ?>>
                <?= h($cat['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Current image</label>
    <img src="<?= h(product_image_url($image)) ?>"
         alt="<?= h($name) ?>"
         class="admin-thumb"
         width="120">

    <label for="image">Replace image (optional)</label>
    <input type="file" id="image" name="image"
           accept="image/png,image/jpeg,image/webp">

    <p style="margin-top:1rem">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save changes</button>
        <a href="<?= h(BASE_URL) ?>/admin/products.php" class="btn"><i class="bi bi-x-lg"></i> Cancel</a>
    </p>
</form>

<?php
include __DIR__ . '/includes/admin_footer.php';
