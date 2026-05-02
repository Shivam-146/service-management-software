<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

// Fetch Summary Stats
try {
    // 1. Open Tickets
    $openTickets = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'Open'")->fetchColumn();

    // 2. Pending Assignments
    $pendingAssignments = $pdo->query("SELECT COUNT(*) FROM complaints WHERE assigned_tech_id IS NULL")->fetchColumn();

    // 3. Total Revenue (Current Month)
    $revenue = $pdo->query("SELECT SUM(grand_total) FROM invoices WHERE payment_status = 'Paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn() ?? 0;

    // 4. AMC Expiries (Next 30 Days)
    $amcExpiries = $pdo->query("SELECT COUNT(*) FROM amc_contracts WHERE end_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 30 DAY)")->fetchColumn();

    // Recent Activity (Last 5 complaints)
    $stmt = $pdo->query("SELECT c.*, cust.customer_name FROM complaints c JOIN customers cust ON c.customer_id = cust.id ORDER BY c.created_at DESC LIMIT 5");
    $recentComplaints = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Dashboard Overview</h2>
        <span class="text-sm text-slate-500"><?php echo date('F j, Y'); ?></span>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Open Tickets</p>
                    <p class="text-2xl font-bold text-slate-900"><?php echo $openTickets; ?></p>
                </div>
                <div class="p-3 bg-red-50 rounded-lg text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Pending Assignment</p>
                    <p class="text-2xl font-bold text-slate-900"><?php echo $pendingAssignments; ?></p>
                </div>
                <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Monthly Revenue</p>
                    <p class="text-2xl font-bold text-slate-900">₹<?php echo number_format($revenue, 2); ?></p>
                </div>
                <div class="p-3 bg-green-50 rounded-lg text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">AMC Expiries (30d)</p>
                    <p class="text-2xl font-bold text-slate-900"><?php echo $amcExpiries; ?></p>
                </div>
                <div class="p-3 bg-yellow-50 rounded-lg text-yellow-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Table -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-100">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800">Recent Complaints</h3>
            <a href="complaints.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View All</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider">
                        <th class="px-6 py-4 font-semibold">ID</th>
                        <th class="px-6 py-4 font-semibold">Customer</th>
                        <th class="px-6 py-4 font-semibold">Issue</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentComplaints)): ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No recent complaints found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentComplaints as $complaint): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $complaint['id']; ?></td>
                            <td class="px-6 py-4 text-sm text-slate-600"><?php echo htmlspecialchars($complaint['customer_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-slate-600 truncate max-w-xs"><?php echo htmlspecialchars($complaint['issue_description']); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php 
                                    $statusClasses = [
                                        'Open' => 'bg-red-100 text-red-700',
                                        'Assigned' => 'bg-blue-100 text-blue-700',
                                        'In-Progress' => 'bg-yellow-100 text-yellow-700',
                                        'Completed' => 'bg-green-100 text-green-700',
                                        'Closed' => 'bg-slate-100 text-slate-700',
                                    ];
                                    $class = $statusClasses[$complaint['status']] ?? 'bg-slate-100 text-slate-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-bold <?php echo $class; ?>">
                                    <?php echo $complaint['status']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500"><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
