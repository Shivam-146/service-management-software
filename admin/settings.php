<?php
require_once '../includes/auth.php';
checkAccess('admin');
require_once '../includes/db_connect.php';

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if (empty($fullname)) {
        $errorMessage = "Name cannot be empty.";
    } else {
        try {
            if (!empty($newPassword)) {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, address = ?, password = ? WHERE id = ?");
                $stmt->execute([$fullname, $address, $hashed, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fullname = ?, address = ? WHERE id = ?");
                $stmt->execute([$fullname, $address, $userId]);
            }
            $_SESSION['fullname'] = $fullname; // Update session just in case
            $successMessage = "Settings updated successfully.";
        } catch (PDOException $e) {
            $errorMessage = "Error updating settings: " . $e->getMessage();
        }
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT fullname, address FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="space-y-8 max-w-2xl mx-auto">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-slate-800">Account Settings</h2>
    </div>

    <?php if ($successMessage): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
        <?php echo $successMessage; ?>
    </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm font-medium">
        <?php echo $errorMessage; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-100">
        <form action="" method="POST" class="space-y-6">
            <div>
                <label for="fullname" class="block text-sm font-bold text-slate-700 mb-1">Full Name</label>
                <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition text-sm">
            </div>

            <div>
                <label for="address" class="block text-sm font-bold text-slate-700 mb-1">Address</label>
                <textarea name="address" id="address" rows="3" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition text-sm"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                <p class="text-xs text-slate-500 mt-2">Update your contact or physical address here. Leave the password field blank if you do not wish to change it.</p>
            </div>

            <div class="pt-6 border-t border-slate-100">
                <h3 class="text-lg font-bold text-slate-800 mb-4">Security</h3>
                <div>
                    <label for="new_password" class="block text-sm font-bold text-slate-700 mb-1">New Password</label>
                    <input type="password" name="new_password" id="new_password" placeholder="Leave blank to keep current password" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 transition text-sm">
                </div>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full sm:w-auto px-8 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
