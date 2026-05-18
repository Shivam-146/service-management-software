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

// Handle Delete Serial
if (isset($_POST['delete_serial'])) {
    $id = $_POST['serial_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM product_serials WHERE id = ?");
        $stmt->execute([$id]);
        $successMsg = "Serial number deleted successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error deleting serial number.";
    }
}

// Handle Edit Serial
if (isset($_POST['edit_serial'])) {
    $id = $_POST['serial_id'];
    $sn = trim($_POST['serial_number']);
    $price = $_POST['purchase_price'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE product_serials SET serial_number = ?, purchase_price = ?, status = ? WHERE id = ?");
        $stmt->execute([$sn, $price, $status, $id]);
        $successMsg = "Serial number updated successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error updating serial number (possibly duplicate).";
    }
}

// Handle Add Serials (Manual)
if (isset($_POST['add_serials'])) {
    $purchase_id = $_POST['purchase_id'] ?: null;
    $serial_groups = $_POST['sn_text'] ?? [];
    $group_prices = $_POST['group_prices'] ?? [];
    $manual_product_id = $_POST['product_id'] ?? null;
    $bill_product_ids = $_POST['bill_product_ids'] ?? []; // Array if from bill
    
    $added = 0;
    $errors = 0;
    
    foreach ($serial_groups as $index => $text) {
        $price = $group_prices[$index] ?? 0;
        $pid = !empty($bill_product_ids[$index]) ? $bill_product_ids[$index] : $manual_product_id;
        
        if (!$pid) continue;

        $serials = explode("\n", str_replace("\r", "", $text));
        foreach ($serials as $sn) {
            $sn = trim($sn);
            if (empty($sn)) continue;
            try {
                $stmt = $pdo->prepare("INSERT INTO product_serials (product_id, purchase_id, serial_number, purchase_price, status) VALUES (?, ?, ?, ?, 'In Stock')");
                $stmt->execute([$pid, $purchase_id, $sn, $price]);
                $added++;
            } catch (PDOException $e) {
                $errors++;
            }
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
$recent_purchases = $pdo->query("SELECT id, bill_no, purchase_date FROM purchases WHERE is_inventory = 1 ORDER BY purchase_date DESC LIMIT 20")->fetchAll();

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
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold border-b border-slate-100">
                        <th class="px-6 py-4">Product / Serial</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Cost</th>
                        <th class="px-6 py-4 text-right">Sale</th>
                        <th class="px-6 py-4 text-right">Profit</th>
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($serials)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400 italic">No serial numbers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($serials as $s): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($s['serial_number']); ?></p>
                                <p class="text-[10px] text-slate-400 uppercase font-bold"><?php echo htmlspecialchars($s['product_name']); ?> (<?php echo htmlspecialchars($s['product_code']); ?>)</p>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $status_classes = [
                                        'In Stock' => 'bg-emerald-100 text-emerald-700',
                                        'Sold' => 'bg-blue-100 text-blue-700',
                                        'Defective' => 'bg-rose-100 text-rose-700',
                                        'Returned' => 'bg-slate-100 text-slate-700'
                                    ];
                                    $cls = $status_classes[$s['status']] ?? 'bg-slate-100 text-slate-700';
                                ?>
                                <span class="px-2 py-1 <?php echo $cls; ?> rounded text-[10px] font-black uppercase tracking-wider"><?php echo $s['status']; ?></span>
                                <?php if ($s['status'] === 'Sold'): ?>
                                    <p class="text-[9px] text-slate-400 mt-1 italic"><?php echo date('d-m-Y', strtotime($s['sold_at'])); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-slate-500">₹<?php echo number_format($s['purchase_price'], 2); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-indigo-600">
                                <?php echo ($s['status'] === 'Sold') ? '₹' . number_format($s['sale_price'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($s['status'] === 'Sold'): 
                                    $profit = $s['sale_price'] - $s['purchase_price'];
                                    $profitCls = ($profit >= 0) ? 'text-emerald-600' : 'text-rose-600';
                                ?>
                                    <span class="text-sm font-black <?php echo $profitCls; ?>">₹<?php echo number_format($profit, 2); ?></span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-rose-100 text-rose-600 rounded text-[10px] font-black uppercase"><?php echo $s['status']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editSerial(<?php echo json_encode($s); ?>)' class="p-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this serial number?')">
                                        <input type="hidden" name="serial_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="delete_serial" value="1">
                                        <button type="submit" class="p-2 bg-rose-50 text-rose-600 rounded-lg hover:bg-rose-100 transition" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="serial_id" value="<?php echo $s['id']; ?>">
                                        <select name="status" onchange="this.form.submit()" class="bg-slate-50 border border-slate-200 rounded-lg text-[10px] font-bold px-2 py-1.5 outline-none hover:border-indigo-300 transition">
                                            <option value="In Stock" <?php echo $s['status'] === 'In Stock' ? 'selected' : ''; ?>>In Stock</option>
                                            <option value="Sold" <?php echo $s['status'] === 'Sold' ? 'selected' : ''; ?>>Sold</option>
                                            <option value="Defective" <?php echo $s['status'] === 'Defective' ? 'selected' : ''; ?>>Defective</option>
                                            <option value="Returned" <?php echo $s['status'] === 'Returned' ? 'selected' : ''; ?>>Returned</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
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
<div id="serialModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50 flex-shrink-0">
            <h2 class="text-xl font-bold text-slate-800">Bulk Add Serial Numbers</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="flex-1 overflow-y-auto p-6 space-y-6">
            <input type="hidden" name="add_serials" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Link to Bill (Optional)</label>
                    <select id="bill_select" name="purchase_id" onchange="loadBillItems(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">No Bill / Opening Stock</option>
                        <?php foreach ($recent_purchases as $rp): ?>
                            <option value="<?php echo $rp['id']; ?>"><?php echo htmlspecialchars($rp['bill_no'] ?: 'ID: '.$rp['id']); ?> (<?php echo date('d M', strtotime($rp['purchase_date'])); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="product-selection-container" class="mt-4">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Target Product *</label>
                <select id="manual_product_select" name="product_id" onchange="loadProductPrice(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Product</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-4 pt-4 border-t border-slate-100 mt-6">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Serial Groups (Batch Entry)</label>
                <div id="sn-groups-container" class="space-y-6 pr-2 pb-4">
                    <div class="p-5 bg-slate-50 border border-slate-200 rounded-none sn-group relative group/sn">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <div class="md:col-span-8">
                                <label class="group-label block text-[9px] font-black text-slate-400 uppercase mb-1">Serial Numbers (One per line)</label>
                                <textarea name="sn_text[]" rows="4" required placeholder="SN1001&#10;SN1002&#10;SN1003" class="w-full bg-white border border-slate-200 rounded-none px-3 py-2 text-sm font-mono outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Unit Selling Price</label>
                                <input type="number" step="0.01" name="group_prices[]" required placeholder="0.00" class="w-full bg-white border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <p class="text-[9px] text-slate-400 mt-2 italic">This price applies to this box.</p>
                            </div>
                        </div>
                        <button type="button" onclick="removeSnGroup(this)" class="remove-grp-btn hidden absolute -right-3 -top-3 bg-rose-500 text-white p-1.5 rounded-none hover:bg-rose-600 transition shadow-lg z-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                <button type="button" onclick="addSnGroup()" class="w-full py-2 border border-dashed border-slate-300 text-[10px] font-bold text-slate-500 hover:bg-slate-50 uppercase tracking-widest transition">+ Add Another Price Group</button>
            </div>

            <div class="flex gap-3 pt-6 border-t border-slate-100 sticky bottom-0 bg-white">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition shadow-none">Save All Serials</button>
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
function addSnGroup() {
    const container = document.getElementById('sn-groups-container');
    const firstGroup = container.querySelector('.sn-group');
    const newGroup = firstGroup.cloneNode(true);
    newGroup.querySelector('textarea').value = '';
    newGroup.querySelector('input').value = '';
    newGroup.querySelector('.group-label').innerText = 'Serial Numbers (One per line)';
    newGroup.querySelector('.remove-grp-btn').classList.remove('hidden');
    container.appendChild(newGroup);
}

async function loadBillItems(purchaseId) {
    const container = document.getElementById('sn-groups-container');
    const productSelection = document.getElementById('product-selection-container');
    
    if (!purchaseId) {
        productSelection.classList.remove('hidden');
        return;
    }

    // Hide product selection as we'll load them from bill
    productSelection.classList.add('hidden');
    container.innerHTML = '<p class="text-xs text-indigo-600 animate-pulse">Loading bill items...</p>';

    try {
        const response = await fetch(`../accounts/ajax_get_bill_items.php?purchase_id=${purchaseId}`);
        const items = await response.json();
        
        container.innerHTML = '';
        if (items && items.length > 0) {
            items.forEach(item => {
                const groupDiv = document.createElement('div');
                groupDiv.className = 'p-4 bg-slate-50 border border-slate-200 rounded-none sn-group relative';
                groupDiv.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-8">
                            <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">
                                <span class="text-indigo-600">${item.item_name}</span> - Enter ${item.qty} Serials
                            </label>
                            <input type="hidden" name="bill_product_ids[]" value="${item.product_id}">
                            <textarea name="sn_text[]" rows="3" required placeholder="Paste ${item.qty} serials..." class="w-full bg-white border border-slate-200 rounded-none px-3 py-2 text-sm font-mono outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                        </div>
                        <div class="md:col-span-4">
                            <label class="block text-[9px] font-black text-slate-400 uppercase mb-1">Purchase Price</label>
                            <input type="number" step="0.01" name="group_prices[]" value="${item.unit_price}" readonly class="w-full bg-slate-100 border border-slate-200 rounded-none px-3 py-2 text-sm text-slate-500 outline-none">
                            <p class="text-[9px] text-slate-400 mt-2 italic">Auto-filled from bill.</p>
                        </div>
                    </div>
                `;
                container.appendChild(groupDiv);
            });
        } else {
            container.innerHTML = '<p class="text-xs text-rose-500">No products found in this bill.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p class="text-xs text-rose-500">Error loading items.</p>';
    }
}
async function loadProductPrice(productId) {
    if (!productId) return;
    try {
        const response = await fetch(`../accounts/ajax_get_product_price.php?product_id=${productId}`);
        const data = await response.json();
        const priceInputs = document.querySelectorAll('input[name="group_prices[]"]');
        if (priceInputs.length > 0 && !priceInputs[0].value) {
            priceInputs[0].value = data.price;
        }
    } catch (e) {
        console.error('Error loading price:', e);
    }
}

function removeSnGroup(btn) {
    btn.closest('.sn-group').remove();
}
function editSerial(data) {
    document.getElementById('edit_serial_id').value = data.id;
    document.getElementById('edit_sn').value = data.serial_number;
    document.getElementById('edit_price').value = data.purchase_price;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<!-- Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h2 class="text-xl font-bold text-slate-800">Edit Serial Number</h2>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="edit_serial" value="1">
            <input type="hidden" name="serial_id" id="edit_serial_id">
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Serial Number</label>
                <input type="text" name="serial_number" id="edit_sn" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Purchase Price (₹)</label>
                <input type="number" step="0.01" name="purchase_price" id="edit_price" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Status</label>
                <select name="status" id="edit_status" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="In Stock">In Stock</option>
                    <option value="Sold">Sold</option>
                    <option value="Defective">Defective</option>
                    <option value="Returned">Returned</option>
                </select>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
