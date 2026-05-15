<?php
require_once '../../includes/auth.php';
require_once '../../includes/db_connect.php';

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    echo json_encode(['price' => 0]);
    exit;
}

try {
    // Fetch latest purchase price for this product
    $stmt = $pdo->prepare("SELECT unit_price FROM purchase_items WHERE product_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$product_id]);
    $price = $stmt->fetchColumn() ?: 0;
    echo json_encode(['price' => $price]);
} catch (PDOException $e) {
    echo json_encode(['price' => 0]);
}
?>
