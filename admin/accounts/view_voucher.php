<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/db_connect.php';

if (!isset($_GET['id'])) {
    die("Voucher ID required.");
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = ?");
$stmt->execute([$id]);
$v = $stmt->fetch();

if (!$v) {
    die("Voucher not found.");
}

$isPayment = ($v['voucher_type'] === 'Payment');
$color = $isPayment ? 'indigo' : 'emerald';

// Fetch Company Profile
$profileStmt = $pdo->query("SELECT * FROM company_profile LIMIT 1");
$profile = $profileStmt->fetch();

$companyLogo = $profile['logo'] ?? '';
$companyName = $profile['company_name'] ?? 'CCTV Management';
$companyAddress = $profile['address'] ?? '123 Business Avenue, Tech Park, City - 110022';
$companyPhone = $profile['contact_number'] ?? '+91 98765 43210';
$companyGst = $profile['gst_number'] ?? '';
$companySignature = $profile['signature'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher - <?php echo $v['voucher_no']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .print-border { border: 2px solid #e2e8f0; }
        }
    </style>
</head>
<body class="bg-slate-50 p-4 md:p-12">

<div class="max-w-3xl mx-auto bg-white p-8 md:p-12 rounded-none shadow-xl print:shadow-none print:border print:rounded-none">
    <!-- Header -->
    <div class="flex justify-between items-start border-b-2 border-slate-100 pb-8 mb-8">
        <div class="flex items-start gap-4">
            <?php if ($companyLogo): ?>
                <img src="../uploads/<?php echo $companyLogo; ?>" class="h-16 w-16 object-contain">
            <?php endif; ?>
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tighter uppercase"><?php echo htmlspecialchars($companyName); ?></h1>
                <p class="text-slate-500 text-xs font-medium max-w-xs"><?php echo nl2br(htmlspecialchars($companyAddress)); ?></p>
                <p class="text-slate-500 text-xs font-medium">Phone: <?php echo htmlspecialchars($companyPhone); ?> <?php if ($companyGst): ?>| GSTIN: <?php echo htmlspecialchars($companyGst); ?><?php endif; ?></p>
            </div>
        </div>
        <div class="text-right">
            <h2 class="text-2xl font-black text-<?php echo $color; ?>-600 uppercase tracking-widest"><?php echo $v['voucher_type']; ?> Voucher</h2>
            <p class="text-slate-400 text-sm font-bold mt-1">No: <span class="text-slate-900"><?php echo $v['voucher_no']; ?></span></p>
            <p class="text-slate-400 text-sm font-bold">Date: <span class="text-slate-900"><?php echo date('d M, Y', strtotime($v['voucher_date'])); ?></span></p>
        </div>
    </div>

    <!-- Body -->
    <div class="space-y-8">
        <div class="grid grid-cols-2 gap-8">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $isPayment ? 'Paid To' : 'Received From'; ?></p>
                <p class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($v['payee_payer']); ?></p>
            </div>
            <div class="space-y-1 text-right">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Account Head</p>
                <p class="text-lg font-black text-slate-800"><?php echo htmlspecialchars($v['account_head']); ?></p>
            </div>
        </div>

        <div class="bg-slate-50 p-8 rounded-none border border-slate-100 flex flex-col items-center justify-center space-y-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Amount in Figures</p>
            <p class="text-4xl font-black text-slate-900">₹<?php echo number_format($v['amount'], 2); ?></p>
        </div>

        <div class="space-y-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Payment Details</p>
            <div class="flex flex-wrap gap-x-8 gap-y-2">
                <p class="text-sm font-medium text-slate-700"><span class="text-slate-400">Method:</span> <?php echo $v['payment_method']; ?></p>
                <?php if ($v['reference_no']): ?>
                    <p class="text-sm font-medium text-slate-700"><span class="text-slate-400">Ref No:</span> <?php echo $v['reference_no']; ?></p>
                <?php endif; ?>
                <?php if ($v['payment_method'] === 'Cheque'): ?>
                    <p class="text-sm font-medium text-slate-700"><span class="text-slate-400">Bank:</span> <?php echo htmlspecialchars($v['bank_name']); ?></p>
                    <p class="text-sm font-medium text-slate-700"><span class="text-slate-400">Cheque No:</span> <?php echo htmlspecialchars($v['cheque_no']); ?></p>
                    <p class="text-sm font-medium text-slate-700"><span class="text-slate-400">Cheque Date:</span> <?php echo $v['cheque_date'] ? date('d-M-Y', strtotime($v['cheque_date'])) : 'N/A'; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="space-y-1">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Narration</p>
            <p class="text-sm text-slate-600 leading-relaxed italic">"<?php echo htmlspecialchars($v['narration'] ?: 'Being amount ' . ($isPayment ? 'paid' : 'received') . ' for ' . $v['account_head']); ?>"</p>
        </div>
    </div>

    <!-- Signatures -->
    <div class="grid grid-cols-2 gap-12 mt-16 pt-8 border-t border-slate-100">
        <div class="text-center">
            <div class="h-12 border-b border-slate-300 mx-auto w-3/4 mb-2 flex items-end justify-center">
                <?php if ($isPayment && $companySignature): ?>
                    <!-- Signature only on Payment Voucher (from admin) -->
                <?php endif; ?>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $isPayment ? "Receiver's Signature" : "Payer's Signature"; ?></p>
        </div>
        <div class="text-center flex flex-col items-center">
            <div class="h-12 border-b border-slate-300 mx-auto w-3/4 mb-2 flex items-end justify-center">
                <?php if ($companySignature): ?>
                    <img src="../uploads/<?php echo $companySignature; ?>" class="h-10 object-contain">
                <?php endif; ?>
            </div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Authorized Signatory</p>
        </div>
    </div>
</div>

<!-- Controls -->
<div class="max-w-3xl mx-auto mt-8 flex justify-between items-center no-print px-4 md:px-0">
    <button onclick="window.close()" class="text-slate-500 font-bold text-sm hover:text-slate-800 transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        Close Window
    </button>
    <button onclick="window.print()" class="bg-indigo-600 text-white px-8 py-3 rounded-none font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
        Print Voucher
    </button>
</div>

</body>
</html>
