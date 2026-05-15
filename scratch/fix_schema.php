<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=cctv_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Update payment_method enum to include 'Pay Later'
    $pdo->exec("ALTER TABLE invoices MODIFY COLUMN payment_method ENUM('Cash', 'UPI', 'Bank', 'Pay Later') DEFAULT 'Cash'");

    // 2. Add gst_mode column if not exists
    $columns = $pdo->query("SHOW COLUMNS FROM invoices LIKE 'gst_mode'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN gst_mode ENUM('Inclusive', 'Exclusive') DEFAULT 'Exclusive'");
    }

    // 3. Add sale_price column to product_serials
    $columns = $pdo->query("SHOW COLUMNS FROM product_serials LIKE 'sale_price'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE product_serials ADD COLUMN sale_price DECIMAL(10,2) DEFAULT 0.00 AFTER purchase_price");
    }

    echo "Table schema updated successfully.\n";

    // 3. Update payments table if needed
    $columns = $pdo->query("SHOW COLUMNS FROM payments LIKE 'payment_method'")->fetchAll();
    if (!empty($columns)) {
         $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_method ENUM('Cash', 'UPI', 'Bank', 'Pay Later') DEFAULT 'Cash'");
         echo "Table 'payments' updated successfully.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
