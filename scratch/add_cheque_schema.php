<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("ALTER TABLE purchases 
        ADD COLUMN bank_name VARCHAR(150) NULL,
        ADD COLUMN cheque_no VARCHAR(50) NULL,
        ADD COLUMN cheque_date DATE NULL");
    echo "purchases table updated with cheque details columns.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
