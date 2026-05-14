<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("ALTER TABLE products CHANGE min_stock_level opening_stock INT(11) DEFAULT 0");
    echo "Column renamed successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
