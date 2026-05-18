<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['customer_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $gst = $_POST['gst_number'] ?? '';
    $amc = isset($_POST['has_active_amc']) && $_POST['has_active_amc'] == '1' ? 1 : 0;

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Name and Phone are required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, address, gst_number, has_active_amc) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $address, $gst, $amc]);
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
