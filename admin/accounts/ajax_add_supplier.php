<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['supplier_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $gst = $_POST['gst_number'] ?? '';

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, address, gst_number) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $address, $gst]);
        $id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'id' => $id,
            'name' => $name
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error saving supplier: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
