<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("DESCRIBE customers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $pdo->query("DESCRIBE invoices");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
