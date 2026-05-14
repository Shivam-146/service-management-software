<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['product_name'] ?? '';
    $cat_id = $_POST['category_id'] ?: null;
    $code = $_POST['product_code'] ?? '';
    $unit = $_POST['unit'] ?? 'Pcs';
    $min = $_POST['opening_stock'] ?? 0;
    $price = $_POST['unit_price'] ?? 0;
    $desc = $_POST['description'] ?? '';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Product name is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO products (category_id, product_name, product_code, unit, opening_stock, unit_price, current_stock, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cat_id, $name, $code, $unit, $min, $price, $min, $desc]);
        $id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => $name
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Product code already exists.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving product: ' . $e->getMessage()]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
