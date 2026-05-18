<?php
require_once '../../includes/db_connect.php';

$successMsg = '';
$errorMsg = '';

// Handle Return Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return'])) {
    $invoiceId = $_POST['invoice_id'];
    $returnDate = $_POST['return_date'];
    $notes = $_POST['notes'];
    $items = $_POST['items']; // Array of item_id => quantity_to_return

    try {
        $pdo->beginTransaction();

        // 1. Fetch Invoice Info
        $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $invStmt->execute([$invoiceId]);
        $invoice = $invStmt->fetch();

        if (!$invoice) throw new Exception("Invoice not found.");

        $totalReturnAmount = 0;
        $returnItemsData = [];

        foreach ($items as $itemId => $qty) {
            if ($qty <= 0) continue;

            // Fetch Item Info
            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE id = ?");
            $itemStmt->execute([$itemId]);
            $item = $itemStmt->fetch();

            if (!$item) continue;

            $itemTotal = $item['unit_price'] * $qty;
            $totalReturnAmount += $itemTotal;

            $returnItemsData[] = [
                'product_id' => $item['product_id'],
                'serial_number' => $item['serial_number'] ?? null,
                'quantity' => $qty,
                'price' => $item['unit_price'],
                'total' => $itemTotal
            ];

            // 2. Update Stock
            $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?")
                ->execute([$qty, $item['product_id']]);

            // 3. Update Serials if applicable
            if (!empty($item['serial_number'])) {
                $pdo->prepare("UPDATE product_serials SET status = 'Available' WHERE serial_number = ?")
                    ->execute([$item['serial_number']]);
            }
        }

        if ($totalReturnAmount > 0) {
            // 4. Insert Return Record
            $retStmt = $pdo->prepare("INSERT INTO sales_returns (invoice_id, customer_id, return_date, total_amount, notes) VALUES (?, ?, ?, ?, ?)");
            $retStmt->execute([$invoiceId, $invoice['customer_id'], $returnDate, $totalReturnAmount, $notes]);
            $returnId = $pdo->lastInsertId();

            // 5. Insert Return Items
            $itemIns = $pdo->prepare("INSERT INTO sales_return_items (return_id, product_id, serial_number, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($returnItemsData as $ri) {
                $itemIns->execute([$returnId, $ri['product_id'], $ri['serial_number'], $ri['quantity'], $ri['price'], $ri['total']]);
            }

            // 6. Handle Ledger/Accounting
            if ($invoice['payment_method'] === 'Pay Later') {
                // Reduce customer debt
                $pdo->prepare("UPDATE customers SET due_amount = due_amount - ? WHERE id = ?")
                    ->execute([$totalReturnAmount, $invoice['customer_id']]);
            } else {
                // It was a cash sale. The return is technically a cash outflow.
                // We could record a payment with negative amount or a separate outflow?
                // For now, let's just record the return. The ledger will pick it up as a Credit.
            }
        }

        $pdo->commit();
        $successMsg = "Sales return recorded successfully!";
        $successMsg .= " <a href='sales_return_print.php?id=$returnId' class='font-bold underline ml-2'>Print Credit Note</a>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch Returns
$returns = $pdo->query("SELECT r.*, i.invoice_no, c.customer_name 
    FROM sales_returns r 
    JOIN invoices i ON r.invoice_id = i.id 
    JOIN customers c ON r.customer_id = c.id 
    ORDER BY r.return_date DESC LIMIT 50")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Sales Returns</h1>
        </div>
        <button onclick="document.getElementById('return-modal').classList.remove('hidden')" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">Record New Return</button>
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
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Invoice</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase">Customer</th>
                    <th class="px-6 py-4 text-xs font-bold text-slate-400 uppercase text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($returns as $r): ?>
                <tr class="hover:bg-slate-50/50 transition">
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y', strtotime($r['return_date'])); ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-slate-800">RET-<?php echo str_pad($r['id'], 5, '0', STR_PAD_LEFT); ?></td>
                    <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($r['invoice_no']); ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-slate-700"><?php echo htmlspecialchars($r['customer_name']); ?></td>
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
            <h2 class="text-xl font-bold text-slate-800">Record Sales Return</h2>
            <button onclick="document.getElementById('return-modal').classList.add('hidden')" class="p-2 hover:bg-slate-100 rounded-full transition text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <div class="p-8 overflow-y-auto">
            <div class="mb-8">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Search Invoice Number</label>
                <div class="flex gap-2">
                    <input type="text" id="invoice-search" placeholder="e.g. SALE123..." class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500">
                    <button onclick="fetchInvoiceItems()" class="bg-slate-800 text-white px-6 py-3 rounded-xl font-bold hover:bg-slate-900 transition">Fetch Items</button>
                </div>
            </div>

            <form id="return-form" method="POST" class="hidden space-y-6">
                <input type="hidden" name="invoice_id" id="form-invoice-id">
                
                <div id="invoice-info-banner" class="bg-indigo-50 border border-indigo-100 p-4 rounded-2xl mb-6 flex justify-between items-center">
                    <div>
                        <p id="info-customer" class="text-sm font-bold text-indigo-900"></p>
                        <p id="info-date" class="text-[10px] font-bold text-indigo-400 uppercase"></p>
                    </div>
                    <div class="text-right">
                        <p id="info-method" class="text-[10px] font-black uppercase text-indigo-500 bg-white px-2 py-1 rounded inline-block mb-1"></p>
                        <p id="info-total" class="text-lg font-black text-indigo-900"></p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Product</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Serial</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Price</th>
                                <th class="px-4 py-3 text-[10px] font-bold text-slate-400 uppercase">Sold Qty</th>
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
                        <input type="date" name="return_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Notes</label>
                        <input type="text" name="notes" placeholder="Reason for return..." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="flex justify-end pt-6 border-t border-slate-100">
                    <button type="submit" name="submit_return" class="bg-indigo-600 text-white px-10 py-4 rounded-2xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Confirm Return</button>
                </div>
            </form>
            
            <div id="no-results" class="hidden py-12 text-center text-slate-400 italic">No invoice found with that number.</div>
        </div>
    </div>
</div>

<script>
async function fetchInvoiceItems() {
    const invNo = document.getElementById('invoice-search').value;
    if (!invNo) return;

    const response = await fetch('ajax_get_invoice.php?invoice_no=' + invNo);
    const data = await response.json();

    if (data.success) {
        document.getElementById('return-form').classList.remove('hidden');
        document.getElementById('no-results').classList.add('hidden');
        document.getElementById('form-invoice-id').value = data.invoice.id;
        
        document.getElementById('info-customer').innerText = data.invoice.customer_name;
        document.getElementById('info-date').innerText = 'Invoice Date: ' + data.invoice.created_at;
        document.getElementById('info-method').innerText = data.invoice.payment_method;
        document.getElementById('info-total').innerText = '₹' + parseFloat(data.invoice.grand_total).toLocaleString();

        const tbody = document.getElementById('items-table-body');
        tbody.innerHTML = '';
        data.items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-3 text-sm font-bold text-slate-800">${item.product_name}</td>
                <td class="px-4 py-3 text-xs text-slate-500">${item.serial_number || '-'}</td>
                <td class="px-4 py-3 text-sm font-medium text-slate-600">₹${parseFloat(item.unit_price).toLocaleString()}</td>
                <td class="px-4 py-3 text-sm text-slate-400">${item.quantity}</td>
                <td class="px-4 py-3">
                    <input type="number" name="items[${item.id}]" max="${item.quantity}" min="0" value="0" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
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
