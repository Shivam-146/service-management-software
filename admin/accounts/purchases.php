<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Outflow Submission (Purchase or General Expense)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_outflow'])) {
    $type = $_POST['outflow_type']; // 'Inventory' or 'General'
    $supplier_id = $_POST['supplier_id'] ?: null;
    $payee_name = $_POST['payee_name'] ?: '';
    $category = $_POST['category'] ?: 'Inventory Purchase';
    $date = $_POST['purchase_date'];
    $bill_no = $_POST['bill_no'];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $is_inventory = ($_POST['outflow_type'] === 'Inventory') ? 1 : 0;
    
    $bank_name = $_POST['bank_name'] ?? null;
    $cheque_no = $_POST['cheque_no'] ?? null;
    $cheque_date = ($_POST['cheque_date'] ?? null) ?: null;

    try {
        $pdo->beginTransaction();

        if ($is_inventory) {
            $product_ids = $_POST['product_id'];
            $quantities = $_POST['qty'];
            $prices = $_POST['price'];
            $gst_slabs = $_POST['gst_slab'];
            $taxable_values = $_POST['taxable_value'];
            $gst_amounts = $_POST['gst_amount'];
            $disc_types = $_POST['discount_type'];
            $disc_vals = $_POST['discount_val'];
            $disc_amts = $_POST['discount_amount'];
            $row_totals = $_POST['row_total'];
            
            if (empty($product_ids)) throw new Exception("Please add at least one product.");
            
            $total_amount = 0;
            foreach ($row_totals as $row_total) {
                $total_amount += $row_total;
            }
        } else {
            $total_amount = $_POST['total_amount'];
            if ($total_amount <= 0) throw new Exception("Amount must be greater than 0.");
        }

        // 1. Insert Purchase Record
        $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, payee_name, category, purchase_date, bill_no, total_amount, payment_method, reference_no, notes, is_inventory, bank_name, cheque_no, cheque_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$supplier_id, $payee_name, $category, $date, $bill_no, $total_amount, $payment_method, $reference_no, $notes, $is_inventory, $bank_name, $cheque_no, $cheque_date]);
        $purchase_id = $pdo->lastInsertId();

        if ($is_inventory) {
            foreach ($product_ids as $index => $pid) {
                if (empty($pid)) continue; // Skip empty selections
                
                $qty = $quantities[$index];
                $price = $prices[$index];
                $gst_rate = $gst_slabs[$index];
                $taxable_val = $taxable_values[$index];
                $gst_amt = $gst_amounts[$index];
                $disc_type = $disc_types[$index];
                $disc_val = $disc_vals[$index];
                $disc_amt = $disc_amts[$index];
                $total = $row_totals[$index];

                // 2. Insert Purchase Item
                $stmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, quantity, unit_price, taxable_value, gst_rate, gst_amount, discount_type, discount_value, discount_amount, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$purchase_id, $pid, $qty, $price, $taxable_val, $gst_rate, $gst_amt, $disc_type, $disc_val, $disc_amt, $total]);
                $item_id = $pdo->lastInsertId();

                // 3. Update Product Stock
                $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                $stmt->execute([$qty, $pid]);

                // 4. Record Stock Movement
                $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, reference_id, notes) VALUES (?, 'Stock In', ?, ?, ?)");
                $stmt->execute([$pid, $qty, $item_id, "Purchase Bill: $bill_no"]);
            }
        }

        $pdo->commit();
        $successMsg = ($is_inventory ? "Purchase recorded & stock updated!" : "General expense recorded successfully!");
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = $e->getMessage();
    }
}

// Fetch Data for form
$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC")->fetchAll();
$products_list = $pdo->query("SELECT id, product_name, product_code FROM products ORDER BY product_name ASC")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM stock_categories ORDER BY category_name ASC")->fetchAll();

// Fetch Recent Outflows
$purchases = $pdo->query("SELECT p.*, s.supplier_name, 
    (SELECT GROUP_CONCAT(CONCAT(pi.quantity, 'x ', pr.product_name) SEPARATOR ', ') 
     FROM purchase_items pi 
     JOIN products pr ON pi.product_id = pr.id 
     WHERE pi.purchase_id = p.id) as products_summary
FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.purchase_date DESC LIMIT 50")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-none text-slate-500 hover:text-indigo-600 transition shadow-none">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">New Purchase / Outflow Entry</h1>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-none"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-none"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Entry Form -->
        <div class="lg:col-span-2">
            <form method="POST" class="bg-white rounded-none border border-slate-100 shadow-none overflow-hidden">
                <div class="p-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800">Transaction Details</h2>
                    <div class="flex bg-white p-1 rounded-none border border-slate-200">
                        <button type="button" onclick="setType('Inventory')" id="btn-inventory" class="px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all bg-indigo-600 text-white shadow-none">Inventory</button>
                        <button type="button" onclick="setType('General')" id="btn-general" class="px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all text-slate-500">General Expense</button>
                        <input type="hidden" name="outflow_type" id="outflow_type" value="Inventory">
                    </div>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div id="supplier-group">
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase">Supplier / Payee</label>
                                <button type="button" onclick="openSupplierModal()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase">+ Add New</button>
                            </div>
                            <select id="supplier_id" name="supplier_id" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['supplier_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="payee-group" class="hidden">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payee Name</label>
                            <input type="text" name="payee_name" placeholder="e.g. Building Owner, Power Corp" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div id="category-group" class="hidden">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category</label>
                            <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="Rent">Rent</option>
                                <option value="Salary">Salary</option>
                                <option value="Electricity">Electricity</option>
                                <option value="Internet">Internet</option>
                                <option value="Travel">Travel</option>
                                <option value="Others">Others</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Date & Time *</label>
                            <input type="datetime-local" name="purchase_date" value="<?php echo date('Y-m-d\TH:i'); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <!-- Inventory Items (Conditional) -->
                    <div id="inventory-section" class="space-y-4">
                        <div class="flex justify-between items-center">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Inventory Products</label>
                            <button type="button" onclick="openProductModal()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 uppercase">+ Add New Product</button>
                        </div>
                        <div id="items-container" class="space-y-4">
                            <!-- Header Row (Hidden on mobile) -->
                            <div class="hidden md:grid grid-cols-12 gap-4 px-4 mb-1">
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">Product</label></div>
                                <div class="col-span-2 text-center"><label class="text-[9px] font-black text-slate-400 uppercase">Qty</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">Unit Price</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">Discount</label></div>
                                <div class="col-span-2"><label class="text-[9px] font-black text-slate-400 uppercase">GST Detail</label></div>
                                <div class="col-span-2 text-right"><label class="text-[9px] font-black text-slate-400 uppercase">Line Total</label></div>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-none p-4 md:p-5 relative purchase-row shadow-none hover:shadow-none transition-shadow">
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                    <!-- Product -->
                                    <div class="md:col-span-2">
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">Product</label>
                                        <select name="product_id[]" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none transition">
                                            <option value="">Select Product</option>
                                            <?php foreach ($products_list as $p): ?>
                                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['product_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Qty -->
                                    <div class="md:col-span-2">
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">Qty</label>
                                        <input type="number" name="qty[]" placeholder="0" required oninput="calculateRow(this)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-2 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>

                                    <!-- Price -->
                                    <div class="md:col-span-2">
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">Unit Price</label>
                                        <input type="number" step="0.01" name="price[]" placeholder="0.00" required oninput="calculateRow(this)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                    </div>

                                    <!-- Discount -->
                                    <div class="md:col-span-2">
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">Discount</label>
                                        <div class="flex">
                                            <input type="number" step="0.01" name="discount_val[]" value="0" oninput="calculateRow(this)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-2 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                                            <select name="discount_type[]" onchange="calculateRow(this)" class="bg-slate-100 border border-l-0 border-slate-200 rounded-none px-1 py-2.5 text-[10px] font-bold">
                                                <option value="Fixed">₹</option>
                                                <option value="Percentage">%</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- GST -->
                                    <div class="md:col-span-2">
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">GST Detail</label>
                                        <div class="flex gap-1">
                                            <select name="gst_type[]" onchange="calculateRow(this)" class="w-1/2 bg-slate-50 border border-slate-200 rounded-none px-1 py-2.5 text-[10px] font-bold">
                                                <option value="Exclusive">Excl</option>
                                                <option value="Inclusive">Incl</option>
                                            </select>
                                            <select name="gst_slab[]" onchange="calculateRow(this)" class="w-1/2 bg-slate-50 border border-slate-200 rounded-none px-1 py-2.5 text-[10px] font-bold">
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
                                        <label class="md:hidden text-[9px] font-black text-slate-400 uppercase mb-1 block">Line Total</label>
                                        <input type="number" step="0.01" name="row_total[]" readonly class="w-full bg-indigo-50 border border-indigo-100 rounded-none px-3 py-2.5 text-sm font-black text-indigo-600 text-right">
                                        <input type="hidden" name="taxable_value[]" class="taxable-value">
                                        <input type="hidden" name="gst_amount[]" class="gst-amount">
                                        <input type="hidden" name="discount_amount[]" class="discount-amount">
                                        
                                        <!-- Remove Button (Desktop Absolute) -->
                                        <button type="button" onclick="removeRow(this)" class="remove-btn absolute -right-2 -top-2 md:-right-3 md:-top-3 bg-rose-500 text-white p-1.5 rounded-none shadow-none hover:bg-rose-600 transition hidden">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col md:flex-row justify-between items-center gap-4 pt-6 border-t border-slate-100">
                            <button type="button" onclick="addItem()" class="w-full md:w-auto bg-slate-100 text-slate-700 hover:bg-slate-200 px-6 py-2.5 rounded-none text-xs font-bold uppercase flex items-center justify-center gap-2 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Add Another Product
                            </button>
                            <div class="w-full md:w-auto bg-slate-800 px-8 py-4 rounded-none shadow-none text-right">
                                <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Final Payable Amount</span>
                                <span id="inventory-grand-total" class="text-3xl font-black text-white">₹0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- General Amount (Conditional) -->
                    <div id="general-section" class="hidden">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Total Amount (₹) *</label>
                        <input type="number" step="0.01" name="total_amount" value="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-2xl font-black text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Payment Details -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bill / Ref No</label>
                            <input type="text" name="bill_no" placeholder="INV-001" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                            <select name="payment_method" onchange="toggleChequeFields(this.value)" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="UPI">UPI</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit">Credit / Pay Later</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reference No (TXN ID)</label>
                            <input type="text" name="reference_no" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                    </div>

                    <!-- Cheque Details (Conditional) -->
                    <div id="cheque-details-section" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="SBI, HDFC, etc." class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cheque No</label>
                            <input type="text" name="cheque_no" placeholder="123456" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Cheque Date</label>
                            <input type="date" name="cheque_date" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description / Notes</label>
                        <textarea name="notes" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm"></textarea>
                    </div>
                </div>
                
                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button type="submit" name="submit_outflow" class="bg-indigo-600 text-white px-10 py-3 rounded-none font-bold hover:bg-indigo-700 transition shadow-none shadow-none">Save Transaction</button>
                </div>
            </form>
        </div>

        <!-- History -->
        <div class="space-y-6">
            <div class="bg-white rounded-none border border-slate-100 shadow-none overflow-hidden">
                <div class="p-6 border-b border-slate-50">
                    <h2 class="text-lg font-bold text-slate-800">Recent History</h2>
                </div>
                <div class="divide-y divide-slate-50 max-h-[600px] overflow-y-auto">
                    <?php if (empty($purchases)): ?>
                        <p class="p-6 text-center text-slate-400 text-sm">No recent transactions.</p>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                        <div class="p-4 hover:bg-slate-50 transition">
                            <div class="flex justify-between items-start mb-1">
                                <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase <?php echo $p['is_inventory'] ? 'bg-indigo-100 text-indigo-600' : 'bg-amber-100 text-amber-600'; ?>">
                                    <?php echo $p['is_inventory'] ? 'Stock' : 'General'; ?>
                                </span>
                                <span class="text-[10px] font-bold text-slate-400"><?php echo date('d M, h:i A', strtotime($p['purchase_date'])); ?></span>
                            </div>
                            <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['supplier_name'] ?: $p['payee_name']); ?></p>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-[10px] text-slate-400 font-medium italic">
                                    <?php echo $p['is_inventory'] ? htmlspecialchars($p['products_summary']) : htmlspecialchars($p['category']); ?>
                                </span>
                                <span class="text-sm font-black text-rose-600">₹<?php echo number_format($p['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div id="supplierModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Add New Supplier</h2>
            <button type="button" onclick="closeSupplierModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="supplierForm" class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Supplier Name *</label>
                    <input type="text" name="supplier_name" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Email</label>
                    <input type="email" name="email" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">GST Number</label>
                    <input type="text" name="gst_number" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeSupplierModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" id="saveSupplierBtn" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div id="productModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Add New Product</h2>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="productForm" class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Product Name *</label>
                    <input type="text" name="product_name" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category</label>
                    <select name="category_id" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">HSN Code / SKU</label>
                    <input type="text" name="product_code" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Unit</label>
                    <input type="text" name="unit" value="Pcs" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Opening Stock Qty</label>
                    <input type="number" name="opening_stock" value="0" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeProductModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" id="saveProductBtn" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function setType(type) {
    document.getElementById('outflow_type').value = type;
    const invBtn = document.getElementById('btn-inventory');
    const genBtn = document.getElementById('btn-general');
    const invSec = document.getElementById('inventory-section');
    const genSec = document.getElementById('general-section');
    const supGrp = document.getElementById('supplier-group');
    const payGrp = document.getElementById('payee-group');
    const catGrp = document.getElementById('category-group');

    if (type === 'Inventory') {
        invBtn.className = 'px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all bg-indigo-600 text-white shadow-none';
        genBtn.className = 'px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all text-slate-500';
        invSec.classList.remove('hidden');
        genSec.classList.add('hidden');
        supGrp.classList.remove('hidden');
        payGrp.classList.add('hidden');
        catGrp.classList.add('hidden');
    } else {
        genBtn.className = 'px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all bg-indigo-600 text-white shadow-none';
        invBtn.className = 'px-4 py-1.5 rounded-none text-xs font-black uppercase tracking-wider transition-all text-slate-500';
        invSec.classList.add('hidden');
        genSec.classList.remove('hidden');
        supGrp.classList.add('hidden');
        payGrp.classList.remove('hidden');
        catGrp.classList.remove('hidden');
    }
}

function toggleChequeFields(method) {
    const chequeSec = document.getElementById('cheque-details-section');
    if (method === 'Cheque') {
        chequeSec.classList.remove('hidden');
    } else {
        chequeSec.classList.add('hidden');
    }
}

function addItem() {
    const container = document.getElementById('items-container');
    const rows = container.querySelectorAll('.purchase-row');
    const firstRow = rows[0];
    const newRow = firstRow.cloneNode(true);
    
    // Clear values
    newRow.querySelectorAll('input').forEach(i => {
        if (i.name === 'discount_val[]') i.value = '0';
        else if (i.type !== 'hidden') i.value = '';
        else i.value = '0';
    });
    newRow.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    
    // Show remove button
    const removeBtn = newRow.querySelector('.remove-btn');
    if (removeBtn) {
        removeBtn.classList.remove('hidden');
    }
    
    container.appendChild(newRow);
}

function calculateRow(el) {
    const row = el.closest('.purchase-row');
    const qty = parseFloat(row.querySelector('input[name="qty[]"]').value) || 0;
    const inputPrice = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
    const discVal = parseFloat(row.querySelector('input[name="discount_val[]"]').value) || 0;
    const discType = row.querySelector('select[name="discount_type[]"]').value;
    const gstType = row.querySelector('select[name="gst_type[]"]').value;
    const gstSlab = parseFloat(row.querySelector('select[name="gst_slab[]"]').value) || 0;

    let baseTotal = inputPrice * qty;
    let discAmt = 0;
    
    if (discType === 'Percentage') {
        discAmt = baseTotal * (discVal / 100);
    } else {
        discAmt = discVal;
    }

    let taxableValue = 0;
    let gstAmount = 0;
    let rowTotal = 0;

    if (gstType === 'Inclusive') {
        // Back calculation after discount
        rowTotal = baseTotal - discAmt;
        taxableValue = rowTotal / (1 + (gstSlab / 100));
        gstAmount = rowTotal - taxableValue;
    } else {
        // Exclusive calculation after discount
        taxableValue = baseTotal - discAmt;
        gstAmount = taxableValue * (gstSlab / 100);
        rowTotal = taxableValue + gstAmount;
    }

    row.querySelector('input[name="row_total[]"]').value = rowTotal.toFixed(2);
    row.querySelector('.taxable-value').value = (taxableValue / qty || 0).toFixed(2);
    row.querySelector('.gst-amount').value = (gstAmount / qty || 0).toFixed(2);
    
    // Store discount amount per unit for database
    row.querySelector('.discount-amount').value = (discAmt / qty || 0).toFixed(2);
    
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('input[name="row_total[]"]').forEach(input => {
        grandTotal += parseFloat(input.value) || 0;
    });
    document.getElementById('inventory-grand-total').innerText = '₹' + grandTotal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function removeRow(btn) {
    btn.closest('.purchase-row').remove();
    calculateGrandTotal();
}

function openSupplierModal() {
    document.getElementById('supplierModal').classList.remove('hidden');
}

function closeSupplierModal() {
    document.getElementById('supplierModal').classList.add('hidden');
    document.getElementById('supplierForm').reset();
}

document.getElementById('supplierForm').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveSupplierBtn');
    const originalText = btn.innerText;
    btn.innerText = 'Saving...';
    btn.disabled = true;

    try {
        const formData = new FormData(this);
        const response = await fetch('ajax_add_supplier.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            const select = document.getElementById('supplier_id');
            const option = new Option(result.name, result.id);
            select.add(option);
            select.value = result.id;
            closeSupplierModal();
            alert('Supplier added successfully!');
        } else {
            alert(result.message || 'Error adding supplier');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
};

function openProductModal() {
    document.getElementById('productModal').classList.remove('hidden');
}

function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
    document.getElementById('productForm').reset();
}

document.getElementById('productForm').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveProductBtn');
    const originalText = btn.innerText;
    btn.innerText = 'Saving...';
    btn.disabled = true;

    try {
        const formData = new FormData(this);
        const response = await fetch('ajax_add_product.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            // Update all product dropdowns
            const selects = document.querySelectorAll('select[name="product_id[]"]');
            selects.forEach(select => {
                const option = new Option(result.name, result.id);
                select.add(option);
            });
            
            // Set value for the first empty dropdown or the last one added
            const activeSelects = Array.from(selects).filter(s => s.value === "");
            if (activeSelects.length > 0) {
                activeSelects[activeSelects.length - 1].value = result.id;
            }

            closeProductModal();
            alert('Product added successfully!');
        } else {
            alert(result.message || 'Error adding product');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
};
</script>

<?php require_once '../../includes/footer.php'; ?>
