<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

$complaintId = $_GET['id'] ?? null;

if (!$complaintId) {
    die("Complaint ID is required.");
}

try {
    // Fetch complaint and customer data
    $stmt = $pdo->prepare("SELECT c.*, i.invoice_no, i.payment_method, cust.customer_name, cust.address, cust.gst_number, u.fullname as tech_name 
                           FROM complaints c 
                           JOIN customers cust ON c.customer_id = cust.id 
                           LEFT JOIN users u ON c.assigned_tech_id = u.id 
                           LEFT JOIN invoices i ON c.id = i.complaint_id
                           WHERE c.id = ?");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        die("Complaint not found.");
    }

    // Fetch Company Profile Settings for Invoice Header
    $profileStmt = $pdo->query("SELECT * FROM company_profile LIMIT 1");
    $profile = $profileStmt->fetch();
    
    $companyName = !empty($profile['company_name']) ? $profile['company_name'] : 'CCTV SECURE';
    $companyAddress = !empty($profile['address']) ? nl2br(htmlspecialchars($profile['address'])) : 'Tech Hub, Sector 5';
    $companyPhone = !empty($profile['contact_number']) ? $profile['contact_number'] : '+91 98765 43210';
    $companyEmail = !empty($profile['email']) ? $profile['email'] : '';
    $companyGst = !empty($profile['gst_number']) ? $profile['gst_number'] : '';
    $companyBank = !empty($profile['bank_details']) ? nl2br(htmlspecialchars($profile['bank_details'])) : '';
    $companyLogo = !empty($profile['logo']) ? $profile['logo'] : '';
    $companySignature = !empty($profile['signature']) ? $profile['signature'] : '';

    // Parse parts consumed
    $partsConsumed = json_decode($complaint['parts_consumed'], true) ?? [];
    
    // Fetch current product details for the consumed parts to get accurate pricing
    $lines = [];
    $subtotal = 0;
    
    if (!empty($partsConsumed)) {
        foreach ($partsConsumed as $part) {
            $pStmt = $pdo->prepare("SELECT product_name as item_name, unit_price FROM products WHERE id = ?");
            $pStmt->execute([$part['product_id']]);
            $product = $pStmt->fetch();
            
            if ($product) {
                $qty = $part['qty'] ?? 1;
                $price = $product['unit_price'];
                $total = $qty * $price;
                
                $lines[] = [
                    'name' => $product['item_name'],
                    'price' => $price,
                    'qty' => $qty,
                    'total' => $total
                ];
                $subtotal += $total;
            }
        }
    }

    $gst = $subtotal * 0.18;
    $grandTotal = $subtotal + $gst;

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Header inclusion (handles print mode inside)
require_once '../includes/header.php';
$isPrint = isset($_GET['print']);
?>

<?php if (!$isPrint): ?>
<div class="mb-8 flex items-center justify-between no-print">
    <h2 class="text-2xl font-bold text-slate-800">Invoice Generation</h2>
    <div class="flex gap-3">
        <a href="complaints.php" class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 font-medium hover:bg-slate-300 transition">Back to Tickets</a>
        <button onclick="window.print()" class="px-4 py-2 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Invoice
        </button>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 max-w-4xl mx-auto mb-10" id="invoice-printable">
    <div class="flex justify-between items-start mb-12">
        <div class="flex items-start gap-6">
            <?php if ($companyLogo): ?>
                <img src="../uploads/<?php echo $companyLogo; ?>" class="h-24 w-24 object-contain">
            <?php endif; ?>
            <div>
                <h1 class="text-3xl font-black text-indigo-600 mb-2"><?php echo htmlspecialchars($companyName); ?></h1>
                <p class="text-slate-500 text-xs leading-relaxed">
                    <?php echo $companyAddress; ?><br>
                    Support: <?php echo htmlspecialchars($companyPhone); ?> 
                    <?php if ($companyEmail): ?> | <?php echo htmlspecialchars($companyEmail); ?><?php endif; ?>
                    <?php if ($companyGst): ?><br><span class="font-bold">GSTIN: <?php echo htmlspecialchars($companyGst); ?></span><?php endif; ?>
                </p>
            </div>
        </div>
        <div class="text-right">
            <h2 class="text-4xl font-light text-slate-400 uppercase tracking-widest mb-4">Invoice</h2>
            <div class="space-y-1 text-sm">
                <p><span class="text-slate-400">Date:</span> <span class="font-bold"><?php echo date('d-m-Y', strtotime($complaint['created_at'])); ?></span></p>
                <?php if ($complaint['invoice_no']): ?>
                    <p><span class="text-slate-400">Invoice No:</span> <span class="font-bold"><?php echo htmlspecialchars($complaint['invoice_no']); ?></span></p>
                <?php endif; ?>
                <p><span class="text-slate-400">Ticket ID:</span> <span class="font-bold">#<?php echo $complaint['id']; ?></span></p>
                <?php if ($complaint['payment_method']): ?>
                    <p><span class="text-slate-400">Method:</span> <span class="font-bold text-indigo-600 uppercase"><?php echo htmlspecialchars($complaint['payment_method']); ?></span></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-12 mb-12 px-2">
        <div>
            <h3 class="text-xs uppercase font-bold text-slate-400 mb-3 tracking-widest">Bill To:</h3>
            <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($complaint['customer_name']); ?></p>
            <p class="text-slate-600 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($complaint['address'])); ?></p>
            <?php if ($complaint['gst_number']): ?>
                <p class="mt-2 text-sm font-medium">GSTIN: <span class="text-indigo-600 uppercase"><?php echo htmlspecialchars($complaint['gst_number']); ?></span></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <h3 class="text-xs uppercase font-bold text-slate-400 mb-3 tracking-widest">Service Details:</h3>
            <p class="text-slate-800 font-medium">Technician: <?php echo htmlspecialchars($complaint['tech_name'] ?? 'General Team'); ?></p>
            <p class="text-slate-500 text-sm italic">"<?php echo htmlspecialchars($complaint['issue_description']); ?>"</p>
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
                    </td>
                    <td class="py-5 px-2 text-center text-slate-600 text-sm"><?php echo $line['qty']; ?></td>
                    <td class="py-5 px-2 text-right text-slate-600 text-sm">₹<?php echo number_format($line['price'], 2); ?></td>
                    <td class="py-5 px-2 text-right font-bold text-slate-800 text-sm">₹<?php echo number_format($line['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            

        </tbody>
    </table>

    <div class="grid grid-cols-2 gap-12">
        <div class="p-6 bg-slate-50 border border-slate-100">
            <h3 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Bank Details for Payment:</h3>
            <p class="text-xs text-slate-600 leading-relaxed font-medium">
                <?php echo $companyBank ?: 'Contact support for bank details.'; ?>
            </p>
        </div>
        <div class="flex justify-end">
            <div class="w-full max-w-xs space-y-4">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">Subtotal:</span>
                    <span class="text-slate-800 font-bold">₹<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-slate-500">GST (18%):</span>
                    <span class="text-slate-800 font-bold">₹<?php echo number_format($subtotal * 0.18, 2); ?></span>
                </div>
                <div class="pt-4 border-t-2 border-indigo-600 flex justify-between items-center text-xl">
                    <span class="font-black text-slate-800 uppercase tracking-wider">Grand Total:</span>
                    <span class="font-black text-indigo-600">₹<?php echo number_format($subtotal * 1.18, 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-20 pt-10 border-t border-slate-100 grid grid-cols-2 text-[10px] text-slate-400 uppercase tracking-widest">
        <div>
            <p class="font-bold mb-2 text-slate-600">Terms & Conditions</p>
            <p class="leading-relaxed">1. Warranty on parts as per manufacturer.<br>2. Service warranty for 7 days only.<br>3. This is a computer generated invoice.</p>
        </div>
        <div class="text-right flex flex-col justify-end items-end">
            <?php if ($companySignature): ?>
                <img src="../uploads/<?php echo $companySignature; ?>" class="h-16 w-32 object-contain mb-2">
            <?php else: ?>
                <div class="mb-2 h-10 w-32 border-b border-slate-300"></div>
            <?php endif; ?>
            <p class="font-bold text-slate-800 italic">Authorized Signature</p>
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
