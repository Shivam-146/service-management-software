<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

// Date & Search Filtering Logic
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$searchTech = trim($_GET['tech_name'] ?? '');

try {
    // 1. Fetch Technicians
    $techQuery = "SELECT id, fullname FROM users WHERE role IN ('technician', 'tech') AND status = 1";
    $techParams = [];

    if (!empty($searchTech)) {
        $techQuery .= " AND fullname LIKE :search";
        $techParams['search'] = '%' . $searchTech . '%';
    }

    $techQuery .= " ORDER BY fullname ASC";
    $techStmt = $pdo->prepare($techQuery);
    $techStmt->execute($techParams);
    $technicians = $techStmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Complaints in date range
    $query = "SELECT c.*, cust.customer_name
              FROM complaints c
              JOIN customers cust ON c.customer_id = cust.id
              WHERE c.assigned_tech_id IS NOT NULL
              AND DATE(c.assigned_at) >= :start_date
              AND DATE(c.assigned_at) <= :end_date
              ORDER BY c.assigned_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Process Data into tech-specific arrays
    $techData = [];
    foreach ($technicians as $tech) {
        $techData[$tech['id']] = [
            'name'           => $tech['fullname'],
            'total_assigned' => 0,
            'completed'      => 0,
            'in_progress'    => 0,
            'pending'        => 0,
            'jobs'           => []
        ];
    }

    foreach ($complaints as $c) {
        $tId = $c['assigned_tech_id'];
        if (isset($techData[$tId])) {
            $techData[$tId]['total_assigned']++;
            $techData[$tId]['jobs'][] = $c;

            if ($c['status'] === 'Completed') {
                $techData[$tId]['completed']++;
            } elseif ($c['status'] === 'In-Progress') {
                $techData[$tId]['in_progress']++;
            } else {
                $techData[$tId]['pending']++;
            }
        }
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Aggregate totals
$aggAssigned   = array_sum(array_column($techData, 'total_assigned'));
$aggCompleted  = array_sum(array_column($techData, 'completed'));
$aggInProgress = array_sum(array_column($techData, 'in_progress'));
$overallRate   = $aggAssigned > 0 ? round(($aggCompleted / $aggAssigned) * 100) : 0;

require_once '../includes/header.php';
?>

<div class="space-y-6 max-w-6xl mx-auto">

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Technician Performance</h2>
            <p class="text-sm text-slate-500 mt-1">
                Viewing: <span class="font-semibold text-slate-700"><?php echo date('M j, Y', strtotime($startDate)); ?></span>
                &rarr; <span class="font-semibold text-slate-700"><?php echo date('M j, Y', strtotime($endDate)); ?></span>
                <?php if ($searchTech): ?>
                    &nbsp;&bull;&nbsp; <span class="text-indigo-600 font-semibold">"<?php echo htmlspecialchars($searchTech); ?>"</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Filter Bar -->
        <form method="GET" class="bg-white border border-slate-200 rounded-2xl shadow-sm p-3 flex flex-wrap gap-3 items-end w-full md:w-auto">
            <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
                <label class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">Search Tech</label>
                <div class="flex items-center gap-2 bg-slate-50 rounded-lg px-3 py-2 border border-slate-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="tech_name" value="<?php echo htmlspecialchars($searchTech); ?>" placeholder="Name..." class="bg-transparent text-sm text-slate-700 outline-none w-full placeholder-slate-400">
                </div>
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">From</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">To</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-bold rounded-lg hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">Apply</button>
                <a href="technician_report.php" class="px-3 py-2 bg-slate-100 text-slate-500 rounded-lg hover:bg-slate-200 transition" title="Reset">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Total Assigned</p>
            <p class="text-3xl font-black text-slate-800"><?php echo $aggAssigned; ?></p>
        </div>
        <div class="bg-white border border-slate-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Completed</p>
            <p class="text-3xl font-black text-green-600"><?php echo $aggCompleted; ?></p>
        </div>
        <div class="bg-white border border-slate-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">In Progress</p>
            <p class="text-3xl font-black text-yellow-500"><?php echo $aggInProgress; ?></p>
        </div>
        <div class="bg-white border border-slate-100 shadow-sm rounded-2xl p-5">
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Overall Rate</p>
            <p class="text-3xl font-black <?php echo $overallRate >= 80 ? 'text-green-600' : ($overallRate >= 50 ? 'text-yellow-500' : 'text-red-500'); ?>"><?php echo $overallRate; ?>%</p>
        </div>
    </div>

    <!-- Technician Cards -->
    <?php if (empty($techData)): ?>
        <div class="bg-white rounded-2xl border border-dashed border-slate-200 p-16 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <p class="text-slate-400 font-medium">No technicians found for the selected filters.</p>
            <a href="technician_report.php" class="mt-3 inline-block text-indigo-600 text-sm font-bold hover:underline">Reset Filters</a>
        </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($techData as $id => $data):
            $rate     = $data['total_assigned'] > 0 ? round(($data['completed'] / $data['total_assigned']) * 100) : 0;
            $rateColor  = $rate >= 80 ? 'text-green-600' : ($rate >= 50 ? 'text-yellow-500' : 'text-red-500');
            $barColor   = $rate >= 80 ? 'bg-green-500' : ($rate >= 50 ? 'bg-yellow-400' : 'bg-red-400');
            $initial    = strtoupper(substr($data['name'], 0, 1));
        ?>
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">

            <!-- Tech Card Header -->
            <div class="flex flex-col md:flex-row md:items-center gap-4 p-5 cursor-pointer select-none" onclick="toggleCard(<?php echo $id; ?>)">

                <!-- Avatar + Name -->
                <div class="flex items-center gap-4 flex-1">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-indigo-700 font-black text-xl flex-shrink-0">
                        <?php echo $initial; ?>
                    </div>
                    <div>
                        <div class="font-bold text-slate-800 text-base"><?php echo htmlspecialchars($data['name']); ?></div>
                        <div class="text-xs text-slate-400 mt-0.5"><?php echo $data['total_assigned']; ?> job<?php echo $data['total_assigned'] !== 1 ? 's' : ''; ?> in this period</div>
                    </div>
                </div>

                <!-- Stat Pills -->
                <div class="flex flex-wrap gap-3 items-center">
                    <div class="flex flex-col items-center bg-slate-50 rounded-xl px-4 py-2 min-w-[64px]">
                        <span class="text-lg font-black text-slate-800"><?php echo $data['total_assigned']; ?></span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase">Total</span>
                    </div>
                    <div class="flex flex-col items-center bg-green-50 rounded-xl px-4 py-2 min-w-[64px]">
                        <span class="text-lg font-black text-green-600"><?php echo $data['completed']; ?></span>
                        <span class="text-[10px] text-green-500 font-bold uppercase">Done</span>
                    </div>
                    <div class="flex flex-col items-center bg-yellow-50 rounded-xl px-4 py-2 min-w-[64px]">
                        <span class="text-lg font-black text-yellow-500"><?php echo $data['in_progress']; ?></span>
                        <span class="text-[10px] text-yellow-500 font-bold uppercase">Active</span>
                    </div>
                    <div class="flex flex-col items-center bg-red-50 rounded-xl px-4 py-2 min-w-[64px]">
                        <span class="text-lg font-black text-red-400"><?php echo $data['pending']; ?></span>
                        <span class="text-[10px] text-red-400 font-bold uppercase">Pending</span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="w-28 hidden md:block">
                        <div class="flex justify-between text-[10px] font-bold mb-1">
                            <span class="text-slate-400 uppercase">Rate</span>
                            <span class="<?php echo $rateColor; ?>"><?php echo $rate; ?>%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full <?php echo $barColor; ?> rounded-full transition-all duration-500" style="width: <?php echo $rate; ?>%"></div>
                        </div>
                    </div>

                    <!-- Chevron -->
                    <div id="chevron-<?php echo $id; ?>" class="transition-transform duration-200 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>
            </div>

            <!-- Collapsible Job Log -->
            <div id="card-<?php echo $id; ?>" class="hidden border-t border-slate-100">
                <?php if (empty($data['jobs'])): ?>
                    <div class="p-6 text-center text-slate-400 text-sm italic">No jobs assigned in this period.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-bold border-b border-slate-100">
                                    <th class="px-6 py-3">Ticket</th>
                                    <th class="px-6 py-3">Customer</th>
                                    <th class="px-6 py-3">Issue</th>
                                    <th class="px-6 py-3">Assigned</th>
                                    <th class="px-6 py-3">Completed</th>
                                    <th class="px-6 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($data['jobs'] as $job):
                                    $statusMap = [
                                        'Open'        => 'bg-red-100 text-red-700',
                                        'Assigned'    => 'bg-blue-100 text-blue-700',
                                        'In-Progress' => 'bg-yellow-100 text-yellow-700',
                                        'Completed'   => 'bg-green-100 text-green-700',
                                        'Closed'      => 'bg-slate-100 text-slate-600',
                                    ];
                                    $sc = $statusMap[$job['status']] ?? 'bg-slate-100 text-slate-600';
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-3 font-bold text-slate-800">#<?php echo $job['id']; ?></td>
                                    <td class="px-6 py-3 text-slate-700 font-medium"><?php echo htmlspecialchars($job['customer_name']); ?></td>
                                    <td class="px-6 py-3 text-slate-500 max-w-[200px] truncate"><?php echo htmlspecialchars($job['issue_description']); ?></td>
                                    <td class="px-6 py-3 text-slate-500 whitespace-nowrap"><?php echo $job['assigned_at'] ? date('M j, Y - g:i A', strtotime($job['assigned_at'])) : '—'; ?></td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <?php if ($job['completed_at']): ?>
                                            <span class="text-green-600 font-medium"><?php echo date('M j, Y - g:i A', strtotime($job['completed_at'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-300 italic">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $sc; ?>"><?php echo $job['status']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- end card -->
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleCard(id) {
    const card    = document.getElementById('card-' + id);
    const chevron = document.getElementById('chevron-' + id);
    const isOpen  = !card.classList.contains('hidden');
    card.classList.toggle('hidden', isOpen);
    chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}
</script>

<?php require_once '../includes/footer.php'; ?>
