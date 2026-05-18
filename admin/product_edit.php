<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();

// get the product id from GET or POST
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('/admin/products.php');
}

// load the product
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

// load categories for the dropdown
$catStmt = $conn->prepare('SELECT id, name FROM categories ORDER BY name');
$catStmt->execute();
$catRes = $catStmt->get_result();
$categories = [];
while ($row = $catRes->fetch_assoc()) {
    $categories[] = $row;
}
$catStmt->close();

// fill form fields from the product
$name        = (string)$product['name'];
$description = (string)$product['description'];
$price       = (string)$product['price'];
$category_id = (int)$product['category_id'];
$image       = (string)$product['image'];

$error = '';

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
    } elseif ($category_id <= 0) {
        $error = 'Category is required';
    } else {
        // make sure the category exists
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

    // image is optional on edit: keep the old one if no new file
    $newImage = $image;

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
                    $newImage = $image;
                }
            }
        } elseif ($uploadErr !== UPLOAD_ERR_NO_FILE) {
            $error = 'Image upload failed';
        }
    }

    if ($error === '') {
        // update the product row
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
