<?php
require_once 'includes/db_connect.php';
$products = $pdo->query("SELECT id, product_name, opening_stock, current_stock FROM products")->fetchAll(PDO::FETCH_ASSOC);

foreach ($products as $p) {
    $stmt = $pdo->prepare("SELECT SUM(CASE WHEN type = 'Stock In' THEN quantity WHEN type = 'Stock Out' THEN -quantity WHEN type = 'Adjustment' THEN quantity ELSE 0 END) as movement_sum FROM stock_movements WHERE product_id = ?");
    $stmt->execute([$p['id']]);
    $movement_sum = $stmt->fetchColumn() ?: 0;
    
    $expected_total = $p['opening_stock'] + $movement_sum;
    
    echo "Product: {$p['product_name']}\n";
    echo "  Opening: {$p['opening_stock']}\n";
    echo "  Movements: $movement_sum\n";
    echo "  Current: {$p['current_stock']}\n";
    echo "  Expected: $expected_total\n";
    
    if ($p['current_stock'] != $expected_total) {
        echo "  --> FIXING...\n";
        $pdo->prepare("UPDATE products SET current_stock = ? WHERE id = ?")->execute([$expected_total, $p['id']]);
    }
}
