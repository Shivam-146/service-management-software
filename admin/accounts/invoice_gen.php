<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$invoiceId = $_GET['id'] ?? null;

if (!$invoiceId) {
    die("Invoice ID is required.");
}

try {
    // Fetch invoice details
    $stmt = $pdo->prepare("SELECT i.*, cust.customer_name, cust.address, cust.gst_number 
                           FROM invoices i 
                           LEFT JOIN customers cust ON (SELECT customer_id FROM payments p WHERE p.invoice_id = i.id LIMIT 1) = cust.id
                           WHERE i.id = ?");
    
    // Note: The customer link for direct sales is currently via the payment record or we could have stored customer_id in invoices.
    // Let's try to get it from payments first.
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        die("Invoice not found.");
    }

    // Fetch Company/Admin Settings
    $adminStmt = $pdo->query("SELECT fullname, address, phone FROM users WHERE role = 'admin' LIMIT 1");
    $adminSettings = $adminStmt->fetch();
    $companyName = !empty($adminSettings['fullname']) ? $adminSettings['fullname'] : 'CCTV SECURE';
    $companyAddress = !empty($adminSettings['address']) ? nl2br(htmlspecialchars($adminSettings['address'])) : 'Tech Hub, Sector 5';
    $companyPhone = !empty($adminSettings['phone']) ? $adminSettings['phone'] : '+91 98765 43210';

    // Fetch Serials linked to this invoice
    $sStmt = $pdo->prepare("SELECT serial_number, purchase_price, product_id FROM product_serials WHERE invoice_id = ?");
    $sStmt->execute([$invoiceId]);
    $serials = $sStmt->fetchAll();
    
    $lines = [];
    $subtotal = (float)$invoice['subtotal'];
    $gst = (float)$invoice['gst_amount'];
    $grandTotal = (float)$invoice['grand_total'];
    $gstMode = $invoice['gst_mode'] ?: 'Exclusive';
    $effectiveRate = ($subtotal > 0) ? ($gst / $subtotal) : 0;

    foreach ($serials as $s) {
        $pStmt = $pdo->prepare("SELECT product_name FROM products WHERE id = ?");
        $pStmt->execute([$s['product_id']]);
        $pName = $pStmt->fetchColumn() ?: "Unknown Product";
        
        $price = (float)$s['purchase_price'];
        if ($gstMode === 'Inclusive' && $effectiveRate > 0) {
            $price = $price / (1 + $effectiveRate);
        }

        $lines[] = [
            'name' => $pName . " (SN: " . $s['serial_number'] . ")",
            'price' => $price,
            'qty' => 1,
            'total' => $price
        ];
    }

    // Add un-serialized parts if there is a difference in subtotal
    $serializedSubtotal = 0;
    foreach ($lines as $l) { $serializedSubtotal += $l['total']; }
    
    if (abs($subtotal - $serializedSubtotal) > 0.1) {
        $diff = $subtotal - $serializedSubtotal;
        $lines[] = [
            'name' => "Other Items / Un-serialized Parts",
            'price' => $diff,
            'qty' => 1,
            'total' => $diff
        ];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/header.php';
$isPrint = isset($_GET['print']);
?>

<?php if (!$isPrint): ?>
<div class="mb-8 flex items-center justify-between no-print max-w-4xl mx-auto mt-8">
    <h2 class="text-2xl font-bold text-slate-800">Sales Invoice</h2>
    <div class="flex gap-3">
        <a href="sales.php" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 font-medium hover:bg-slate-300 transition">Return to Sales</a>
        <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Invoice
        </button>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 max-w-4xl mx-auto mb-10 mt-8" id="invoice-printable">
    <div class="flex justify-between items-start mb-12">
        <div>
            <h1 class="text-3xl font-black text-indigo-600 mb-2"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-slate-500 text-sm">Service & Maintenance Specialist<br><?php echo $companyAddress; ?><br>Support: <?php echo htmlspecialchars($companyPhone); ?></p>
        </div>
        <div class="text-right">
            <h2 class="text-4xl font-light text-slate-400 uppercase tracking-widest mb-4">Invoice</h2>
            <div class="space-y-1 text-sm">
                <p><span class="text-slate-400">Date:</span> <span class="font-bold"><?php echo date('d-m-Y', strtotime($invoice['created_at'])); ?></span></p>
                <p><span class="text-slate-400">Invoice No:</span> <span class="font-bold"><?php echo htmlspecialchars($invoice['invoice_no']); ?></span></p>
                <p><span class="text-slate-400">Method:</span> <span class="font-bold text-indigo-600 uppercase"><?php echo htmlspecialchars($invoice['payment_method']); ?></span></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-12 mb-12 px-2">
        <div>
            <h3 class="text-xs uppercase font-bold text-slate-400 mb-3 tracking-widest">Bill To:</h3>
            <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($invoice['customer_name'] ?: 'Retail Customer'); ?></p>
            <p class="text-slate-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($invoice['address'] ?? '')); ?></p>
            <?php if (!empty($invoice['gst_number'])): ?>
                <p class="mt-2 text-sm font-medium">GSTIN: <span class="text-indigo-600 uppercase"><?php echo htmlspecialchars($invoice['gst_number']); ?></span></p>
            <?php endif; ?>
        </div>
    </div>

    <table class="w-full mb-12">
        <thead>
            <tr class="border-b-2 border-slate-100 text-left text-xs uppercase font-bold text-slate-400 tracking-wider">
                <th class="py-4 px-2">Description</th>
                <th class="py-4 px-2 text-center">Qty</th>
                <th class="py-4 px-2 text-right">Unit Price</th>
                <th class="py-4 px-2 text-right">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <?php foreach ($lines as $line): ?>
            <tr>
                <td class="py-5 px-2">
                    <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($line['name']); ?></p>
                </td>
                <td class="py-5 px-2 text-center text-slate-600 text-sm"><?php echo $line['qty']; ?></td>
                <td class="py-5 px-2 text-right text-slate-600 text-sm">₹<?php echo number_format($line['price'], 2); ?></td>
                <td class="py-5 px-2 text-right font-bold text-slate-800 text-sm">₹<?php echo number_format($line['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="flex justify-end">
        <div class="w-full max-w-xs space-y-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Subtotal:</span>
                <span class="text-slate-800 font-bold">₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">GST (<?php echo round($effectiveRate * 100); ?>%):</span>
                <span class="text-slate-800 font-bold">₹<?php echo number_format($gst, 2); ?></span>
            </div>
            <div class="pt-4 border-t-2 border-indigo-600 flex justify-between items-center text-xl">
                <span class="font-black text-slate-800 uppercase tracking-wider">Grand Total:</span>
                <span class="font-black text-indigo-600">₹<?php echo number_format($grandTotal, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="mt-20 pt-10 border-t border-slate-100 text-[10px] text-slate-400 uppercase tracking-widest text-center">
        <p>This is a computer generated invoice.</p>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    main { margin-left: 0 !important; padding: 0 !important; }
    .bg-white { box-shadow: none !important; border: none !important; }
    #invoice-printable { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
