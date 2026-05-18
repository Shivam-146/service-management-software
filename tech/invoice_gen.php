<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';

$complaintId = $_GET['id'] ?? null;
$techId = $_SESSION['user_id'];

if (!$complaintId) {
    die("Complaint ID is required.");
}

try {
    // Fetch complaint, invoice details, and check authorization
    $stmt = $pdo->prepare("SELECT c.*, i.invoice_no, i.payment_method, i.grand_total as invoice_total, cust.customer_name, cust.address, cust.gst_number, u.fullname as tech_name 
                           FROM complaints c 
                           JOIN customers cust ON c.customer_id = cust.id 
                           JOIN users u ON c.assigned_tech_id = u.id 
                           JOIN invoices i ON c.id = i.complaint_id
                           WHERE c.id = ? AND c.assigned_tech_id = ?");
    $stmt->execute([$complaintId, $techId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        die("Unauthorized: You do not have permission to print this invoice, or it does not exist.");
    }

    // Fetch Company/Admin Settings for Invoice Header
    $adminStmt = $pdo->query("SELECT fullname, address, phone FROM users WHERE role = 'admin' LIMIT 1");
    $adminSettings = $adminStmt->fetch();
    $companyName = !empty($adminSettings['fullname']) ? $adminSettings['fullname'] : 'CCTV SECURE';
    $companyAddress = !empty($adminSettings['address']) ? nl2br(htmlspecialchars($adminSettings['address'])) : 'Tech Hub, Sector 5';
    $companyPhone = !empty($adminSettings['phone']) ? $adminSettings['phone'] : '+91 98765 43210';

    // 1. Fetch Serials linked to this invoice to get accurate individual pricing
    $sStmt = $pdo->prepare("SELECT serial_number, purchase_price, product_id FROM product_serials WHERE invoice_id = (SELECT id FROM invoices WHERE complaint_id = ? LIMIT 1)");
    $sStmt->execute([$complaintId]);
    $serials = $sStmt->fetchAll();
    
    $lines = [];
    $subtotal = 0;
    $serialCountByProduct = [];

    // Add serials to lines
    foreach ($serials as $s) {
        $pStmt = $pdo->prepare("SELECT product_name, product_code FROM products WHERE id = ?");
        $pStmt->execute([$s['product_id']]);
        $product = $pStmt->fetch();
        $pName = $product['product_name'] ?? "Unknown Product";
        $pCode = $product['product_code'] ?? "";
        
        $price = (float)$s['purchase_price'];
        $lines[] = [
            'name' => $pName . " (SN: " . $s['serial_number'] . ")",
            'hsn' => $pCode,
            'price' => $price,
            'qty' => 1,
            'total' => $price
        ];
        $subtotal += $price;
        $serialCountByProduct[$s['product_id']] = ($serialCountByProduct[$s['product_id']] ?? 0) + 1;
    }

    // 2. Add remaining parts (non-serialized) from parts_consumed JSON
    $partsConsumed = json_decode($complaint['parts_consumed'], true) ?? [];
    foreach ($partsConsumed as $part) {
        $pid = $part['product_id'];
        $qty = (int)$part['qty'];
        $serialsUsed = $serialCountByProduct[$pid] ?? 0;
        $remainingQty = max(0, $qty - $serialsUsed);

        if ($remainingQty > 0) {
            $pStmt = $pdo->prepare("SELECT product_name, product_code, unit_price FROM products WHERE id = ?");
            $pStmt->execute([$pid]);
            $product = $pStmt->fetch();
            if ($product) {
                $price = (float)$product['unit_price'];
                $total = $remainingQty * $price;
                $lines[] = [
                    'name' => $product['product_name'] . " (Un-serialized)",
                    'hsn' => $product['product_code'],
                    'price' => $price,
                    'qty' => $remainingQty,
                    'total' => $total
                ];
                $subtotal += $total;
            }
        }
    }

    // 3. Use GST and Total from the Invoice table (don't re-calculate at hardcoded 18%)
    $invStmt = $pdo->prepare("SELECT subtotal, gst_amount, grand_total, gst_mode FROM invoices WHERE complaint_id = ?");
    $invStmt->execute([$complaintId]);
    $invoiceData = $invStmt->fetch();

    if ($invoiceData) {
        $dbSubtotal = (float)$invoiceData['subtotal'];
        $gst = (float)$invoiceData['gst_amount'];
        $grandTotal = (float)$invoiceData['grand_total'];
        $gstMode = $invoiceData['gst_mode'] ?: 'Exclusive';
        
        // Calculate the effective rate
        $effectiveRate = ($dbSubtotal > 0) ? ($gst / $dbSubtotal) : 0;

        // If Inclusive, we need to show the taxable (pre-tax) price for each line to be consistent
        if ($gstMode === 'Inclusive' && $effectiveRate > 0) {
            foreach ($lines as &$line) {
                $line['price'] = $line['price'] / (1 + $effectiveRate);
                $line['total'] = $line['qty'] * $line['price'];
            }
        }
        
        // Use database subtotal for final display
        $subtotal = $dbSubtotal;
    } else {
        $gst = 0;
        $grandTotal = $subtotal;
        $gstMode = 'Exclusive';
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Header inclusion (handles print mode inside)
require_once '../includes/header.php';
$isPrint = isset($_GET['print']);
?>

<?php if (!$isPrint): ?>
<div class="mb-8 flex items-center justify-between no-print max-w-4xl mx-auto mt-8">
    <h2 class="text-2xl font-bold text-slate-800">Invoice Review</h2>
    <div class="flex gap-3">
        <a href="billing.php" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 font-medium hover:bg-slate-300 transition">Return to Billing</a>
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
                <p><span class="text-slate-400">Date:</span> <span class="font-bold"><?php echo date('d-m-Y', strtotime($complaint['created_at'])); ?></span></p>
                <p><span class="text-slate-400">Invoice No:</span> <span class="font-bold"><?php echo htmlspecialchars($complaint['invoice_no']); ?></span></p>
                <p><span class="text-slate-400">Ticket ID:</span> <span class="font-bold">#<?php echo $complaint['id']; ?></span></p>
                <p><span class="text-slate-400">Method:</span> <span class="font-bold text-indigo-600 uppercase"><?php echo htmlspecialchars($complaint['payment_method']); ?></span></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-12 mb-12 px-2">
        <div>
            <h3 class="text-xs uppercase font-bold text-slate-400 mb-3 tracking-widest">Bill To:</h3>
            <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($complaint['customer_name']); ?></p>
            <p class="text-slate-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($complaint['address'])); ?></p>
            <?php if (!empty($complaint['gst_number'])): ?>
                <p class="mt-2 text-sm font-medium">GSTIN: <span class="text-indigo-600 uppercase"><?php echo htmlspecialchars($complaint['gst_number']); ?></span></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <h3 class="text-xs uppercase font-bold text-slate-400 mb-3 tracking-widest">Service Details:</h3>
            <p class="text-slate-800 font-medium">Technician: <?php echo htmlspecialchars($complaint['tech_name']); ?></p>
            <p class="text-slate-500 text-sm italic mt-1">"<?php echo htmlspecialchars($complaint['issue_description']); ?>"</p>
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
            <?php if (empty($lines)): ?>
                <tr><td colspan="4" class="py-8 text-center text-slate-400 italic">No inventory parts consumed for this ticket.</td></tr>
            <?php else: ?>
                <?php foreach ($lines as $line): ?>
                <tr>
                    <td class="py-5 px-2">
                        <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($line['name']); ?></p>
                        <?php if (!empty($line['hsn'])): ?>
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">HSN Code: <?php echo htmlspecialchars($line['hsn']); ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="py-5 px-2 text-center text-slate-600 text-sm"><?php echo $line['qty']; ?></td>
                    <td class="py-5 px-2 text-right text-slate-600 text-sm">₹<?php echo number_format($line['price'], 2); ?></td>
                    <td class="py-5 px-2 text-right font-bold text-slate-800 text-sm">₹<?php echo number_format($line['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            

        </tbody>
    </table>

    <div class="flex justify-end">
        <div class="w-full max-w-xs space-y-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Subtotal:</span>
                <span class="text-slate-800 font-bold">₹<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <?php 
                    $gstRateDisplay = ($subtotal > 0) ? round(($gst / $subtotal) * 100) : 0;
                ?>
                <span class="text-slate-500">GST (<?php echo $gstRateDisplay; ?>%):</span>
                <span class="text-slate-800 font-bold">₹<?php echo number_format($gst, 2); ?></span>
            </div>
            <div class="pt-4 border-t-2 border-indigo-600 flex justify-between items-center text-xl">
                <span class="font-black text-slate-800 uppercase tracking-wider">Grand Total:</span>
                <span class="font-black text-indigo-600">₹<?php echo number_format($grandTotal, 2); ?></span>
            </div>
        </div>
    </div>

    <div class="mt-20 pt-10 border-t border-slate-100 grid grid-cols-2 text-[10px] text-slate-400 uppercase tracking-widest">
        <div>
            <p class="font-bold mb-2">Terms & Conditions</p>
            <p>1. Warranty on parts as per manufacturer.<br>2. Service warranty for 7 days only.<br>3. This is a computer generated invoice.</p>
        </div>
        <div class="text-right flex flex-col justify-end">
            <div class="mb-2 h-10 w-32 border-b border-slate-300 ml-auto"></div>
            <p>Authorized Signature: <?php echo htmlspecialchars($complaint['tech_name']); ?></p>
        </div>
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

<?php if ($isPrint): ?>
<script>
window.onload = function() {
    window.print();
}
</script>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>
