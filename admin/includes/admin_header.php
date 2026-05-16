<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Shop Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <nav class="site-nav">
        <a class="brand" href="<?= BASE_URL ?>/admin/index.php"><?= h('Nexus Shop — Admin') ?></a>
        <ul class="nav-links">
            <li><a href="<?= BASE_URL ?>/admin/index.php">Dashboard</a></li>
            <li><a href="<?= BASE_URL ?>/admin/products.php">Products</a></li>
            <li><a href="<?= BASE_URL ?>/admin/categories.php">Categories</a></li>
            <li><a href="<?= BASE_URL ?>/admin/logout.php">Logout</a></li>
        </ul>
    </nav>
    <div class="container">
