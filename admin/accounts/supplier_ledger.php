<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$supplierId = $_GET['supplier_id'] ?? null;
$supplier = null;
$transactions = [];

if ($supplierId) {
    // Fetch supplier info
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();

    if ($supplier) {
        // Fetch Purchases - Logic: Credit Purchase = Credit, Cash Purchase = Debit (One line)
        $purStmt = $pdo->prepare("SELECT 
            purchase_date as date, 
            bill_no as ref, 
            (CASE WHEN payment_method = 'Credit' THEN total_amount ELSE 0 END) as credit, 
            (CASE WHEN payment_method != 'Credit' THEN total_amount ELSE 0 END) as debit, 
            (CASE WHEN payment_method != 'Credit' THEN 'Cash Purchase' ELSE 'Purchase' END) as type 
            FROM purchases WHERE supplier_id = ?");
        $purStmt->execute([$supplierId]);
        $purchases = $purStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Payments (Vouchers)
        $vouchStmt = $pdo->prepare("SELECT voucher_date as date, CONCAT('Payment (', payment_method, ')') as ref, 0 as credit, amount as debit, 'Payment' as type FROM vouchers WHERE payee_payer = ? AND account_head = 'Supplier Payment'");
        $vouchStmt->execute([$supplier['supplier_name']]);
        $vouchers = $vouchStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Purchase Returns
        $retStmt = $pdo->prepare("SELECT 
            r.return_date as date, 
            CONCAT('Return (', p.bill_no, ')') as ref, 
            r.total_amount as debit, 
            0 as credit, 
            'Purchase Return' as type 
            FROM purchase_returns r
            JOIN purchases p ON r.purchase_id = p.id
            WHERE r.supplier_id = ?");
        $retStmt->execute([$supplierId]);
        $returns = $retStmt->fetchAll(PDO::FETCH_ASSOC);

        $transactions = array_merge($purchases, $vouchers, $returns);
        
        // Sort transactions by date
        usort($transactions, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Accurate Summary Totals
        $totalPur = $pdo->prepare("SELECT SUM(total_amount) FROM purchases WHERE supplier_id = ?");
        $totalPur->execute([$supplierId]);
        $totalPurchasesVal = $totalPur->fetchColumn() ?: 0;

        $totalPaidVal = $pdo->prepare("SELECT (SELECT IFNULL(SUM(amount), 0) FROM vouchers WHERE payee_payer = ? AND account_head = 'Supplier Payment') + (SELECT IFNULL(SUM(total_amount), 0) FROM purchase_returns WHERE supplier_id = ?)");
        $totalPaidVal->execute([$supplier['supplier_name'], $supplierId]);
        $totalPaymentsVal = $totalPaidVal->fetchColumn() ?: 0;
    }
}

$suppliers = $pdo->query("SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <h1 class="text-2xl font-bold text-slate-800">Supplier Ledger</h1>
        </div>
        
        <form class="flex items-end gap-3 bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-64">
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Select Supplier</label>
                <select name="supplier_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $supplierId == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['supplier_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-indigo-700 transition">View Ledger</button>
        </form>
    </div>

    <?php if ($supplier): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Purchases</p>
            <p class="text-2xl font-black text-slate-800">₹<?php echo number_format($totalPurchasesVal, 2); ?></p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Paid</p>
            <p class="text-2xl font-black text-emerald-600">₹<?php echo number_format($totalPaymentsVal, 2); ?></p>
        </div>
        <div class="bg-slate-900 p-6 rounded-3xl shadow-xl">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Total Outstanding</p>
            <p class="text-2xl font-black text-white">₹<?php echo number_format($totalPurchasesVal - $totalPaymentsVal, 2); ?></p>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-50 flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Transaction History with <?php echo htmlspecialchars($supplier['supplier_name']); ?></h3>
            <button onclick="window.print()" class="text-xs font-bold text-indigo-600 hover:underline">Print Statement</button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest font-bold">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Particulars</th>
                        <th class="px-6 py-4 text-right">Debit (Paid)</th>
                        <th class="px-6 py-4 text-right">Credit (Purchase)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">No transactions found for this supplier.</td></tr>
                    <?php else: ?>
                        <?php 
                        foreach ($transactions as $t): 
                        ?>
                        <tr class="hover:bg-slate-50/50 transition">
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo date('d M, Y', strtotime($t['date'])); ?></td>
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($t['ref'] ?: 'N/A'); ?></p>
                                <p class="text-[10px] uppercase font-bold text-slate-400"><?php echo $t['type']; ?></p>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-emerald-600">
                                <?php echo $t['debit'] > 0 ? '₹' . number_format($t['debit'], 2) : '-'; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-slate-700">
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
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">🚚</div>
            <h3 class="text-xl font-bold text-slate-800">No Supplier Selected</h3>
            <p class="text-slate-500 mt-2">Please select a supplier from the dropdown above to view their financial ledger.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
