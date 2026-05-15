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

    if (!$customerId) {
        $error = "Please select a customer.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Calculate Total accurately on backend
            $grandTotal = 0;
            $selectedSerialIds = $_POST['serial_ids'] ?? [];
            $allParts = $_POST['parts'] ?? [];
            $allQtys = $_POST['qtys'] ?? [];
            $serialCounts = [];

            // Add prices of selected serials
            if (!empty($selectedSerialIds)) {
                $placeholders = implode(',', array_fill(0, count($selectedSerialIds), '?'));
                $sStmt = $pdo->prepare("SELECT id, product_id, purchase_price FROM product_serials WHERE id IN ($placeholders)");
                $sStmt->execute($selectedSerialIds);
                $gstSlab = (float)($_POST['gst_slab'] ?? 18);
                $gstMode = $_POST['gst_mode'] ?? 'Exclusive';

                while ($sRow = $sStmt->fetch()) {
                    $itemPrice = (float)$sRow['purchase_price'];
                    
                    // Calculate individual taxable price for this serial
                    if ($gstMode === 'Inclusive') {
                        $itemSalePrice = $itemPrice / (1 + ($gstSlab / 100));
                    } else {
                        $itemSalePrice = $itemPrice;
                    }

                    $grandTotal += $itemPrice;
                    $serialCounts[$sRow['product_id']] = ($serialCounts[$sRow['product_id']] ?? 0) + 1;
                    
                    // Update serial status and record sale price
                    $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'Sold', sale_price = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $updSerial->execute([$itemSalePrice, $sRow['id']]);
                }
            }

            // Process Products and remaining stock deductions
            foreach ($allParts as $idx => $pid) {
                if (empty($pid)) continue;
                $qty = (int)$allQtys[$idx];
                
                // Fetch product default price for un-serialized quantity
                $pStmt = $pdo->prepare("SELECT unit_price FROM products WHERE id = ?");
                $pStmt->execute([$pid]);
                $unitPrice = $pStmt->fetchColumn() ?: 0;
                
                $serialsUsed = $serialCounts[$pid] ?? 0;
                $unserializedQty = max(0, $qty - $serialsUsed);
                
                if ($unserializedQty > 0) {
                    $grandTotal += ($unitPrice * $unserializedQty);
                }

                // Deduct total stock
                $updStock = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                $updStock->execute([$qty, $pid]);
                
                // Log movement
                $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock Out', ?, ?)");
                $mov->execute([$pid, $qty, "Direct Sale to Customer ID #$customerId"]);
            }

            // 3. Create Invoice
            $gstSlab = (float)($_POST['gst_slab'] ?? 18);
            $gstMode = $_POST['gst_mode'] ?? 'Exclusive';
            
            if ($gstMode === 'Inclusive') {
                $subtotal = $grandTotal / (1 + ($gstSlab / 100));
                $gstAmount = $grandTotal - $subtotal;
            } else {
                $subtotal = $grandTotal;
                $gstAmount = $subtotal * ($gstSlab / 100);
                $grandTotal = $subtotal + $gstAmount;
            }

            $invoiceNo = 'SALE' . time() . rand(10, 99);
            $ins = $pdo->prepare("INSERT INTO invoices (invoice_no, subtotal, gst_amount, grand_total, payment_status, payment_method, gst_mode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$invoiceNo, $subtotal, $gstAmount, $grandTotal, $paymentStatus, $paymentMethod, $gstMode]);
            $invoiceId = $pdo->lastInsertId();

            // 4. Link Serials to Invoice
            foreach ($selectedSerialIds as $sid) {
                $link = $pdo->prepare("UPDATE product_serials SET invoice_id = ? WHERE id = ?");
                $link->execute([$invoiceId, $sid]);
            }

            // 5. Record Payment
            if ($paymentStatus === 'Paid') {
                $payStmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $payStmt->execute([$customerId, $invoiceId, date('Y-m-d H:i:s'), $grandTotal, $paymentMethod, "Payment for Direct Sale #$invoiceNo. $remarks"]);
            }

            $pdo->commit();
            $success = "Sale recorded successfully! <a href='invoice_gen.php?id=$invoiceId' class='underline font-black ml-2'>View Invoice #$invoiceNo</a>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch initial data
$customers = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC")->fetchAll();
$productsRes = $pdo->query("SELECT id, product_name, unit_price FROM products WHERE current_stock > 0 ORDER BY product_name ASC");
$products = $productsRes->fetchAll();

$priceMap = [];
foreach ($products as $p) { $priceMap[$p['id']] = $p['unit_price']; }

require_once '../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Direct Sales Entry</h2>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Create instant invoices for direct sales</p>
        </div>
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-indigo-600 transition shadow-sm">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </a>
    </div>

    <?php if ($success): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-2xl animate-pulse"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" id="sales-form" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Sale Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Customer Selection -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between mb-4">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest">Customer Details</label>
                    <button type="button" onclick="openCustomerModal()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 transition uppercase tracking-tighter">+ Add New Customer</button>
                </div>
                <select name="customer_id" id="customer_select" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm appearance-none">
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo $c['phone']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Products Selection -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Items for Sale</label>
                <div id="parts-container" class="space-y-4">
                    <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl part-row group/row">
                        <div class="flex gap-2 mb-3">
                            <select name="parts[]" onchange="loadSerials(this); calculateBill();" class="flex-1 bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm product-select">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?> (₹<?php echo number_format($p['unit_price'], 2); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="w-24 relative">
                                <input type="number" name="qtys[]" value="1" min="1" oninput="calculateBill()" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-3 text-center text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 qty-input">
                                <span class="absolute -top-2 left-2 bg-white px-1 text-[8px] font-black text-slate-400 uppercase">Qty</span>
                            </div>
                        </div>
                        <div class="serial-selection-area hidden mt-4 pt-4 border-t border-slate-200/50">
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2">Assign Serial Numbers</label>
                            <div class="serial-checkboxes grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1"></div>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addPartRow()" class="mt-4 w-full py-3 border-2 border-dashed border-slate-100 rounded-2xl text-[10px] font-black text-slate-400 hover:border-indigo-200 hover:text-indigo-500 transition uppercase tracking-widest">+ Add Another Item</button>
            </div>
            
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Sale Remarks</label>
                <textarea name="remarks" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="Enter any specific notes for this transaction..."></textarea>
            </div>
        </div>

        <!-- Right: Summary & Checkout -->
        <div class="space-y-6">
            <div class="bg-slate-900 text-white p-8 rounded-3xl shadow-xl shadow-slate-200 sticky top-6">
                <div class="flex justify-between items-center pb-4 border-b border-white/10 mb-6">
                    <span class="text-slate-400 font-bold uppercase tracking-widest text-xs">Checkout Summary</span>
                    <div class="flex items-center gap-1">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span>
                        <span class="text-[10px] font-black">ACTIVE</span>
                    </div>
                </div>
                
                <div id="selection-summary" class="space-y-4 mb-8 max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                    <p class="text-slate-500 italic text-center py-8 text-xs">Your items will appear here.</p>
                </div>

                <div class="space-y-4 pt-6 border-t border-white/10">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="relative">
                            <select name="gst_slab" id="gst_slab" onchange="calculateBill()" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2 outline-none text-xs text-white appearance-none focus:ring-1 focus:ring-indigo-500">
                                <option value="0" class="bg-slate-800 text-white">0% GST</option>
                                <option value="5" class="bg-slate-800 text-white">5% GST</option>
                                <option value="12" class="bg-slate-800 text-white">12% GST</option>
                                <option value="18" selected class="bg-slate-800 text-white">18%</option>
                                <option value="28" class="bg-slate-800 text-white">28%</option>
                            </select>
                            <span class="absolute -top-2 left-3 bg-slate-900 px-1 text-[8px] font-black text-slate-500 uppercase">Slab</span>
                        </div>
                        <div class="relative">
                            <select name="gst_mode" id="gst_mode" onchange="calculateBill()" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-2 outline-none text-xs text-white appearance-none focus:ring-1 focus:ring-indigo-500">
                                <option value="Exclusive" selected class="bg-slate-800 text-white">Exclusive</option>
                                <option value="Inclusive" class="bg-slate-800 text-white">Inclusive</option>
                            </select>
                            <span class="absolute -top-2 left-3 bg-slate-900 px-1 text-[8px] font-black text-slate-500 uppercase">Mode</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="flex justify-between text-[10px] text-slate-400">
                            <span>Subtotal</span>
                            <span>₹<span id="sub-total-display">0.00</span></span>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400">
                            <span>Tax Amount</span>
                            <span>₹<span id="gst-amount-display">0.00</span></span>
                        </div>
                        <div class="flex justify-between text-3xl font-black pt-2">
                            <span class="text-slate-400 text-lg">Total</span>
                            <span class="text-emerald-400">₹<span id="grand-total">0.00</span></span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 space-y-4">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest">Choose Payment Mode</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col items-center justify-center p-4 border border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-500">
                            <input type="radio" name="payment_method" value="Cash" checked class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-tighter">Cash</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-4 border border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-500">
                            <input type="radio" name="payment_method" value="UPI" class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-tighter">UPI / QR</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-4 border border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-500 col-span-2">
                            <input type="radio" name="payment_method" value="Pay Later" class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-widest">Pay Later (Credit)</span>
                        </label>
                    </div>
                </div>

                <input type="hidden" name="final_total" id="hidden-total" value="0">
                <button type="submit" name="submit_sale" class="w-full mt-8 py-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-2xl font-black text-lg transition transform active:scale-95 shadow-2xl shadow-emerald-500/20">Finalize Sale</button>
            </div>
        </div>
    </form>
</div>

<!-- Quick Add Customer Modal -->
<div id="customerModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-[100] flex items-center justify-center p-4 transition-all">
    <div class="bg-white rounded-[2rem] w-full max-w-md p-8 shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-black text-slate-800">New Customer</h3>
            <button onclick="closeCustomerModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="quick-customer-form" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Full Name</label>
                <input type="text" id="cust_name" required placeholder="John Doe" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Phone Number</label>
                <input type="text" id="cust_phone" required placeholder="9876543210" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div class="flex gap-3 pt-6">
                <button type="button" onclick="closeCustomerModal()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="button" onclick="saveCustomer()" class="flex-1 py-4 bg-indigo-600 text-white rounded-2xl font-black shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition">Create & Select</button>
            </div>
        </form>
    </div>
</div>

<script>
const productPrices = <?php echo json_encode($priceMap); ?>;

function calculateBill() {
    const rows = document.querySelectorAll('.part-row');
    const summaryContainer = document.getElementById('selection-summary');
    let total = 0;
    let summaryHtml = '<table class="w-full text-left text-xs border-collapse text-white">';
    summaryHtml += '<thead class="text-[9px] text-slate-400 uppercase font-black border-b border-white/10"><tr><th class="pb-2">Item</th><th class="pb-2 text-right">Qty</th><th class="pb-2 text-right">Price</th></tr></thead>';
    summaryHtml += '<tbody class="divide-y divide-white/5">';

    rows.forEach(row => {
        const productSelect = row.querySelector('.product-select');
        const productId = productSelect.value;
        const productName = productSelect.options[productSelect.selectedIndex]?.text.split(' (₹')[0] || '';
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        
        if (productId && productPrices[productId]) {
            let rowTotal = 0;
            const selectedSerials = row.querySelectorAll('.serial-checkbox:checked');
            
            if (selectedSerials.length > 0) {
                summaryHtml += `<tr class="font-bold"><td class="py-2">${productName}</td><td class="py-2 text-right" colspan="2"></td></tr>`;
                
                selectedSerials.forEach(checkbox => {
                    const price = parseFloat(checkbox.closest('label').getAttribute('data-price')) || 0;
                    rowTotal += price;
                    const sn = checkbox.closest('label').querySelector('.sn-text').innerText;
                    summaryHtml += `<tr class="text-slate-400 italic"><td class="py-1 pl-4">└ SN: ${sn}</td><td class="py-1 text-right">1</td><td class="py-1 text-right">₹${price.toFixed(2)}</td></tr>`;
                });
                
                const remaining = qty - selectedSerials.length;
                if (remaining > 0) {
                    const remTotal = remaining * productPrices[productId];
                    rowTotal += remTotal;
                    summaryHtml += `<tr class="text-slate-400"><td class="py-1 pl-4">└ Un-serialized</td><td class="py-1 text-right">x${remaining}</td><td class="py-1 text-right">₹${remTotal.toFixed(2)}</td></tr>`;
                }
            } else {
                rowTotal = productPrices[productId] * qty;
                summaryHtml += `<tr><td class="py-2 font-bold">${productName}</td><td class="py-2 text-right">x${qty}</td><td class="py-2 text-right">₹${rowTotal.toFixed(2)}</td></tr>`;
            }
            total += rowTotal;
        }
    });

    summaryHtml += '</tbody></table>';
    summaryContainer.innerHTML = total > 0 ? summaryHtml : '<p class="text-slate-500 italic text-center py-8 text-xs">Your items will appear here.</p>';
    
    const gstSlab = parseFloat(document.getElementById('gst_slab').value) || 0;
    const gstMode = document.getElementById('gst_mode').value;
    
    let finalSubtotal = 0;
    let finalGstAmount = 0;
    let finalGrandTotal = 0;

    if (gstMode === 'Inclusive') {
        finalGrandTotal = total;
        finalSubtotal = finalGrandTotal / (1 + (gstSlab / 100));
        finalGstAmount = finalGrandTotal - finalSubtotal;
    } else {
        finalSubtotal = total;
        finalGstAmount = finalSubtotal * (gstSlab / 100);
        finalGrandTotal = finalSubtotal + finalGstAmount;
    }

    document.getElementById('sub-total-display').innerText = finalSubtotal.toFixed(2);
    document.getElementById('gst-amount-display').innerText = finalGstAmount.toFixed(2);
    document.getElementById('grand-total').innerText = finalGrandTotal.toFixed(2);
    document.getElementById('hidden-total').value = finalGrandTotal.toFixed(2);
}

async function loadSerials(selectEl) {
    const row = selectEl.closest('.part-row');
    const productId = selectEl.value;
    const serialArea = row.querySelector('.serial-selection-area');
    const serialContainer = row.querySelector('.serial-checkboxes');
    
    if (!productId) {
        serialArea.classList.add('hidden');
        calculateBill();
        return;
    }

    try {
        const response = await fetch(`ajax_get_serials.php?product_id=${productId}`);
        const serials = await response.json();

        if (serials && serials.length > 0) {
            serialArea.classList.remove('hidden');
            serialContainer.innerHTML = '';
            serials.forEach(s => {
                const price = parseFloat(s.purchase_price) || 0;
                const label = document.createElement('label');
                label.className = 'flex items-center justify-between p-3 rounded-xl bg-white border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/50 cursor-pointer transition mb-2 shadow-sm group/serial';
                label.setAttribute('data-price', price);
                label.innerHTML = `
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="serial_ids[]" value="${s.id}" onchange="calculateBill()" class="w-5 h-5 text-indigo-600 rounded-lg border-slate-300 transition serial-checkbox">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-slate-700 sn-text">${s.serial_number}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="block text-xs font-black text-indigo-600">₹${price.toFixed(2)}</span>
                    </div>
                `;
                serialContainer.appendChild(label);
            });
        } else {
            serialArea.classList.add('hidden');
        }
        calculateBill();
    } catch (e) { console.error(e); }
}

function addPartRow() {
    const container = document.getElementById('parts-container');
    const firstRow = container.querySelector('.part-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.product-select').value = "";
    newRow.querySelector('.qty-input').value = "1";
    newRow.querySelector('.serial-selection-area').classList.add('hidden');
    newRow.querySelector('.serial-checkboxes').innerHTML = '';
    container.appendChild(newRow);
    calculateBill();
}

function openCustomerModal() { document.getElementById('customerModal').classList.remove('hidden'); }
function closeCustomerModal() { document.getElementById('customerModal').classList.add('hidden'); }

async function saveCustomer() {
    const name = document.getElementById('cust_name').value;
    const phone = document.getElementById('cust_phone').value;
    if (!name || !phone) return alert("Please fill name and phone.");

    const formData = new FormData();
    formData.append('customer_name', name);
    formData.append('phone', phone);
    formData.append('address', 'Direct Sale Entry');

    try {
        const response = await fetch('../accounts/ajax_add_customer.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            const select = document.getElementById('customer_select');
            const opt = document.createElement('option');
            opt.value = data.id;
            opt.text = `${name} (${phone})`;
            opt.selected = true;
            select.appendChild(opt);
            closeCustomerModal();
        } else {
            alert("Error: " + data.message);
        }
    } catch (e) { alert("Failed to save customer."); }
}
</script>

<?php require_once '../../includes/header.php'; ?>
