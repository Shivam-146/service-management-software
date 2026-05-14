<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("ALTER TABLE purchase_items 
        ADD COLUMN gst_rate DECIMAL(5,2) DEFAULT 0,
        ADD COLUMN gst_amount DECIMAL(10,2) DEFAULT 0,
        ADD COLUMN taxable_value DECIMAL(10,2) DEFAULT 0");
    echo "purchase_items table updated successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
