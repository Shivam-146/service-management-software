<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("DESCRIBE purchases");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
?>
