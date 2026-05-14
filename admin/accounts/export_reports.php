<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $filename = $type . "_report_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'income') {
        fputcsv($output, ['Date', 'Category', 'Source', 'Amount', 'Method', 'Ref No', 'Description']);
        $stmt = $pdo->query("SELECT income_date, category, source, amount, payment_method, reference_no, description FROM income ORDER BY income_date DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    } 
    elseif ($type === 'expenses') {
        fputcsv($output, ['Date', 'Category', 'Payee', 'Amount', 'Method', 'Ref No', 'Notes']);
        $stmt = $pdo->query("SELECT p.purchase_date, p.category, COALESCE(s.supplier_name, p.payee_name, '') as payee, p.total_amount, p.payment_method, p.reference_no, p.notes FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.purchase_date DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    }
    elseif ($type === 'vouchers') {
        fputcsv($output, ['Voucher No', 'Type', 'Date', 'Account Head', 'Payee/Payer', 'Amount', 'Method', 'Ref No', 'Narration']);
        $stmt = $pdo->query("SELECT voucher_no, voucher_type, voucher_date, account_head, payee_payer, amount, payment_method, reference_no, narration FROM vouchers ORDER BY voucher_date DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) fputcsv($output, $row);
    }
    elseif ($type === 'dues') {
        fputcsv($output, ['Customer Name', 'Phone', 'Total Invoiced', 'Total Paid', 'Outstanding Due']);
        $query = "
            SELECT c.customer_name, c.phone,
            IFNULL((SELECT SUM(grand_total) FROM invoices WHERE complaint_id IN (SELECT id FROM complaints WHERE customer_id = c.id)), 0) as total_invoiced,
            IFNULL((SELECT SUM(amount) FROM payments WHERE customer_id = c.id), 0) as total_paid
            FROM customers c HAVING (total_invoiced - total_paid) > 0
        ";
        $stmt = $pdo->query($query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['due'] = $row['total_invoiced'] - $row['total_paid'];
            fputcsv($output, $row);
        }
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
        <h1 class="text-2xl font-bold text-slate-800">Export Reports</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Income Export -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl mb-4">💰</div>
            <h3 class="font-bold text-slate-800 mb-2">Income Report</h3>
            <p class="text-xs text-slate-500 mb-6">Export all income entries including categories and payment methods.</p>
            <a href="?type=income" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Download CSV</a>
        </div>

        <!-- Expense Export -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center text-2xl mb-4">💸</div>
            <h3 class="font-bold text-slate-800 mb-2">Purchase/Expense Report</h3>
            <p class="text-xs text-slate-500 mb-6">Export all business outflows including inventory and utility bills.</p>
            <a href="?type=expenses" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Download CSV</a>
        </div>

        <!-- Voucher Export -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-4">📑</div>
            <h3 class="font-bold text-slate-800 mb-2">Voucher Report</h3>
            <p class="text-xs text-slate-500 mb-6">Export all Payment and Receipt vouchers with narrations.</p>
            <a href="?type=vouchers" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Download CSV</a>
        </div>

        <!-- Dues Export -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-md transition">
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-2xl mb-4">⏳</div>
            <h3 class="font-bold text-slate-800 mb-2">Outstanding Dues</h3>
            <p class="text-xs text-slate-500 mb-6">Export a list of customers with pending balances.</p>
            <a href="?type=dues" class="block w-full text-center bg-slate-900 text-white py-3 rounded-xl font-bold text-sm hover:bg-slate-800 transition">Download CSV</a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
