<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

$techId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$techId]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="max-w-xl mx-auto space-y-8">
    <h2 class="text-2xl font-black text-slate-800 tracking-tight">My Profile</h2>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8">
            <div class="flex flex-col items-center mb-10">
                <div class="h-32 w-32 rounded-3xl bg-slate-900 text-white flex items-center justify-center text-5xl font-black mb-4 shadow-xl shadow-slate-200">
                    <?php echo substr($user['fullname'], 0, 1); ?>
                </div>
                <h3 class="text-2xl font-black text-slate-800"><?php echo htmlspecialchars($user['fullname']); ?></h3>
                <p class="text-slate-400 font-bold uppercase tracking-widest text-xs mt-1"><?php echo $user['role']; ?></p>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-10">
                 <div class="p-6 bg-slate-50 rounded-3xl text-center">
                      <p class="text-[10px] uppercase font-black text-slate-400 tracking-widest mb-1">Performance</p>
                      <div class="flex items-center justify-center gap-1">
                          <span class="text-2xl font-black text-slate-800"><?php echo number_format($user['rating'], 1); ?></span>
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-yellow-500 fill-current" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" /></svg>
                      </div>
                 </div>
                 <div class="p-6 bg-indigo-50 rounded-3xl text-center border border-indigo-100">
                      <p class="text-[10px] uppercase font-black text-indigo-400 tracking-widest mb-1">Status</p>
                      <p class="text-2xl font-black text-indigo-700">Active</p>
                 </div>
            </div>

            <div class="space-y-4">
                 <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                      <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Phone</span>
                      <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($user['phone']); ?></span>
                 </div>
                 <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                      <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Email</span>
                      <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($user['email']); ?></span>
                 </div>
                 <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl">
                      <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Joined</span>
                      <span class="text-sm font-bold text-slate-800"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                 </div>
            </div>
        </div>
        <div class="p-4 bg-slate-50 border-t border-slate-100 text-center">
             <button class="text-xs font-black text-indigo-600 uppercase tracking-widest hover:text-indigo-800 transition">Edit Security Settings</button>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
