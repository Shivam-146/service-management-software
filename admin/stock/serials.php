<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Status Update
if (isset($_POST['update_status'])) {
    $id = $_POST['serial_id'];
    $status = $_POST['status'];
    try {
        $stmt = $pdo->prepare("UPDATE product_serials SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $successMsg = "Serial number status updated!";
    } catch (PDOException $e) {
        $errorMsg = "Error updating status.";
    }
}

// Handle Add Serials (Manual)
if (isset($_POST['add_serials'])) {
    $product_id = $_POST['product_id'];
    $serials_text = $_POST['serial_numbers']; // Textarea
    $serials = explode("\n", str_replace("\r", "", $serials_text));
    
    $added = 0;
    $errors = 0;
    
    foreach ($serials as $sn) {
        $sn = trim($sn);
        if (empty($sn)) continue;
        try {
            $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, serial_number, status) VALUES (?, ?, 'In Stock')");
            $stmt->execute([$product_id, $sn]);
            $added++;
        } catch (PDOException $e) {
            $errors++;
        }
    }
    
    if ($added > 0) $successMsg = "Added $added serial numbers successfully!";
    if ($errors > 0) $errorMsg = "Failed to add $errors serial numbers (possibly duplicate).";
}

// Search and Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT s.*, p.product_name, p.product_code 
          FROM product_serials s 
          JOIN products p ON s.product_id = p.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (s.serial_number LIKE ? OR p.product_name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status_filter) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY s.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$serials = $stmt->fetchAll();

$products = $pdo->query("SELECT id, product_name FROM products ORDER BY product_name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Serial Number Tracking</h1>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add Serials
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Search & Filters -->
    <form class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search Serials</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Serial No, Product..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="w-48">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Status</label>
            <select name="status" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Status</option>
                <option value="In Stock" <?php echo $status_filter === 'In Stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="Sold" <?php echo $status_filter === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                <option value="Defective" <?php echo $status_filter === 'Defective' ? 'selected' : ''; ?>>Defective</option>
                <option value="Returned" <?php echo $status_filter === 'Returned' ? 'selected' : ''; ?>>Returned</option>
            </select>
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Filter</button>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Serial Number</th>
                        <th class="px-6 py-4">Product Name</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Created At</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($serials)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">No serial numbers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($serials as $s): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-black text-slate-900"><?php echo htmlspecialchars($s['serial_number']); ?></td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($s['product_name']); ?></p>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tighter"><?php echo htmlspecialchars($s['product_code']); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($s['status'] === 'In Stock'): ?>
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-600 rounded text-[10px] font-black uppercase">In Stock</span>
                                <?php elseif ($s['status'] === 'Sold'): ?>
                                    <span class="px-2 py-1 bg-indigo-100 text-indigo-600 rounded text-[10px] font-black uppercase">Sold</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-rose-100 text-rose-600 rounded text-[10px] font-black uppercase"><?php echo $s['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="serial_id" value="<?php echo $s['id']; ?>">
                                    <select name="status" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded text-[10px] font-bold px-2 py-1 outline-none">
                                        <option value="In Stock" <?php echo $s['status'] === 'In Stock' ? 'selected' : ''; ?>>In Stock</option>
                                        <option value="Sold" <?php echo $s['status'] === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                                        <option value="Defective" <?php echo $s['status'] === 'Defective' ? 'selected' : ''; ?>>Defective</option>
                                        <option value="Returned" <?php echo $s['status'] === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
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
<div id="serialModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Add Serial Numbers</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_serials" value="1">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Select Product *</label>
                <select name="product_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Serial Numbers (One per line) *</label>
                <textarea name="serial_numbers" rows="8" required placeholder="SN123456&#10;SN789012" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">Save Serials</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('serialModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('serialModal').classList.add('hidden');
}
</script>

<?php require_once '../../includes/footer.php'; ?>
