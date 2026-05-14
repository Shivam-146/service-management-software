<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM income WHERE id = ?");
        $stmt->execute([$id]);
        $successMsg = "Income entry deleted successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error deleting entry: " . $e->getMessage();
    }
}

// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $date = $_POST['income_date'];
    $category = $_POST['category'];
    $source = $_POST['source'];
    $amount = $_POST['amount'];
    $method = $_POST['payment_method'];
    $ref = $_POST['reference_no'];
    $desc = $_POST['description'];

    if (empty($date) || empty($category) || empty($source) || empty($amount)) {
        $errorMsg = "Please fill all required fields.";
    } else {
        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE income SET income_date=?, category=?, source=?, amount=?, payment_method=?, reference_no=?, description=? WHERE id=?");
                $stmt->execute([$date, $category, $source, $amount, $method, $ref, $desc, $id]);
                $successMsg = "Income entry updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO income (income_date, category, source, amount, payment_method, reference_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$date, $category, $source, $amount, $method, $ref, $desc]);
                $successMsg = "Income entry added successfully!";
            }
        } catch (PDOException $e) {
            $errorMsg = "Error saving entry: " . $e->getMessage();
        }
    }
}

// Search and Filter
$search = $_GET['search'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_date = $_GET['date'] ?? '';

$query = "SELECT * FROM income WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (source LIKE ? OR reference_no LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_category) {
    $query .= " AND category = ?";
    $params[] = $filter_category;
}
if ($filter_date) {
    $query .= " AND DATE(income_date) = ?";
    $params[] = $filter_date;
}

// Pagination
$limit = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $query));
$countStmt->execute($params);
$total_rows = $countStmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query .= " ORDER BY income_date DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$incomes = $stmt->fetchAll();

// Summary
$totalIncomeStmt = $pdo->query("SELECT SUM(amount) FROM income");
$total_income = $totalIncomeStmt->fetchColumn() ?: 0;

// Categories for filter/dropdown
$categories = ['Service Fee', 'Product Sale', 'AMC Payment', 'Installation', 'Repair', 'Other'];

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <!-- Header & Summary -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Income Entry</h1>
        </div>
        
        <div class="flex items-center gap-4 w-full md:w-auto">
            <div class="bg-emerald-50 border border-emerald-100 px-6 py-3 rounded-2xl flex items-center gap-4 flex-1 md:flex-none">
                <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center text-white text-xl">💰</div>
                <div>
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Income</p>
                    <p class="text-xl font-black text-emerald-700">₹<?php echo number_format($total_income, 2); ?></p>
                </div>
            </div>
            <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Add Income
            </button>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Filters -->
    <form class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search source, ref..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="w-48">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category</label>
            <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Date</label>
            <input type="date" name="date" value="<?php echo $filter_date; ?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Filter</button>
        <a href="income.php" class="bg-slate-100 text-slate-500 px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-200 transition">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Source</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4">Ref No.</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($incomes)): ?>
                        <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400">No income entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($incomes as $inc): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-medium text-slate-600"><?php echo date('d M, Y h:i A', strtotime($inc['income_date'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-bold uppercase"><?php echo htmlspecialchars($inc['category']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($inc['source']); ?></p>
                                <p class="text-[10px] text-slate-400 truncate max-w-[200px]"><?php echo htmlspecialchars($inc['description']); ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm font-black text-slate-900">₹<?php echo number_format($inc['amount'], 2); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($inc['payment_method']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-400 italic"><?php echo htmlspecialchars($inc['reference_no'] ?: 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='editEntry(<?php echo json_encode($inc); ?>)' class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <a href="?delete=<?php echo $inc['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-slate-50 flex items-center justify-between border-t border-slate-100">
            <p class="text-xs text-slate-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_rows); ?> of <?php echo $total_rows; ?> entries</p>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($filter_category); ?>&date=<?php echo $filter_date; ?>" 
                       class="px-3 py-1 rounded text-xs font-bold transition <?php echo $page === $i ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="incomeModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Add New Income</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="income_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Income Date & Time *</label>
                    <input type="datetime-local" name="income_date" id="income_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category *</label>
                    <select name="category" id="income_category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Source / Payer *</label>
                    <input type="text" name="source" id="income_source" required placeholder="e.g. Reliance JIO, Apartment AMC" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" id="income_amount" required placeholder="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Payment Method</label>
                    <select name="payment_method" id="income_method" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="Cash">Cash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="UPI">UPI</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reference No.</label>
                    <input type="text" name="reference_no" id="income_ref" placeholder="TXN-12345" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                    <textarea name="description" id="income_desc" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">Save Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('incomeModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Add New Income';
    document.getElementById('income_id').value = '';
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('income_date').value = now.toISOString().slice(0, 16);
}

function closeModal() {
    document.getElementById('incomeModal').classList.add('hidden');
}

function editEntry(data) {
    openModal();
    document.getElementById('modalTitle').innerText = 'Edit Income Entry';
    document.getElementById('income_id').value = data.id;
    document.getElementById('income_date').value = data.income_date.replace(' ', 'T').slice(0, 16);
    document.getElementById('income_category').value = data.category;
    document.getElementById('income_source').value = data.source;
    document.getElementById('income_amount').value = data.amount;
    document.getElementById('income_method').value = data.payment_method;
    document.getElementById('income_ref').value = data.reference_no;
    document.getElementById('income_desc').value = data.description;
}
</script>

<?php require_once '../../includes/footer.php'; ?>
