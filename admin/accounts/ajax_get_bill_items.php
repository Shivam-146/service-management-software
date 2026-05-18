<?php
require_once '../../includes/auth.php';
require_once '../../includes/db_connect.php';

$purchase_id = $_GET['purchase_id'] ?? null;

if (!$purchase_id) {
    echo json_encode([]);
    exit;
}

try {
    // Fetch items for this purchase
    $stmt = $pdo->prepare("SELECT pi.product_id, p.product_name as item_name, pi.taxable_value as unit_price, pi.quantity as qty 
                           FROM purchase_items pi 
                           JOIN products p ON pi.product_id = p.id 
                           WHERE pi.purchase_id = ?");
    $stmt->execute([$purchase_id]);
    $items = $stmt->fetchAll();
    echo json_encode($items);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
