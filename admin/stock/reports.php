<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $filename = "stock_" . $type . "_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'inventory') {
        fputcsv($output, ['Product Name', 'SKU', 'Category', 'Unit', 'Opening Stock', 'Closing Stock']);
        $stmt = $pdo->query("SELECT p.product_name, p.product_code, c.category_name, p.unit, p.opening_stock, p.current_stock 
                             FROM products p LEFT JOIN stock_categories c ON p.category_id = c.id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    } 
    elseif ($type === 'movements') {
        fputcsv($output, ['Date', 'Product', 'Type', 'Quantity', 'Reference/Notes']);
        $stmt = $pdo->query("SELECT m.created_at, p.product_name, m.type, m.quantity, m.notes 
                             FROM stock_movements m JOIN products p ON m.product_id = p.id ORDER BY m.created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    }
    elseif ($type === 'purchases') {
        fputcsv($output, ['Date', 'Supplier', 'Bill No', 'Amount', 'Notes']);
        $stmt = $pdo->query("SELECT p.purchase_date, s.supplier_name, p.bill_no, p.total_amount, p.notes 
                             FROM purchases p JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.purchase_date DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex items-center gap-4">
        <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Inventory Reports</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Current Stock -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-4">📦</div>
            <h3 class="font-bold text-slate-800 mb-2">Current Stock Report</h3>
            <p class="text-xs text-slate-500 mb-6">Full list of products with their current quantities and categories.</p>
            <a href="?type=inventory" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Export CSV</a>
        </div>

        <!-- Stock Movements -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-4">🔄</div>
            <h3 class="font-bold text-slate-800 mb-2">Stock Movements</h3>
            <p class="text-xs text-slate-500 mb-6">Detailed ledger of all stock in, stock out, and manual adjustments.</p>
            <a href="?type=movements" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Export CSV</a>
        </div>

        <!-- Purchase History -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-2xl mb-4">📥</div>
            <h3 class="font-bold text-slate-800 mb-2">Purchase History</h3>
            <p class="text-xs text-slate-500 mb-6">Historical list of all purchase bills and supplier transactions.</p>
            <a href="?type=purchases" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Export CSV</a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
