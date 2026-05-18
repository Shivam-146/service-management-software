<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$customerId = $_GET['customer_id'] ?? null;
$customer = null;
$transactions = [];

if ($customerId) {
    // Fetch customer info
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();

    if ($customer) {
        // Fetch Invoices - Logic: Pay Later = Debit, Cash/Settled = Credit (One line)
        $invStmt = $pdo->prepare("SELECT 
            created_at as date, 
            invoice_no as ref, 
            (CASE WHEN payment_method = 'Pay Later' THEN grand_total ELSE 0 END) as debit, 
            (CASE WHEN payment_method != 'Pay Later' THEN grand_total ELSE 0 END) as credit, 
            (CASE WHEN payment_method != 'Pay Later' THEN 'Cash Sale' ELSE 'Invoice' END) as type 
            FROM invoices WHERE customer_id = ?");
        $invStmt->execute([$customerId]);
        $invoices = $invStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Payments - Only fetch direct payments or payments for 'Pay Later' invoices
        $payStmt = $pdo->prepare("SELECT 
            p.payment_date as date, 
            CONCAT('Payment (', p.payment_method, ')') as ref, 
            0 as debit, 
            p.amount as credit, 
            'Payment' as type 
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            WHERE p.customer_id = ? 
            AND (p.invoice_id IS NULL OR i.payment_method = 'Pay Later')");
        $payStmt->execute([$customerId]);
        $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Sales Returns
        $retStmt = $pdo->prepare("SELECT 
            r.return_date as date, 
            CONCAT('Return (', i.invoice_no, ')') as ref, 
            0 as debit, 
            r.total_amount as credit, 
            'Sales Return' as type 
            FROM sales_returns r
            JOIN invoices i ON r.invoice_id = i.id
            WHERE r.customer_id = ?");
        $retStmt->execute([$customerId]);
        $returns = $retStmt->fetchAll(PDO::FETCH_ASSOC);

        $transactions = array_merge($invoices, $payments, $returns);
        
        // Sort transactions by date
        usort($transactions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Accurate Summary Totals
        $totalInv = $pdo->prepare("SELECT SUM(grand_total) FROM invoices WHERE customer_id = ?");
        $totalInv->execute([$customerId]);
        $totalInvoicedVal = $totalInv->fetchColumn() ?: 0;

        $totalPaidVal = $pdo->prepare("SELECT (SELECT IFNULL(SUM(amount), 0) FROM payments WHERE customer_id = ?) + (SELECT IFNULL(SUM(total_amount), 0) FROM sales_returns WHERE customer_id = ?)");
        $totalPaidVal->execute([$customerId, $customerId]);
        $totalPaymentsVal = $totalPaidVal->fetchColumn() ?: 0;
    }
}

$customers = $pdo->query("SELECT id, customer_name FROM customers ORDER BY customer_name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Customer Ledger</h1>
        </div>
        
        <form class="flex items-end gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-64">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Select Customer</label>
                <select name="customer_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $customerId == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['customer_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">View Ledger</button>
        </form>
    </div>

    <?php if ($customer): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Invoiced</p>
            <p class="text-2xl font-black text-slate-800">₹<?php echo number_format($totalInvoicedVal, 2); ?></p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Paid</p>
            <p class="text-2xl font-black text-emerald-600">₹<?php echo number_format($totalPaymentsVal, 2); ?></p>
        </div>
        <div class="bg-slate-900 p-6 rounded-3xl shadow-xl">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Outstanding Balance</p>
            <p class="text-2xl font-black text-white">₹<?php echo number_format($totalInvoicedVal - $totalPaymentsVal, 2); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Transaction History for <?php echo htmlspecialchars($customer['customer_name']); ?></h3>
            <button onclick="window.print()" class="text-xs font-bold text-indigo-600 hover:underline">Print Statement</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Particulars</th>
                        <th class="px-6 py-4 text-right">Debit (+)</th>
                        <th class="px-6 py-4 text-right">Credit (-)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">No transactions found for this customer.</td></tr>
                    <?php else: ?>
                        <?php 
                        foreach ($transactions as $t): 
                        ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo date('d M, Y', strtotime($t['date'])); ?></td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($t['ref']); ?></p>
                                <p class="text-[10px] uppercase font-bold text-slate-400"><?php echo $t['type']; ?></p>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-slate-700">
                                <?php echo $t['debit'] > 0 ? '₹' . number_format($t['debit'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-emerald-600">
                                <?php echo $t['credit'] > 0 ? '₹' . number_format($t['credit'], 2) : '-'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="bg-white p-12 rounded-3xl border border-slate-100 shadow-sm text-center">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">📖</div>
            <h3 class="text-xl font-bold text-slate-800">No Customer Selected</h3>
            <p class="text-slate-500 mt-2">Please select a customer from the dropdown above to view their financial ledger.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
