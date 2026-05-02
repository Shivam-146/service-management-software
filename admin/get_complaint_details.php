<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(['error' => 'ID is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.address as customer_address, cust.phone as customer_phone, u.fullname as tech_name 
                           FROM complaints c 
                           JOIN customers cust ON c.customer_id = cust.id 
                           LEFT JOIN users u ON c.assigned_tech_id = u.id 
                           WHERE c.id = ?");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        echo json_encode(['error' => 'Complaint not found']);
        exit;
    }

    // Resolve parts consumed
    $partsConsumed = json_decode($complaint['parts_consumed'], true) ?? [];
    $resolvedParts = [];
    foreach ($partsConsumed as $part) {
        $pStmt = $pdo->prepare("SELECT item_name, unit_price FROM products WHERE id = ?");
        $pStmt->execute([$part['product_id']]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $resolvedParts[] = [
                'name' => $product['item_name'],
                'price' => $product['unit_price'],
                'qty' => $part['qty']
            ];
        }
    }
    $complaint['resolved_parts'] = $resolvedParts;

    header('Content-Type: application/json');
    echo json_encode($complaint);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
