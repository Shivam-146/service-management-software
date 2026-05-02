<?php
require_once 'includes/db_connect.php';

$id = 6;
$status = 'In-Progress';
$remarks = 'Testing update';

try {
    $stmt = $pdo->prepare("UPDATE complaints SET status = ?, tech_remarks = ? WHERE id = ?");
    $stmt->execute([$status, $remarks, $id]);
    echo "Updated successfully. Current status in DB: ";
    $check = $pdo->prepare("SELECT status FROM complaints WHERE id = ?");
    $check->execute([$id]);
    echo $check->fetchColumn() . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
