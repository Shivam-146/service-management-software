<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SELECT * FROM purchases WHERE payee_name = 'Shivam Das' OR supplier_id IN (SELECT id FROM suppliers WHERE supplier_name = 'Shivam Das')");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
