<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$returnId = $_GET['id'] ?? null;

if (!$returnId) {
    die("Return ID is required.");
}

try {
    // Fetch return details
    $stmt = $pdo->prepare("SELECT r.*, i.invoice_no, c.customer_name, c.address as customer_address, c.phone as customer_phone 
                           FROM sales_returns r 
                           JOIN invoices i ON r.invoice_id = i.id 
                           JOIN customers c ON r.customer_id = c.id 
                           WHERE r.id = ?");
    $stmt->execute([$returnId]);
    $return = $stmt->fetch();

    if (!$return) {
        die("Return record not found.");
    }

    // Fetch items linked to this return
    $itemsStmt = $pdo->prepare("SELECT ri.*, p.product_name 
                                FROM sales_return_items ri 
                                JOIN products p ON ri.product_id = p.id 
                                WHERE ri.return_id = ?");
    $itemsStmt->execute([$returnId]);
    $items = $itemsStmt->fetchAll();

    // Fetch Company Settings
    $adminStmt = $pdo->query("SELECT fullname, address, phone FROM users WHERE role = 'admin' LIMIT 1");
    $adminSettings = $adminStmt->fetch();
    $companyName = !empty($adminSettings['fullname']) ? $adminSettings['fullname'] : 'CCTV SECURE';
    $companyAddress = !empty($adminSettings['address']) ? nl2br(htmlspecialchars($adminSettings['address'])) : 'Tech Hub, Sector 5';
    $companyPhone = !empty($adminSettings['phone']) ? $adminSettings['phone'] : '+91 98765 43210';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/header.php';
?>

<div class="mb-8 flex items-center justify-between no-print max-w-4xl mx-auto mt-8">
    <div class="flex items-center gap-4">
        <a href="sales_returns.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Return Credit Note</h2>
    </div>
    <button onclick="window.print()" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 transition flex items-center gap-2 shadow-lg shadow-indigo-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 00-2 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        Print Note
    </button>
</div>

<div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-12 max-w-4xl mx-auto mb-10 mt-8" id="voucher-printable">
    <div class="flex justify-between items-start mb-12">
        <div>
            <h1 class="text-3xl font-black text-slate-800 mb-2 uppercase tracking-tighter"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-slate-500 text-xs leading-relaxed"><?php echo $companyAddress; ?><br>Contact: <?php echo htmlspecialchars($companyPhone); ?></p>
        </div>
        <div class="text-right">
            <h2 class="text-4xl font-black text-rose-100 uppercase tracking-tighter mb-4">CREDIT<br>NOTE</h2>
            <div class="space-y-1 text-xs">
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Date:</span> <span class="font-black text-slate-800"><?php echo date('d-M-Y', strtotime($return['return_date'])); ?></span></p>
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Note No:</span> <span class="font-black text-slate-800">RET-<?php echo str_pad($return['id'], 5, '0', STR_PAD_LEFT); ?></span></p>
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Against Inv:</span> <span class="font-black text-slate-800"><?php echo htmlspecialchars($return['invoice_no']); ?></span></p>
            </div>
        </div>
    </div>

    <div class="mb-12 border-y border-slate-100 py-8">
        <h3 class="text-[10px] uppercase font-black text-slate-400 mb-3 tracking-widest">Customer Details:</h3>
        <p class="text-lg font-black text-slate-800 mb-1"><?php echo htmlspecialchars($return['customer_name']); ?></p>
        <p class="text-slate-500 text-xs leading-relaxed"><?php echo nl2br(htmlspecialchars($return['customer_address'])); ?></p>
        <p class="text-xs font-bold text-slate-600 mt-2">Phone: <?php echo htmlspecialchars($return['customer_phone']); ?></p>
    </div>

    <table class="w-full mb-12">
        <thead>
            <tr class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                <th class="py-4 px-4 rounded-l-xl text-left">Description</th>
                <th class="py-4 px-4 text-center">Qty</th>
                <th class="py-4 px-4 text-right">Price</th>
                <th class="py-4 px-4 text-right rounded-r-xl">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($items as $item): ?>
            <tr>
                <td class="py-6 px-4">
                    <p class="font-black text-slate-800 text-sm uppercase tracking-tight"><?php echo htmlspecialchars($item['product_name']); ?></p>
                    <?php if ($item['serial_number']): ?>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">Serial: <?php echo htmlspecialchars($item['serial_number']); ?></p>
                    <?php endif; ?>
                </td>
                <td class="py-6 px-4 text-center text-slate-600 font-black text-sm"><?php echo $item['quantity']; ?></td>
                <td class="py-6 px-4 text-right text-slate-600 font-bold text-xs">₹<?php echo number_format($item['price'], 2); ?></td>
                <td class="py-6 px-4 text-right font-black text-slate-800 text-sm">₹<?php echo number_format($item['total'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="flex justify-end pt-8 border-t-4 border-slate-900">
        <div class="w-full max-w-xs space-y-4 text-right">
            <div class="pt-4 flex justify-between items-center text-2xl">
                <span class="font-black text-slate-900 uppercase tracking-tighter">Total Credit:</span>
                <span class="font-black text-rose-600 tracking-tighter">₹<?php echo number_format($return['total_amount'], 2); ?></span>
            </div>
            <p class="text-[10px] font-bold text-slate-400 italic">This amount has been credited back to your account/refunded.</p>
        </div>
    </div>

    <div class="mt-24 grid grid-cols-2 gap-12">
        <div class="text-center">
            <div class="h-16 border-b border-slate-200 mb-2 mx-auto w-48"></div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Authorized Signatory</p>
        </div>
        <div class="text-center">
            <div class="h-16 border-b border-slate-200 mb-2 mx-auto w-48"></div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Customer Signature</p>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    main { margin-left: 0 !important; padding: 0 !important; }
    #voucher-printable { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
