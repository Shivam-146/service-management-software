<?php
require_once __DIR__ . '/../includes/db_connect.php';
echo "--- purchase_items ---\n";
$stmt = $pdo->query("DESCRIBE purchase_items");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- product_serials ---\n";
$stmt = $pdo->query("DESCRIBE product_serials");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- products ---\n";
$stmt = $pdo->query("DESCRIBE products");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
