<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Shop Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <nav class="site-nav">
        <a class="brand" href="<?= BASE_URL ?>/admin/index.php"><?= h('Nexus Shop — Admin') ?></a>
        <ul class="nav-links">
            <li><a href="<?= BASE_URL ?>/admin/index.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/admin/products.php"><i class="bi bi-box-seam"></i> Products</a></li>
            <li><a href="<?= BASE_URL ?>/admin/categories.php"><i class="bi bi-tags"></i> Categories</a></li>
            <li><a href="<?= BASE_URL ?>/admin/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>
    <div class="container">
