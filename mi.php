<?php
require_once 'includes/db_connect.php';

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting migration...\n";

    // 1. Update product_serials table
    echo "Updating product_serials table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM product_serials LIKE 'technician_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE product_serials ADD COLUMN technician_id INT NULL AFTER invoice_id, ADD COLUMN allocated_at TIMESTAMP NULL AFTER technician_id");
        echo " - Added technician_id and allocated_at to product_serials.\n";
    } else {
        echo " - Columns already exist in product_serials.\n";
    }

    // 2. Create technician_stock table
    echo "Creating technician_stock table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS technician_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        technician_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 0.00,
        allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (technician_id),
        INDEX (product_id)
    ) ENGINE=InnoDB");
    echo " - technician_stock table ready.\n";

    echo "\nMigration completed successfully!\n";

} catch (PDOException $e) {
    die("\nMigration failed: " . $e->getMessage());
}
?>
