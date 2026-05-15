<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("DESCRIBE product_serials");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
