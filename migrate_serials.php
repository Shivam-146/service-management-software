<?php
require_once __DIR__ . '/includes/db_connect.php';

try {
    $queries = [
        "ALTER TABLE product_serials ADD COLUMN purchase_id INT NULL AFTER product_id",
        "ALTER TABLE product_serials ADD COLUMN purchase_price DECIMAL(10,2) DEFAULT 0 AFTER serial_number",
        "ALTER TABLE product_serials ADD COLUMN invoice_id INT NULL AFTER purchase_price",
        "ALTER TABLE product_serials ADD COLUMN sold_at DATETIME NULL AFTER status",
        "ALTER TABLE product_serials ADD CONSTRAINT fk_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE SET NULL",
        "ALTER TABLE product_serials ADD CONSTRAINT fk_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL"
    ];

    foreach ($queries as $query) {
        try {
            $pdo->exec($query);
            echo "Success: $query\n";
        } catch (PDOException $e) {
            echo "Skipped/Error: " . $e->getMessage() . "\n";
        }
    }
    echo "Migration completed.\n";
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
?>
