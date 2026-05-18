<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$success = $error = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sale'])) {
    $customerId = $_POST['customer_id'] ?? null;
    $paymentMethod = $_POST['payment_method'] ?? 'Cash';
    $paymentStatus = ($paymentMethod === 'Pay Later') ? 'Unpaid' : 'Paid';
    $remarks = $_POST['remarks'] ?? '';
    $billNo = $_POST['bill_no'] ?? '';
    $saleDate = $_POST['sale_date'] ?? date('Y-m-d H:i:s');

    if (!$customerId) {
        $error = "Please select a customer.";
    } else {
        try {
            $pdo->beginTransaction();

            $grandTotal = 0;
            $totalSubtotal = 0;
            $totalGst = 0;
            
            $product_ids = $_POST['parts'] ?? [];
            $quantities = $_POST['qtys'] ?? [];
            $prices = $_POST['price'] ?? [];
            $gst_slabs = $_POST['gst_slab'] ?? [];
            $gst_modes = $_POST['row_gst_mode'] ?? []; // Row level GST mode
            $taxable_values = $_POST['taxable_value'] ?? [];
            $gst_amounts = $_POST['gst_amount'] ?? [];
            $disc_types = $_POST['discount_type'] ?? [];
            $disc_vals = $_POST['discount_val'] ?? [];
            $disc_amts = $_POST['discount_amount'] ?? [];
            $row_totals = $_POST['row_total'] ?? [];
            $selectedSerialIds = $_POST['serial_ids'] ?? [];

            if (empty($product_ids)) throw new Exception("Please add at least one product.");

            foreach ($row_totals as $row_total) {
                $grandTotal += (float)$row_total;
            }
            foreach ($taxable_values as $taxable) {
                $totalSubtotal += (float)$taxable;
            }
            foreach ($gst_amounts as $gst_amt) {
                $totalGst += (float)$gst_amt;
            }

            // 1. Create Invoice
            $invoiceNo = $billNo ?: ('SALE' . time());
            // Store the first row's mode as global mode for legacy reasons or UI default
            $globalGstMode = $gst_modes[0] ?? 'Exclusive';
            
            $ins = $pdo->prepare("INSERT INTO invoices (invoice_no, customer_id, subtotal, gst_amount, grand_total, payment_status, payment_method, gst_mode, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$invoiceNo, $customerId, $totalSubtotal, $totalGst, $grandTotal, $paymentStatus, $paymentMethod, $globalGstMode, $saleDate]);
            $invoiceId = $pdo->lastInsertId();

            // 2. Insert Invoice Items
            foreach ($product_ids as $index => $pid) {
                if (empty($pid)) continue;
                
                $qty = (int)$quantities[$index];
                $price = (float)$prices[$index];
                $gst_rate = (float)$gst_slabs[$index];
                $gst_mode = $gst_modes[$index] ?? 'Exclusive';
                $taxable_val = (float)$taxable_values[$index];
                $gst_amt = (float)$gst_amounts[$index];
                $disc_type = $disc_types[$index];
                $disc_val = (float)$disc_vals[$index];
                $disc_amt = (float)$disc_amts[$index];
                $total = (float)$row_totals[$index];

                $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, taxable_value, gst_rate, gst_amount, gst_mode, discount_type, discount_value, discount_amount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$invoiceId, $pid, $qty, $price, $taxable_val, $gst_rate, $gst_amt, $gst_mode, $disc_type, $disc_val, $disc_amt, $total]);

                // Update stock
                $updStock = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                $updStock->execute([$qty, $pid]);

                $mvmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock Out', ?, ?)");
                $mvmt->execute([$pid, $qty, "Direct Sale Bill #$invoiceNo"]);
            }

            // 3. Link Serials
            if (!empty($selectedSerialIds)) {
                $placeholders = implode(',', array_fill(0, count($selectedSerialIds), '?'));
                $link = $pdo->prepare("UPDATE product_serials SET invoice_id = ?, status = 'Sold', sold_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
                $params = array_merge([$invoiceId], $selectedSerialIds);
                $link->execute($params);
            }

            // 4. Record Payment or Outflow (for Credit)
            if ($paymentMethod === 'Pay Later') {
                // Add to Outflow (Purchases table as an expense type)
                $custData = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
                $custData->execute([$customerId]);
                $custName = $custData->fetchColumn();
                
                $outflow = $pdo->prepare("INSERT INTO purchases (purchase_date, payee_name, category, subtotal, gst_amount, total_amount, payment_method, is_inventory, notes) VALUES (?, ?, 'Credit Sale', ?, ?, ?, 'Pay Later', 0, ?)");
                $outflow->execute([date('Y-m-d', strtotime($saleDate)), $custName, $totalSubtotal, $totalGst, $grandTotal, "Credit sale for Invoice #$invoiceNo"]);
                
                // Add to Customer Due
                $updDue = $pdo->prepare("UPDATE customers SET due_amount = due_amount + ? WHERE id = ?");
                $updDue->execute([$grandTotal, $customerId]);
            } else {
                $payStmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $payStmt->execute([$customerId, $invoiceId, $saleDate, $grandTotal, $paymentMethod, "Direct Sale #$invoiceNo. $remarks"]);
            }

            $pdo->commit();
            $success = "Sale recorded successfully! <a href='invoice_gen.php?id=$invoiceId' class='underline font-bold ml-2'>View Invoice #$invoiceNo</a>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch Data
$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC")->fetchAll();
$productsRes = $pdo->query("SELECT p.id, p.product_name, p.gst_rate,
                            (SELECT pi.taxable_value FROM purchase_items pi WHERE pi.product_id = p.id ORDER BY pi.id DESC LIMIT 1) as latest_purchase_price
                            FROM products p ORDER BY p.product_name ASC")->fetchAll();
$productData = [];
foreach ($productsRes as $p) {
    $productData[$p['id']] = ['price' => (float)$p['latest_purchase_price'], 'gst' => (float)$p['gst_rate']];
}

$recentSales = $pdo->query("SELECT i.*, c.customer_name FROM invoices i 
    LEFT JOIN payments p ON i.id = p.invoice_id
    LEFT JOIN customers c ON p.customer_id = c.id
    GROUP BY i.id
    ORDER BY i.created_at DESC LIMIT 20")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-none text-slate-500 hover:text-indigo-600 transition shadow-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">New Direct Sale Entry</h1>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-none"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-none"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Entry Form -->
        <div class="lg:col-span-3">
            <form method="POST" id="sales-form" class="bg-white rounded-none border border-slate-100 shadow-none overflow-hidden">
                <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800">Sale Details</h2>
                    <div class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 border border-indigo-100">Direct Invoice</div>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Customer Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Customer Information</label>
                                <button type="button" onclick="openCustomerModal()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase">+ Add New</button>
                            </div>
                            <select name="customer_id" id="customer_select" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo $c['phone']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Billing Date</label>
                            <input type="datetime-local" name="sale_date" value="<?php echo date('Y-m-d\TH:i'); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Sale Items</label>
                        </div>

                        <div id="parts-container" class="space-y-4">
                             <!-- Header Row -->
                             <div class="hidden md:grid grid-cols-12 gap-2 px-4 mb-1">
                                <div class="col-span-3"><label class="text-[9px] font-black text-slate-400 uppercase">Product Description</label></div>
                                <div class="col-span-1 text-center"><label class="text-[9px] font-black text-slate-400 uppercase">Qty</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">Selling Price</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">Discount</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">GST Type / Slab</label></div>
                                <div class="col-span-2 text-right"><label class="text-[9px] font-black text-slate-400 uppercase">Total</label></div>
                            </div>

                            <!-- Item Row -->
                            <div class="p-4 bg-slate-50 border border-slate-100 rounded-none part-row relative group">
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-start">
                                    <!-- Product -->
                                    <div class="md:col-span-3">
                                        <select name="parts[]" onchange="loadSerials(this); updatePriceFromSelection(this); calculateRow(this);" class="w-full bg-white border border-slate-200 rounded-none px-2 py-2 text-xs font-bold product-select">
                                            <option value="">-- Product --</option>
                                            <?php foreach ($productsRes as $p): ?>
                                                <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['latest_purchase_price']; ?>" data-gst="<?php echo $p['gst_rate']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (₹<?php echo number_format($p['latest_purchase_price'] ?? 0, 2); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="mt-1 flex items-center gap-2 px-1">
                                            <span class="text-[8px] font-black text-slate-400 uppercase">Purchase Cost:</span>
                                            <span class="text-[9px] font-black text-indigo-600 purchase-cost-label">₹0.00</span>
                                        </div>
                                    </div>
                                    <!-- Qty -->
                                    <div class="md:col-span-1">
                                        <input type="number" name="qtys[]" value="1" min="1" oninput="calculateRow(this)" class="w-full bg-white border border-slate-200 rounded-none px-2 py-2 text-xs font-black text-center qty-input">
                                    </div>
                                    <!-- Unit Price -->
                                    <div class="md:col-span-2">
                                        <input type="number" step="0.01" name="price[]" placeholder="0.00" oninput="calculateRow(this)" class="w-full bg-white border border-slate-200 rounded-none px-2 py-2 text-xs font-black price-input">
                                    </div>
                                    <!-- Discount -->
                                    <div class="md:col-span-2">
                                        <div class="flex">
                                            <input type="number" step="0.01" name="discount_val[]" value="0" oninput="calculateRow(this)" class="w-full bg-white border border-slate-200 rounded-none px-1 py-2 text-xs font-black disc-val-input">
                                            <select name="discount_type[]" onchange="calculateRow(this)" class="bg-slate-100 border border-l-0 border-slate-200 rounded-none px-1 py-2 text-[9px] font-bold">
                                                <option value="Fixed">₹</option>
                                                <option value="Percentage">%</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- GST Type & Slab -->
                                    <div class="md:col-span-2">
                                        <div class="flex flex-col gap-1">
                                            <select name="row_gst_mode[]" onchange="calculateRow(this)" class="w-full bg-slate-100 border border-slate-200 rounded-none px-1 py-1 text-[9px] font-black uppercase row-gst-mode">
                                                <option value="Exclusive">Excl</option>
                                                <option value="Inclusive">Incl</option>
                                            </select>
                                            <select name="gst_slab[]" onchange="calculateRow(this)" class="w-full bg-white border border-slate-200 rounded-none px-2 py-1 text-xs font-black gst-slab-select">
                                                <option value="0">0%</option>
                                                <option value="5">5%</option>
                                                <option value="12">12%</option>
                                                <option value="18">18%</option>
                                                <option value="28">28%</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!-- Total -->
                                    <div class="md:col-span-2 relative">
                                        <input type="number" step="0.01" name="row_total[]" readonly class="w-full bg-indigo-50 border-indigo-100 rounded-none px-2 py-2 text-xs font-black text-indigo-600 text-right row-total-input">
                                        
                                        <input type="hidden" name="taxable_value[]" class="taxable-value">
                                        <input type="hidden" name="gst_amount[]" class="gst-amount">
                                        <input type="hidden" name="discount_amount[]" class="discount-amount">

                                        <button type="button" onclick="removeRow(this)" class="absolute -right-2 -top-2 bg-rose-500 text-white p-1 rounded-none opacity-0 group-hover:opacity-100 transition">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="serial-selection-area hidden mt-3 pt-3 border-t border-slate-200/50">
                                    <div class="serial-checkboxes grid grid-cols-1 sm:grid-cols-3 gap-2 max-h-48 overflow-y-auto pr-1"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4 pt-6 border-t border-slate-100">
                            <button type="button" onclick="addPartRow()" class="w-full md:w-auto bg-slate-100 text-slate-700 hover:bg-slate-200 px-6 py-2.5 rounded-none text-xs font-bold uppercase flex items-center justify-center gap-2 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Add Another Product
                            </button>
                            <div class="w-full md:w-auto bg-slate-800 px-10 py-5 rounded-none text-right">
                                <div class="flex flex-col text-right">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase block">Grand Total Payable</span>
                                    <span class="text-3xl font-black text-white">₹<span id="grand-total-display">0.00</span></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment & Checkout -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-slate-100">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Invoice / Bill No</label>
                            <input type="text" name="bill_no" placeholder="Optional" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                            <select name="payment_method" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI / QR</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Pay Later">Credit (Pay Later)</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Sale Remarks</label>
                            <textarea name="remarks" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm" placeholder="Any internal notes..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button type="submit" name="submit_sale" class="bg-indigo-600 text-white px-12 py-3 rounded-none font-bold hover:bg-indigo-700 transition shadow-none">Finalize Sale</button>
                </div>
            </form>
        </div>

        <!-- History Sidebar -->
        <div class="space-y-6">
            <div class="bg-white rounded-none border border-slate-100 shadow-none overflow-hidden">
                <div class="p-6 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="text-lg font-bold text-slate-800">Recent Sales</h2>
                </div>
                <div class="divide-y divide-slate-50 max-h-[700px] overflow-y-auto">
                    <?php if (empty($recentSales)): ?>
                        <p class="p-8 text-center text-slate-400 text-sm italic">No sales yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                        <div class="p-5 hover:bg-slate-50 transition border-l-4 border-transparent hover:border-indigo-500">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-2 py-0.5"><?php echo htmlspecialchars($sale['invoice_no']); ?></span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter"><?php echo date('d M', strtotime($sale['created_at'])); ?></span>
                            </div>
                            <p class="text-sm font-black text-slate-800 truncate mb-1"><?php echo htmlspecialchars($sale['customer_name'] ?: 'Direct Customer'); ?></p>
                            <div class="flex justify-between items-center mt-3">
                                <span class="text-sm font-black text-emerald-600">₹<?php echo number_format($sale['grand_total'], 2); ?></span>
                                <a href="invoice_gen.php?id=<?php echo $sale['id']; ?>" target="_blank" class="p-2 bg-white border border-slate-200 rounded-none text-slate-400 hover:text-indigo-600 transition shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="customerModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none w-full max-w-md shadow-2xl border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="text-lg font-bold text-slate-800 uppercase tracking-tight">New Customer</h3>
            <button onclick="closeCustomerModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="quick-customer-form" class="p-8 space-y-5">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Name *</label>
                <input type="text" id="cust_name" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Phone *</label>
                <input type="text" id="cust_phone" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Service Address *</label>
                <textarea id="cust_address" required rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GST Number (Optional)</label>
                <input type="text" id="cust_gst" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. 29GGGGG1314R9Z6">
            </div>
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input id="cust_amc" type="checkbox" value="1" class="w-4 h-4 border border-slate-300 rounded bg-slate-50 focus:ring-3 focus:ring-indigo-300">
                </div>
                <label for="cust_amc" class="ml-2 text-sm font-medium text-slate-900">Has Active AMC Contract right now</label>
            </div>
            <div class="flex gap-4 pt-4">
                <button type="button" onclick="closeCustomerModal()" class="flex-1 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="button" onclick="saveCustomer()" class="flex-1 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const productData = <?php echo json_encode($productData); ?>;

function updatePriceFromSelection(select) {
    const row = select.closest('.part-row');
    const option = select.options[select.selectedIndex];
    if (option && option.value) {
        const price = option.getAttribute('data-price');
        const gst = option.getAttribute('data-gst');
        row.querySelector('.price-input').value = price;
        row.querySelector('.gst-slab-select').value = Math.round(gst);
        row.querySelector('.purchase-cost-label').innerText = '₹' + parseFloat(price).toLocaleString('en-IN', {minimumFractionDigits: 2});
    }
}

function calculateRow(el) {
    const row = el.closest('.part-row');
    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
    const unitPrice = parseFloat(row.querySelector('.price-input').value) || 0;
    const gstRate = parseFloat(row.querySelector('.gst-slab-select').value) || 0;
    const gstMode = row.querySelector('.row-gst-mode').value;
    const discVal = parseFloat(row.querySelector('.disc-val-input').value) || 0;
    const discType = row.querySelector('select[name="discount_type[]"]').value;

    let discountAmount = 0;
    if (discType === 'Percentage') {
        discountAmount = (unitPrice * qty) * (discVal / 100);
    } else {
        discountAmount = discVal * qty;
    }

    let taxableValue = 0;
    let gstAmount = 0;
    let total = 0;

    const rawSubtotal = (unitPrice * qty) - discountAmount;

    if (gstMode === 'Inclusive') {
        total = rawSubtotal;
        taxableValue = total / (1 + (gstRate / 100));
        gstAmount = total - taxableValue;
    } else {
        taxableValue = rawSubtotal;
        gstAmount = taxableValue * (gstRate / 100);
        total = taxableValue + gstAmount;
    }

    row.querySelector('.taxable-value').value = taxableValue.toFixed(2);
    row.querySelector('.gst-amount').value = gstAmount.toFixed(2);
    row.querySelector('.discount-amount').value = discountAmount.toFixed(2);
    row.querySelector('.row-total-input').value = total.toFixed(2);

    updateGrandTotal();
}

function updateGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.row-total-input').forEach(input => {
        grandTotal += parseFloat(input.value) || 0;
    });
    document.getElementById('grand-total-display').innerText = grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
}

async function loadSerials(selectEl) {
    const row = selectEl.closest('.part-row');
    const productId = selectEl.value;
    const serialArea = row.querySelector('.serial-selection-area');
    const serialContainer = row.querySelector('.serial-checkboxes');
    
    if (!productId) {
        serialArea.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`ajax_get_serials.php?product_id=${productId}`);
        const serials = await response.json();

        if (serials && serials.length > 0) {
            serialArea.classList.remove('hidden');
            serialContainer.innerHTML = '';
            serials.forEach(s => {
                const label = document.createElement('label');
                label.className = 'flex items-center justify-between p-2 rounded-none bg-white border border-slate-100 hover:border-indigo-200 cursor-pointer transition';
                label.innerHTML = `
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="serial_ids[]" value="${s.id}" class="w-4 h-4 text-indigo-600 rounded-none serial-checkbox">
                        <span class="text-[10px] font-bold text-slate-700">${s.serial_number}</span>
                    </div>
                `;
                serialContainer.appendChild(label);
            });
        } else {
            serialArea.classList.add('hidden');
        }
    } catch (error) { console.error(error); }
}

function addPartRow() {
    const container = document.getElementById('parts-container');
    const firstRow = container.querySelector('.part-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.product-select').value = "";
    newRow.querySelector('.qty-input').value = "1";
    newRow.querySelector('.price-input').value = "";
    newRow.querySelector('.disc-val-input').value = "0";
    newRow.querySelector('.row-total-input').value = "0.00";
    newRow.querySelector('.purchase-cost-label').innerText = "₹0.00";
    newRow.querySelector('.serial-selection-area').classList.add('hidden');
    newRow.querySelector('.serial-checkboxes').innerHTML = '';
    container.appendChild(newRow);
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.part-row');
    if (rows.length > 1) {
        btn.closest('.part-row').remove();
        updateGrandTotal();
    }
}

function openCustomerModal() { document.getElementById('customerModal').classList.remove('hidden'); }
function closeCustomerModal() { document.getElementById('customerModal').classList.add('hidden'); }

async function saveCustomer() {
    const name = document.getElementById('cust_name').value;
    const phone = document.getElementById('cust_phone').value;
    const address = document.getElementById('cust_address').value;
    const gst = document.getElementById('cust_gst').value;
    const amc = document.getElementById('cust_amc').checked;
    
    if (!name || !phone || !address) return alert("Fill all required fields");

    const formData = new FormData();
    formData.append('customer_name', name);
    formData.append('phone', phone);
    formData.append('address', address);
    if (gst) formData.append('gst_number', gst);
    if (amc) formData.append('has_active_amc', '1');

    const res = await fetch('ajax_add_customer.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (data.success) {
        const sel = document.getElementById('customer_select');
        const opt = new Option(name + " (" + phone + ")", data.id);
        sel.add(opt);
        sel.value = data.id;
        closeCustomerModal();
        document.getElementById('quick-customer-form').reset();
    } else {
        alert(data.message || "Error saving customer");
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
