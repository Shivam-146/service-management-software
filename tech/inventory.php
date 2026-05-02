<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

try {
    $products = $pdo->query("SELECT * FROM products ORDER BY category ASC, item_name ASC")->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Spare Parts & Inventory</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($products as $p): ?>
        <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 flex flex-col justify-between">
            <div>
                 <div class="flex items-center justify-between mb-4">
                    <span class="px-2 py-1 rounded-lg text-[10px] font-black uppercase bg-slate-100 text-slate-500 tracking-wider"><?php echo htmlspecialchars($p['category']); ?></span>
                    <span class="text-indigo-600 font-black">₹<?php echo number_format($p['unit_price'], 2); ?></span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1 leading-tight"><?php echo htmlspecialchars($p['item_name']); ?></h3>
                <div class="flex items-center gap-2 mt-4 mb-4">
                     <div class="flex-1 p-2 bg-slate-50 rounded-xl">
                          <p class="text-[8px] uppercase font-bold text-slate-400 tracking-widest leading-none mb-1">Stock</p>
                          <p class="text-sm font-black <?php echo $p['stock_qty'] < 5 ? 'text-red-500' : 'text-slate-800'; ?>"><?php echo $p['stock_qty']; ?> Units</p>
                     </div>
                     <div class="flex-1 p-2 bg-slate-50 rounded-xl">
                          <p class="text-[8px] uppercase font-bold text-slate-400 tracking-widest leading-none mb-1">Warranty</p>
                          <p class="text-sm font-black text-slate-800"><?php echo $p['warranty_months']; ?>m</p>
                     </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
