<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

// Fetch Outstanding Dues from Customers
$customer_dues_query = "
    SELECT 
        c.id, 
        c.customer_name, 
        c.phone,
        IFNULL((SELECT SUM(grand_total) FROM invoices WHERE complaint_id IN (SELECT id FROM complaints WHERE customer_id = c.id)), 0) as total_invoiced,
        IFNULL((SELECT SUM(amount) FROM payments WHERE customer_id = c.id), 0) as total_paid
    FROM customers c
    HAVING (total_invoiced - total_paid) > 0
    ORDER BY (total_invoiced - total_paid) DESC
";

// Fetch Outstanding Dues to Suppliers
$supplier_dues_query = "
    SELECT 
        s.id, 
        s.supplier_name, 
        s.phone,
        IFNULL((SELECT SUM(total_amount) FROM purchases WHERE supplier_id = s.id AND payment_method = 'Credit'), 0) as total_purchase,
        IFNULL((SELECT SUM(amount) FROM vouchers WHERE payee_payer = s.supplier_name AND account_head = 'Supplier Payment'), 0) as total_paid
    FROM suppliers s
    HAVING (total_purchase - total_paid) > 0
    ORDER BY (total_purchase - total_paid) DESC
";

try {
    $customer_dues = $pdo->query($customer_dues_query)->fetchAll();
    $supplier_dues = $pdo->query($supplier_dues_query)->fetchAll();
} catch (PDOException $e) {
    $customer_dues = [];
    $supplier_dues = [];
}

$totalCustomerOutstanding = 0;
foreach ($customer_dues as $d) {
    $totalCustomerOutstanding += ($d['total_invoiced'] - $d['total_paid']);
}

$totalSupplierOutstanding = 0;
foreach ($supplier_dues as $d) {
    $totalSupplierOutstanding += ($d['total_purchase'] - $d['total_paid']);
}

require_once '../../includes/header.php';
?>

<div class="space-y-10">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Financial Outstanding</h1>
        </div>
        
        <div class="flex flex-wrap gap-4">
            <div class="bg-rose-50 border border-rose-100 px-6 py-3 rounded-2xl flex items-center gap-4">
                <div class="w-10 h-10 bg-rose-500 rounded-xl flex items-center justify-center text-white text-xl">⏳</div>
                <div>
                    <p class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">Customer Dues (Receivable)</p>
                    <p class="text-xl font-black text-rose-700">₹<?php echo number_format($totalCustomerOutstanding, 2); ?></p>
                </div>
            </div>
            <div class="bg-amber-50 border border-amber-100 px-6 py-3 rounded-2xl flex items-center gap-4">
                <div class="w-10 h-10 bg-amber-500 rounded-xl flex items-center justify-center text-white text-xl">💳</div>
                <div>
                    <p class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Supplier Dues (Payable)</p>
                    <p class="text-xl font-black text-amber-700">₹<?php echo number_format($totalSupplierOutstanding, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Details Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Customer Receivables</h3>
            <span class="px-3 py-1 bg-rose-100 text-rose-600 rounded-full text-xs font-bold"><?php echo count($customer_dues); ?> Pending</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Customer Name</th>
                        <th class="px-6 py-4">Phone</th>
                        <th class="px-6 py-4 text-right">Total Invoiced</th>
                        <th class="px-6 py-4 text-right">Total Paid</th>
                        <th class="px-6 py-4 text-right">Due Amount</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($customer_dues)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">No customer receivables found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customer_dues as $d): ?>
                        <?php $dueAmt = $d['total_invoiced'] - $d['total_paid']; ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($d['customer_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($d['phone']); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-slate-600">₹<?php echo number_format($d['total_invoiced'], 2); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-emerald-600">₹<?php echo number_format($d['total_paid'], 2); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-black text-rose-600 bg-rose-50/30">₹<?php echo number_format($dueAmt, 2); ?></td>
                            <td class="px-6 py-4 text-center">
                                <a href="customer_payments.php?search=<?php echo urlencode($d['customer_name']); ?>" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold uppercase tracking-wider">Record Payment</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supplier Details Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Supplier Payables</h3>
            <span class="px-3 py-1 bg-amber-100 text-amber-600 rounded-full text-xs font-bold"><?php echo count($supplier_dues); ?> Pending</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Supplier Name</th>
                        <th class="px-6 py-4">Phone</th>
                        <th class="px-6 py-4 text-right">Total Purchases</th>
                        <th class="px-6 py-4 text-right">Total Paid</th>
                        <th class="px-6 py-4 text-right">Outstanding</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($supplier_dues)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 italic">No supplier payables found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($supplier_dues as $d): ?>
                        <?php $dueAmt = $d['total_purchase'] - $d['total_paid']; ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($d['supplier_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo htmlspecialchars($d['phone']); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-slate-600">₹<?php echo number_format($d['total_purchase'], 2); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium text-emerald-600">₹<?php echo number_format($d['total_paid'], 2); ?></td>
                            <td class="px-6 py-4 text-right text-sm font-black text-amber-600 bg-amber-50/30">₹<?php echo number_format($dueAmt, 2); ?></td>
                            <td class="px-6 py-4 text-center">
                                <a href="supplier_payments.php?search=<?php echo urlencode($d['supplier_name']); ?>" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold uppercase tracking-wider">Clear Dues</a>
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
