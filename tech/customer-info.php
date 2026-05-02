<?php
require_once '../includes/auth.php';
checkAccess('tech');
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

$complaintId = $_GET['id'] ?? null;
if (!$complaintId) header("Location: dashboard.php");

try {
    $stmt = $pdo->prepare("SELECT c.*, cust.customer_name, cust.address, cust.phone as client_phone 
                           FROM complaints c 
                           JOIN customers cust ON c.customer_id = cust.id 
                           WHERE c.id = ?");
    $stmt->execute([$complaintId]);
    $data = $stmt->fetch();

    if (!$data) die("Complaint not found.");

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$encodedAddress = urlencode($data['address']);
$mapUrl = "https://www.google.com/maps/search/?api=1&query=" . $encodedAddress;
?>

<div class="max-w-2xl mx-auto space-y-8">
    <div class="flex items-center gap-4">
        <a href="dashboard.php" class="p-2 bg-white rounded-xl shadow-sm border border-slate-100 text-slate-400 hover:text-indigo-600 transition">
             <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
        </a>
        <h2 class="text-2xl font-black text-slate-800 tracking-tight">Customer Information</h2>
    </div>

    <!-- Customer Card -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 space-y-6">
            <div class="flex items-center gap-6">
                <div class="h-20 w-20 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                     <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                </div>
                <div>
                    <h3 class="text-2xl font-black text-slate-800"><?php echo htmlspecialchars($data['customer_name']); ?></h3>
                    <p class="text-slate-500 font-medium italic">Complaint #<?php echo $complaintId; ?></p>
                </div>
            </div>

            <div class="space-y-4 pt-6 border-t border-slate-50">
                <div class="flex items-start gap-4">
                     <div class="p-2 bg-slate-50 rounded-lg text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                     </div>
                     <div>
                         <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-1">Service Address</p>
                         <p class="text-slate-700 font-medium leading-relaxed"><?php echo nl2br(htmlspecialchars($data['address'])); ?></p>
                     </div>
                </div>

                <div class="flex items-start gap-4">
                     <div class="p-2 bg-slate-50 rounded-lg text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                     </div>
                     <div>
                         <p class="text-[10px] uppercase font-bold text-slate-400 tracking-widest mb-1">Contact Primary</p>
                         <p class="text-slate-700 font-medium"><?php echo htmlspecialchars($data['client_phone']); ?></p>
                     </div>
                </div>

                <div class="p-6 bg-amber-50 rounded-2xl border border-amber-100">
                     <div class="flex items-center gap-2 mb-2">
                         <span class="px-2 py-0.5 rounded-lg bg-amber-200 text-amber-800 text-[10px] font-black uppercase">Reported Issue</span>
                     </div>
                     <p class="text-amber-900 font-medium"><?php echo htmlspecialchars($data['issue_description']); ?></p>
                </div>
            </div>
        </div>

        <!-- Action Grid -->
        <div class="grid grid-cols-2 bg-slate-50 border-t border-slate-100">
             <a href="tel:<?php echo $data['client_phone']; ?>" class="flex items-center justify-center gap-2 py-6 border-r border-slate-100 hover:bg-slate-100 transition">
                  <span class="p-2 bg-green-100 text-green-600 rounded-lg">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                  </span>
                  <span class="font-bold text-slate-700">Call Now</span>
             </a>
             <a href="<?php echo $mapUrl; ?>" target="_blank" class="flex items-center justify-center gap-2 py-6 hover:bg-slate-100 transition">
                  <span class="p-2 bg-blue-100 text-blue-600 rounded-lg">
                       <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                  </span>
                  <span class="font-bold text-slate-700">Open Maps</span>
             </a>
        </div>
    </div>

    <div class="flex justify-center">
         <a href="service-update.php?id=<?php echo $complaintId; ?>" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black text-center shadow-xl shadow-indigo-100 hover:bg-indigo-700 transition transform active:scale-95">Proceed to Service Update</a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
