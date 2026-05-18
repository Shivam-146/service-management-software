<?php
require_once '../../includes/db_connect.php';

$successMsg = '';
$errorMsg = '';

// Handle Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $purchaseId = $_POST['purchase_id'];
    $returnDate = $_POST['return_date'];
    $notes = $_POST['notes'];
    $items = $_POST['items']; // Array of item_id => quantity_to_return

    try {
        $pdo->beginTransaction();

        // 1. Fetch Purchase Info
        $purStmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
        $purStmt->execute([$purchaseId]);
        $purchase = $purStmt->fetch();

        if (!$purchase) throw new Exception("Purchase record not found.");

        $totalReturnAmount = 0;
        $returnItemsData = [];

        foreach ($items as $itemId => $qty) {
            if ($qty <= 0) continue;

            // Fetch Item Info
            $itemStmt = $pdo->prepare("SELECT * FROM purchase_items WHERE id = ?");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();

            if (!$item) continue;

            $itemTotal = $item['unit_price'] * $qty;
            $totalReturnAmount += $itemTotal;

            $returnItemsData[] = [
                'product_id' => $item['product_id'],
                'quantity' => $qty,
                'price' => $item['unit_price'],
                'total' => $itemTotal
            ];

            // 2. Update Stock (Decrease)
            $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?")
                ->execute([$qty, $item['product_id']]);

            // Note: If serials were recorded for this purchase, we'd need to find them and mark as 'Returned'.
            // For now, we'll mark them as 'Defective' or 'Returned' if they are still 'In Stock'.
        }

        if ($totalReturnAmount > 0) {
            // 3. Insert Return Record
            $retStmt = $pdo->prepare("INSERT INTO purchase_returns (purchase_id, supplier_id, return_date, total_amount, notes) VALUES (?, ?, ?, ?, ?)");
            $retStmt->execute([$purchaseId, $purchase['supplier_id'], $returnDate, $totalReturnAmount, $notes]);
            $returnId = $pdo->lastInsertId();

            // 4. Insert Return Items
            $itemIns = $pdo->prepare("INSERT INTO purchase_return_items (return_id, product_id, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
            foreach ($returnItemsData as $ri) {
                $itemIns->execute([$returnId, $ri['product_id'], $ri['quantity'], $ri['price'], $ri['total']]);
            }
        }

        $pdo->commit();
        $successMsg = "Purchase return recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch Returns
$returns = $pdo->query("SELECT r.*, p.bill_no, s.supplier_name 
    FROM purchase_returns r 
    JOIN purchases p ON r.purchase_id = p.id 
    JOIN suppliers s ON r.supplier_id = s.id 
    ORDER BY r.return_date DESC LIMIT 50")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Purchase Returns</h1>
        </div>
        <button onclick="document.getElementById('return-modal').classList.remove('hidden')" class="bg-violet-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-violet-700 transition">Record New Return</button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-lg"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Returns List -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Date</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Return ID</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Bill No</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Supplier</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($returns as $r): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y', strtotime($r['return_date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">PRET-<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($r['bill_no'] ?: 'N/A'); ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-slate-700"><?php echo htmlspecialchars($r['supplier_name']); ?></td>
                    <td class="px-6 py-4 text-sm font-black text-rose-600 text-right">₹<?php echo number_format($r['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($returns)): ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">No returns found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Return Modal -->
<div id="return-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Record Purchase Return</h2>
            <button onclick="document.getElementById('return-modal').classList.add('hidden')" class="p-2 hover:bg-slate-100 rounded-full transition text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <div class="p-8 overflow-y-auto">
            <div class="mb-8">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Search Bill Number</label>
                <div class="flex gap-2">
                    <input type="text" id="bill-search" placeholder="e.g. BILL-123..." class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-violet-500">
                    <button onclick="fetchPurchaseItems()" class="bg-slate-800 text-white px-6 py-3 rounded-xl font-bold hover:bg-slate-900 transition">Fetch Items</button>
                </div>
            </div>

            <form id="return-form" method="POST" class="hidden space-y-6">
                <input type="hidden" name="purchase_id" id="form-purchase-id">
                
                <div id="purchase-info-banner" class="bg-violet-50 border border-violet-100 p-4 rounded-2xl mb-6 flex justify-between items-center">
                    <div>
                        <p id="info-supplier" class="text-sm font-bold text-violet-900"></p>
                        <p id="info-date" class="text-[10px] font-bold text-violet-400 uppercase"></p>
                    </div>
                    <div class="text-right">
                        <p id="info-method" class="text-[10px] font-black uppercase text-violet-500 bg-white px-2 py-1 rounded inline-block mb-1"></p>
                        <p id="info-total" class="text-lg font-black text-violet-900"></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Unit Price</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Purchased Qty</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase w-32">Return Qty</th>
                            </tr>
                        </thead>
                        <tbody id="items-table-body" class="divide-y divide-slate-50">
                            <!-- Items will be injected here -->
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Return Date</label>
                        <input type="date" name="return_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-violet-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Notes</label>
                        <input type="text" name="notes" placeholder="Reason for return..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-violet-500">
                    </div>
                </div>

                <div class="flex justify-end pt-6 border-t border-slate-100">
                    <button type="submit" name="submit_return" class="bg-violet-600 text-white px-10 py-4 rounded-2xl font-bold hover:bg-violet-700 transition shadow-lg shadow-violet-200">Confirm Return</button>
                </div>
            </form>
            
            <div id="no-results" class="hidden py-12 text-center text-slate-400 italic">No purchase record found with that bill number.</div>
        </div>
    </div>
</div>

<script>
async function fetchPurchaseItems() {
    const billNo = document.getElementById('bill-search').value;
    if (!billNo) return;

    const response = await fetch('ajax_get_purchase.php?bill_no=' + billNo);
    const data = await response.json();

    if (data.success) {
        document.getElementById('return-form').classList.remove('hidden');
        document.getElementById('no-results').classList.add('hidden');
        document.getElementById('form-purchase-id').value = data.purchase.id;
        
        document.getElementById('info-supplier').innerText = data.purchase.supplier_name;
        document.getElementById('info-date').innerText = 'Purchase Date: ' + data.purchase.purchase_date;
        document.getElementById('info-method').innerText = data.purchase.payment_method;
        document.getElementById('info-total').innerText = '₹' + parseFloat(data.purchase.total_amount).toLocaleString();

        const tbody = document.getElementById('items-table-body');
        tbody.innerHTML = '';
        data.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3 text-sm font-bold text-slate-800">${item.product_name}</td>
                <td class="px-4 py-3 text-sm font-medium text-slate-600">₹${parseFloat(item.unit_price).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-slate-400">${item.quantity}</td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${item.id}]" max="${item.quantity}" min="0" value="0" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-violet-500">
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        document.getElementById('return-form').classList.add('hidden');
        document.getElementById('no-results').classList.remove('hidden');
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
