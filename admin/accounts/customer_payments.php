<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        $successMsg = "Payment record deleted successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error deleting payment: " . $e->getMessage();
    }
}

// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $custId = $_POST['customer_id'];
    $invId = $_POST['invoice_id'] ?: null;
    $date = $_POST['payment_date'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $ref = $_POST['reference_no'];
    $notes = $_POST['notes'];

    if (empty($custId) || empty($date) || empty($amount)) {
        $errorMsg = "Please fill all required fields.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE payments SET customer_id=?, invoice_id=?, payment_date=?, amount=?, payment_method=?, reference_no=?, notes=? WHERE id=?");
                $stmt->execute([$custId, $invId, $date, $amount, $method, $ref, $notes, $id]);
                $successMsg = "Payment record updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, reference_no, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$custId, $invId, $date, $amount, $method, $ref, $notes]);
                $successMsg = "Payment recorded successfully!";
            }
            
            // Optional: Update invoice status if fully paid? 
            // For now, keep it simple.
        } catch (PDOException $e) {
            $errorMsg = "Error saving payment: " . $e->getMessage();
        }
    }
}

// Fetch Customers and Invoices for dropdowns
$customers = $pdo->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC")->fetchAll();
$invoices = $pdo->query("SELECT i.id, i.invoice_no, i.grand_total, c.customer_id FROM invoices i JOIN complaints c ON i.complaint_id = c.id ORDER BY i.created_at DESC LIMIT 100")->fetchAll();

// Search and Filter
$search = $_GET['search'] ?? '';
$query = "SELECT p.*, c.customer_name, i.invoice_no 
          FROM payments p 
          JOIN customers c ON p.customer_id = c.id 
          LEFT JOIN invoices i ON p.invoice_id = i.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (c.customer_name LIKE ? OR p.reference_no LIKE ? OR i.invoice_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Pagination
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare(str_replace("SELECT p.*, c.customer_name, i.invoice_no", "SELECT COUNT(*)", $query));
$countStmt->execute($params);
$total_rows = $countStmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Customer Payments</h1>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Record Payment
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Search -->
    <form class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex gap-4 items-end">
        <div class="flex-1">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search Payments</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer, invoice, ref..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Search</button>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Customer</th>
                        <th class="px-6 py-4">Invoice</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4">Ref No.</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">No payment records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y h:i A', strtotime($p['payment_date'])); ?></td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['customer_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-indigo-600 font-medium"><?php echo htmlspecialchars($p['invoice_no'] ?: 'Direct Payment'); ?></td>
                            <td class="px-6 py-4 text-sm font-black text-slate-900">₹<?php echo number_format($p['amount'], 2); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($p['payment_method']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-400 italic"><?php echo htmlspecialchars($p['reference_no'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editPayment(<?php echo json_encode($p); ?>)' class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="paymentModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Record Customer Payment</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="payment_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Customer *</label>
                    <select name="customer_id" id="customer_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Customer</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Invoice (Optional)</label>
                    <select name="invoice_id" id="invoice_id" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Direct / Advanced Payment</option>
                        <?php foreach ($invoices as $i): ?>
                            <option value="<?php echo $i['id']; ?>" data-customer="<?php echo $i['customer_id']; ?>"><?php echo htmlspecialchars($i['invoice_no']); ?> (₹<?php echo number_format($i['grand_total'], 2); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Date & Time *</label>
                    <input type="datetime-local" name="payment_date" id="payment_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" id="payment_amount" required placeholder="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="payment_ref" placeholder="TXN-12345" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Notes</label>
                    <textarea name="notes" id="payment_notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('paymentModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Record Customer Payment';
    document.getElementById('payment_id').value = '';
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('payment_date').value = now.toISOString().slice(0, 16);
}
function closeModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}
function editPayment(data) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Payment Record';
    document.getElementById('payment_id').value = data.id;
    document.getElementById('customer_id').value = data.customer_id;
    document.getElementById('invoice_id').value = data.invoice_id;
    document.getElementById('payment_date').value = data.payment_date.replace(' ', 'T').slice(0, 16);
    document.getElementById('payment_amount').value = data.amount;
    document.getElementById('payment_method').value = data.payment_method;
    document.getElementById('payment_ref').value = data.reference_no;
    document.getElementById('payment_notes').value = data.notes;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
