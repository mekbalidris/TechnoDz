<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechnoDz Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/style.css') ?: time() ?>">
</head>
<body>
    <nav class="site-nav admin-nav">
        <a class="brand" href="<?= BASE_URL ?>/admin/index.php"><?= h('TechnoDz — Admin') ?></a>
        <button type="button" class="nav-toggle" aria-label="Toggle navigation" aria-expanded="false" data-nav-toggle>
            <i class="bi bi-list"></i>
        </button>
        <ul class="nav-links" data-nav>
            <li><a href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/admin/products.php"><i class="bi bi-box-seam"></i> Products</a></li>
            <li><a href="<?= BASE_URL ?>/admin/categories.php"><i class="bi bi-tags"></i> Categories</a></li>
            <li><a href="<?= BASE_URL ?>/admin/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>
    <div class="container">
