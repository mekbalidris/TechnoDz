<?php
require_once __DIR__ . '/../includes/config.php';
$pdo = getPDO();
$count = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='admin'")->fetchColumn();
echo "admins={$count}\n";
