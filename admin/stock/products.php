<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$successMsg = $errorMsg = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $successMsg = "Product deleted successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Cannot delete product as it has linked movements or purchases.";
    }
}

// Handle Add/Update
// Handle Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['adjust_stock'])) {
    $id = $_POST['id'] ?? null;
    $cat_id = ($_POST['category_id'] ?? null) ?: null;
    $name = trim($_POST['product_name'] ?? '');
    $code = !empty(trim($_POST['product_code'])) ? trim($_POST['product_code']) : null;
    $unit = $_POST['unit'] ?? 'Pcs';
    $min = $_POST['opening_stock'] ?? 0;
    $price = $_POST['unit_price'] ?? 0; // Added this line
    $desc = $_POST['description'] ?? null;

    if ($name) {
        try {
            if ($id) {
                $old_prod = $pdo->prepare("SELECT opening_stock FROM products WHERE id = ?");
                $old_prod->execute([$id]);
                $old_opening = $old_prod->fetchColumn() ?: 0;
                $diff = $min - $old_opening;

                // Added unit_price = ? to the UPDATE query
                $stmt = $pdo->prepare("UPDATE products SET category_id=?, product_name=?, product_code=?, unit=?, opening_stock=?, unit_price=?, current_stock = current_stock + ?, description=? WHERE id=?");
                $stmt->execute([$cat_id, $name, $code, $unit, $min, $price, $diff, $desc, $id]);
                $successMsg = "Product updated successfully!";
            } else {
                // Added unit_price and ? to the INSERT query
                $stmt = $pdo->prepare("INSERT INTO products (category_id, product_name, product_code, unit, opening_stock, unit_price, current_stock, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$cat_id, $name, $code, $unit, $min, $price, $min, $desc]);
                $successMsg = "Product added successfully!";
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errorMsg = "Product code already exists.";
            } else {
                $errorMsg = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $id = $_POST['prod_id'];
    $qty = $_POST['adj_qty'];
    $type = $_POST['adj_type'];
    $notes = $_POST['adj_notes'];
    
    try {
        $pdo->beginTransaction();
        
        $sign = ($type === 'Add') ? '+' : '-';
        $move_type = ($type === 'Add') ? 'Stock In' : 'Stock Out';
        
        $stmt = $pdo->prepare("UPDATE products SET current_stock = current_stock $sign ? WHERE id = ?");
        $stmt->execute([$qty, $id]);
        
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id, 'Adjustment', $qty, "Manual Adjustment ($type): $notes"]);
        
        $pdo->commit();
        $successMsg = "Stock adjusted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMsg = "Error adjusting stock.";
    }
}

// Search and Filter
$search = $_GET['search'] ?? '';
$filter_cat = $_GET['category'] ?? '';
$filter_low = isset($_GET['low']) ? true : false;

$query = "SELECT p.*, c.category_name FROM products p LEFT JOIN stock_categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (p.product_name LIKE ? OR p.product_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter_cat) {
    $query .= " AND p.category_id = ?";
    $params[] = $filter_cat;
}
if ($filter_low) {
    $query .= " AND p.current_stock < p.opening_stock";
}

$products = $pdo->prepare($query);
$products->execute($params);
$products = $products->fetchAll();

$categories = $pdo->query("SELECT * FROM stock_categories ORDER BY category_name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-none text-slate-500 hover:text-indigo-600 transition shadow-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Product Inventory</h1>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 text-white px-6 py-3 rounded-none font-bold hover:bg-indigo-700 transition shadow-none shadow-none flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
            Add Product
        </button>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-none"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-none"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <!-- Search & Filters -->
    <form class="bg-white p-4 rounded-none border border-slate-100 shadow-none flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Search Products</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Product name or SKU..." class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div class="w-48">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category</label>
            <select name="category" class="w-full bg-slate-50 border border-slate-200 rounded-none px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo $filter_cat == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2 mb-2">
            <input type="checkbox" name="low" id="low_stock" value="1" <?php echo $filter_low ? 'checked' : ''; ?> class="w-4 h-4 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500">
            <label for="low_stock" class="text-xs font-bold text-slate-600 uppercase">Below Opening Stock</label>
        </div>
        <button type="submit" class="bg-slate-800 text-white px-6 py-2 rounded-none text-sm font-bold hover:bg-slate-900 transition">Filter</button>
        <a href="products.php" class="bg-slate-100 text-slate-500 px-6 py-2 rounded-none text-sm font-bold hover:bg-slate-200 transition">Reset</a>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-none border border-slate-100 shadow-none overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Product Name</th>
                        <th class="text-[10px] font-bold text-indigo-500 uppercase">HSN Code</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4 text-center">Opening Stock</th>
                        <th class="px-6 py-4 text-center">Closing Stock</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($products)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['product_name']); ?></p>
                            </td>
                            <td class="text-[10px] font-bold text-indigo-500 uppercase"><?php echo htmlspecialchars($p['product_code']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($p['category_name'] ?: 'Uncategorized'); ?></td>
                            <td class="px-6 py-4 text-center text-sm text-slate-400"><?php echo $p['opening_stock']; ?></td>
                             <td class="px-6 py-4 text-center">
                                 <?php if ($p['current_stock'] < $p['opening_stock']): ?>
                                     <span class="px-2 py-1 bg-rose-500 text-white text-[10px] font-black uppercase tracking-wider rounded-none">
                                         <?php echo $p['current_stock']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                                     </span>
                                 <?php else: ?>
                                     <span class="text-sm font-bold text-slate-900">
                                         <?php echo $p['current_stock']; ?> <?php echo htmlspecialchars($p['unit']); ?>
                                     </span>
                                 <?php endif; ?>
                             </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick='openAdjustModal(<?php echo json_encode($p); ?>)' class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-none transition" title="Adjust Stock">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                                    </button>
                                    <button onclick='editProduct(<?php echo json_encode($p); ?>)' class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-none transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </button>
                                    <a href="?delete=<?php echo $p['id']; ?>" onclick="return confirm('Are you sure?')" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-none transition">
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
    </div>
</div>

<!-- Modal -->
<div id="productModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-xl overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-xl font-bold text-slate-800">Add Product</h2>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="id" id="prod_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Product Name *</label>
                    <input type="text" name="product_name" id="prod_name" required placeholder="e.g. Hikvision 2MP Dome Camera" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Category</label>
                    <select name="category_id" id="prod_cat" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">HSN Code </label>
                    <input type="text" name="product_code" id="prod_code" placeholder="e.g. CAM-HIK-001" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Unit (e.g. Pcs, Mtr)</label>
                    <input type="text" name="unit" id="prod_unit" placeholder="Pcs" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Selling Price (₹) *</label>
                    <input type="number" step="0.01" name="unit_price" id="prod_price" required placeholder="0.00" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Opening Stock</label>
                    <input type="number" name="opening_stock" id="prod_min" placeholder="0" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                    <textarea name="description" id="prod_desc" rows="2" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-none font-bold hover:bg-indigo-700 transition shadow-none shadow-none">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Add Product';
    document.getElementById('prod_id').value = '';
    document.getElementById('prod_name').value = '';
    document.getElementById('prod_cat').value = '';
    document.getElementById('prod_code').value = '';
    document.getElementById('prod_unit').value = 'Pcs';
    document.getElementById('prod_min').value = '5';
    document.getElementById('prod_desc').value = '';
    document.getElementById('prod_price').value = '';
}
function closeModal() {
    document.getElementById('productModal').classList.add('hidden');
}
function editProduct(data) {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('modalTitle').innerText = 'Edit Product';
    document.getElementById('prod_id').value = data.id;
    document.getElementById('prod_name').value = data.product_name;
    document.getElementById('prod_cat').value = data.category_id || '';
    document.getElementById('prod_code').value = data.product_code || '';
    document.getElementById('prod_unit').value = data.unit || 'Pcs';
    document.getElementById('prod_price').value = data.unit_price;
    document.getElementById('prod_min').value = data.opening_stock;
    document.getElementById('prod_desc').value = data.description || '';
}

function openAdjustModal(data) {
    document.getElementById('adjust_prod_id').value = data.id;
    document.getElementById('adjust_prod_name').innerText = data.product_name;
    document.getElementById('adjustModal').classList.remove('hidden');
}
function closeAdjustModal() {
    document.getElementById('adjustModal').classList.add('hidden');
}
</script>

<!-- Adjust Modal -->
<div id="adjustModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-none z-[100] flex items-center justify-center p-4">
    <div class="bg-white rounded-none shadow-none w-full max-w-sm overflow-hidden transform transition-all">
        <div class="p-6 border-b border-slate-100 flex justify-between items-center">
            <h2 class="text-xl font-bold text-slate-800">Adjust Stock</h2>
            <button onclick="closeAdjustModal()" class="text-slate-400 hover:text-slate-600 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="prod_id" id="adjust_prod_id">
            <input type="hidden" name="adjust_stock" value="1">
            <p class="text-sm font-bold text-slate-600 mb-2">Product: <span id="adjust_prod_name" class="text-indigo-600"></span></p>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Adjustment Type</label>
                <select name="adj_type" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="Add">Add Stock (+)</option>
                    <option value="Remove">Remove Stock (-)</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Quantity</label>
                <input type="number" name="adj_qty" required min="1" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Reason / Notes</label>
                <textarea name="adj_notes" rows="2" placeholder="e.g. Damage, Found in warehouse" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAdjustModal()" class="flex-1 px-6 py-3 bg-slate-100 text-slate-600 rounded-none font-bold hover:bg-slate-200 transition">Cancel</button>
                <button type="submit" class="flex-1 px-6 py-3 bg-slate-800 text-white rounded-none font-bold hover:bg-slate-900 transition shadow-none shadow-none">Update Stock</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
