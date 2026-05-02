<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

// Simple Revenue Stats for Reports
try {
    $totalRevenue = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE payment_status = 'Paid'")->fetchColumn() ?? 0;
    $unpaidRevenue = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE payment_status = 'Unpaid'")->fetchColumn() ?? 0;
    
    // Complaints by status count
    $statusCounts = $pdo->query("SELECT status, COUNT(*) as count FROM complaints GROUP BY status")->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Reports & Analytics</h2>
        <button class="bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Generate PDF Report</button>
    </div>

    <!-- Revenue Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-indigo-600 rounded-2xl p-8 text-white shadow-xl shadow-indigo-100">
            <h3 class="text-indigo-100 text-sm font-bold uppercase tracking-widest mb-2">Total Collection</h3>
            <p class="text-4xl font-black mb-6">₹<?php echo number_format($totalRevenue, 2); ?></p>
            <div class="flex items-center gap-2 text-xs font-bold text-indigo-200">
                <span class="bg-white/20 px-2 py-1 rounded">Lifetime</span>
                <span>Includes all paid invoices</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl p-8 border border-slate-100 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-slate-400 text-sm font-bold uppercase tracking-widest mb-2">Outstanding Dues</h3>
                <p class="text-4xl font-black text-slate-800">₹<?php echo number_format($unpaidRevenue, 2); ?></p>
            </div>
            <div class="flex items-center gap-2 text-xs font-bold text-red-500 mt-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>
                <span>Requires Collection Follow-up</span>
            </div>
        </div>
    </div>

    <!-- Complaint Distribution -->
    <div class="bg-white rounded-2xl p-8 border border-slate-100 shadow-sm">
        <h3 class="text-lg font-bold text-slate-800 mb-6">Ticket Distribution by Status</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <?php foreach ($statusCounts as $s): ?>
                <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 text-center">
                    <p class="text-2xl font-black text-slate-800"><?php echo $s['count']; ?></p>
                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mt-1"><?php echo $s['status']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-2 bg-white rounded-2xl p-8 border border-slate-100 shadow-sm">
             <h3 class="text-lg font-bold text-slate-800 mb-2">Performance Metrics</h3>
             <p class="text-sm text-slate-400 mb-6">Average resolution time and technician performance charts would appear here.</p>
             <div class="h-48 bg-slate-50 rounded-xl border border-dashed border-slate-200 flex items-center justify-center">
                 <p class="text-slate-300 font-medium italic">Data Visualization Component Placeholder</p>
             </div>
        </div>
        <div class="bg-white rounded-2xl p-8 border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-4">Quick Stats</h3>
            <ul class="space-y-4">
                <li class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Avg. Ticket Value</span>
                    <span class="text-sm font-bold text-slate-800">₹2,450</span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Support Ratio</span>
                    <span class="text-sm font-bold text-slate-800">1:120</span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="text-sm text-slate-500">Customer NPS</span>
                    <span class="text-sm font-bold text-green-600">8.4</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
