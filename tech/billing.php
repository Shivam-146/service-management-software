<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';

$techId = $_SESSION['user_id'];

// Handle Generate Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    $complaintId = $_POST['complaint_id'];
    
    // Safety check: Make sure this complaint belongs to this tech and is completed
    $chk = $pdo->prepare("SELECT * FROM complaints WHERE id = ? AND assigned_tech_id = ? AND status = 'Completed'");
    $chk->execute([$complaintId, $techId]);
    $complaint = $chk->fetch();
    
    if ($complaint) {
        $partsConsumed = json_decode($complaint['parts_consumed'], true) ?? [];
        $subtotal = 500; // Standard Service Fee

        if (!empty($partsConsumed)) {
            foreach ($partsConsumed as $part) {
                $pStmt = $pdo->prepare("SELECT unit_price FROM products WHERE id = ?");
                $pStmt->execute([$part['product_id']]);
                if ($product = $pStmt->fetch()) {
                    $qty = $part['qty'] ?? 1;
                    $subtotal += ($qty * $product['unit_price']);
                }
            }
        }
        
        $gstAmount = $subtotal * 0.18;
        $grandTotal = $subtotal + $gstAmount;
        $invoiceNo = 'INV' . time() . rand(10, 99);
        
        try {
            $paymentMethod = $_POST['payment_method'] ?? 'Cash';
            $ins = $pdo->prepare("INSERT INTO invoices (complaint_id, invoice_no, subtotal, gst_amount, grand_total, payment_status, payment_method) VALUES (?, ?, ?, ?, ?, 'Unpaid', ?)");
            $ins->execute([$complaintId, $invoiceNo, $subtotal, $gstAmount, $grandTotal, $paymentMethod]);
            $successMsg = "Invoice generated successfully! You can now print and hand it to the customer.";
        } catch (PDOException $e) {
            $errorMsg = "Error generating invoice: " . $e->getMessage();
        }
    } else {
        $errorMsg = "Invalid ticket or the ticket is not yet marked as 'Completed'.";
    }
}

// Handle Payment Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $invoiceId = $_POST['invoice_id'];
    $method = $_POST['payment_method'];
    $status = $_POST['payment_status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE invoices SET payment_method = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$method, $status, $invoiceId]);
        $successMsg = "Payment details updated!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch data
try {
    // Invoices generated for this tech's jobs
    $stmt = $pdo->prepare("SELECT i.*, cust.customer_name 
                           FROM invoices i 
                           JOIN complaints c ON i.complaint_id = c.id 
                           JOIN customers cust ON c.customer_id = cust.id 
                           WHERE c.assigned_tech_id = ? 
                           ORDER BY i.created_at DESC");
    $stmt->execute([$techId]);
    $invoices = $stmt->fetchAll();
    
    // Pending completed jobs without invoices
    $pendingStmt = $pdo->prepare("SELECT c.id, cust.customer_name, c.completed_at 
                                  FROM complaints c 
                                  JOIN customers cust ON c.customer_id = cust.id 
                                  LEFT JOIN invoices i ON c.id = i.complaint_id
                                  WHERE c.assigned_tech_id = ? AND c.status = 'Completed' AND i.id IS NULL");
    $pendingStmt->execute([$techId]);
    $pendingJobs = $pendingStmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="space-y-8 max-w-5xl mx-auto">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">My Billing & Invoices</h2>
        <?php if (!empty($pendingJobs)): ?>
            <button onclick="openGenerateModal()" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2 animate-pulse">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Generate New Invoice
            </button>
        <?php else: ?>
            <button disabled class="bg-slate-200 text-slate-400 px-5 py-2.5 rounded-lg text-sm font-bold cursor-not-allowed">Generate New Invoice</button>
        <?php endif; ?>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">Invoice No</th>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Grand Total</th>
                        <th class="px-6 py-4 font-semibold">Method</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Date</th>
                        <th class="px-6 py-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 font-medium">You haven't generated any invoices yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-900"><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-600"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                            <td class="px-6 py-4 text-sm font-black text-slate-800">₹<?php echo number_format($invoice['grand_total'], 2); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase <?php 
                                        if (!$invoice['payment_method']) echo 'bg-slate-100 text-slate-500';
                                        elseif ($invoice['payment_method'] === 'UPI') echo 'bg-purple-100 text-purple-700';
                                        else echo 'bg-amber-100 text-amber-700'; 
                                    ?>">
                                        <?php echo $invoice['payment_method'] ?: 'PENDING'; ?>
                                    </span>
                                    <button onclick="openEditPaymentModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['payment_method']; ?>', '<?php echo $invoice['payment_status']; ?>')" class="text-slate-400 hover:text-indigo-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $paymentClasses = [
                                        'Paid' => 'bg-green-100 text-green-700',
                                        'Unpaid' => 'bg-red-100 text-red-700',
                                        'Partial' => 'bg-yellow-100 text-yellow-700',
                                    ];
                                    $class = $paymentClasses[$invoice['payment_status']] ?? 'bg-slate-100 text-slate-700';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase <?php echo $class; ?>">
                                    <?php echo $invoice['payment_status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo date('M j, Y - g:i A', strtotime($invoice['created_at'])); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <a href="invoice_gen.php?id=<?php echo $invoice['complaint_id']; ?>&print=1" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1 bg-indigo-50 px-3 py-1.5 rounded-lg w-max transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    View / Print
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Generate Invoice Modal -->
<div id="generateModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl shadow-xl w-full max-w-lg p-8 transform transition-all">
        <div class="flex items-center justify-between mb-8">
            <h3 class="text-xl font-bold text-slate-900">Generate Invoice</h3>
            <button onclick="closeGenerateModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed">You can only generate invoices for jobs that have been distinctly marked as <span class="font-bold text-green-600">Completed</span>.</p>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Select Completed Ticket</label>
                <select name="complaint_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="">-- Choose Ticket --</option>
                    <?php foreach ($pendingJobs as $job): ?>
                        <option value="<?php echo $job['id']; ?>">
                            #<?php echo $job['id']; ?> - <?php echo htmlspecialchars($job['customer_name']); ?> (Closed: <?php echo date('d M', strtotime($job['completed_at'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Payment Method</label>
                <select name="payment_method" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="Cash">Cash Payment</option>
                    <option value="UPI">UPI / Digital Payment</option>
                </select>
            </div>
            <div class="flex items-center gap-3 pt-6 border-t border-slate-100">
                <button type="button" onclick="closeGenerateModal()" class="flex-1 px-4 py-3 rounded-xl border-2 border-slate-100 text-slate-500 font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="generate_invoice" class="flex-1 px-4 py-3 rounded-xl bg-indigo-600 text-white font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Confirm Generation</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Payment Modal -->
<div id="editPaymentModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900">Update Payment</h3>
            <button onclick="closeEditPaymentModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="invoice_id" id="edit_invoice_id">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-widest">Method</label>
                <select name="payment_method" id="edit_payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">None (Unpaid)</option>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-widest">Status</label>
                <select name="payment_status" id="edit_payment_status" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid</option>
                </select>
            </div>
            <div class="flex gap-2 pt-4">
                <button type="button" onclick="closeEditPaymentModal()" class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-500 text-sm font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="update_payment" class="flex-1 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 transition">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGenerateModal() {
    document.getElementById('generateModal').classList.remove('hidden');
}
function closeGenerateModal() {
    document.getElementById('generateModal').classList.add('hidden');
}
function openEditPaymentModal(id, method, status) {
    document.getElementById('edit_invoice_id').value = id;
    document.getElementById('edit_payment_method').value = method;
    document.getElementById('edit_payment_status').value = status;
    document.getElementById('editPaymentModal').classList.remove('hidden');
}
function closeEditPaymentModal() {
    document.getElementById('editPaymentModal').classList.add('hidden');
}
</script>

<?php require_once '../includes/footer.php'; ?>
