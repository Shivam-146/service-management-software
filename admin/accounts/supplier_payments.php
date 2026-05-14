<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Add Supplier (Quick Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_add_supplier'])) {
    $name = $_POST['supplier_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $gst = $_POST['gst_number'];

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, gst_number) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $email, $gst]);
            $successMsg = "Supplier '$name' added successfully!";
        } catch (PDOException $e) {
            $errorMsg = "Error adding supplier: " . $e->getMessage();
        }
    }
}

// Handle Add/Update Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['quick_add_supplier'])) {
    $id = $_POST['id'] ?? null;
    $type = 'Payment';
    $date = $_POST['voucher_date'];
    $head = 'Supplier Payment';
    $payee = $_POST['payee_payer'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $ref = $_POST['reference_no'];
    $narration = $_POST['narration'];

    $bank_name = $_POST['bank_name'] ?? null;
    $cheque_no = $_POST['cheque_no'] ?? null;
    $cheque_date = ($_POST['cheque_date'] ?? null) ?: null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE vouchers SET voucher_date=?, payee_payer=?, amount=?, payment_method=?, reference_no=?, narration=?, bank_name=?, cheque_no=?, cheque_date=? WHERE id=? AND account_head=?");
            $stmt->execute([$date, $payee, $amount, $method, $ref, $narration, $bank_name, $cheque_no, $cheque_date, $id, $head]);
            $successMsg = "Supplier payment updated successfully!";
        } else {
            // Generate PV No
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT voucher_no FROM vouchers WHERE voucher_type='Payment' AND voucher_no LIKE 'PV-$year-%' ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $last = $stmt->fetchColumn();
            if ($last) {
                $parts = explode('-', $last);
                $num = (int)end($parts) + 1;
            } else {
                $num = 1;
            }
            $vNo = "PV-$year-" . str_pad($num, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO vouchers (voucher_no, voucher_type, voucher_date, account_head, payee_payer, amount, payment_method, reference_no, narration, bank_name, cheque_no, cheque_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$vNo, $type, $date, $head, $payee, $amount, $method, $ref, $narration, $bank_name, $cheque_no, $cheque_date]);
            $successMsg = "Supplier payment recorded successfully!";
        }
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch Suppliers
$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC")->fetchAll();

// Search and Filter
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM vouchers WHERE voucher_type = 'Payment' AND account_head = 'Supplier Payment'";
$params = [];

if ($search) {
    $query .= " AND (payee_payer LIKE ? OR voucher_no LIKE ? OR reference_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Pagination
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $query));
$countStmt->execute($params);
$total_rows = $countStmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query .= " ORDER BY voucher_date DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-none text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Supplier Payments</h1>
        </div>
        <div class="flex gap-3">
            <a href="suppliers.php" class="bg-white border border-slate-200 text-slate-600 px-6 py-3 rounded-none font-bold hover:bg-slate-50 transition shadow-sm flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                Suppliers
            </a>
            <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-none font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Record Payment
            </button>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-none"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-none"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Search -->
    <form class="bg-white p-4 rounded-none border border-slate-100 shadow-sm flex gap-4 items-end">
        <div class="flex-1">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search Payments</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search supplier, ref..." class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-none text-sm font-bold hover:bg-slate-900 transition">Search</button>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-none border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Supplier</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4">Ref No.</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400">No supplier payments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y h:i A', strtotime($p['voucher_date'])); ?></td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['payee_payer']); ?></td>
                            <td class="px-6 py-4 text-sm font-black text-rose-600">₹<?php echo number_format($p['amount'], 2); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($p['payment_method']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-400 italic"><?php echo htmlspecialchars($p['reference_no'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="view_voucher.php?id=<?php echo $p['id']; ?>" target="_blank" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-none transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    </a>
                                    <button onclick='editPayment(<?php echo json_encode($p); ?>)' class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-none transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
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
    <div class="bg-white rounded-none shadow-2xl w-full max-w-xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Record Supplier Payment</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="payment_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier *</label>
                    <div class="flex gap-2">
                        <select name="payee_payer" id="payee_payer" required class="flex-1 bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?php echo htmlspecialchars($s['supplier_name']); ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="openAddSupplierModal()" class="px-4 bg-white border border-slate-200 text-slate-500 rounded-none hover:bg-slate-50 hover:text-indigo-600 transition shadow-sm" title="Add New Supplier">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Date & Time *</label>
                    <input type="datetime-local" name="voucher_date" id="voucher_date" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" id="payment_amount" required placeholder="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                    <select name="payment_method" id="payment_method" onchange="toggleChequeFields(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reference No (TXN ID)</label>
                    <input type="text" name="reference_no" id="payment_ref" placeholder="TXN-12345" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Cheque Details (Conditional) -->
                <div id="cheque-details-section" class="hidden md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-slate-50">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bank Name</label>
                        <input type="text" name="bank_name" id="bank_name" placeholder="SBI, HDFC..." class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cheque No</label>
                        <input type="text" name="cheque_no" id="cheque_no" placeholder="123456" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cheque Date</label>
                        <input type="date" name="cheque_date" id="cheque_date" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Narration</label>
                    <textarea name="narration" id="payment_narration" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('paymentModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Record Supplier Payment';
    document.getElementById('payment_id').value = '';
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('voucher_date').value = now.toISOString().slice(0, 16);
}
function closeModal() {
    document.getElementById('paymentModal').classList.add('hidden');
}
function editPayment(data) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Payment Record';
    document.getElementById('payment_id').value = data.id;
    document.getElementById('payee_payer').value = data.payee_payer;
    document.getElementById('voucher_date').value = data.voucher_date.replace(' ', 'T').slice(0, 16);
    document.getElementById('payment_amount').value = data.amount;
    document.getElementById('payment_method').value = data.payment_method;
    document.getElementById('payment_ref').value = data.reference_no;
    document.getElementById('payment_narration').value = data.narration;
    
    // Cheque Details
    document.getElementById('bank_name').value = data.bank_name || '';
    document.getElementById('cheque_no').value = data.cheque_no || '';
    document.getElementById('cheque_date').value = data.cheque_date || '';
    toggleChequeFields(data.payment_method);
}

function toggleChequeFields(method) {
    const chequeSec = document.getElementById('cheque-details-section');
    if (method === 'Cheque') {
        chequeSec.classList.remove('hidden');
    } else {
        chequeSec.classList.add('hidden');
    }
}

function openAddSupplierModal() {
    document.getElementById('addSupplierModal').classList.remove('hidden');
}
function closeAddSupplierModal() {
    document.getElementById('addSupplierModal').classList.add('hidden');
}
</script>

<!-- Quick Add Supplier Modal -->
<div id="addSupplierModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[110] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-2xl w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-lg font-bold text-slate-800">Quick Add Supplier</h2>
            <button onclick="closeAddSupplierModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="quick_add_supplier" value="1">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Name *</label>
                <input type="text" name="supplier_name" required placeholder="Vendor Name" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Phone</label>
                <input type="text" name="phone" placeholder="Contact No" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GSTIN</label>
                    <input type="text" name="gst_number" placeholder="Optional" class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email</label>
                    <input type="email" name="email" placeholder="Optional" class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <button type="button" onclick="closeAddSupplierModal()" class="flex-1 py-2.5 bg-slate-100 text-slate-600 rounded-none font-bold text-sm hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-indigo-600 text-white rounded-none font-bold text-sm hover:bg-indigo-700 transition">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
