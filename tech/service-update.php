<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';

$complaintId = $_GET['id'] ?? null;
if (!$complaintId) header("Location: dashboard.php");

// Fetch Data first so it's available for POST processing
try {
    $complaintStmt = $pdo->prepare("SELECT c.*, cust.customer_name FROM complaints c JOIN customers cust ON c.customer_id = cust.id WHERE c.id = ?");
    $complaintStmt->execute([$complaintId]);
    $complaint = $complaintStmt->fetch();

    if (!$complaint) {
        die("Complaint not found.");
    }

    $techId = $_SESSION['user_id'];
    
    // Fetch products that this technician has in stock (either serialized or quantity-based)
    $productsRes = $pdo->prepare("
        (SELECT p.id, p.product_name as item_name, p.unit_price FROM products p 
         JOIN technician_stock ts ON p.id = ts.product_id 
         WHERE ts.technician_id = ? AND ts.quantity > 0)
        UNION
        (SELECT DISTINCT p.id, p.product_name as item_name, p.unit_price FROM products p 
         JOIN product_serials ps ON p.id = ps.product_id 
         WHERE ps.technician_id = ? AND ps.status = 'In Stock')
    ");
    $productsRes->execute([$techId, $techId]);
    $products = $productsRes->fetchAll();
    
    // For JS calculations
    $priceMap = [];
    foreach ($products as $p) {
        $priceMap[$p['id']] = $p['unit_price'];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? null;
    $remarks = $_POST['remarks'] ?? '';

    if (!$status) {
        $error = "Please select a service status (In-Progress or Completed) before saving.";
    } else {
        $partsJson = null;
        $photoBefore = null;
        $photoAfter = null;
        $success = "";

        // Handle Parts JSON
        if (!empty($_POST['parts'])) {
            $partsData = [];
            foreach ($_POST['parts'] as $index => $partId) {
                if (!empty($partId)) {
                    $partsData[] = [
                        'product_id' => $partId,
                        'qty' => $_POST['qtys'][$index] ?? 1
                    ];
                }
            }
            $partsJson = json_encode($partsData);
        }

        // Handle Photo Uploads
        $uploadDir = '../uploads/complaints/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        if (isset($_FILES['photo_before']) && $_FILES['photo_before']['error'] === 0) {
            $name = $complaintId . "_before_" . time() . "." . pathinfo($_FILES['photo_before']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['photo_before']['tmp_name'], $uploadDir . $name);
            $photoBefore = '/cctv/uploads/complaints/' . $name;
        }

        if (isset($_FILES['photo_after']) && $_FILES['photo_after']['error'] === 0) {
            $name = $complaintId . "_after_" . time() . "." . pathinfo($_FILES['photo_after']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['photo_after']['tmp_name'], $uploadDir . $name);
            $photoAfter = '/cctv/uploads/complaints/' . $name;
        }

        // Update Complaint
        try {
            $pdo->beginTransaction();

            // 1. Restore previous inventory quantities before overwriting (Restore to technician stock)
            $oldStmt = $pdo->prepare("SELECT parts_consumed FROM complaints WHERE id = ?");
            $oldStmt->execute([$complaintId]);
            $oldRow = $oldStmt->fetch();
            if ($oldRow && !empty($oldRow['parts_consumed'])) {
                $oldParts = json_decode($oldRow['parts_consumed'], true);
                if (is_array($oldParts)) {
                    foreach ($oldParts as $op) {
                        // Restore to technician stock
                        $restore = $pdo->prepare("UPDATE technician_stock SET quantity = quantity + ? WHERE product_id = ? AND technician_id = ?");
                        $restore->execute([$op['qty'], $op['product_id'], $_SESSION['user_id']]);
                        
                        // Log Restoration Movement
                        $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock In', ?, ?)");
                        $mov->execute([$op['product_id'], $op['qty'], "Reversing technician consumed parts for Ticket #$complaintId update"]);
                    }
                }
            }

            // 2. Update the complaint record
            $query = "UPDATE complaints SET status = ?, tech_remarks = ?";
            $params = [$status, $remarks];

            if ($partsJson) { $query .= ", parts_consumed = ?"; $params[] = $partsJson; }
            if ($photoBefore) { $query .= ", photo_before = ?"; $params[] = $photoBefore; }
            if ($photoAfter) { $query .= ", photo_after = ?"; $params[] = $photoAfter; }

            if ($status === 'In-Progress') {
                $query .= ", started_at = COALESCE(started_at, CURRENT_TIMESTAMP)";
            }
            if ($status === 'Completed') {
                $query .= ", completed_at = CURRENT_TIMESTAMP";
            }

            $query .= " WHERE id = ?";
            $params[] = $complaintId;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);

            // 3. Deduct newly submitted inventory quantities (From Technician Stock)
            if (!empty($partsData)) {
                foreach ($partsData as $np) {
                    $deduct = $pdo->prepare("UPDATE technician_stock SET quantity = quantity - ? WHERE product_id = ? AND technician_id = ?");
                    $deduct->execute([$np['qty'], $np['product_id'], $_SESSION['user_id']]);
                    
                    // Log Deduction Movement
                    $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock Out', ?, ?)");
                    $mov->execute([$np['product_id'], $np['qty'], "Parts consumed by Technician (Tech ID: {$_SESSION['user_id']}) for Ticket #$complaintId"]);
                }
            }

            // 4. Auto-generate Invoice and record payment if status is Completed
            if ($status === 'Completed') {
                $paymentMethod = $_POST['payment_method'] ?? 'Pay Later';
                $paymentStatus = ($paymentMethod === 'Pay Later') ? 'Unpaid' : 'Paid';
                
                // Calculate Total from Parts Data accurately
                $baseTotal = 0;
                $selectedSerials = $_POST['serial_ids'] ?? [];
                $serialPrices = [];
                
                // 1. Get prices for all selected serials
                if (!empty($selectedSerials)) {
                    $placeholders = implode(',', array_fill(0, count($selectedSerials), '?'));
                    $sStmt = $pdo->prepare("SELECT id, product_id, purchase_price FROM product_serials WHERE id IN ($placeholders)");
                    $sStmt->execute($selectedSerials);
                    while ($sRow = $sStmt->fetch()) {
                        $baseTotal += (float)$sRow['purchase_price'];
                        $serialPrices[$sRow['product_id']] = ($serialPrices[$sRow['product_id']] ?? 0) + 1;
                    }
                }

                // 2. Add prices for remaining un-serialized quantities
                if (!empty($partsData)) {
                    foreach ($partsData as $p) {
                        $pStmt = $pdo->prepare("SELECT unit_price FROM products WHERE id = ?");
                        $pStmt->execute([$p['product_id']]);
                        if ($prod = $pStmt->fetch()) {
                            $serialsUsed = $serialPrices[$p['product_id']] ?? 0;
                            $remainingQty = max(0, (int)$p['qty'] - $serialsUsed);
                            
                            if ($remainingQty > 0) {
                                $baseTotal += ($prod['unit_price'] * $remainingQty);
                            }
                        }
                    }
                }

                // 3. Apply GST logic
                $gstSlab = (float)($_POST['gst_slab'] ?? 18);
                $gstMode = $_POST['gst_mode'] ?? 'Exclusive';
                
                if ($gstMode === 'Inclusive') {
                    $grandTotal = $baseTotal;
                    $subtotal = $grandTotal / (1 + ($gstSlab / 100));
                    $gstAmount = $grandTotal - $subtotal;
                } else {
                    $subtotal = $baseTotal;
                    $gstAmount = $subtotal * ($gstSlab / 100);
                    $grandTotal = $subtotal + $gstAmount;
                }
                
                $invoiceNo = 'INV' . time() . rand(10, 99);
                $ins = $pdo->prepare("INSERT INTO invoices (complaint_id, invoice_no, subtotal, gst_amount, grand_total, payment_status, payment_method, gst_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$complaintId, $invoiceNo, $subtotal, $gstAmount, $grandTotal, $paymentStatus, $paymentMethod, $gstMode]);
                $invoiceId = $pdo->lastInsertId();

                // 5. Update Serial Numbers to 'Sold' and link to Invoice
                if (!empty($_POST['serial_ids'])) {
                    $serialIds = $_POST['serial_ids'];
                    foreach ($serialIds as $sid) {
                        $sStmt = $pdo->prepare("SELECT id, purchase_price FROM product_serials WHERE id = ?");
                        $sStmt->execute([$sid]);
                        $sRow = $sStmt->fetch();
                        $sPrice = (float)($sRow['purchase_price'] ?? 0);
                        
                        // Calculate individual taxable price for this serial
                        if ($gstMode === 'Inclusive') {
                            $sSalePrice = $sPrice / (1 + ($gstSlab / 100));
                        } else {
                            $sSalePrice = $sPrice;
                        }
                        
                        $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'Sold', sale_price = ?, invoice_id = ?, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $updSerial->execute([$sSalePrice, $invoiceId, $sid]);
                    }
                }

                // Record payment in ledger if it's not 'Pay Later'
                if ($paymentStatus === 'Paid') {
                    $payStmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $payStmt->execute([$complaint['customer_id'], $invoiceId, date('Y-m-d H:i:s'), $grandTotal, $paymentMethod, "Payment received by technician for Ticket #$complaintId"]);
                    $success .= " Payment recorded in ledger.";
                }
                
                $success .= " Invoice #$invoiceNo generated.";
            }

            $pdo->commit();
            $success .= " Job updated successfully! Inventory synchronized.";

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Update Service</h2>
            <p class="text-sm text-slate-500 font-medium">Complaint #<?php echo $complaintId; ?> - <?php echo htmlspecialchars($complaint['customer_name']); ?></p>
        </div>
        <a href="dashboard.php" class="text-slate-400 hover:text-slate-600 transition">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </a>
    </div>

    <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-2xl" role="alert"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-2xl" role="alert"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Status -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <label class="block text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider">Service Status</label>
            <div class="grid grid-cols-2 gap-4">
                <label id="label-in-progress" class="relative flex flex-col items-center p-4 border-2 rounded-2xl cursor-pointer transition focus-within:ring-2 focus-within:ring-indigo-500 <?php echo $complaint['status'] === 'In-Progress' ? 'bg-indigo-50 border-indigo-500' : 'bg-slate-50 border-transparent'; ?>">
                    <input type="radio" name="status" value="In-Progress" class="sr-only" onchange="updateUI()" <?php echo $complaint['status'] === 'In-Progress' ? 'checked' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    <span class="text-sm font-black text-indigo-900">In-Progress</span>
                </label>
                <label id="label-completed" class="relative flex flex-col items-center p-4 border-2 rounded-2xl cursor-pointer transition focus-within:ring-2 focus-within:ring-green-500 <?php echo $complaint['status'] === 'Completed' ? 'bg-green-50 border-green-500' : 'bg-slate-50 border-transparent'; ?>">
                    <input type="radio" name="status" value="Completed" class="sr-only" onchange="updateUI()" <?php echo $complaint['status'] === 'Completed' ? 'checked' : ''; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-2 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="text-sm font-black text-green-900">Completed</span>
                </label>
            </div>
        </div>

        <!-- Parts Consumed -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-bold text-slate-700 uppercase tracking-wider">Parts Consumed</label>
                <span class="text-[10px] font-black text-indigo-500 bg-indigo-50 px-2 py-1 rounded-lg uppercase">Inventory Link</span>
            </div>
            
            <div id="parts-container" class="space-y-4">
                <!-- Initial Row -->
                <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl part-row group/row relative">
                    <div class="flex gap-2 mb-3">
                        <div class="flex-1 relative">
                            <select name="parts[]" onchange="loadSerials(this); calculateBill();" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm product-select appearance-none">
                                <option value="">-- Select Product --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['unit_price']; ?>"><?php echo htmlspecialchars($p['item_name']); ?> (₹<?php echo number_format($p['unit_price'], 2); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                        </div>
                        <div class="w-24 relative">
                            <input type="number" name="qtys[]" value="1" min="1" oninput="calculateBill()" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-3 text-center text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 qty-input">
                            <span class="absolute -top-2 left-3 bg-white px-1 text-[8px] font-black text-slate-400 uppercase">Qty</span>
                        </div>
                    </div>
                    
                    <!-- Serial Numbers Selection -->
                    <div class="serial-selection-area hidden mt-4 pt-4 border-t border-slate-200/50">
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Available Serials & Individual Pricing</label>
                            <span class="text-[9px] font-bold text-indigo-400">Select to assign to this job</span>
                        </div>
                        <div class="serial-checkboxes grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 bg-white/50 rounded-xl">
                            <!-- Serials will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="button" onclick="addPartRow()" class="mt-4 w-full py-3 border-2 border-dashed border-slate-200 rounded-2xl text-xs font-bold text-slate-400 hover:border-indigo-300 hover:text-indigo-500 hover:bg-indigo-50/30 transition flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Add Another Product
            </button>
        </div>

        <!-- NEW: Selected Parts & Serials Breakdown Section -->
        <div id="selection-summary-card" class="bg-indigo-900 text-white p-6 rounded-3xl shadow-xl hidden">
            <div class="flex items-center justify-between mb-4 pb-4 border-b border-white/10">
                <h3 class="text-sm font-black uppercase tracking-widest">Selected Inventory Breakdown</h3>
                <span class="text-[10px] bg-white/10 px-2 py-1 rounded">Live Validation</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="text-indigo-300 font-bold uppercase tracking-tighter border-b border-white/5">
                            <th class="py-2">Item / Serial</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Price (₹)</th>
                        </tr>
                    </thead>
                    <tbody id="summary-table-body" class="divide-y divide-white/5">
                        <!-- Summary items will be injected here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Billing Summary -->
        <div id="billing-section" class="bg-slate-900 text-white p-8 rounded-3xl shadow-xl shadow-slate-200 space-y-4">
            <div class="flex justify-between items-center pb-4 border-b border-white/10">
                <span class="text-slate-400 font-bold uppercase tracking-widest text-xs">Billing Summary</span>
                <span class="bg-indigo-500 text-white px-2 py-1 rounded text-[10px] font-black uppercase">Instant Estimate</span>
            </div>
            
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Parts Total</span>
                    <span class="font-bold">₹<span id="parts-total">0.00</span></span>
                </div>
                <div class="flex justify-between text-2xl pt-2 font-black border-t border-white/10">
                    <span>Grand Total</span>
                    <span class="text-green-400">₹<span id="grand-total">0.00</span></span>
                </div>
            </div>

            <!-- Payment & Billing (Visible only if status is Completed) -->
        <div id="payment-method-container" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 <?php echo $complaint['status'] === 'Completed' ? '' : 'hidden'; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- GST Settings -->
                <div class="space-y-4">
                    <label class="block text-sm font-bold text-slate-700 uppercase tracking-wider">GST Settings</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="relative">
                            <select name="gst_slab" id="gst_slab" onchange="calculateBill()" class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2 outline-none text-xs text-white appearance-none focus:ring-1 focus:ring-indigo-500">
                                <option value="0" class="bg-slate-800 text-white">0% GST</option>
                                <option value="5" class="bg-slate-800 text-white">5% GST</option>
                                <option value="12" class="bg-slate-800 text-white">12% GST</option>
                                <option value="18" selected class="bg-slate-800 text-white">18%</option>
                                <option value="28" class="bg-slate-800 text-white">28%</option>
                            </select>
                            <span class="absolute -top-2 left-3 bg-slate-900 px-1 text-[8px] font-black text-slate-500 uppercase">Slab</span>
                        </div>
                        <div class="relative">
                            <select name="gst_mode" id="gst_mode" onchange="calculateBill()" class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-2 outline-none text-xs text-white appearance-none focus:ring-1 focus:ring-indigo-500">
                                <option value="Exclusive" selected class="bg-slate-800 text-white">Exclusive</option>
                                <option value="Inclusive" class="bg-slate-800 text-white">Inclusive</option>
                            </select>
                            <span class="absolute -top-2 left-3 bg-slate-900 px-1 text-[8px] font-black text-slate-500 uppercase">Mode</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="space-y-4">
                    <label class="block text-sm font-bold text-slate-700 uppercase tracking-wider">Payment Method</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col items-center justify-center p-3 border-2 border-slate-100 rounded-2xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-400 has-[:checked]:text-white">
                            <input type="radio" name="payment_method" value="Cash" checked class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-tighter">Cash</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-3 border-2 border-slate-100 rounded-2xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-400 has-[:checked]:text-white">
                            <input type="radio" name="payment_method" value="UPI" class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-tighter">UPI / QR</span>
                        </label>
                        <label class="flex flex-col items-center justify-center p-3 border-2 border-slate-100 rounded-2xl cursor-pointer hover:bg-slate-50 transition has-[:checked]:bg-slate-900 has-[:checked]:border-slate-800 has-[:checked]:text-white col-span-2">
                            <input type="radio" name="payment_method" value="Pay Later" class="sr-only">
                            <span class="text-xs font-bold uppercase tracking-widest">Pay Later (Credit)</span>
                        </label>
                    </div>
                </div>
            </div>
            <p class="mt-6 text-[10px] text-slate-400 italic text-center">Invoices are generated using the selected tax slab and pricing model.</p>
        </div>

        <!-- Photos -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Photo Before</label>
                <input type="file" name="photo_before" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 transition">
                <?php if ($complaint['photo_before']): ?><img src="<?php echo $complaint['photo_before']; ?>" class="mt-2 rounded-xl h-20 object-cover"><?php endif; ?>
            </div>
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Photo After</label>
                <input type="file" name="photo_after" accept="image/*" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 transition">
                <?php if ($complaint['photo_after']): ?><img src="<?php echo $complaint['photo_after']; ?>" class="mt-2 rounded-xl h-20 object-cover"><?php endif; ?>
            </div>
        </div>

        <!-- Remarks -->
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100">
            <label class="block text-sm font-bold text-slate-700 mb-2 uppercase tracking-wider">Technical Remarks</label>
            <textarea name="remarks" rows="4" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm transition" placeholder="Describe the work done, issues found, and testing results..."><?php echo htmlspecialchars($complaint['tech_remarks'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-lg shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition transform active:scale-95">Complete Update</button>
    </form>
</div>

<script>
const productPrices = <?php echo json_encode($priceMap); ?>;

function updateUI() {
    const inProgressInput = document.querySelector('input[value="In-Progress"]');
    const completedInput = document.querySelector('input[value="Completed"]');
    const inProgressLabel = document.getElementById('label-in-progress');
    const completedLabel = document.getElementById('label-completed');
    const payContainer = document.getElementById('payment-method-container');

    if (inProgressInput.checked) {
        inProgressLabel.classList.add('bg-indigo-50', 'border-indigo-500');
        inProgressLabel.classList.remove('bg-slate-50', 'border-transparent');
        payContainer.classList.add('hidden');
    } else {
        inProgressLabel.classList.remove('bg-indigo-50', 'border-indigo-500');
        inProgressLabel.classList.add('bg-slate-50', 'border-transparent');
    }

    if (completedInput.checked) {
        completedLabel.classList.add('bg-green-50', 'border-green-500');
        completedLabel.classList.remove('bg-slate-50', 'border-transparent');
        payContainer.classList.remove('hidden');
    } else {
        completedLabel.classList.remove('bg-green-50', 'border-green-500');
        completedLabel.classList.add('bg-slate-50', 'border-transparent');
        payContainer.classList.add('hidden');
    }
}

function calculateBill() {
    const rows = document.querySelectorAll('.part-row');
    const summaryBody = document.getElementById('summary-table-body');
    const summaryCard = document.getElementById('selection-summary-card');
    let total = 0;
    let summaryHtml = '';

    rows.forEach(row => {
        const productSelect = row.querySelector('.product-select');
        const productId = productSelect.value;
        const productName = productSelect.options[productSelect.selectedIndex]?.text.split(' (₹')[0] || '';
        const qtyInput = row.querySelector('.qty-input');
        const qty = parseInt(qtyInput.value) || 0;
        
        if (productId && productPrices[productId]) {
            let rowTotal = 0;
            const selectedSerials = row.querySelectorAll('.serial-checkbox:checked');
            
            // If serials are selected, their individual prices take precedence
            if (selectedSerials.length > 0) {
                selectedSerials.forEach(checkbox => {
                    const serialLabel = checkbox.closest('label');
                    const serialNumber = serialLabel.querySelector('.serial-no-text').innerText;
                    const serialPrice = parseFloat(serialLabel.getAttribute('data-price')) || 0;
                    
                    rowTotal += serialPrice;
                    
                    summaryHtml += `
                        <tr class="text-indigo-300 italic border-l-2 border-indigo-500/30">
                            <td class="py-1 pl-4">└ SN: ${serialNumber}</td>
                            <td class="py-1 text-right">1</td>
                            <td class="py-1 text-right text-[10px]">₹${serialPrice.toFixed(2)}</td>
                        </tr>
                    `;
                });

                // If qty > serials selected, add the remaining using product unit price
                const remainingQty = qty - selectedSerials.length;
                if (remainingQty > 0) {
                    const remainingTotal = remainingQty * productPrices[productId];
                    rowTotal += remainingTotal;
                    summaryHtml = `
                        <tr class="font-bold border-t border-white/5">
                            <td class="py-3">${productName} (Un-serialized)</td>
                            <td class="py-3 text-right">x${remainingQty}</td>
                            <td class="py-3 text-right">₹${remainingTotal.toFixed(2)}</td>
                        </tr>
                    ` + summaryHtml;
                } else {
                    // Just the header for grouped serials
                    summaryHtml = `
                        <tr class="font-bold border-t border-white/5">
                            <td class="py-3" colspan="2">${productName}</td>
                            <td class="py-3 text-right">₹${rowTotal.toFixed(2)}</td>
                        </tr>
                    ` + summaryHtml;
                }
            } else {
                // No serials selected, use product unit price
                rowTotal = productPrices[productId] * qty;
                summaryHtml += `
                    <tr class="font-bold border-t border-white/5">
                        <td class="py-3">${productName}</td>
                        <td class="py-3 text-right">x${qty}</td>
                        <td class="py-3 text-right">₹${rowTotal.toFixed(2)}</td>
                    </tr>
                `;
            }
            
            total += rowTotal;
        }
    });

    if (total > 0) {
        summaryCard.classList.remove('hidden');
        
        const gstSlab = parseFloat(document.getElementById('gst_slab').value) || 0;
        const gstMode = document.getElementById('gst_mode').value;
        
        let taxableTotal = 0;
        let gstAmount = 0;
        let grandTotal = 0;

        if (gstMode === 'Inclusive') {
            grandTotal = total;
            taxableTotal = grandTotal / (1 + (gstSlab / 100));
            gstAmount = grandTotal - taxableTotal;
        } else {
            taxableTotal = total;
            gstAmount = taxableTotal * (gstSlab / 100);
            grandTotal = taxableTotal + gstAmount;
        }

        summaryHtml += `
            <tr class="border-t border-white/20">
                <td class="py-2 text-slate-400 text-xs">Subtotal</td>
                <td colspan="2" class="py-2 text-right text-xs">₹${taxableTotal.toFixed(2)}</td>
            </tr>
            <tr>
                <td class="py-2 text-slate-400 text-xs">GST (${gstSlab}%)</td>
                <td colspan="2" class="py-2 text-right text-xs">₹${gstAmount.toFixed(2)}</td>
            </tr>
        `;

        summaryBody.innerHTML = summaryHtml;
        document.getElementById('parts-total').innerText = taxableTotal.toFixed(2);
        document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
    } else {
        summaryCard.classList.add('hidden');
    }
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
        const response = await fetch(`../admin/accounts/ajax_get_serials.php?product_id=${productId}`);
        const serials = await response.json();

        if (serials && serials.length > 0) {
            serialArea.classList.remove('hidden');
            serialContainer.innerHTML = '';
            
            serials.forEach(s => {
                const label = document.createElement('label');
                label.className = 'flex items-center justify-between p-3 rounded-xl bg-white border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50/50 cursor-pointer transition group/serial shadow-sm';
                
                // Use purchase_price as the display price as per user request ("prices given by the admin")
                const displayPrice = parseFloat(s.purchase_price) || 0;
                label.setAttribute('data-price', displayPrice);
                
                label.innerHTML = `
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="serial_ids[]" value="${s.id}" onchange="calculateBill()" class="w-5 h-5 text-indigo-600 rounded-lg border-slate-300 focus:ring-indigo-500 transition cursor-pointer serial-checkbox">
                        <div class="flex flex-col">
                            <span class="text-xs font-black text-slate-700 serial-no-text">${s.serial_number}</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="block text-xs font-black text-indigo-600">₹${displayPrice.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                        <span class="block text-[8px] font-bold text-slate-300 uppercase tracking-tighter">Admin Price</span>
                    </div>
                `;
                serialContainer.appendChild(label);
            });
        } else {
            serialArea.classList.add('hidden');
        }
        calculateBill();
    } catch (error) {
        console.error('Error fetching serials:', error);
    }
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

// Initial calculation
calculateBill();
updateUI();
</script>

<?php require_once '../includes/footer.php'; ?>
