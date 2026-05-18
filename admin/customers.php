<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

// Handle Add/Edit Customer Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        $name = trim($_POST['customer_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $gst = trim($_POST['gst_number']);
        $amc = isset($_POST['has_active_amc']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("INSERT INTO customers (customer_name, phone, address, gst_number, has_active_amc) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $phone, $address, $gst, $amc]);
            $successMsg = "Customer added successfully!";
        } catch (PDOException $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_customer'])) {
        $id = (int)$_POST['customer_id'];
        $name = trim($_POST['customer_name']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $gst = trim($_POST['gst_number']);
        $amc = isset($_POST['has_active_amc']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE customers SET customer_name = ?, phone = ?, address = ?, gst_number = ?, has_active_amc = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $address, $gst, $amc, $id]);
            $successMsg = "Customer updated successfully!";
        } catch (PDOException $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';

// Fetch Customers
try {
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY customer_name ASC");
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch History if requested
$historyData = null;
$historyCustomerName = "";
if (isset($_GET['history_id'])) {
    $hid = (int)$_GET['history_id'];
    try {
        $hStmt = $pdo->prepare("SELECT * FROM complaints WHERE customer_id = ? ORDER BY created_at DESC");
        $hStmt->execute([$hid]);
        $historyData = $hStmt->fetchAll();
        
        $nStmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
        $nStmt->execute([$hid]);
        $historyCustomerName = $nStmt->fetchColumn();
    } catch (PDOException $e) {
        $errorMsg = "History Error: " . $e->getMessage();
    }
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Customer Master</h2>
        <button onclick="openCustomerModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Add New Customer</button>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Name</th>
                        <th class="px-6 py-4 font-semibold">Contact</th>
                        <th class="px-6 py-4 font-semibold">Outstanding Due</th>
                        <th class="px-6 py-4 font-semibold">AMC Status</th>
                        <th class="px-6 py-4 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No customers found. Click "Add New Customer" to start.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($customers as $customer): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-6 py-4 text-sm font-medium text-slate-400">#<?php echo $customer['id']; ?></td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($customer['customer_name']); ?></div>
                            <div class="text-xs text-slate-400 truncate max-w-xs"><?php echo htmlspecialchars($customer['address']); ?></div>
                            <?php if (!empty($customer['gst_number'])): ?>
                                <span class="text-[10px] text-slate-500 font-medium">GST: <?php echo htmlspecialchars($customer['gst_number']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?php echo htmlspecialchars($customer['phone']); ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-black <?php echo $customer['due_amount'] > 0 ? 'text-rose-600' : 'text-slate-400'; ?>">
                                ₹<?php echo number_format($customer['due_amount'] ?? 0, 2); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($customer['has_active_amc']): ?>
                                <span class="px-2 py-1 rounded-full text-[10px] font-black uppercase bg-green-100 text-green-700">AMC Active</span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full text-[10px] font-black uppercase bg-slate-100 text-slate-400">No AMC</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex items-center gap-3">
                                <button 
                                    onclick="openCustomerModal({
                                        id: <?php echo $customer['id']; ?>,
                                        name: '<?php echo addslashes($customer['customer_name']); ?>',
                                        phone: '<?php echo addslashes($customer['phone']); ?>',
                                        address: '<?php echo addslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $customer['address'])); ?>',
                                        gst: '<?php echo addslashes($customer['gst_number']); ?>',
                                        amc: <?php echo $customer['has_active_amc']; ?>
                                    })"
                                    class="text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                <a href="?history_id=<?php echo $customer['id']; ?>" class="text-slate-400 hover:text-indigo-600 font-medium transition cursor-pointer">History</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="customerModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Add New Customer</h3>
            <button type="button" onclick="closeCustomerModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" id="customerForm" class="space-y-4">
            <input type="hidden" name="customer_id" id="modal_customer_id">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Customer/Company Name *</label>
                <input type="text" name="customer_name" id="modal_customer_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number *</label>
                <input type="text" name="phone" id="modal_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Full Service Address *</label>
                <textarea name="address" id="modal_address" required rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">GST Number (Optional)</label>
                <input type="text" name="gst_number" id="modal_gst_number" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. 29GGGGG1314R9Z6">
            </div>
            <div class="flex items-start mb-4">
                <div class="flex items-center h-5">
                    <input id="modal_amc" name="has_active_amc" type="checkbox" value="1" class="w-4 h-4 border border-slate-300 rounded bg-slate-50 focus:ring-3 focus:ring-indigo-300">
                </div>
                <label for="modal_amc" class="ml-2 text-sm font-medium text-slate-900">Has Active AMC Contract right now</label>
            </div>
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeCustomerModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" id="modal_submit_btn" name="add_customer" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCustomerModal(data = null) {
    const modal = document.getElementById('customerModal');
    const form = document.getElementById('customerForm');
    const title = modal.querySelector('h3');
    const submitBtn = document.getElementById('modal_submit_btn');

    if (data) {
        title.innerText = 'Edit Customer';
        submitBtn.innerText = 'Update Customer';
        submitBtn.name = 'edit_customer';
        document.getElementById('modal_customer_id').value = data.id;
        document.getElementById('modal_customer_name').value = data.name;
        document.getElementById('modal_phone').value = data.phone;
        document.getElementById('modal_address').value = data.address;
        document.getElementById('modal_gst_number').value = data.gst;
        document.getElementById('modal_amc').checked = data.amc == 1;
    } else {
        title.innerText = 'Add New Customer';
        submitBtn.innerText = 'Save Customer';
        submitBtn.name = 'add_customer';
        form.reset();
        document.getElementById('modal_customer_id').value = '';
    }
    modal.classList.remove('hidden');
}
function closeCustomerModal() {
    document.getElementById('customerModal').classList.add('hidden');
}
</script>

<?php if ($historyData !== null): ?>
<!-- History Modal -->
<div id="historyModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Service History</h3>
                <p class="text-sm font-medium text-slate-500"><?php echo htmlspecialchars($historyCustomerName); ?></p>
            </div>
            <a href="customers.php" class="text-slate-400 hover:text-rose-500 transition cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </a>
        </div>
        <div class="space-y-4">
            <?php if (empty($historyData)): ?>
                <div class="p-8 text-center bg-slate-50 rounded-xl border border-slate-100/50 text-slate-500">No service history found for this customer.</div>
            <?php else: ?>
                <?php foreach ($historyData as $log): ?>
                <div class="border <?php echo $log['status'] === 'Completed' ? 'border-green-100 bg-green-50/30' : 'border-slate-100 bg-slate-50'; ?> rounded-xl p-5 hover:shadow-md transition">
                    <div class="flex justify-between items-center mb-3">
                        <span class="font-black text-slate-800 tracking-tight">Ticket #<?php echo $log['id']; ?></span>
                        <span class="text-[10px] font-black uppercase px-3 py-1 rounded-full <?php echo $log['status'] === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-indigo-100 text-indigo-700'; ?>">
                            <?php echo htmlspecialchars($log['status']); ?>
                        </span>
                    </div>
                    <p class="text-sm text-slate-700 font-medium mb-4 leading-relaxed"><?php echo htmlspecialchars($log['issue_description']); ?></p>
                    <div class="flex justify-between items-center pt-3 border-t border-slate-200/50">
                         <div class="text-[10px] uppercase font-bold text-slate-400">Created: <?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                         <?php if ($log['completed_at']): ?>
                             <div class="text-[10px] uppercase font-bold text-green-600">Resolved: <?php echo date('M j, Y', strtotime($log['completed_at'])); ?></div>
                         <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
