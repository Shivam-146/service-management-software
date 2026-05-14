<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

// Search and Filter
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

$query = "SELECT m.*, p.product_name, p.product_code 
          FROM stock_movements m 
          JOIN products p ON m.product_id = p.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($type) {
    $query .= " AND m.type = ?";
    $params[] = $type;
}

// Pagination
$limit = 20;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $limit;

$countStmt = $pdo->prepare(str_replace("SELECT m.*, p.product_name, p.product_code", "SELECT COUNT(*)", $query));
$countStmt->execute($params);
$total_rows = $countStmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$query .= " ORDER BY m.created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Stock Movement History</h1>
    </div>

    <!-- Filters -->
    <form class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search Product</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name or code..." class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="w-48">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Movement Type</label>
            <select name="type" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Types</option>
                <option value="Stock In" <?php echo $type === 'Stock In' ? 'selected' : ''; ?>>Stock In</option>
                <option value="Stock Out" <?php echo $type === 'Stock Out' ? 'selected' : ''; ?>>Stock Out</option>
                <option value="Adjustment" <?php echo $type === 'Adjustment' ? 'selected' : ''; ?>>Adjustment</option>
            </select>
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Filter</button>
        <a href="movements.php" class="bg-slate-100 text-slate-500 px-6 py-2 rounded-lg text-sm font-bold hover:bg-slate-200 transition">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date & Time</th>
                        <th class="px-6 py-4">Product</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4 text-center">Quantity</th>
                        <th class="px-6 py-4">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">No stock movements recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?></td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($m['product_name']); ?></p>
                                <p class="text-[10px] text-slate-400 font-mono"><?php echo htmlspecialchars($m['product_code']); ?></p>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($m['type'] === 'Stock In'): ?>
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-600 rounded text-[10px] font-black uppercase">Stock In</span>
                                <?php elseif ($m['type'] === 'Stock Out'): ?>
                                    <span class="px-2 py-1 bg-rose-100 text-rose-600 rounded text-[10px] font-black uppercase">Stock Out</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-amber-100 text-amber-600 rounded text-[10px] font-black uppercase">Adjustment</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center text-sm font-black <?php echo $m['type'] === 'Stock In' ? 'text-emerald-600' : 'text-rose-600'; ?>">
                                <?php echo $m['type'] === 'Stock In' ? '+' : '-'; ?> <?php echo $m['quantity']; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500 italic"><?php echo htmlspecialchars($m['notes'] ?: '---'); ?></td>
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
                    <a href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($type); ?>" 
                       class="px-3 py-1 rounded text-xs font-bold transition <?php echo $page === $i ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
