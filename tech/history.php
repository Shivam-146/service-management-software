<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

$techId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT c.*, cust.customer_name 
                           FROM complaints c 
                           JOIN customers cust ON c.customer_id = cust.id 
                           WHERE c.assigned_tech_id = ? AND c.status IN ('Completed', 'Closed') 
                           ORDER BY c.completed_at DESC");
    $stmt->execute([$techId]);
    $history = $stmt->fetchAll();

    // Map products to resolve parts JSON
    $prodStmt = $pdo->query("SELECT id, product_name as item_name FROM products");
    $products = [];
    while ($row = $prodStmt->fetch()) {
        $products[$row['id']] = $row['item_name'];
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="space-y-8">
    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Job History</h2>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="divide-y divide-slate-100">
            <?php if (empty($history)): ?>
                <div class="p-12 text-center text-slate-400 font-medium">No completed jobs found in your history.</div>
            <?php else: ?>
                <?php foreach ($history as $job): ?>
                <div class="p-6 hover:bg-slate-50 transition border-l-4 border-green-500">
                    <div class="flex items-center justify-between mb-2">
                         <h4 class="font-bold text-slate-800"><?php echo htmlspecialchars($job['customer_name']); ?></h4>
                          <span class="text-xs font-bold text-slate-400">
                             <?php echo $job['completed_at'] ? date('M j, Y - g:i A', strtotime($job['completed_at'])) : 'Completed'; ?>
                          </span>
                    </div>
                    <p class="text-sm text-slate-600 mb-4"><?php echo htmlspecialchars($job['issue_description']); ?></p>
                    
                    <div class="mt-2 mb-4 pt-4 border-t border-slate-100 flex flex-wrap gap-8">
                        <?php if ($job['parts_consumed']): 
                            $parts = json_decode($job['parts_consumed'], true);
                            if (!empty($parts)):
                        ?>
                        <div class="flex-1 min-w-[200px]">
                            <h5 class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-2">Parts Consumed</h5>
                            <ul class="space-y-1">
                                <?php foreach ($parts as $p): ?>
                                    <li class="text-xs text-slate-600 font-semibold flex items-center gap-1.5">
                                        <div class="w-1 h-1 bg-indigo-400 rounded-full"></div>
                                        <?php echo $p['qty']; ?>x <?php echo htmlspecialchars($products[$p['product_id']] ?? 'Unknown Product'); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; endif; ?>

                        <?php if ($job['photo_before'] || $job['photo_after']): ?>
                        <div class="flex gap-4">
                            <?php if ($job['photo_before']): ?>
                            <div>
                                <h5 class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-2 text-center">Before</h5>
                                <a href="<?php echo htmlspecialchars($job['photo_before']); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($job['photo_before']); ?>" class="h-16 w-20 object-cover rounded-lg border border-slate-200 shadow-sm hover:opacity-80 transition hover:scale-105 transform">
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($job['photo_after']): ?>
                            <div>
                                <h5 class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-2 text-center">After</h5>
                                <a href="<?php echo htmlspecialchars($job['photo_after']); ?>" target="_blank">
                                    <img src="<?php echo htmlspecialchars($job['photo_after']); ?>" class="h-16 w-20 object-cover rounded-lg border border-green-200 shadow-sm hover:opacity-80 transition hover:scale-105 transform">
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-4 bg-slate-50 p-3 rounded-xl border border-slate-100">
                         <div class="text-[10px] uppercase font-bold text-green-600 tracking-widest flex items-center gap-1 border-r border-slate-200 pr-4">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                             Resolved
                         </div>
                         <?php if ($job['tech_remarks']): ?>
                             <div class="text-[11px] font-medium text-slate-500 italic max-w-sm truncate flex-1 leading-relaxed">
                                 "<?php echo htmlspecialchars($job['tech_remarks']); ?>"
                             </div>
                         <?php else: ?>
                             <div class="text-[11px] text-slate-400 italic">No remarks provided.</div>
                         <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
