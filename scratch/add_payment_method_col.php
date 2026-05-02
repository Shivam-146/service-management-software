<?php
require_once 'includes/db_connect.php';

try {
    $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_method ENUM('UPI', 'Cash') DEFAULT 'Cash' AFTER payment_status");
    echo "Database updated successfully: Added payment_method to invoices table.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>
