<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

$purchaseId = $_GET['id'] ?? null;

if (!$purchaseId) {
    die("Purchase ID is required.");
}

try {
    // Fetch purchase details
    $stmt = $pdo->prepare("SELECT p.*, s.supplier_name, s.address as supplier_address, s.gst_number as supplier_gst 
                           FROM purchases p 
                           LEFT JOIN suppliers s ON p.supplier_id = s.id 
                           WHERE p.id = ?");
    $stmt->execute([$purchaseId]);
    $purchase = $stmt->fetch();

    if (!$purchase) {
        die("Purchase record not found.");
    }

    // Fetch items linked to this purchase
    $itemsStmt = $pdo->prepare("SELECT pi.*, pr.product_name, pr.product_code 
                                FROM purchase_items pi 
                                JOIN products pr ON pi.product_id = pr.id 
                                WHERE pi.purchase_id = ?");
    $itemsStmt->execute([$purchaseId]);
    $items = $itemsStmt->fetchAll();

    // Fetch Company/Admin Settings
    $adminStmt = $pdo->query("SELECT fullname, address, phone FROM users WHERE role = 'admin' LIMIT 1");
    $adminSettings = $adminStmt->fetch();
    $companyName = !empty($adminSettings['fullname']) ? $adminSettings['fullname'] : 'CCTV SECURE';
    $companyAddress = !empty($adminSettings['address']) ? nl2br(htmlspecialchars($adminSettings['address'])) : 'Tech Hub, Sector 5';
    $companyPhone = !empty($adminSettings['phone']) ? $adminSettings['phone'] : '+91 98765 43210';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/header.php';
$isPrint = isset($_GET['print']);
?>

<?php if (!$isPrint): ?>
<div class="mb-8 flex items-center justify-between no-print max-w-4xl mx-auto mt-8">
    <div class="flex items-center gap-4">
        <a href="purchases.php" class="p-2 bg-white border border-slate-200 rounded-lg text-slate-500 hover:text-indigo-600 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h2 class="text-2xl font-bold text-slate-800">Purchase Voucher</h2>
    </div>
    <div class="flex gap-3">
        <button onclick="window.print()" class="px-6 py-3 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 transition flex items-center gap-2 shadow-lg shadow-indigo-200">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
            Print Voucher
        </button>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-3xl shadow-xl border border-slate-100 p-12 max-w-4xl mx-auto mb-10 mt-8" id="voucher-printable">
    <div class="flex justify-between items-start mb-12">
        <div>
            <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase tracking-widest rounded-full mb-4 inline-block">Recipient Copy</span>
            <h1 class="text-3xl font-black text-slate-800 mb-2 uppercase tracking-tighter"><?php echo htmlspecialchars($companyName); ?></h1>
            <p class="text-slate-500 text-xs leading-relaxed"><?php echo $companyAddress; ?><br>Contact: <?php echo htmlspecialchars($companyPhone); ?></p>
        </div>
        <div class="text-right">
            <h2 class="text-4xl font-black text-slate-200 uppercase tracking-tighter mb-4">PURCHASE<br>VOUCHER</h2>
            <div class="space-y-1 text-xs">
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Date:</span> <span class="font-black text-slate-800"><?php echo date('d-M-Y', strtotime($purchase['purchase_date'])); ?></span></p>
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Bill No:</span> <span class="font-black text-slate-800"><?php echo htmlspecialchars($purchase['bill_no']); ?></span></p>
                <p><span class="text-slate-400 font-bold uppercase tracking-widest">Status:</span> <span class="font-black text-emerald-600 uppercase">Paid</span></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-12 mb-12 border-y border-slate-100 py-8">
        <div>
            <h3 class="text-[10px] uppercase font-black text-slate-400 mb-3 tracking-widest">Supplier Details:</h3>
            <?php if ($purchase['supplier_id']): ?>
                <p class="text-lg font-black text-slate-800 mb-1"><?php echo htmlspecialchars($purchase['supplier_name']); ?></p>
                <p class="text-slate-500 text-xs leading-relaxed mb-2"><?php echo nl2br(htmlspecialchars($purchase['supplier_address'])); ?></p>
                <?php if (!empty($purchase['supplier_gst'])): ?>
                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600">GSTIN: <?php echo htmlspecialchars($purchase['supplier_gst']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-lg font-black text-slate-800 mb-1"><?php echo htmlspecialchars($purchase['payee_name']); ?></p>
                <p class="text-slate-400 text-xs italic">Cash / General Expense</p>
            <?php endif; ?>
        </div>
        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
            <h3 class="text-[10px] uppercase font-black text-slate-400 mb-3 tracking-widest text-center">Payment Information:</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500 font-bold uppercase tracking-widest">Method:</span>
                    <span class="font-black text-slate-800 uppercase bg-white px-2 py-1 rounded border border-slate-200"><?php echo htmlspecialchars($purchase['payment_method']); ?></span>
                </div>
                <?php if ($purchase['reference_no']): ?>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500 font-bold uppercase tracking-widest">Ref No:</span>
                    <span class="font-black text-slate-800"><?php echo htmlspecialchars($purchase['reference_no']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($purchase['bank_name']): ?>
                <div class="flex justify-between items-center text-xs">
                    <span class="text-slate-500 font-bold uppercase tracking-widest">Bank:</span>
                    <span class="font-black text-slate-800 uppercase"><?php echo htmlspecialchars($purchase['bank_name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <table class="w-full mb-12">
        <thead>
            <tr class="bg-slate-900 text-white text-[10px] uppercase font-black tracking-widest">
                <th class="py-4 px-4 rounded-l-xl">Description</th>
                <th class="py-4 px-4 text-center">Qty</th>
                <th class="py-4 px-4 text-right">Net Price</th>
                <th class="py-4 px-4 text-right rounded-r-xl">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($items)): ?>
                <tr>
                    <td class="py-8 px-4 font-black text-slate-800 text-sm">
                        <?php echo htmlspecialchars($purchase['category']); ?>
                        <p class="text-[10px] text-slate-400 mt-1 uppercase font-bold tracking-widest"><?php echo htmlspecialchars($purchase['notes'] ?? 'General Expense'); ?></p>
                    </td>
                    <td class="py-8 px-4 text-center text-slate-800 font-bold">1</td>
                    <td class="py-8 px-4 text-right text-slate-800 font-bold">₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                    <td class="py-8 px-4 text-right text-slate-800 font-bold">-</td>
                    <td class="py-8 px-4 text-right font-black text-slate-900 text-lg">₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                </tr>
            <?php else: ?>
                <?php 
                $totalTaxable = 0;
                $totalGst = 0;
                foreach ($items as $item): 
                    $totalTaxable += ($item['taxable_value'] * $item['quantity']);
                    $totalGst += ($item['gst_amount'] * $item['quantity']);
                ?>
                <tr>
                    <td class="py-6 px-4">
                        <p class="font-black text-slate-800 text-sm uppercase tracking-tight"><?php echo htmlspecialchars($item['product_name']); ?></p>
                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-1">HSN: <?php echo htmlspecialchars($item['product_code']); ?> | GST <?php echo $item['gst_rate']; ?>%</p>
                    </td>
                    <td class="py-6 px-4 text-center text-slate-600 font-black text-sm"><?php echo $item['quantity']; ?></td>
                    <td class="py-6 px-4 text-right text-indigo-600 font-black text-xs">₹<?php echo number_format($item['unit_price'], 2); ?></td>
                    <td class="py-6 px-4 text-right font-black text-slate-800 text-sm">₹<?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="flex justify-end pt-8 border-t-4 border-slate-900">
        <div class="w-full max-w-xs space-y-4">
            <?php if (!empty($items)): ?>
            <?php 
            $gstRates = array_unique(array_column($items, 'gst_rate'));
            $gstLabel = "Total GST" . (count($gstRates) === 1 ? " (" . reset($gstRates) . "%)" : "");
            ?>
            <div class="flex justify-between text-[10px] font-black uppercase tracking-widest">
                <span class="text-slate-400">Total Price:</span>
                <span class="text-slate-800 font-black tracking-tight">₹<?php echo number_format($totalTaxable, 2); ?></span>
            </div>
            <div class="flex justify-between text-[10px] font-black uppercase tracking-widest">
                <span class="text-slate-400"><?php echo $gstLabel; ?>:</span>
                <span class="text-slate-800 font-black tracking-tight">₹<?php echo number_format($totalGst, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="pt-4 flex justify-between items-center text-2xl">
                <span class="font-black text-slate-900 uppercase tracking-tighter">Total:</span>
                <span class="font-black text-indigo-600 tracking-tighter">₹<?php echo number_format($purchase['total_amount'], 2); ?></span>
            </div>
            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100 text-center mt-4">
                <p class="text-[9px] font-black text-indigo-600 uppercase tracking-widest mb-1">Amount In Words</p>
                <p class="text-[10px] font-bold text-slate-600 italic">Rupees <?php echo ucwords(number_to_word($purchase['total_amount'])); ?> Only</p>
            </div>
        </div>
    </div>

    <div class="mt-24 grid grid-cols-2 gap-12">
        <div class="text-center">
            <div class="h-16 border-b border-slate-200 mb-2 mx-auto w-48"></div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Authorized Signatory</p>
        </div>
        <div class="text-center">
            <div class="h-16 border-b border-slate-200 mb-2 mx-auto w-48"></div>
            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Supplier Acknowledgement</p>
        </div>
    </div>
</div>

<?php
function number_to_word($number) {
    $hyphen      = '-';
    $conjunction = ' and ';
    $separator   = ', ';
    $negative    = 'negative ';
    $decimal     = ' point ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'one',
        2                   => 'two',
        3                   => 'three',
        4                   => 'four',
        5                   => 'five',
        6                   => 'six',
        7                   => 'seven',
        8                   => 'eight',
        9                   => 'nine',
        10                  => 'ten',
        11                  => 'eleven',
        12                  => 'twelve',
        13                  => 'thirteen',
        14                  => 'fourteen',
        15                  => 'fifteen',
        16                  => 'sixteen',
        17                  => 'seventeen',
        18                  => 'eighteen',
        19                  => 'nineteen',
        20                  => 'twenty',
        30                  => 'thirty',
        40                  => 'fourty',
        50                  => 'fifty',
        60                  => 'sixty',
        70                  => 'seventy',
        80                  => 'eighty',
        90                  => 'ninety',
        100                 => 'hundred',
        1000                => 'thousand',
        1000000             => 'million',
        1000000000          => 'billion',
        1000000000000       => 'trillion',
        1000000000000000    => 'quadrillion',
        1000000000000000000 => 'quintillion'
    );
    if (!is_numeric($number)) return false;
    if ($number < 0) return $negative . number_to_word(abs($number));
    $string = $fraction = null;
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $hundreds  = $number / 100;
            $remainder = $number % 100;
            $string = $dictionary[(int) $hundreds] . ' ' . $dictionary[100];
            if ($remainder) {
                $string .= $conjunction . number_to_word($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = number_to_word($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= number_to_word($remainder);
            }
            break;
    }
    return $string;
}
?>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    main { margin-left: 0 !important; padding: 0 !important; }
    #voucher-printable { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; box-shadow: none !important; border: none !important; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
