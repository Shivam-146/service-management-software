<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("DESCRIBE invoices");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
