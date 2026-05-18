<?php
require_once 'includes/db_connect.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    if (strpos($t, 'serial') !== false) {
        echo "Found: $t\n";
    }
}
?>
