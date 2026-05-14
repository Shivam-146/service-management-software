<?php
require_once 'includes/db_connect.php';
$cols = $pdo->query("DESCRIBE vouchers")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
