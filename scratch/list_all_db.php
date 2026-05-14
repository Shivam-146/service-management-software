<?php
require_once 'includes/db_connect.php';
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "Table: $table\n";
    $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_COLUMN);
    echo "  Columns: " . implode(", ", $cols) . "\n\n";
}
