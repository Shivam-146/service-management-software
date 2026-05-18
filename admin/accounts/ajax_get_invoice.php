<?php
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if (isset($_GET['invoice_no'])) {
    $invNo = $_GET['invoice_no'];
    
    // Fetch Invoice
    $stmt = $pdo->prepare("SELECT i.*, c.customer_name FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE i.invoice_no = ?");
    $stmt->execute([$invNo]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invoice) {
        // Fetch Items
        $itemStmt = $pdo->prepare("SELECT ii.*, p.product_name FROM invoice_items ii JOIN products p ON ii.product_id = p.id WHERE ii.invoice_id = ?");
        $itemStmt->execute([$invoice['id']]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'invoice' => $invoice,
            'items' => $items
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>
