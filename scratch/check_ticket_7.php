<?php
require 'includes/db_connect.php';
$s = $pdo->prepare('SELECT * FROM complaints WHERE id = ?');
$s->execute([9]);
$row = $s->fetch(PDO::FETCH_ASSOC);
echo "ID: " . $row['id'] . "\n";
echo "Status: " . $row['status'] . "\n";
echo "Assigned At: " . $row['assigned_at'] . "\n";
echo "Started At: " . $row['started_at'] . "\n";
echo "Completed At: " . $row['completed_at'] . "\n";
?>
