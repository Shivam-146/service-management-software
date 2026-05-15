<?php
require_once '../../includes/auth.php';
require_once '../../includes/db_connect.php';

$product_id = $_GET['product_id'] ?? null;

if (!$product_id) {
    echo json_encode([]);
    exit;
}

try {
    $role = $_SESSION['role'] ?? 'technician';
    $techId = $_SESSION['user_id'];
    
    $query = "SELECT s.id, s.serial_number, s.purchase_price, p.unit_price 
              FROM product_serials s 
              JOIN products p ON s.product_id = p.id
              WHERE s.product_id = ? AND s.status = 'In Stock'";
    
    $params = [$product_id];
    
    if ($role === 'technician' || $role === 'tech') {
        $query .= " AND s.technician_id = ?";
        $params[] = $techId;
    } else {
        // Admin sees only unallocated serials (Main Store)
        $query .= " AND s.technician_id IS NULL";
    }
    
    $query .= " ORDER BY s.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $serials = $stmt->fetchAll();
    echo json_encode($serials);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
