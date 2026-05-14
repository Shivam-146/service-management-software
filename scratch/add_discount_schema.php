<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("ALTER TABLE purchase_items 
        ADD COLUMN discount_type ENUM('Fixed', 'Percentage') DEFAULT 'Fixed',
        ADD COLUMN discount_value DECIMAL(10,2) DEFAULT 0,
        ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0");
    echo "purchase_items table updated with discount columns.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
