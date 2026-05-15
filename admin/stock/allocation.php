<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Allocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate'])) {
    $technicianId = $_POST['technician_id'];
    $serializedIds = $_POST['serial_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? []; // product_id => qty

    try {
        $pdo->beginTransaction();

        // 1. Allocate Serialized Items
        if (!empty($serializedIds)) {
            $placeholders = implode(',', array_fill(0, count($serializedIds), '?'));
            $stmt = $pdo->prepare("UPDATE product_serials SET technician_id = ?, allocated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders) AND status = 'In Stock'");
            $params = array_merge([$technicianId], $serializedIds);
            $stmt->execute($params);
            
            // Record Movement for each serial
            foreach ($serializedIds as $sid) {
                $sInfo = $pdo->query("SELECT serial_number FROM product_serials WHERE id = $sid")->fetch();
                $move = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) 
                                       SELECT product_id, 'Stock Out', 1, CONCAT('Allotted SN: ', ?, ' to Tech ID: ', ?) 
                                       FROM product_serials WHERE id = ?");
                $move->execute([$sInfo['serial_number'], $technicianId, $sid]);
            }
        }

        // 2. Allocate Quantities (Non-serialized)
        foreach ($quantities as $productId => $qty) {
            if ($qty <= 0) continue;

            // Check if product exists and has enough stock
            $pStmt = $pdo->prepare("SELECT current_stock, product_name FROM products WHERE id = ?");
            $pStmt->execute([$productId]);
            $product = $pStmt->fetch();

            if ($product['current_stock'] < $qty) {
                throw new Exception("Insufficient stock for product: " . $product['product_name']);
            }

            // Decrement main stock
            $dec = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
            $dec->execute([$qty, $productId]);

            // Increment or Create technician stock
            $check = $pdo->prepare("SELECT id FROM technician_stock WHERE technician_id = ? AND product_id = ?");
            $check->execute([$technicianId, $productId]);
            $existing = $check->fetch();

            if ($existing) {
                $upd = $pdo->prepare("UPDATE technician_stock SET quantity = quantity + ? WHERE id = ?");
                $upd->execute([$qty, $existing['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO technician_stock (technician_id, product_id, quantity) VALUES (?, ?, ?)");
                $ins->execute([$technicianId, $productId, $qty]);
            }

            // Record Movement
            $move = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock Out', ?, ?)");
            $move->execute([$productId, $qty, "Allotted quantity to Tech ID: $technicianId"]);
        }

        $pdo->commit();
        $successMsg = "Inventory allocated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Allocation failed: " . $e->getMessage();
    }
}

// Fetch Data
$technicians = $pdo->query("SELECT id, fullname FROM users WHERE role = 'technician' AND status = 1")->fetchAll();
$products = $pdo->query("SELECT id, product_name, current_stock FROM products WHERE current_stock > 0 ORDER BY product_name ASC")->fetchAll();
$availableSerials = $pdo->query("SELECT s.*, p.product_name FROM product_serials s JOIN products p ON s.product_id = p.id WHERE s.status = 'In Stock' AND s.technician_id IS NULL ORDER BY p.product_name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Inventory Allocation</h1>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <input type="hidden" name="allocate" value="1">
        
        <!-- Left: Technician Selection -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-4">Select Technician</h3>
                <select name="technician_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Choose Technician...</option>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['fullname']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-400 mt-2 italic uppercase font-bold tracking-widest">Inventory will be transferred to this person</p>
            </div>

            <div class="bg-slate-900 p-6 rounded-3xl shadow-xl shadow-slate-200">
                <h3 class="text-lg font-bold text-white mb-2">Allocation Summary</h3>
                <div id="allocation-summary" class="text-slate-400 text-sm space-y-2">
                    <p class="italic text-xs">No items selected yet...</p>
                </div>
                <button type="submit" class="w-full mt-6 bg-indigo-600 text-white py-4 rounded-2xl font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-500/30">Confirm Allocation</button>
            </div>
        </div>

        <!-- Right: Inventory Selection -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Serialized Items -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-50">
                    <h3 class="font-bold text-slate-800">Serialized Items (Select SN)</h3>
                </div>
                <div class="max-h-64 overflow-y-auto p-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                    <?php if (empty($availableSerials)): ?>
                        <p class="col-span-2 text-slate-400 text-center py-8 italic text-sm">No serial numbers available in store.</p>
                    <?php else: ?>
                        <?php foreach ($availableSerials as $s): ?>
                            <label class="flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-xl cursor-pointer hover:border-indigo-300 transition">
                                <input type="checkbox" name="serial_ids[]" value="<?php echo $s['id']; ?>" class="w-4 h-4 rounded text-indigo-600" onchange="updateSummary()">
                                <div>
                                    <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($s['serial_number']); ?></p>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold"><?php echo htmlspecialchars($s['product_name']); ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Non-Serialized Items -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-50">
                    <h3 class="font-bold text-slate-800">Non-Serialized Quantities</h3>
                </div>
                <div class="p-4">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] text-slate-400 uppercase font-black">
                                <th class="pb-4">Product Name</th>
                                <th class="pb-4 text-center">Available</th>
                                <th class="pb-4 text-right">Allot Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="py-3 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['product_name']); ?></td>
                                    <td class="py-3 text-center text-sm text-slate-500"><?php echo $p['current_stock']; ?></td>
                                    <td class="py-3 text-right">
                                        <input type="number" name="quantities[<?php echo $p['id']; ?>]" step="0.01" min="0" max="<?php echo $p['current_stock']; ?>" placeholder="0.00" class="w-24 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-right text-sm outline-none focus:ring-2 focus:ring-indigo-500" oninput="updateSummary()">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function updateSummary() {
    const summary = document.getElementById('allocation-summary');
    const checkboxes = document.querySelectorAll('input[name="serial_ids[]"]:checked');
    const qtyInputs = document.querySelectorAll('input[name^="quantities"]');
    
    let html = '<ul class="space-y-2">';
    let count = 0;

    checkboxes.forEach(cb => {
        const label = cb.closest('label');
        const sn = label.querySelector('p:first-child').innerText;
        html += `<li class="flex justify-between text-xs text-indigo-300"><span>SN: ${sn}</span><span>1 Unit</span></li>`;
        count++;
    });

    qtyInputs.forEach(input => {
        const val = parseFloat(input.value) || 0;
        if (val > 0) {
            const productName = input.closest('tr').querySelector('td:first-child').innerText;
            html += `<li class="flex justify-between text-xs text-slate-300"><span>${productName}</span><span>${val.toFixed(2)} Units</span></li>`;
            count++;
        }
    });

    html += '</ul>';
    summary.innerHTML = count > 0 ? html : '<p class="italic text-xs">No items selected yet...</p>';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
