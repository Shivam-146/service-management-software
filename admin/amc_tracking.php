<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

// Handle Add AMC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_amc'])) {
    $custId = $_POST['customer_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $amount = $_POST['amount'];
    try {
        $stmt = $pdo->prepare("INSERT INTO amc_contracts (customer_id, start_date, end_date, amount, status) VALUES (?, ?, ?, ?, 'Active')");
        $stmt->execute([$custId, $startDate, $endDate, $amount]);
        
        $upd = $pdo->prepare("UPDATE customers SET has_active_amc = 1 WHERE id = ?");
        $upd->execute([$custId]);
        
        $successMsg = "AMC Contract registered successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Handle Edit AMC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_amc'])) {
    $id = $_POST['amc_id'];
    $custId = $_POST['customer_id'];
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $amount = $_POST['amount'];
    try {
        $stmt = $pdo->prepare("UPDATE amc_contracts SET customer_id=?, start_date=?, end_date=?, amount=? WHERE id=?");
        $stmt->execute([$custId, $startDate, $endDate, $amount, $id]);
        $successMsg = "AMC Contract updated successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch AMC Contracts
try {
    $stmt = $pdo->query("SELECT a.*, c.customer_name, c.phone FROM amc_contracts a JOIN customers c ON a.customer_id = c.id ORDER BY a.end_date ASC");
    $contracts = $stmt->fetchAll();
    
    // Fetch customers for new amc modal
    $custStmt = $pdo->query("SELECT id, customer_name, phone FROM customers ORDER BY customer_name ASC");
    $customers = $custStmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">AMC Tracking</h2>
        <button onclick="openAmcModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">New AMC Contract</button>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">Contract ID</th>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Duration</th>
                        <th class="px-6 py-4 font-semibold">Amount</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Expiry Note</th>
                        <th class="px-6 py-4 font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($contracts)): ?>
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-400">No AMC contracts found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $contract): 
                            $expiryDate = new DateTime($contract['end_date']);
                            $today = new DateTime();
                            $diff = $today->diff($expiryDate);
                            $daysLeft = (int)$diff->format("%r%a");
                            
                            $isExpiringSoon = ($daysLeft >= 0 && $daysLeft <= 15);
                            $isExpired = ($daysLeft < 0);
                        ?>
                        <tr class="hover:bg-slate-50 transition <?php echo $isExpired ? 'bg-red-50/50' : ''; ?>">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $contract['id']; ?></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($contract['customer_name']); ?></div>
                                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($contract['phone']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <?php echo date('M j, Y', strtotime($contract['start_date'])); ?> - 
                                <span class="<?php echo $isExpiringSoon ? 'text-red-600 font-bold' : ''; ?>">
                                    <?php echo date('M j, Y', strtotime($contract['end_date'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">₹<?php echo number_format($contract['amount'], 2); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($isExpired): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-gray-200 text-gray-700">Expired</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($isExpired): ?>
                                    <span class="text-red-600 font-bold flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                                        Expired <?php echo abs($daysLeft); ?> days ago
                                    </span>
                                <?php elseif ($isExpiringSoon): ?>
                                    <span class="text-amber-600 font-bold flex items-center gap-1 animate-pulse">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                                        Expires in <?php echo $daysLeft; ?> days!
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic"><?php echo $daysLeft; ?> days remaining</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <button onclick='openEditAmcModal(<?php echo json_encode($contract, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="text-indigo-600 hover:text-indigo-800 font-medium text-xs flex items-center gap-1 bg-indigo-50 px-3 py-2 rounded-lg w-max transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AMC Modal -->
<div id="amcModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Register AMC Contract</h3>
            <button type="button" onclick="closeAmcModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Customer *</label>
                <select name="customer_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo htmlspecialchars($c['phone']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Start Date *</label>
                    <input type="date" name="start_date" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">End Date *</label>
                    <input type="date" name="end_date" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contract Amount (₹) *</label>
                <input type="number" step="0.01" name="amount" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. 5000.00">
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeAmcModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="add_amc" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Save Contract</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAmcModal() {
    document.getElementById('amcModal').classList.remove('hidden');
}
function closeAmcModal() {
    document.getElementById('amcModal').classList.add('hidden');
}

function openEditAmcModal(contract) {
    document.getElementById('edit_amc_id').value = contract.id;
    document.getElementById('edit_customer_id').value = contract.customer_id;
    document.getElementById('edit_start_date').value = contract.start_date;
    document.getElementById('edit_end_date').value = contract.end_date;
    document.getElementById('edit_amount').value = contract.amount;
    document.getElementById('editAmcModal').classList.remove('hidden');
}
function closeEditAmcModal() {
    document.getElementById('editAmcModal').classList.add('hidden');
}
</script>

<!-- Edit AMC Modal -->
<div id="editAmcModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-slate-900">Update AMC Contract</h3>
            <button type="button" onclick="closeEditAmcModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="amc_id" id="edit_amc_id">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Select Customer *</label>
                <select name="customer_id" id="edit_customer_id" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                    <option value="">-- Choose Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['customer_name']); ?> (<?php echo htmlspecialchars($c['phone']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Start Date *</label>
                    <input type="date" name="start_date" id="edit_start_date" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">End Date *</label>
                    <input type="date" name="end_date" id="edit_end_date" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Contract Amount (₹) *</label>
                <input type="number" step="0.01" name="amount" id="edit_amount" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. 5000.00">
            </div>

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeEditAmcModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="edit_amc" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Update Contract</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
