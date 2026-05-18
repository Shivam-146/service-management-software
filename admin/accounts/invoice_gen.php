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
                           LEFT JOIN payments p ON i.id = p.invoice_id
                           LEFT JOIN customers cust ON p.customer_id = cust.id
                           WHERE i.id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        die("Invoice not found.");
    }

    // Fetch Company Profile (using company_profile table from migrate.php)
    $profileStmt = $pdo->query("SELECT * FROM company_profile LIMIT 1");
    $profile = $profileStmt->fetch();
    $companyName = $profile['company_name'] ?? 'CCTV SECURE';
    $companyAddress = $profile['address'] ?? 'Tech Hub, Sector 5';
    $companyPhone = $profile['contact_number'] ?? '+91 98765 43210';
    $companyEmail = $profile['email'] ?? 'support@cctvsecure.com';
    $companyGst = $profile['gst_number'] ?? '';

    // Fetch Invoice Items
    $itemsStmt = $pdo->prepare("SELECT ii.*, p.product_name, p.product_code 
                                FROM invoice_items ii 
                                LEFT JOIN products p ON ii.product_id = p.id 
                                WHERE ii.invoice_id = ?");
    $itemsStmt->execute([$invoiceId]);
    $items = $itemsStmt->fetchAll();

    // Fetch Serial Numbers for this invoice to display as sub-items
    $serialsStmt = $pdo->prepare("SELECT serial_number, product_id FROM product_serials WHERE invoice_id = ?");
    $serialsStmt->execute([$invoiceId]);
    $allSerials = $serialsStmt->fetchAll();
    
    $productSerials = [];
    foreach ($allSerials as $s) {
        $productSerials[$s['product_id']][] = $s['serial_number'];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/header.php';
$isPrint = isset($_GET['print']);
?>

<?php if (!$isPrint): ?>
<div class="mb-8 flex items-center justify-between no-print max-w-5xl mx-auto mt-8">
    <div class="flex items-center gap-4">
        <a href="sales.php" class="p-2 bg-white border border-slate-200 rounded-none text-slate-500 hover:text-indigo-600 transition shadow-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h2 class="text-2xl font-bold text-slate-800 uppercase tracking-tight">Sales Invoice Viewer</h2>
    </div>
    <div class="flex gap-3">
        <button onclick="window.print()" class="px-8 py-3 rounded-none bg-indigo-600 text-white font-bold hover:bg-indigo-700 transition flex items-center gap-2 shadow-lg shadow-indigo-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Invoice
        </button>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-none border border-slate-100 p-12 max-w-5xl mx-auto mb-20 shadow-none" id="invoice-printable">
    <!-- Header -->
    <div class="flex justify-between items-start mb-16 border-b-4 border-slate-900 pb-8">
        <div>
            <h1 class="text-4xl font-black text-slate-900 mb-2 uppercase tracking-tighter"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-slate-500 text-sm font-medium leading-relaxed">
                <?php echo nl2br(htmlspecialchars($companyAddress)); ?><br>
                Phone: <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($companyPhone); ?></span><br>
                Email: <span class="text-slate-800 font-bold"><?php echo htmlspecialchars($companyEmail); ?></span>
                <?php if ($companyGst): ?><br>GSTIN: <span class="text-indigo-600 font-black"><?php echo htmlspecialchars($companyGst); ?></span><?php endif; ?>
            </p>
        </div>
        <div class="text-right">
            <h2 class="text-6xl font-black text-slate-200 uppercase mb-4 opacity-50">Invoice</h2>
            <div class="space-y-1 text-sm">
                <p class="flex justify-end gap-2"><span class="text-slate-400 font-bold uppercase tracking-widest text-[10px]">No:</span> <span class="font-black text-slate-900">#<?php echo htmlspecialchars($invoice['invoice_no']); ?></span></p>
                <p class="flex justify-end gap-2"><span class="text-slate-400 font-bold uppercase tracking-widest text-[10px]">Date:</span> <span class="font-bold text-slate-900"><?php echo date('d-m-Y', strtotime($invoice['created_at'])); ?></span></p>
                <p class="flex justify-end gap-2"><span class="text-slate-400 font-bold uppercase tracking-widest text-[10px]">Status:</span> <span class="px-2 bg-indigo-50 text-indigo-600 font-black text-[10px] uppercase border border-indigo-100"><?php echo htmlspecialchars($invoice['payment_status']); ?></span></p>
            </div>
        </div>
    </div>

    <!-- Bill To -->
    <div class="grid grid-cols-2 gap-12 mb-16">
        <div class="bg-slate-50 p-6 border-l-4 border-indigo-600">
            <h3 class="text-[10px] uppercase font-black text-slate-400 mb-4 tracking-[0.2em]">Billed To:</h3>
            <p class="text-xl font-black text-slate-900 mb-1"><?php echo htmlspecialchars($invoice['customer_name'] ?: 'Direct Retail Customer'); ?></p>
            <p class="text-slate-500 text-sm font-medium leading-relaxed italic"><?php echo nl2br(htmlspecialchars($invoice['address'] ?? 'No address provided.')); ?></p>
            <?php if (!empty($invoice['gst_number'])): ?>
                <p class="mt-4 text-xs font-black">GSTIN: <span class="text-indigo-600 uppercase"><?php echo htmlspecialchars($invoice['gst_number']); ?></span></p>
            <?php endif; ?>
        </div>
        <div class="flex flex-col justify-end">
            <div class="text-right space-y-2">
                <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest">Payment Method</p>
                <p class="text-2xl font-black text-slate-900 uppercase"><?php echo htmlspecialchars($invoice['payment_method']); ?></p>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="w-full mb-16">
        <thead>
            <tr class="bg-slate-900 text-white text-left text-[10px] uppercase font-black tracking-widest">
                <th class="py-4 px-4">Item Description</th>
                <th class="py-4 px-2 text-center">Qty</th>
                <th class="py-4 px-2 text-right">Unit Price</th>
                <th class="py-4 px-2 text-right">Discount</th>
                <th class="py-4 px-2 text-right">GST %</th>
                <th class="py-4 px-4 text-right">Line Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 border-x border-slate-100">
            <?php foreach ($items as $item): ?>
            <tr class="hover:bg-slate-50 transition">
                <td class="py-6 px-4">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2">
                            <span class="font-black text-slate-800 text-sm uppercase tracking-tight"><?php echo htmlspecialchars($item['product_name']); ?></span>
                            <?php if (isset($productSerials[$item['product_id']])): ?>
                                <span class="text-[9px] font-bold text-slate-400">
                                    (SN: <?php echo implode(', ', $productSerials[$item['product_id']]); ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['product_code']): ?>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-1">HSN Code: <?php echo htmlspecialchars($item['product_code']); ?></span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="py-6 px-2 text-center text-slate-700 font-black text-sm"><?php echo $item['quantity']; ?></td>
                <td class="py-6 px-2 text-right text-slate-700 font-bold text-sm">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                <td class="py-6 px-2 text-right text-rose-600 font-bold text-sm">
                    <?php if ($item['discount_amount'] > 0): ?>
                        -₹<?php echo number_format($item['discount_amount'], 2); ?>
                        <div class="text-[8px] text-slate-400 italic">
                            <?php echo $item['discount_type'] == 'Percentage' ? $item['discount_value'].'%' : 'Fixed'; ?>
                        </div>
                    <?php else: ?>
                        0.00
                    <?php endif; ?>
                </td>
                <td class="py-6 px-2 text-right text-slate-700 font-bold text-sm">
                    <?php echo round($item['gst_rate'], 1); ?>%
                    <div class="text-[8px] text-slate-400 uppercase font-black tracking-tighter">
                        <?php echo $item['gst_mode'] == 'Inclusive' ? 'Incl' : 'Excl'; ?>
                    </div>
                </td>
                <td class="py-6 px-4 text-right font-black text-slate-900 text-sm">₹<?php echo number_format($item['total_price'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="flex justify-end">
        <div class="w-full max-w-sm">
            <div class="space-y-3 px-4">
                <div class="flex justify-between text-xs font-bold text-slate-500 uppercase tracking-widest">
                    <span>Taxable Value (Subtotal)</span>
                    <span>₹<?php echo number_format($invoice['subtotal'], 2); ?></span>
                </div>
                <div class="flex justify-between text-xs font-bold text-slate-500 uppercase tracking-widest">
                    <span>GST (Total Tax)</span>
                    <span>₹<?php echo number_format($invoice['gst_amount'], 2); ?></span>
                </div>
                <div class="pt-6 border-t-4 border-slate-900 flex justify-between items-center mt-4">
                    <span class="font-black text-slate-900 text-2xl uppercase tracking-tighter">Grand Total</span>
                    <div class="text-right">
                        <span class="font-black text-indigo-600 text-4xl tracking-tighter">₹<?php echo number_format($invoice['grand_total'], 2); ?></span>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-1">Net Payable Amount</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="mt-24 pt-10 border-t border-slate-100 flex justify-between items-center opacity-40">
        <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">
            Terms: Payment expected within 15 days. Subject to local jurisdiction.
        </div>
        <div class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em]">
            This is a computer generated invoice
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; padding: 0 !important; margin: 0 !important; }
    main { margin-left: 0 !important; padding: 0 !important; width: 100% !important; }
    #invoice-printable { 
        margin: 0 !important; 
        padding: 40px !important; 
        width: 100% !important; 
        max-width: none !important; 
        border: none !important;
        box-shadow: none !important;
    }
    .bg-white { background: white !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
