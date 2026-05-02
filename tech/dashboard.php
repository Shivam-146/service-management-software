<?php
require_once '../includes/auth.php';
checkAccess('technician');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

$techId = $_SESSION['user_id'];

// Handle Start Job Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_job') {
    $cid = (int)$_POST['complaint_id'];
    try {
        $stmt = $pdo->prepare("UPDATE complaints SET status = 'In-Progress', started_at = COALESCE(started_at, CURRENT_TIMESTAMP) WHERE id = ? AND assigned_tech_id = ?");
        $stmt->execute([$cid, $techId]);
        if ($stmt->rowCount() > 0) {
            $successMsg = "Job #$cid started successfully! Status updated to In-Progress.";
        } else {
            $errorMsg = "Could not start job. Please try again.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Database error: " . $e->getMessage();
    }
}

// Fetch Next Job (Highest priority / Oldest open/assigned)
try {
    $focusId = $_GET['focus_id'] ?? null;
    $nextJob = null;

    if ($focusId) {
        $focusStmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.address, cust.phone as client_phone 
                                      FROM complaints c 
                                      JOIN customers cust ON c.customer_id = cust.id 
                                      WHERE c.id = ? AND c.assigned_tech_id = ? AND c.status IN ('Assigned', 'In-Progress')");
        $focusStmt->execute([$focusId, $techId]);
        $nextJob = $focusStmt->fetch();
    }

    if (!$nextJob) {
        $nextJobStmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.address, cust.phone as client_phone 
                                      FROM complaints c 
                                      JOIN customers cust ON c.customer_id = cust.id 
                                      WHERE c.assigned_tech_id = ? AND c.status IN ('Assigned', 'In-Progress') 
                                      ORDER BY c.priority DESC, c.created_at ASC 
                                      LIMIT 1");
        $nextJobStmt->execute([$techId]);
        $nextJob = $nextJobStmt->fetch();
    }

    // Fetch Today's Tasks
    $tasksStmt = $pdo->prepare("SELECT c.*, cust.customer_name 
                                FROM complaints c 
                                JOIN customers cust ON c.customer_id = cust.id 
                                WHERE c.assigned_tech_id = ? AND c.status IN ('Assigned', 'In-Progress') 
                                ORDER BY c.created_at DESC");
    $tasksStmt->execute([$techId]);
    $tasks = $tasksStmt->fetchAll();

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <?php if (isset($successMsg)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-2xl animate-bounce" role="alert">
            <span class="block sm:inline font-bold"><?php echo $successMsg; ?></span>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMsg)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-2xl" role="alert">
            <span class="block sm:inline font-bold"><?php echo $errorMsg; ?></span>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Technician Dashboard</h2>
        <div class="flex items-center gap-2">
            <span class="flex h-3 w-3 rounded-full bg-green-500"></span>
            <span class="text-sm font-bold text-slate-500 uppercase">Available</span>
        </div>
    </div>

    <?php if ($nextJob): ?>
    <!-- Next Job Card -->
    <div class="bg-indigo-600 rounded-3xl p-8 text-white shadow-xl shadow-indigo-100 overflow-hidden relative">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-4">
                <div class="inline-flex items-center px-3 py-1 rounded-full bg-white/20 text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
                    <?php echo (isset($_GET['focus_id']) && $_GET['focus_id'] == $nextJob['id']) ? 'Selected Task' : 'Next Priority Job'; ?>
                </div>
                <div>
                    <h3 class="text-3xl font-black"><?php echo htmlspecialchars($nextJob['customer_name']); ?></h3>
                    <p class="text-indigo-100 mt-1 flex items-center gap-2 italic">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <?php echo htmlspecialchars($nextJob['address']); ?>
                    </p>
                </div>
                <div class="p-4 bg-white/10 rounded-2xl border border-white/10">
                    <p class="text-xs uppercase font-bold text-indigo-200 mb-1">Issue Reported</p>
                    <p class="text-lg font-medium"><?php echo htmlspecialchars($nextJob['issue_description']); ?></p>
                </div>
            </div>
            <div class="flex flex-col gap-3 shrink-0">
                <a href="customer-info.php?id=<?php echo $nextJob['id']; ?>" class="px-6 py-4 bg-white text-indigo-600 rounded-2xl font-black text-center shadow-lg hover:bg-slate-50 transition transform hover:scale-105">View Details</a>
                <?php if ($nextJob['status'] === 'Assigned'): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="start_job">
                        <input type="hidden" name="complaint_id" value="<?php echo $nextJob['id']; ?>">
                        <button type="submit" class="w-full px-6 py-4 bg-indigo-500/50 border border-white/20 rounded-2xl font-bold text-center hover:bg-indigo-500/70 transition">Start Job Now</button>
                    </form>
                <?php else: ?>
                    <a href="service-update.php?id=<?php echo $nextJob['id']; ?>" class="px-6 py-4 bg-indigo-500/50 border border-white/20 rounded-2xl font-bold text-center hover:bg-indigo-500/70 transition">Update Status</a>
                <?php endif; ?>
            </div>
        </div>
        <!-- Decorative Circle -->
        <div class="absolute -right-20 -bottom-20 h-64 w-64 bg-white/5 rounded-full blur-3xl"></div>
    </div>
    <?php endif; ?>

    <!-- Task List -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-slate-800 tracking-tight">Assigned Tasks</h3>
            <span class="px-3 py-1 bg-slate-100 rounded-full text-xs font-bold text-slate-500"><?php echo count($tasks); ?> Active</span>
        </div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($tasks)): ?>
                <div class="p-12 text-center">
                    <div class="flex justify-center mb-4">
                        <div class="p-4 bg-green-50 rounded-full text-green-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        </div>
                    </div>
                    <p class="text-slate-500 font-medium">All caught up! No active tasks for today.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $task): 
                    $isActive = ($nextJob && $nextJob['id'] == $task['id']);
                ?>
                <div onclick="window.location.href='?focus_id=<?php echo $task['id']; ?>'" class="p-6 hover:bg-slate-50 transition flex items-center justify-between gap-4 cursor-pointer <?php echo $isActive ? 'bg-indigo-50/50 border-l-4 border-indigo-500' : ''; ?>">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-2xl <?php echo $isActive ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400'; ?> flex items-center justify-center transition-colors">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800"><?php echo htmlspecialchars($task['customer_name']); ?></h4>
                            <p class="text-sm text-slate-500 truncate max-w-[200px] md:max-w-md"><?php echo htmlspecialchars($task['issue_description']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                         <?php 
                                $statusClasses = [
                                    'Open' => 'bg-red-50 text-red-600',
                                    'Assigned' => 'bg-blue-50 text-blue-600',
                                    'In-Progress' => 'bg-yellow-50 text-amber-600 animate-pulse',
                                ];
                                $class = $statusClasses[$task['status']] ?? 'bg-slate-50 text-slate-400';
                            ?>
                        <span class="px-3 py-1 hidden md:inline rounded-full text-[10px] font-black uppercase tracking-wider <?php echo $class; ?>">
                            <?php echo $task['status']; ?>
                        </span>
                        <a href="service-update.php?id=<?php echo $task['id']; ?>" class="p-2 text-slate-400 hover:text-indigo-600 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
