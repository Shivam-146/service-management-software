<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['customer_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and Phone are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, address) VALUES (?, ?, ?)");
        $stmt->execute([$name, $phone, $address]);
        $id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => $name
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving customer: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
