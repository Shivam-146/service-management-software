<?php
require_once 'includes/db_connect.php';
echo "PURCHASES TABLE:\n";
$stmt = $pdo->query("DESCRIBE purchases");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nPURCHASE_ITEMS TABLE:\n";
$stmt = $pdo->query("DESCRIBE purchase_items");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
