<?php
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($fullname) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        try {
            // Check if email or phone already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
            $stmt->execute([$email, $phone]);
            if ($stmt->fetch()) {
                $error = "Email or Phone number already registered.";
            } else {
                // Insert new technician
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'technician', 1)");
                if ($stmt->execute([$fullname, $email, $phone, $hashed_password])) {
                    $success = "Registration successful! You can now sign in.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "System error: " . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-2xl shadow-xl border border-slate-100">
        <div>
            <div class="flex justify-center mb-6">
                <div class="p-3 bg-indigo-600 rounded-2xl text-white shadow-lg shadow-indigo-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
            </div>
            <h2 class="text-center text-3xl font-black text-slate-900 tracking-tight">
                Join our Tech Team
            </h2>
            <p class="mt-2 text-center text-sm text-slate-500 font-medium">
                Create your technician account
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-medium" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-xl text-sm font-medium" role="alert">
                <?php echo $success; ?>
                <div class="mt-2">
                    <a href="index.php" class="text-indigo-600 font-bold hover:underline">Go to Login</a>
                </div>
            </div>
        <?php else: ?>
            <form class="mt-8 space-y-4" action="" method="POST">
                <div>
                    <label for="fullname" class="block text-sm font-bold text-slate-700 mb-1 ml-1">Full Name</label>
                    <input id="fullname" name="fullname" type="text" required value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" class="appearance-none block w-full px-3 py-3 border border-slate-200 placeholder-slate-400 text-slate-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm" placeholder="John Doe">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-bold text-slate-700 mb-1 ml-1">Email Address</label>
                    <input id="email" name="email" type="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" class="appearance-none block w-full px-3 py-3 border border-slate-200 placeholder-slate-400 text-slate-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm" placeholder="tech@example.com">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-bold text-slate-700 mb-1 ml-1">Phone Number</label>
                    <input id="phone" name="phone" type="text" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" class="appearance-none block w-full px-3 py-3 border border-slate-200 placeholder-slate-400 text-slate-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm" placeholder="1234567890">
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-bold text-slate-700 mb-1 ml-1">Password</label>
                        <input id="password" name="password" type="password" required class="appearance-none block w-full px-3 py-3 border border-slate-200 placeholder-slate-400 text-slate-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm" placeholder="••••••••">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-bold text-slate-700 mb-1 ml-1">Confirm</label>
                        <input id="confirm_password" name="confirm_password" type="password" required class="appearance-none block w-full px-3 py-3 border border-slate-200 placeholder-slate-400 text-slate-900 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition text-sm" placeholder="••••••••">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition shadow-lg shadow-indigo-100">
                        Create Technician Account
                    </button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="mt-6 text-center">
            <p class="text-sm text-slate-600">
                Already have an account? 
                <a href="index.php" class="font-bold text-indigo-600 hover:text-indigo-500">Sign in</a>
            </p>
            <p class="mt-4 text-xs text-slate-400">© <?php echo date('Y'); ?> CCTV SECURE Management Solutions</p>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
