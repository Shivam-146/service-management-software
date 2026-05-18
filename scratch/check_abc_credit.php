<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT * FROM purchases WHERE payee_name = 'abc' AND category = 'Credit Sale'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
