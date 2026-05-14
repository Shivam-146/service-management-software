<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("ALTER TABLE vouchers 
        ADD COLUMN bank_name VARCHAR(150) NULL,
        ADD COLUMN cheque_no VARCHAR(50) NULL,
        ADD COLUMN cheque_date DATE NULL");
    echo "vouchers table updated with cheque details columns.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
