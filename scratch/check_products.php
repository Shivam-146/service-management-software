<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT product_name, opening_stock, current_stock FROM products");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
