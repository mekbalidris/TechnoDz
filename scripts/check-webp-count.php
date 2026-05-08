<?php
require_once __DIR__ . '/../includes/config.php';
$pdo = getPDO();
$stmt = $pdo->query('SELECT COUNT(*) AS c FROM produits WHERE image LIKE "%.webp"');
$row = $stmt->fetch();
echo $row['c'] . " rows remain\n";
