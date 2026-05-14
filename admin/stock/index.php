<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

// Fetch Statistics
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM stock_categories")->fetchColumn();
$lowStockItems = $pdo->query("SELECT COUNT(*) FROM products WHERE current_stock < opening_stock")->fetchColumn();
$recentPurchases = $pdo->query("SELECT COUNT(*) FROM purchases WHERE purchase_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

require_once '../../includes/header.php';
?>

<div class="space-y-8">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Stock Management</h1>
            <p class="text-slate-500 font-medium">Inventory control, purchase tracking & stock movements</p>
        </div>
        <div class="text-right">
            <span class="px-4 py-2 bg-indigo-50 text-indigo-600 rounded-full text-xs font-bold uppercase tracking-widest">v2.0 Inventory Engine</span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Products</p>
            <div class="flex items-center justify-between">
                <h3 class="text-3xl font-black text-slate-800"><?php echo $totalProducts; ?></h3>
                <div class="w-10 h-10 bg-indigo-50 text-indigo-500 rounded-xl flex items-center justify-center">📦</div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Categories</p>
            <div class="flex items-center justify-between">
                <h3 class="text-3xl font-black text-slate-800"><?php echo $totalCategories; ?></h3>
                <div class="w-10 h-10 bg-emerald-50 text-emerald-500 rounded-xl flex items-center justify-center">📁</div>
            </div>
        </div>
        <div class="bg-rose-50 p-6 rounded-3xl border border-rose-100 shadow-sm hover:shadow-md transition">
            <p class="text-[10px] font-bold text-rose-400 uppercase tracking-widest mb-1">Below Opening Stock</p>
            <div class="flex items-center justify-between">
                <h3 class="text-3xl font-black text-rose-600"><?php echo $lowStockItems; ?></h3>
                <div class="w-10 h-10 bg-rose-500 text-white rounded-xl flex items-center justify-center font-bold">!</div>
            </div>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Recent Purchases</p>
            <div class="flex items-center justify-between">
                <h3 class="text-3xl font-black text-slate-800"><?php echo $recentPurchases; ?></h3>
                <div class="w-10 h-10 bg-slate-100 text-slate-500 rounded-xl flex items-center justify-center">🛒</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <a href="products.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-indigo-600 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">📦</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Product Inventory</h3>
            <p class="text-sm text-slate-500">Manage products, opening stock & current inventory.</p>
        </a>
        <a href="categories.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">📁</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Stock Categories</h3>
            <p class="text-sm text-slate-500">Organize products into categories for better tracking.</p>
        </a>
        <a href="../accounts/purchases.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-amber-500 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">📥</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Purchase Entry</h3>
            <p class="text-sm text-slate-500">Record stock-in from suppliers & generate purchase bills.</p>
        </a>
        <a href="serials.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-slate-800 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">🔢</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Serial Tracking</h3>
            <p class="text-sm text-slate-500">Track individual product serial numbers and their history.</p>
        </a>
        <a href="movements.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-rose-600 text-white rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">🔄</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Stock Movements</h3>
            <p class="text-sm text-slate-500">View detailed history of stock in, stock out & adjustments.</p>
        </a>
        <a href="reports.php" class="group bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-300">
            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition">📊</div>
            <h3 class="text-lg font-bold text-slate-800 mb-1">Download Stock Reports</h3>
            <p class="text-sm text-slate-500">Export inventory data, low stock lists & valuation.</p>
        </a>
    </div>

    <!-- Low Stock Table (Preview) -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-slate-800">Below Opening Stock</h3>
            <a href="products.php?filter=low" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-widest">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Product Name</th>
                        <th class="px-6 py-4">Code</th>
                        <th class="px-6 py-4 text-center">Opening Stock</th>
                        <th class="px-6 py-4 text-center">Closing Stock</th>
                        <th class="px-6 py-4 text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php
                    $lowStock = $pdo->query("SELECT * FROM products WHERE current_stock <= opening_stock LIMIT 5")->fetchAll();
                    if (empty($lowStock)):
                    ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">All products are within healthy stock levels.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStock as $p): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['product_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($p['product_code']); ?></td>
                            <td class="px-6 py-4 text-center text-sm text-slate-400"><?php echo $p['opening_stock']; ?></td>
                            <td class="px-6 py-4 text-center text-sm font-black text-rose-600"><?php echo $p['current_stock']; ?> <?php echo $p['unit']; ?></td>
                            <td class="px-6 py-4 text-right">
                                <span class="px-3 py-1 bg-rose-100 text-rose-600 rounded-full text-[10px] font-black uppercase">Attention</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
