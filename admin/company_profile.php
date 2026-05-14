<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

$successMsg = '';
$errorMsg = '';

// Fetch existing profile
$profile = $pdo->query("SELECT * FROM company_profile LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'];
    $address = $_POST['address'];
    $contact_number = $_POST['contact_number'];
    $email = $_POST['email'];
    $gst_number = $_POST['gst_number'];
    $bank_details = $_POST['bank_details'];

    // Handle File Uploads (Logo & Signature)
    $logo = $profile['logo'] ?? '';
    $signature = $profile['signature'] ?? '';

    if (!empty($_FILES['logo']['name'])) {
        $logoName = time() . '_logo_' . $_FILES['logo']['name'];
        if (move_uploaded_file($_FILES['logo']['tmp_name'], "../uploads/" . $logoName)) {
            $logo = $logoName;
        }
    }

    if (!empty($_FILES['signature']['name'])) {
        $sigName = time() . '_sig_' . $_FILES['signature']['name'];
        if (move_uploaded_file($_FILES['signature']['tmp_name'], "../uploads/" . $sigName)) {
            $signature = $sigName;
        }
    }

    try {
        if ($profile) {
            $stmt = $pdo->prepare("UPDATE company_profile SET company_name=?, address=?, contact_number=?, email=?, gst_number=?, bank_details=?, logo=?, signature=? WHERE id=?");
            $stmt->execute([$company_name, $address, $contact_number, $email, $gst_number, $bank_details, $logo, $signature, $profile['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO company_profile (company_name, address, contact_number, email, gst_number, bank_details, logo, signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$company_name, $address, $contact_number, $email, $gst_number, $bank_details, $logo, $signature]);
        }
        $successMsg = "Company profile updated successfully!";
        $profile = $pdo->query("SELECT * FROM company_profile LIMIT 1")->fetch();
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
}

require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8">
    <div class="flex items-center justify-between">
        <h1 class="text-3xl font-black text-slate-800 tracking-tight">Company Profile</h1>
        <p class="text-slate-500 font-medium">Invoice & Branding Details</p>
    </div>

    <?php if ($successMsg): ?>
        <div class="bg-emerald-100 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-none"><?php echo $successMsg; ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div class="bg-rose-100 border border-rose-200 text-rose-700 px-4 py-3 rounded-none"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-none shadow-none overflow-hidden">
        <div class="p-8 space-y-8">
            <!-- Basic Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Company Name *</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Contact Number</label>
                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Company Address</label>
                    <textarea name="address" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">GST Number</label>
                    <input type="text" name="gst_number" value="<?php echo htmlspecialchars($profile['gst_number'] ?? ''); ?>" class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm">
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Bank Details -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Bank Details (Account No, IFSC, Branch)</label>
                <textarea name="bank_details" rows="3" placeholder="Account Name: ...&#10;Account No: ...&#10;IFSC: ...&#10;Bank: ..." class="w-full bg-slate-50 border border-slate-200 rounded-none px-4 py-3 text-sm"><?php echo htmlspecialchars($profile['bank_details'] ?? ''); ?></textarea>
            </div>

            <hr class="border-slate-100">

            <!-- Branding -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Company Logo</label>
                    <div class="flex items-center gap-4">
                        <?php if ($profile['logo']): ?>
                            <img src="../uploads/<?php echo $profile['logo']; ?>" class="w-20 h-20 object-contain bg-slate-50 border border-slate-100">
                        <?php endif; ?>
                        <input type="file" name="logo" class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-none file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-2">Authorized Signature</label>
                    <div class="flex items-center gap-4">
                        <?php if ($profile['signature']): ?>
                            <img src="../uploads/<?php echo $profile['signature']; ?>" class="w-20 h-20 object-contain bg-slate-50 border border-slate-100">
                        <?php endif; ?>
                        <input type="file" name="signature" class="text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-none file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                </div>
            </div>
        </div>

        <div class="p-8 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button type="submit" class="bg-indigo-600 text-white px-10 py-3 rounded-none font-bold hover:bg-indigo-700 transition">Update Profile</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>
