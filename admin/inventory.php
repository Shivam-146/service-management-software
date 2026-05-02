<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $sn = trim($_POST['serial_number']);
    $price = $_POST['unit_price'];
    $qty = $_POST['stock_qty'];
    $warranty = $_POST['warranty_months'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO products (item_name, category, serial_number, unit_price, stock_qty, warranty_months) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$item_name, $category, $sn, $price, $qty, $warranty]);
        $successMsg = "Product added to inventory successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Handle Edit Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $id = $_POST['product_id'];
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $sn = trim($_POST['serial_number']);
    $price = $_POST['unit_price'];
    $qty = $_POST['stock_qty'];
    $warranty = $_POST['warranty_months'];
    
    try {
        $stmt = $pdo->prepare("UPDATE products SET item_name=?, category=?, serial_number=?, unit_price=?, stock_qty=?, warranty_months=? WHERE id=?");
        $stmt->execute([$item_name, $category, $sn, $price, $qty, $warranty, $id]);
        $successMsg = "Product updated successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

// Fetch Products
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY item_name ASC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Inventory Master</h2>
        <button onclick="openProductModal()" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition shadow-sm">Add New Product</button>
    </div>

    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
            <span class="block sm:inline"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($products)): ?>
            <div class="col-span-full py-12 text-center text-slate-500 bg-white rounded-xl border border-slate-100 shadow-sm">
                No products found in inventory.
            </div>
        <?php endif; ?>
        <?php foreach ($products as $product): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-500"><?php echo htmlspecialchars($product['category']); ?></span>
                    <span class="text-indigo-600 font-bold">₹<?php echo number_format($product['unit_price'], 2); ?></span>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($product['item_name']); ?></h3>
                <p class="text-xs text-slate-400 mb-4">SN: <?php echo htmlspecialchars($product['serial_number'] ?? 'N/A'); ?></p>
                
                <div class="flex items-center gap-4 mb-6">
                    <div>
                        <p class="text-[10px] text-slate-400 uppercase font-bold">In Stock</p>
                        <p class="text-xl font-black <?php echo $product['stock_qty'] < 5 ? 'text-red-500' : 'text-slate-800'; ?>"><?php echo $product['stock_qty']; ?></p>
                    </div>
                    <div class="h-8 w-px bg-slate-100"></div>
                    <div>
                        <p class="text-[10px] text-slate-400 uppercase font-bold">Warranty</p>
                        <p class="text-xl font-black text-slate-800"><?php echo $product['warranty_months']; ?>m</p>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 pt-4 border-t border-slate-50">
                <button onclick='openEditProductModal(<?php echo json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="flex-1 text-xs font-bold text-indigo-600 hover:bg-indigo-50 py-2 rounded transition">Edit</button>
                <button class="flex-1 text-xs font-bold text-slate-400 hover:text-red-500 py-2 rounded transition">Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Product Modal -->
<div id="productModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <h3 class="text-xl font-bold text-slate-900">Add New Product</h3>
            <button type="button" onclick="closeProductModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Product Name *</label>
                <input type="text" name="item_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. 4MP Dome Camera">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                    <input type="text" name="category" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="e.g. Camera, DVR">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Serial Number</label>
                    <input type="text" name="serial_number" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="Optional">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Unit Price (₹) *</label>
                    <input type="number" step="0.01" name="unit_price" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Stock Qty</label>
                    <input type="number" name="stock_qty" value="0" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Warranty (M)</label>
                    <input type="number" name="warranty_months" value="12" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6">
                <button type="button" onclick="closeProductModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="add_product" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function openProductModal() {
    document.getElementById('productModal').classList.remove('hidden');
}
function closeProductModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function openEditProductModal(product) {
    document.getElementById('edit_product_id').value = product.id;
    document.getElementById('edit_item_name').value = product.item_name;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_serial_number').value = product.serial_number;
    document.getElementById('edit_unit_price').value = product.unit_price;
    document.getElementById('edit_stock_qty').value = product.stock_qty;
    document.getElementById('edit_warranty_months').value = product.warranty_months;
    document.getElementById('editProductModal').classList.remove('hidden');
}
function closeEditProductModal() {
    document.getElementById('editProductModal').classList.add('hidden');
}
</script>

<!-- Edit Product Modal -->
<div id="editProductModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto transform transition-all">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100">
            <h3 class="text-xl font-bold text-slate-900">Edit Product</h3>
            <button type="button" onclick="closeEditProductModal()" class="text-slate-400 hover:text-slate-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Product Name *</label>
                <input type="text" id="edit_item_name" name="item_name" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                    <input type="text" id="edit_category" name="category" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Serial Number</label>
                    <input type="text" id="edit_serial_number" name="serial_number" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Unit Price (₹) *</label>
                    <input type="number" id="edit_unit_price" step="0.01" name="unit_price" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Stock Qty</label>
                    <input type="number" id="edit_stock_qty" name="stock_qty" required class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Warranty (M)</label>
                    <input type="number" id="edit_warranty_months" name="warranty_months" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500 transition">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-6">
                <button type="button" onclick="closeEditProductModal()" class="flex-1 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button>
                <button type="submit" name="edit_product" class="flex-1 px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition shadow-lg shadow-indigo-200">Update Product</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
