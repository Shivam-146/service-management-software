<?php
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['bill_no'])) {
    $billNo = $_GET['bill_no'];
    
    // Fetch Purchase
    $stmt = $pdo->prepare("SELECT p.*, s.supplier_name FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.bill_no = ?");
    $stmt->execute([$billNo]);
    $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($purchase) {
        // Fetch Items
        $itemStmt = $pdo->prepare("SELECT pi.*, p.product_name FROM purchase_items pi JOIN products p ON pi.product_id = p.id WHERE pi.purchase_id = ?");
        $itemStmt->execute([$purchase['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'purchase' => $purchase,
            'items' => $items
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
