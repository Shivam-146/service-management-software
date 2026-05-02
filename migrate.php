<?php
require_once 'includes/db_connect.php';

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Migration</title><style>body { font-family: sans-serif; background: #f8fafc; padding: 2rem; color: #334155; } .container { max-width: 600px; margin: auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); } h2 { color: #0f172a; margin-top: 0; } .success { color: #16a34a; } .info { color: #3b82f6; } .error { color: #dc2626; } .log { margin: 0.5rem 0; padding: 0.5rem; background: #f1f5f9; border-radius: 4px; }</style></head><body>";
echo "<div class='container'>";
echo "<h2>Database Migration Status</h2>";

try {
    // 1. Add rating to users table if not exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `users` LIKE 'rating'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `users` ADD `rating` DECIMAL(3, 2) DEFAULT 5.00 AFTER `status`");
        echo "<div class='log success'>✅ Added `rating` column to `users` table.</div>";
    } else {
        echo "<div class='log info'>ℹ️ `rating` column already exists in `users` table.</div>";
    }

    // 2. Add photo_before and photo_after to complaints table if not exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `complaints` LIKE 'photo_before'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `complaints` ADD `photo_before` VARCHAR(255) NULL AFTER `parts_consumed`");
        $pdo->exec("ALTER TABLE `complaints` ADD `photo_after` VARCHAR(255) NULL AFTER `photo_before`");
        echo "<div class='log success'>✅ Added `photo_before` and `photo_after` columns to `complaints` table.</div>";
    } else {
         echo "<div class='log info'>ℹ️ `photo_before` and `photo_after` columns already exist in `complaints` table.</div>";
    }

    // 3. Add assigned_at and started_at to complaints table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `complaints` LIKE 'assigned_at'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `complaints` ADD `assigned_at` DATETIME NULL AFTER `created_at`");
        $pdo->exec("ALTER TABLE `complaints` ADD `started_at` DATETIME NULL AFTER `assigned_at`");
        echo "<div class='log success'>✅ Added `assigned_at` and `started_at` columns to `complaints` table.</div>";
    } else {
        echo "<div class='log info'>ℹ️ `assigned_at` and `started_at` columns already exist in `complaints` table.</div>";
    }

    // 4. Update admin phone number
    $pdo->exec("UPDATE `users` SET `phone` = '9876543211' WHERE `email` = 'admin@example.in' AND (`phone` IS NULL OR `phone` = '')");
    echo "<div class='log success'>✅ Checked System Admin phone number.</div>";

    // 5. Seed a test Technician User if they don't exist
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'tech@example.in'");
    $stmt->execute();
    if ($stmt->rowCount() == 0) {
        $pdo->exec("INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`, `rating`) VALUES ('Field Technician', 'tech@example.in', '$2y$10$/dEw/u9VYQS0yzbtNkDFH.sRjdTrkBzuKAP1pRlvR2dZrqEi1zXpa', '9876543210', 'technician', 1, 4.80)");
        echo "<div class='log success'>✅ Seeded test Technician user (tech@example.in).</div>";
    } else {
        echo "<div class='log info'>ℹ️ Test Technician user already exists.</div>";
    }

    echo "<h3 style='margin-top:2rem; color:#16a34a;'>Migration Completed Successfully!</h3>";
    echo "<p><a href='index.php' style='color:#4f46e5; text-decoration:none; font-weight:bold;'>&larr; Return to Login</a></p>";
} catch (PDOException $e) {
    echo "<h3 class='error'>❌ Migration Failed</h3>";
    echo "<div class='log error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
