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

    $productsRes = $pdo->query("SELECT id, product_name as item_name, unit_price FROM products WHERE current_stock > 0");
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

            // 1. Restore previous inventory quantities before overwriting
            $oldStmt = $pdo->prepare("SELECT parts_consumed FROM complaints WHERE id = ?");
            $oldStmt->execute([$complaintId]);
            $oldRow = $oldStmt->fetch();
            if ($oldRow && !empty($oldRow['parts_consumed'])) {
                $oldParts = json_decode($oldRow['parts_consumed'], true);
                if (is_array($oldParts)) {
                    foreach ($oldParts as $op) {
                        $restore = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
                        $restore->execute([$op['qty'], $op['product_id']]);
                        
                        // Log Restoration Movement
                        $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock In', ?, ?)");
                        $mov->execute([$op['product_id'], $op['qty'], "Reversing consumed parts for Ticket #$complaintId update"]);
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

            // 3. Deduct newly submitted inventory quantities
            if (!empty($partsData)) {
                foreach ($partsData as $np) {
                    $deduct = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ?");
                    $deduct->execute([$np['qty'], $np['product_id']]);
                    
                    // Log Deduction Movement
                    $mov = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, 'Stock Out', ?, ?)");
                    $mov->execute([$np['product_id'], $np['qty'], "Parts consumed for Ticket #$complaintId"]);
                }
            }

            $pdo->commit();
            $success = "Job updated successfully! Inventory synchronized.";

            // Auto-generate Invoice and record payment if status is Completed
            if ($status === 'Completed' && isset($_POST['payment_method'])) {
                $paymentMethod = $_POST['payment_method'];
                $paymentStatus = ($paymentMethod === 'Pay Later') ? 'Unpaid' : 'Paid';
                
                // Calculate Total from Parts Data
                $grandTotal = 0;
                if (!empty($partsData)) {
                    foreach ($partsData as $p) {
                        $pStmt = $pdo->prepare("SELECT unit_price FROM products WHERE id = ?");
                        $pStmt->execute([$p['product_id']]);
                        if ($prod = $pStmt->fetch()) {
                            $grandTotal += ($prod['unit_price'] * $p['qty']);
                        }
                    }
                }
                
                $invoiceNo = 'INV' . time() . rand(10, 99);
                $ins = $pdo->prepare("INSERT INTO invoices (complaint_id, invoice_no, subtotal, gst_amount, grand_total, payment_status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$complaintId, $invoiceNo, $grandTotal, 0, $grandTotal, $paymentStatus, $paymentMethod]);
                $invoiceId = $pdo->lastInsertId();

                // Record payment in ledger if it's not 'Pay Later'
                if ($paymentStatus === 'Paid') {
                    $payStmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $payStmt->execute([$complaint['customer_id'], $invoiceId, date('Y-m-d H:i:s'), $grandTotal, $paymentMethod, "Payment received by technician for Ticket #$complaintId"]);
                    $success .= " Payment recorded in ledger.";
                }
                
                $success .= " Invoice #$invoiceNo generated.";
            }

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
            <label class="block text-sm font-bold text-slate-700 mb-4 uppercase tracking-wider">Parts Consumed</label>
            <div id="parts-container" class="space-y-3">
                <div class="flex gap-2 part-row">
                    <select name="parts[]" onchange="calculateBill()" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm product-select">
                        <option value="">-- Select Product --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['item_name']); ?> (₹<?php echo $p['unit_price']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="qtys[]" value="1" min="1" oninput="calculateBill()" class="w-20 bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-center text-sm qty-input">
                </div>
            </div>
            <button type="button" onclick="addPartRow()" class="mt-4 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                Add Another Part
            </button>
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

            <div id="payment-method-container" class="hidden pt-4 space-y-4">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Select Payment Method</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col items-center justify-center p-3 border-2 border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-500 has-[:checked]:border-indigo-400">
                        <input type="radio" name="payment_method" value="Cash" checked class="sr-only">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        <span class="text-xs font-bold">Cash</span>
                    </label>
                    <label class="flex flex-col items-center justify-center p-3 border-2 border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-500 has-[:checked]:border-indigo-400">
                        <input type="radio" name="payment_method" value="Bank" class="sr-only">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3a2 2 0 002 2h4a2 2 0 002-2v-3m-10 0V8a2 2 0 012-2h4a2 2 0 012 2v6M4 18h16" /></svg>
                        <span class="text-xs font-bold">Bank / QR</span>
                    </label>
                    <label class="flex flex-col items-center justify-center p-3 border-2 border-white/10 rounded-2xl cursor-pointer hover:bg-white/5 transition has-[:checked]:bg-indigo-500 has-[:checked]:border-indigo-400 col-span-2">
                        <input type="radio" name="payment_method" value="Pay Later" class="sr-only">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="text-xs font-bold uppercase tracking-widest">Pay Later</span>
                    </label>
                </div>
                <p class="text-[10px] text-slate-400 italic">Marking as 'Completed' will automatically generate the invoice.</p>
            </div>
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
    let total = 0;

    rows.forEach(row => {
        const productId = row.querySelector('.product-select').value;
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        
        if (productId && productPrices[productId]) {
            total += (productPrices[productId] * qty);
        }
    });

    document.getElementById('parts-total').innerText = total.toFixed(2);
    document.getElementById('grand-total').innerText = total.toFixed(2);
}

function addPartRow() {
    const container = document.getElementById('parts-container');
    const firstRow = container.querySelector('.part-row');
    const newRow = firstRow.cloneNode(true);
    newRow.querySelector('.product-select').value = "";
    newRow.querySelector('.qty-input').value = "1";
    container.appendChild(newRow);
    calculateBill();
}

// Initial calculation
calculateBill();
updateUI();
</script>

<?php require_once '../includes/footer.php'; ?>
