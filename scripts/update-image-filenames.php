<?php
require_once __DIR__ . '/../includes/config.php';
$pdo = getPDO();
$stmt = $pdo->prepare('UPDATE produits SET image = REPLACE(image, ".webp", ".png") WHERE image LIKE ?');
$stmt->execute(['%.webp']);
echo 'Updated ' . $stmt->rowCount() . ' rows\n';
