<?php
require 'includes/db_connect.php';
$id = 9;
$lastStatus = '';
echo "Monitoring Ticket #9 status for 60 seconds...\n";
$start = time();
while (time() - $start < 60) {
    $s = $pdo->prepare('SELECT status, started_at FROM complaints WHERE id = ?');
    $s->execute([$id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    $status = $row['status'] . " | Started: " . ($row['started_at'] ?? 'NULL');
    if ($status !== $lastStatus) {
        echo date('H:i:s') . " - Status changed to: " . $status . "\n";
        $lastStatus = $status;
    }
    usleep(500000); // 0.5 sec
}
?>
