<?php
require_once 'includes/db_connect.php';

try {
    // 1. Fix invoices linked to complaints
    $stmt1 = $pdo->query("UPDATE invoices i 
                          JOIN complaints c ON i.complaint_id = c.id 
                          SET i.customer_id = c.customer_id 
                          WHERE i.customer_id IS NULL AND i.complaint_id IS NOT NULL");
    echo "Fixed " . $stmt1->rowCount() . " invoices via complaints.\n";

    // 2. Fix invoices linked to payments (Direct Sales)
    $stmt2 = $pdo->query("UPDATE invoices i 
                          JOIN payments p ON i.id = p.invoice_id 
                          SET i.customer_id = p.customer_id 
                          WHERE i.customer_id IS NULL");
    echo "Fixed " . $stmt2->rowCount() . " invoices via payments.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
