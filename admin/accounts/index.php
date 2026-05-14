<?php
require_once '../../includes/auth.php';
checkAccess('admin');
require_once '../../includes/header.php';

$modules = [
    [
        'title' => 'Income Entry',
        'link' => 'income.php',
        'icon' => '💰',
        'bg' => 'bg-emerald-100',
        'text' => 'text-emerald-600',
        'desc' => 'Record and manage all sources of incoming revenue.'
    ],
    [
        'title' => 'Purchase Entry',
        'link' => 'purchases.php',
        'icon' => '💸',
        'bg' => 'bg-rose-100',
        'text' => 'text-rose-600',
        'desc' => 'Record all business outflows, including inventory and utility bills.'
    ],
    [
        'title' => 'Payment Voucher',
        'link' => 'payment_voucher.php',
        'icon' => '📑',
        'bg' => 'bg-amber-100',
        'text' => 'text-amber-600',
        'desc' => 'Generate and manage official payment vouchers.'
    ],
    [
        'title' => 'Receipt Voucher',
        'link' => 'receipt_voucher.php',
        'icon' => '🧾',
        'bg' => 'bg-sky-100',
        'text' => 'text-sky-600',
        'desc' => 'Issue and track receipts for payments received.'
    ],
    [
        'title' => 'Customer Payments',
        'link' => 'customer_payments.php',
        'icon' => '👤',
        'bg' => 'bg-indigo-100',
        'text' => 'text-indigo-600',
        'desc' => 'Manage accounts receivable and customer history.'
    ],
    [
        'title' => 'Supplier Payments',
        'link' => 'supplier_payments.php',
        'icon' => '🚚',
        'bg' => 'bg-violet-100',
        'text' => 'text-violet-600',
        'desc' => 'Track accounts payable and vendor settlements.'
    ],
    [
        'title' => 'Cash Ledger',
        'link' => 'cash_ledger.php',
        'icon' => '💵',
        'bg' => 'bg-green-100',
        'text' => 'text-green-600',
        'desc' => 'Detailed statement of all cash transactions.'
    ],
    [
        'title' => 'Bank Ledger',
        'link' => 'bank_ledger.php',
        'icon' => '🏦',
        'bg' => 'bg-blue-100',
        'text' => 'text-blue-600',
        'desc' => 'Monitor bank balances and digital transfers.'
    ],
    [
        'title' => 'Daily Reports',
        'link' => 'daily_reports.php',
        'icon' => '📊',
        'bg' => 'bg-orange-100',
        'text' => 'text-orange-600',
        'desc' => 'Quick snapshot of daily financial performance.'
    ],
    [
        'title' => 'Outstanding Dues',
        'link' => 'dues.php',
        'icon' => '⏳',
        'bg' => 'bg-red-100',
        'text' => 'text-red-600',
        'desc' => 'Monitor pending payments and overdue accounts.'
    ],
    [
        'title' => 'Export Reports',
        'link' => 'export_reports.php',
        'icon' => '📤',
        'bg' => 'bg-slate-100',
        'text' => 'text-slate-600',
        'desc' => 'Download financial data in Excel or PDF formats.'
    ]
];
?>

<div class="max-w-7xl mx-auto space-y-8">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Accounts Management</h1>
            <p class="text-slate-500 mt-1">Manage finances, ledgers, and vouchers from a central hub.</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="px-4 py-2 bg-white border border-slate-200 rounded-xl shadow-sm flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span class="text-sm font-medium text-slate-600">Financial Year 2024-25</span>
            </div>
        </div>
    </div>

    <!-- Quick Links Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($modules as $module): ?>
        <a href="<?php echo $module['link']; ?>" class="group block bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
            <div class="flex items-start justify-between mb-4">
                <div class="w-12 h-12 <?php echo $module['bg']; ?> rounded-xl flex items-center justify-center text-2xl group-hover:scale-110 transition-transform duration-300">
                    <?php echo $module['icon']; ?>
                </div>
                <div class="text-slate-300 group-hover:text-indigo-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">
                <?php echo $module['title']; ?>
            </h3>
            <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                <?php echo $module['desc']; ?>
            </p>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
