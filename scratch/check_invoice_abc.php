<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->prepare("SELECT id, invoice_no, customer_id FROM invoices WHERE invoice_no = ?");
$stmt->execute(['SALE1778912973']);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>
