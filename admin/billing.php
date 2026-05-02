<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';
require_once '../includes/db_connect.php';

// Handle Payment Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $invoiceId = $_POST['invoice_id'];
    $method = $_POST['payment_method'];
    $status = $_POST['payment_status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE invoices SET payment_method = ?, payment_status = ? WHERE id = ?");
        $stmt->execute([$method, $status, $invoiceId]);
        $successMsg = "Payment details updated successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error updating payment: " . $e->getMessage();
    }
}

require_once '../includes/header.php';

// Fetch Invoices
try {
    $stmt = $pdo->query("SELECT i.*, cust.customer_name, u.fullname as tech_name 
                         FROM invoices i 
                         JOIN complaints c ON i.complaint_id = c.id 
                         JOIN customers cust ON c.customer_id = cust.id 
                         LEFT JOIN users u ON c.assigned_tech_id = u.id 
                         ORDER BY i.created_at DESC");
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Billing & Invoices</h2>
        <div class="flex gap-2">
            <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Export CSV</button>
            <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">New Manual Invoice</button>
        </div>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative" role="alert"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative" role="alert"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">Invoice No</th>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Technician</th>
                        <th class="px-6 py-4 font-semibold">Grand Total</th>
                        <th class="px-6 py-4 font-semibold">Method</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Date</th>
                        <th class="px-6 py-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400">No invoices generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-900"><?php echo htmlspecialchars($invoice['invoice_no']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500 italic"><?php echo htmlspecialchars($invoice['tech_name'] ?? 'Not Assigned'); ?></td>
                            <td class="px-6 py-4 text-sm font-black text-slate-800">₹<?php echo number_format($invoice['grand_total'], 2); ?></td>
                            <td class="px-6 py-4 text-sm flex items-center gap-2">
                                <span class="px-2 py-1 rounded-lg text-[10px] font-bold uppercase <?php 
                                    if (!$invoice['payment_method']) echo 'bg-slate-100 text-slate-500';
                                    elseif ($invoice['payment_method'] === 'UPI') echo 'bg-purple-100 text-purple-700';
                                    else echo 'bg-amber-100 text-amber-700';
                                ?>">
                                    <?php echo $invoice['payment_method'] ?: 'PENDING'; ?>
                                </span>
                                <button onclick="openEditPaymentModal(<?php echo $invoice['id']; ?>, '<?php echo $invoice['payment_method']; ?>', '<?php echo $invoice['payment_status']; ?>')" class="text-slate-400 hover:text-indigo-600 transition" title="Edit Payment Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
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
                                <span class="px-2 py-1 rounded-full text-[10px] font-black uppercase <?php echo $class; ?>">
                                    <?php echo $invoice['payment_status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo date('M j, Y - g:i A', strtotime($invoice['created_at'])); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <a href="invoice_gen.php?id=<?php echo $invoice['complaint_id']; ?>&print=1" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    View
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
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-widest">Payment Method</label>
                <select name="payment_method" id="edit_payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">None (Unpaid)</option>
                    <option value="Cash">Cash</option>
                    <option value="UPI">UPI</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1 uppercase tracking-widest">Payment Status</label>
                <select name="payment_status" id="edit_payment_status" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="Unpaid">Unpaid</option>
                    <option value="Paid">Paid</option>
                    <option value="Partial">Partial</option>
                </select>
            </div>
            <div class="flex gap-2 pt-4">
                <button type="button" onclick="closeEditPaymentModal()" class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-500 text-sm font-bold hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="update_payment" class="flex-1 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700 transition">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
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
