<?php
require_once 'includes/db_connect.php';

try {
    // Fix invoices where customer_id is NULL by matching with the purchases table (outflows)
    $stmt = $pdo->query("UPDATE invoices i 
                         JOIN purchases p ON p.notes LIKE CONCAT('%', i.invoice_no, '%')
                         JOIN customers c ON c.customer_name = p.payee_name
                         SET i.customer_id = c.id 
                         WHERE i.customer_id IS NULL AND p.category IN ('Credit Sale', 'Credit Service')");
    echo "Fixed " . $stmt->rowCount() . " invoices via purchases (outflow) records.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
