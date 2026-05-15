<?php
$pdo = new PDO('mysql:host=localhost;dbname=cctv_db', 'root', '');
$stmt = $pdo->query('DESCRIBE stock_movements');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
