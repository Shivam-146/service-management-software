<?php
require 'includes/db_connect.php';
$id = 9;
$pdo->prepare("UPDATE complaints SET status = 'In-Progress', started_at = NOW() WHERE id = ?")->execute([$id]);
echo "Updated to In-Progress. Checking again...\n";
$s = $pdo->prepare('SELECT status FROM complaints WHERE id = ?');
$s->execute([$id]);
echo "Current Status: " . $s->fetchColumn() . "\n";
?>
