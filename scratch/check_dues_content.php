<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT id, customer_name, due_amount FROM customers WHERE due_amount > 0");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
