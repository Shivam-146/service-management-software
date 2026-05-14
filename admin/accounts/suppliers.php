<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        $successMsg = "Supplier deleted successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error deleting supplier: " . $e->getMessage();
    }
}

// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['supplier_name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $gst = $_POST['gst_number'];

    if (empty($name)) {
        $errorMsg = "Supplier name is required.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name=?, phone=?, email=?, address=?, gst_number=? WHERE id=?");
                $stmt->execute([$name, $phone, $email, $address, $gst, $id]);
                $successMsg = "Supplier updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, phone, email, address, gst_number) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $email, $address, $gst]);
                $successMsg = "Supplier added successfully!";
            }
        } catch (PDOException $e) {
            $errorMsg = "Error saving supplier: " . $e->getMessage();
        }
    }
}

$suppliers = $pdo->query("
    SELECT s.*, 
        (IFNULL((SELECT SUM(total_amount) FROM purchases WHERE supplier_id = s.id AND payment_method = 'Credit'), 0) - 
         IFNULL((SELECT SUM(amount) FROM vouchers WHERE payee_payer = s.supplier_name AND account_head = 'Supplier Payment'), 0)) as balance
    FROM suppliers s
    ORDER BY s.supplier_name ASC
")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="supplier_payments.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Manage Suppliers</h1>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add Supplier
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Supplier Name</th>
                        <th class="px-6 py-4">Contact</th>
                        <th class="px-6 py-4">GSTIN</th>
                        <th class="px-6 py-4 text-right">Current Balance</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">No suppliers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-800">
                                <?php echo htmlspecialchars($s['supplier_name']); ?>
                                <p class="text-[10px] text-slate-400 font-normal mt-1 truncate max-w-[200px]"><?php echo htmlspecialchars($s['address']); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm text-slate-600"><?php echo htmlspecialchars($s['phone']); ?></p>
                                <p class="text-xs text-slate-400"><?php echo htmlspecialchars($s['email']); ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($s['gst_number'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="px-3 py-1 rounded-full text-xs font-black <?php echo $s['balance'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'; ?>">
                                    ₹<?php echo number_format($s['balance'], 2); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editSupplier(<?php echo json_encode($s); ?>)' class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <a href="?delete=<?php echo $s['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
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
<div id="supplierModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Add New Supplier</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="supplier_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Name *</label>
                    <input type="text" name="supplier_name" id="supplier_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Phone</label>
                    <input type="text" name="phone" id="phone" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email</label>
                    <input type="email" name="email" id="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GST Number</label>
                    <input type="text" name="gst_number" id="gst_number" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Address</label>
                    <textarea name="address" id="address" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('supplierModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Add New Supplier';
    document.getElementById('supplier_id').value = '';
}
function closeModal() {
    document.getElementById('supplierModal').classList.add('hidden');
}
function editSupplier(data) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Supplier';
    document.getElementById('supplier_id').value = data.id;
    document.getElementById('supplier_name').value = data.supplier_name;
    document.getElementById('phone').value = data.phone;
    document.getElementById('email').value = data.email;
    document.getElementById('gst_number').value = data.gst_number;
    document.getElementById('address').value = data.address;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
