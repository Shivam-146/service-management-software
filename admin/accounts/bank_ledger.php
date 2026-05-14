<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

// Fetch all digital/bank transactions (Anything not Cash)
$query = "
    (SELECT 'Income' as source_type, income_date as t_date, source as description, category as head, amount as credit, 0 as debit, payment_method FROM income WHERE payment_method != 'Cash')
    UNION ALL
    (SELECT IF(p.is_inventory=1, 'Purchase', 'Expense') as source_type, p.purchase_date as t_date, COALESCE(s.supplier_name, p.payee_name, '') as description, p.category as head, 0 as credit, p.total_amount as debit, p.payment_method 
     FROM purchases p LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.payment_method != 'Cash')
    UNION ALL
    (SELECT voucher_type as source_type, voucher_date as t_date, narration as description, account_head as head, IF(voucher_type='Receipt', amount, 0) as credit, IF(voucher_type='Payment', amount, 0) as debit, payment_method FROM vouchers WHERE payment_method != 'Cash')
    UNION ALL
    (SELECT 'Customer Payment' as source_type, payment_date as t_date, notes as description, 'Customer Account' as head, amount as credit, 0 as debit, payment_method FROM payments WHERE payment_method != 'Cash')
    ORDER BY t_date DESC
";

try {
    $transactions = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $transactions = [];
}

// Calculate Balances
$totalCredit = 0;
$totalDebit = 0;
foreach ($transactions as $t) {
    $totalCredit += $t['credit'];
    $totalDebit += $t['debit'];
}
$balance = $totalCredit - $totalDebit;

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Bank Ledger</h1>
        </div>
        
        <div class="flex gap-4">
            <div class="bg-white border border-slate-100 px-6 py-3 rounded-2xl shadow-sm">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Net Bank Balance</p>
                <p class="text-xl font-black <?php echo $balance >= 0 ? 'text-indigo-600' : 'text-rose-600'; ?>">₹<?php echo number_format($balance, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-indigo-50 border border-indigo-100 p-6 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center text-white text-2xl">🏦</div>
            <div>
                <p class="text-xs font-bold text-indigo-600 uppercase">Total Bank Credit</p>
                <p class="text-2xl font-black text-indigo-700">₹<?php echo number_format($totalCredit, 2); ?></p>
            </div>
        </div>
        <div class="bg-slate-50 border border-slate-100 p-6 rounded-2xl flex items-center gap-4">
            <div class="w-12 h-12 bg-slate-500 rounded-xl flex items-center justify-center text-white text-2xl">💸</div>
            <div>
                <p class="text-xs font-bold text-slate-600 uppercase">Total Bank Debit</p>
                <p class="text-2xl font-black text-slate-700">₹<?php echo number_format($totalDebit, 2); ?></p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4">Account Head</th>
                        <th class="px-6 py-4">Method</th>
                        <th class="px-6 py-4 text-indigo-600">Credit (+)</th>
                        <th class="px-6 py-4 text-rose-600">Debit (-)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400">No bank transactions found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $t): ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo date('d M, Y h:i A', strtotime($t['t_date'])); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 bg-slate-100 text-slate-500 rounded text-[10px] font-bold uppercase"><?php echo $t['source_type']; ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($t['head']); ?></p>
                                <p class="text-[10px] text-slate-400 italic truncate max-w-[150px]"><?php echo htmlspecialchars($t['description']); ?></p>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-500"><?php echo $t['payment_method']; ?></td>
                            <td class="px-6 py-4 text-sm font-black text-indigo-600">
                                <?php echo $t['credit'] > 0 ? '₹' . number_format($t['credit'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-black text-rose-600">
                                <?php echo $t['debit'] > 0 ? '₹' . number_format($t['debit'], 2) : '-'; ?>
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
