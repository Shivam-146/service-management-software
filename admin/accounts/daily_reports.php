<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$date = $_GET['date'] ?? date('Y-m-d');

// Fetch all transactions for the selected date
$query = "
    (SELECT 'Income' as source_type, source as head, amount as amount, 'Inflow' as type, payment_method FROM income WHERE DATE(income_date) = ?)
    UNION ALL
    (SELECT IF(p.is_inventory=1, 'Purchase', 'Expense') as source_type, COALESCE(s.supplier_name, p.payee_name, '') as head, p.total_amount as amount, 'Outflow' as type, p.payment_method FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE DATE(p.purchase_date) = ?)
    UNION ALL
    (SELECT voucher_type as source_type, payee_payer as head, amount as amount, IF(voucher_type='Receipt', 'Inflow', 'Outflow') as type, payment_method FROM vouchers WHERE DATE(voucher_date) = ?)
    UNION ALL
    (SELECT 'Customer Payment' as source_type, COALESCE(c.customer_name, 'Direct Payment') as head, p.amount as amount, 'Inflow' as type, p.payment_method FROM payments p LEFT JOIN customers c ON p.customer_id = c.id WHERE DATE(p.payment_date) = ?)
";

$stmt = $pdo->prepare($query);
$stmt->execute([$date, $date, $date, $date]);
$transactions = $stmt->fetchAll();

$totalIn = 0;
$totalOut = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'Inflow') $totalIn += $t['amount'];
    else $totalOut += $t['amount'];
}

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Daily Transaction Report</h1>
        </div>
        
        <form class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-400 uppercase">Select Date:</label>
            <input type="date" name="date" value="<?php echo $date; ?>" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm font-bold outline-none focus:ring-2 focus:ring-indigo-500 shadow-sm">
        </form>
    </div>

    <!-- Daily Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Inflow</p>
            <p class="text-3xl font-black text-emerald-600">₹<?php echo number_format($totalIn, 2); ?></p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Outflow</p>
            <p class="text-3xl font-black text-rose-600">₹<?php echo number_format($totalOut, 2); ?></p>
        </div>
        <div class="bg-slate-900 p-6 rounded-3xl shadow-xl shadow-slate-200">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Net Cashflow</p>
            <p class="text-3xl font-black text-white">₹<?php echo number_format($totalIn - $totalOut, 2); ?></p>
        </div>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Transaction Details</h3>
            <span class="text-xs font-bold text-slate-400"><?php echo date('d F, Y', strtotime($date)); ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Source</th>
                        <th class="px-6 py-4">Head / Payee</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4 text-right">Inflow (+)</th>
                        <th class="px-6 py-4 text-right">Outflow (-)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="px-6 py-12 text-center text-slate-400 italic">No transactions recorded on this day.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-slate-100 text-slate-500 rounded text-[10px] font-bold uppercase tracking-tight"><?php echo $t['source_type']; ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-slate-800"><?php echo htmlspecialchars($t['head']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo $t['payment_method']; ?></td>
                            <td class="px-6 py-4 text-right text-sm font-black text-emerald-600">
                                <?php echo $t['type'] === 'Inflow' ? '₹' . number_format($t['amount'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-black text-rose-600">
                                <?php echo $t['type'] === 'Outflow' ? '₹' . number_format($t['amount'], 2) : '-'; ?>
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
