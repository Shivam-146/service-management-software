<?php
require_once 'includes/db_connect.php';
$cols = $pdo->query("DESCRIBE supplier_payments")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
