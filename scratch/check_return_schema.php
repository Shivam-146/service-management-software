<?php
require_once 'includes/db_connect.php';
$tables = ['invoice_items', 'purchase_items', 'products', 'serials'];
foreach ($tables as $t) {
    echo "\nTable: $t\n";
    try {
        $res = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $row) {
            echo "{$row['Field']} - {$row['Type']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
