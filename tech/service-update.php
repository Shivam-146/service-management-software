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
    
    // Fetch products that this technician has in stock (either serialized or quantity-based) with latest purchase price
    $productsRes = $pdo->prepare("
        (SELECT p.id, p.product_name as item_name, p.product_code,
         (SELECT pi.taxable_value FROM purchase_items pi WHERE pi.product_id = p.id ORDER BY pi.id DESC LIMIT 1) as unit_price, 
         p.gst_rate FROM products p 
         JOIN technician_stock ts ON p.id = ts.product_id 
         WHERE ts.technician_id = ? AND ts.quantity > 0)
        UNION
        (SELECT DISTINCT p.id, p.product_name as item_name, p.product_code,
         (SELECT pi.taxable_value FROM purchase_items pi WHERE pi.product_id = p.id ORDER BY pi.id DESC LIMIT 1) as unit_price, 
         p.gst_rate FROM products p 
         JOIN product_serials ps ON p.id = ps.product_id 
         WHERE ps.technician_id = ? AND ps.status = 'In Stock')
    ");
    $productsRes->execute([$techId, $techId]);
    $products = $productsRes->fetchAll();
    
    // For JS calculations
    $productData = [];
    foreach ($products as $p) {
        $productData[$p['id']] = ['price' => (float)$p['unit_price'], 'gst' => (float)$p['gst_rate']];
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
                $totalSubtotal = 0;
                $totalGst = 0;
                $grandTotal = 0;
                $selectedSerials = $_POST['serial_ids'] ?? [];
                $serialPrices = [];
                $gstMode = $_POST['gst_mode'] ?? 'Exclusive';
                
                // 1. Get prices for all selected serials
                if (!empty($selectedSerials)) {
                    $placeholders = implode(',', array_fill(0, count($selectedSerials), '?'));
                    $sStmt = $pdo->prepare("SELECT s.id, s.product_id, s.purchase_price, p.gst_rate FROM product_serials s JOIN products p ON s.product_id = p.id WHERE s.id IN ($placeholders)");
                    $sStmt->execute($selectedSerials);
                    while ($sRow = $sStmt->fetch()) {
                        $sPrice = (float)$sRow['purchase_price'];
                        $itemGstRate = (float)$sRow['gst_rate'];
                        
                        $lineSub = 0; $lineGst = 0; $lineTotal = 0;
                        if ($gstMode === 'Inclusive') {
                            $lineTotal = $sPrice;
                            $lineSub = $lineTotal / (1 + ($itemGstRate / 100));
                            $lineGst = $lineTotal - $lineSub;
                        } else {
                            $lineSub = $sPrice;
                            $lineGst = $lineSub * ($itemGstRate / 100);
                            $lineTotal = $lineSub + $lineGst;
                        }

                        $totalSubtotal += $lineSub;
                        $totalGst += $lineGst;
                        $grandTotal += $lineTotal;

                        $serialPrices[$sRow['product_id']] = ($serialPrices[$sRow['product_id']] ?? 0) + 1;
                        
                        // Update Serial status and record taxable price
                        $updSerial = $pdo->prepare("UPDATE product_serials SET status = 'Sold', sale_price = ?, invoice_id = 0, sold_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $updSerial->execute([$lineSub, $sRow['id']]);
                    }
                }

                // 2. Add prices for remaining un-serialized quantities
                if (!empty($partsData)) {
                    foreach ($partsData as $p) {
                        $pStmt = $pdo->prepare("SELECT unit_price, gst_rate FROM products WHERE id = ?");
                        $pStmt->execute([$p['product_id']]);
                        if ($prod = $pStmt->fetch()) {
                            $unitPrice = (float)$prod['unit_price'];
                            $itemGstRate = (float)$prod['gst_rate'];
                            $serialsUsed = $serialPrices[$p['product_id']] ?? 0;
                            $remainingQty = max(0, (int)$p['qty'] - $serialsUsed);
                            
                            if ($remainingQty > 0) {
                                $basePrice = ($unitPrice * $remainingQty);
                                
                                $lineSub = 0; $lineGst = 0; $lineTotal = 0;
                                if ($gstMode === 'Inclusive') {
                                    $lineTotal = $basePrice;
                                    $lineSub = $lineTotal / (1 + ($itemGstRate / 100));
                                    $lineGst = $lineTotal - $lineSub;
                                } else {
                                    $lineSub = $basePrice;
                                    $lineGst = $lineSub * ($itemGstRate / 100);
                                    $lineTotal = $lineSub + $lineGst;
                                }

                                $totalSubtotal += $lineSub;
                                $totalGst += $lineGst;
                                $grandTotal += $lineTotal;
                            }
                        }
                    }
                }
                
                $invoiceNo = 'INV' . time() . rand(10, 99);
                $ins = $pdo->prepare("INSERT INTO invoices (complaint_id, customer_id, invoice_no, subtotal, gst_amount, grand_total, payment_status, payment_method, gst_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$complaintId, $complaint['customer_id'], $invoiceNo, $totalSubtotal, $totalGst, $grandTotal, $paymentStatus, $paymentMethod, $gstMode]);
                $invoiceId = $pdo->lastInsertId();

                // 5. Correct Link Serials to Invoice
                if (!empty($selectedSerials)) {
                    $link = $pdo->prepare("UPDATE product_serials SET invoice_id = ? WHERE id IN ($placeholders)");
                    $link->execute(array_merge([$invoiceId], $selectedSerials));
                }

                // Record payment in ledger or record outflow if credit
                if ($paymentStatus === 'Paid') {
                    $payStmt = $pdo->prepare("INSERT INTO payments (customer_id, invoice_id, payment_date, amount, payment_method, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $payStmt->execute([$complaint['customer_id'], $invoiceId, date('Y-m-d H:i:s'), $grandTotal, $paymentMethod, "Payment received by technician for Ticket #$complaintId"]);
                    $success .= " Payment recorded in ledger.";
                } else if ($paymentMethod === 'Pay Later') {
                    // Add to Outflow (Purchases table)
                    $custStmt = $pdo->prepare("SELECT customer_name FROM customers WHERE id = ?");
                    $custStmt->execute([$complaint['customer_id']]);
                    $custName = $custStmt->fetchColumn();
                    
                    $outflow = $pdo->prepare("INSERT INTO purchases (purchase_date, payee_name, category, subtotal, gst_amount, total_amount, payment_method, is_inventory, notes) VALUES (?, ?, 'Credit Service', ?, ?, ?, 'Pay Later', 0, ?)");
                    $outflow->execute([date('Y-m-d'), $custName, $totalSubtotal, $totalGst, $grandTotal, "Credit service for Ticket #$complaintId"]);
                    
                    // Add to Customer Due
                    $updDue = $pdo->prepare("UPDATE customers SET due_amount = due_amount + ? WHERE id = ?");
                    $updDue->execute([$grandTotal, $complaint['customer_id']]);
                    $success .= " Credit recorded in outstanding due.";
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

<div class="max-w-7xl mx-auto px-4 pb-12">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4 bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="p-2 bg-slate-50 text-slate-400 hover:text-indigo-600 rounded-xl transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">Service Update</h2>
                <p class="text-xs text-slate-500 font-bold uppercase tracking-widest">Ticket #<?php echo $complaintId; ?></p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase tracking-widest rounded-full">Technician Portal</span>
            <div class="h-8 w-px bg-slate-100"></div>
            <div class="text-right">
                <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($_SESSION['fullname'] ?? 'Technician'); ?></p>
                <p class="text-[9px] font-bold text-slate-400 uppercase">On-Site Tech</p>
            </div>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-6 py-4 rounded-3xl mb-8 flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="text-sm font-bold"><?php echo $success; ?></span>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="bg-rose-50 border border-rose-100 text-rose-700 px-6 py-4 rounded-3xl mb-8 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <span class="text-sm font-bold"><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Job Details & Status -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Job Info Card -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 opacity-5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-32 w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                </div>
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-2">Customer Information</h3>
                        <p class="text-2xl font-black text-slate-800"><?php echo htmlspecialchars($complaint['customer_name']); ?></p>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 bg-slate-900 text-white text-[9px] font-black uppercase tracking-widest rounded-full mb-2">Issue Reported</span>
                        <p class="text-sm font-medium text-slate-500 max-w-xs ml-auto italic">"<?php echo htmlspecialchars($complaint['issue_description']); ?>"</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t border-slate-50">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl">
                        <div class="p-2 bg-white rounded-xl shadow-sm text-indigo-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase">Created</p>
                            <p class="text-xs font-black text-slate-700"><?php echo date('d M, Y', strtotime($complaint['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl">
                        <div class="p-2 bg-white rounded-xl shadow-sm text-emerald-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase">Location</p>
                            <p class="text-xs font-black text-slate-700 truncate w-32">On-Site Service</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-2xl">
                        <div class="p-2 bg-white rounded-xl shadow-sm text-rose-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase">Support ID</p>
                            <p class="text-xs font-black text-slate-700">#<?php echo rand(1000, 9999); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Service Status -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Current Service Phase</h3>
                <div class="grid grid-cols-2 gap-4">
                    <label id="label-in-progress" class="relative flex flex-col items-center p-6 border-2 rounded-2xl cursor-pointer transition-all duration-300 has-[:checked]:bg-indigo-50/50 has-[:checked]:border-indigo-500 group">
                        <input type="radio" name="status" value="In-Progress" class="sr-only" onchange="updateUI()" <?php echo $complaint['status'] === 'In-Progress' ? 'checked' : ''; ?>>
                        <div class="p-3 bg-white rounded-full shadow-sm mb-3 group-has-[:checked]:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                        </div>
                        <span class="text-sm font-black text-indigo-900 mb-1">In-Progress</span>
                        <p class="text-[10px] font-bold text-slate-400 text-center">Service started, still working</p>
                    </label>
                    <label id="label-completed" class="relative flex flex-col items-center p-6 border-2 rounded-2xl cursor-pointer transition-all duration-300 has-[:checked]:bg-green-50/50 has-[:checked]:border-green-500 group">
                        <input type="radio" name="status" value="Completed" class="sr-only" onchange="updateUI()" <?php echo $complaint['status'] === 'Completed' ? 'checked' : ''; ?>>
                        <div class="p-3 bg-white rounded-full shadow-sm mb-3 group-has-[:checked]:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <span class="text-sm font-black text-green-900 mb-1">Job Completed</span>
                        <p class="text-[10px] font-bold text-slate-400 text-center">Work done, generate invoice</p>
                    </label>
                </div>
            </div>

            <!-- Technical Remarks -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Technician Field Notes</h3>
                <div class="relative">
                    <textarea name="remarks" rows="5" class="w-full bg-slate-50 border border-slate-200 rounded-3xl px-6 py-5 outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 text-sm font-medium transition placeholder:text-slate-300" placeholder="Describe the problem found, solution applied, and any advice for the customer..."><?php echo htmlspecialchars($complaint['tech_remarks'] ?? ''); ?></textarea>
                    <div class="absolute bottom-4 right-6 text-[9px] font-black text-slate-300 uppercase tracking-widest pointer-events-none">Visible on Invoice</div>
                </div>
            </div>

            <!-- Photos -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-6">Visual Proof</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="group">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Before Service</label>
                        <div class="relative h-40 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 hover:border-indigo-300 transition-all flex items-center justify-center overflow-hidden">
                            <?php if ($complaint['photo_before']): ?>
                                <img src="<?php echo $complaint['photo_before']; ?>" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                    <span class="text-[10px] font-black text-white uppercase tracking-widest">Change Photo</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-[9px] font-black text-slate-400 uppercase">Upload Before</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="photo_before" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                    </div>
                    <div class="group">
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">After Service</label>
                        <div class="relative h-40 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200 hover:border-green-300 transition-all flex items-center justify-center overflow-hidden">
                            <?php if ($complaint['photo_after']): ?>
                                <img src="<?php echo $complaint['photo_after']; ?>" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                    <span class="text-[10px] font-black text-white uppercase tracking-widest">Change Photo</span>
                                </div>
                            <?php else: ?>
                                <div class="text-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-slate-300 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    <span class="text-[9px] font-black text-slate-400 uppercase">Upload After</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="photo_after" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Parts & Billing -->
        <div class="space-y-8">
            <!-- Parts Consumed Card -->
            <div class="bg-white p-8 rounded-[2rem] border border-slate-100 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.2em]">Inventory Log</h3>
                    <div class="flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                        <span class="text-[9px] font-black text-indigo-500 uppercase">Tech Stock</span>
                    </div>
                </div>

                <div id="parts-container" class="space-y-4">
                    <?php 
                    $existingParts = json_decode($complaint['parts_consumed'], true) ?: [['product_id' => '', 'qty' => 1]];
                    foreach ($existingParts as $index => $ep): 
                    ?>
                    <!-- Dynamic Part Row -->
                    <div class="p-5 bg-slate-50 border border-slate-100 rounded-2xl part-row relative group">
                        <div class="flex flex-col gap-3">
                            <div class="relative">
                                <select name="parts[]" onchange="loadSerials(this); calculateBill();" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 text-sm product-select font-bold appearance-none">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['unit_price']; ?>" <?php echo ($ep['product_id'] == $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['item_name']); ?> (₹<?php echo number_format($p['unit_price'], 2); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex-1 relative">
                                    <input type="number" name="qtys[]" value="<?php echo $ep['qty']; ?>" min="1" oninput="calculateBill()" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-black outline-none focus:ring-2 focus:ring-indigo-500 qty-input">
                                    <span class="absolute -top-2 left-3 bg-white px-1 text-[8px] font-black text-slate-400 uppercase">Qty</span>
                                </div>
                                <button type="button" onclick="removePartRow(this)" class="p-3 bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white rounded-xl transition-all <?php echo (count($existingParts) > 1 || $index > 0) ? '' : 'opacity-0'; ?> remove-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Serial Selection -->
                        <div class="serial-selection-area hidden mt-4 pt-4 border-t border-slate-200/30">
                            <label class="block text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3">Serial Allocation</label>
                            <div class="serial-checkboxes grid grid-cols-1 gap-2 max-h-40 overflow-y-auto pr-2 custom-scrollbar"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addPartRow()" class="mt-4 w-full py-4 border-2 border-dashed border-slate-200 rounded-3xl text-[10px] font-black text-slate-400 hover:border-indigo-300 hover:text-indigo-600 hover:bg-indigo-50/50 transition flex items-center justify-center gap-2 uppercase tracking-[0.2em]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    Add Product
                </button>
            </div>

            <!-- Billing Sidebar Card -->
            <div id="billing-sidebar" class="bg-slate-900 text-white p-8 rounded-[2rem] shadow-2xl shadow-slate-200 sticky top-8 border border-white/5 overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                
                <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-8 pb-4 border-b border-white/10">Summary Estimate</h3>
                
                <div id="summary-content" class="space-y-4 mb-10 min-h-[100px]">
                    <div id="summary-placeholder" class="text-center py-6">
                        <p class="text-[10px] font-bold text-slate-500 italic">No items added yet</p>
                    </div>
                    <table class="w-full text-xs hidden" id="summary-table">
                        <tbody id="summary-table-body" class="divide-y divide-white/5"></tbody>
                    </table>
                </div>

                <!-- Completed Only: Tax & Payment -->
                <div id="payment-method-container" class="space-y-6 pt-6 border-t border-white/10 <?php echo $complaint['status'] === 'Completed' ? '' : 'hidden'; ?>">
                    <div>
                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3">Billing Mode</label>
                        <select name="gst_mode" id="gst_mode" onchange="calculateBill()" class="w-full bg-slate-800 border border-white/10 rounded-xl px-4 py-3 outline-none text-[10px] font-black text-white appearance-none uppercase tracking-widest">
                            <option value="Exclusive" selected>Exclusive (Base + GST)</option>
                            <option value="Inclusive">Inclusive (MRP Only)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3">Payment Method</label>
                        <div class="grid grid-cols-2 gap-2">
                            <?php foreach(['Cash', 'UPI', 'Pay Later'] as $method): ?>
                                <label class="flex items-center justify-center p-3 bg-slate-800/50 border border-white/5 rounded-xl cursor-pointer hover:bg-indigo-600 hover:border-indigo-400 transition-all has-[:checked]:bg-indigo-600 has-[:checked]:border-indigo-400 <?php echo $method === 'Pay Later' ? 'col-span-2' : ''; ?>">
                                    <input type="radio" name="payment_method" value="<?php echo $method; ?>" <?php echo $method === 'Cash' ? 'checked' : ''; ?> class="sr-only">
                                    <span class="text-[9px] font-black uppercase tracking-tighter"><?php echo $method; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="space-y-2 mt-10 pt-6 border-t border-white/20">
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-slate-400 font-bold uppercase tracking-widest">Parts Amount</span>
                        <span class="font-black">₹<span id="parts-total">0.00</span></span>
                    </div>
                    <div class="flex justify-between items-center pt-2">
                        <span class="text-slate-400 font-bold uppercase tracking-widest text-lg">Total</span>
                        <div class="text-right">
                            <span class="text-3xl font-black text-emerald-400 leading-none block">₹<span id="grand-total">0.00</span></span>
                            <span class="text-[8px] font-bold text-slate-500 uppercase">Live Calculation</span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="mt-8 w-full py-5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-3xl font-black text-sm uppercase tracking-[0.2em] shadow-xl shadow-indigo-900/50 transition-all transform active:scale-95 flex items-center justify-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                    Save & Update Job
                </button>
            </div>
        </div>
    </form>
</div>

<script>
const productData = <?php echo json_encode($productData); ?>;

function updateUI() {
    const inProgressInput = document.querySelector('input[value="In-Progress"]');
    const paymentContainer = document.getElementById('payment-method-container');
    if (inProgressInput.checked) {
        paymentContainer.classList.add('hidden');
    } else {
        paymentContainer.classList.remove('hidden');
    }
}

function calculateBill() {
    const rows = document.querySelectorAll('.part-row');
    const summaryTable = document.getElementById('summary-table');
    const summaryTableBody = document.getElementById('summary-table-body');
    const summaryPlaceholder = document.getElementById('summary-placeholder');
    const gstMode = document.getElementById('gst_mode').value;
    
    let subtotalSum = 0;
    let gstAmountSum = 0;
    let grandTotalSum = 0;
    let summaryHtml = '';

    rows.forEach(row => {
        const productSelect = row.querySelector('.product-select');
        const productId = productSelect.value;
        const productName = productSelect.options[productSelect.selectedIndex]?.text.split(' (₹')[0] || '';
        const qty = parseInt(row.querySelector('.qty-input').value) || 0;
        
        if (productId && productData[productId]) {
            const itemGstRate = productData[productId].gst;
            const itemUnitPrice = productData[productId].price;
            let rowSubtotal = 0;
            let rowGst = 0;
            let rowTotal = 0;

            const selectedSerials = row.querySelectorAll('.serial-checkbox:checked');
            
            if (selectedSerials.length > 0) {
                summaryHtml += `
                    <tr class="font-bold border-t border-white/5">
                        <td class="py-3 text-indigo-400 uppercase tracking-widest text-[9px]" colspan="3">${productName}</td>
                    </tr>
                `;
                
                selectedSerials.forEach(checkbox => {
                    const serialLabel = checkbox.closest('label');
                    const serialNumber = serialLabel.querySelector('.serial-no-text').innerText;
                    const serialPrice = parseFloat(serialLabel.getAttribute('data-price')) || 0;
                    
                    let lineSub = 0, lineGst = 0, lineTotal = 0;
                    if (gstMode === 'Inclusive') {
                        lineTotal = serialPrice;
                        lineSub = lineTotal / (1 + (itemGstRate / 100));
                        lineGst = lineTotal - lineSub;
                    } else {
                        lineSub = serialPrice;
                        lineGst = lineSub * (itemGstRate / 100);
                        lineTotal = lineSub + lineGst;
                    }

                    rowSubtotal += lineSub;
                    rowGst += lineGst;
                    rowTotal += lineTotal;
                    
                    summaryHtml += `
                        <tr class="text-indigo-300 italic border-l-2 border-indigo-500/30">
                            <td class="py-1 pl-4 text-[9px]">└ SN: ${serialNumber}</td>
                            <td class="py-1 text-right text-[9px]">1</td>
                            <td class="py-1 text-right text-[9px]">₹${serialPrice.toFixed(2)}</td>
                        </tr>
                    `;
                });

                const remainingQty = qty - selectedSerials.length;
                if (remainingQty > 0) {
                    const basePrice = remainingQty * itemUnitPrice;
                    let lineSub = 0, lineGst = 0, lineTotal = 0;
                    if (gstMode === 'Inclusive') {
                        lineTotal = basePrice;
                        lineSub = lineTotal / (1 + (itemGstRate / 100));
                        lineGst = lineTotal - lineSub;
                    } else {
                        lineSub = basePrice;
                        lineGst = lineSub * (itemGstRate / 100);
                        lineTotal = lineSub + lineGst;
                    }

                    rowSubtotal += lineSub;
                    rowGst += lineGst;
                    rowTotal += lineTotal;

                    summaryHtml += `
                        <tr class="text-slate-500 border-l-2 border-slate-500/30">
                            <td class="py-1 pl-4 text-[9px]">└ Bulk Stock</td>
                            <td class="py-1 text-right text-[9px]">x${remainingQty}</td>
                            <td class="py-1 text-right text-[9px]">₹${basePrice.toFixed(2)}</td>
                        </tr>
                    `;
                }
            } else if (qty > 0) {
                const basePrice = itemUnitPrice * qty;
                if (gstMode === 'Inclusive') {
                    rowTotal = basePrice;
                    rowSubtotal = rowTotal / (1 + (itemGstRate / 100));
                    rowGst = rowTotal - rowSubtotal;
                } else {
                    rowSubtotal = basePrice;
                    rowGst = rowSubtotal * (itemGstRate / 100);
                    rowTotal = rowSubtotal + rowGst;
                }
                summaryHtml += `
                    <tr class="font-bold border-t border-white/5">
                        <td class="py-3 text-[9px] uppercase tracking-tighter">${productName}</td>
                        <td class="py-3 text-right text-[9px]">x${qty}</td>
                        <td class="py-3 text-right text-[9px]">₹${basePrice.toFixed(2)}</td>
                    </tr>
                `;
            }
            
            subtotalSum += rowSubtotal;
            gstAmountSum += rowGst;
            grandTotalSum += rowTotal;
        }
    });

    if (grandTotalSum > 0) {
        summaryTable.classList.remove('hidden');
        summaryPlaceholder.classList.add('hidden');
        summaryTableBody.innerHTML = summaryHtml;
        document.querySelectorAll('#parts-total').forEach(el => el.innerText = subtotalSum.toLocaleString('en-IN', {minimumFractionDigits: 2}));
        document.querySelectorAll('#grand-total').forEach(el => el.innerText = grandTotalSum.toLocaleString('en-IN', {minimumFractionDigits: 2}));
    } else {
        summaryTable.classList.add('hidden');
        summaryPlaceholder.classList.remove('hidden');
        document.querySelectorAll('#parts-total').forEach(el => el.innerText = "0.00");
        document.querySelectorAll('#grand-total').forEach(el => el.innerText = "0.00");
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
    
    const removeBtn = newRow.querySelector('.remove-btn');
    if (removeBtn) removeBtn.classList.remove('opacity-0');
    
    container.appendChild(newRow);
    calculateBill();
}

function removePartRow(btn) {
    const container = document.getElementById('parts-container');
    if (container.querySelectorAll('.part-row').length > 1) {
        btn.closest('.part-row').remove();
        calculateBill();
    } else {
        alert("At least one product row is required.");
    }
}

// Initial calculation
document.querySelectorAll('.product-select').forEach(sel => {
    if (sel.value) loadSerials(sel);
});
calculateBill();
updateUI();
</script>

<?php require_once '../includes/footer.php'; ?>
