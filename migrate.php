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

    // 6. Create income table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `income` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `income_date` DATE NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `source` VARCHAR(150) NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `reference_no` VARCHAR(100),
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `income` table.</div>";

    // 7. Create expenses table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `expenses` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `expense_date` DATE NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `payee` VARCHAR(150) NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `reference_no` VARCHAR(100),
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `expenses` table.</div>";

    // 8. Create suppliers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `suppliers` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `supplier_name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(15),
        `email` VARCHAR(100),
        `address` TEXT,
        `gst_number` VARCHAR(20),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `suppliers` table.</div>";

    // 9. Create vouchers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `vouchers` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `voucher_no` VARCHAR(50) UNIQUE NOT NULL,
        `voucher_type` ENUM('Payment', 'Receipt') NOT NULL,
        `voucher_date` DATE NOT NULL,
        `account_head` VARCHAR(100) NOT NULL,
        `payee_payer` VARCHAR(150) NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `reference_no` VARCHAR(100),
        `narration` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `vouchers` table.</div>";

    // 9.1 Create company_profile table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `company_profile` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_name` VARCHAR(255) NOT NULL,
        `address` TEXT,
        `contact_number` VARCHAR(50),
        `email` VARCHAR(100),
        `gst_number` VARCHAR(50),
        `bank_details` TEXT,
        `logo` VARCHAR(255),
        `signature` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $count = $pdo->query("SELECT COUNT(*) FROM `company_profile`")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO `company_profile` (`company_name`) VALUES ('CCTV Management')");
    }
    echo "<div class='log success'>✅ Checked `company_profile` table.</div>";

    // 10. Check invoices and payments tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoices` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `complaint_id` INT,
        `invoice_no` VARCHAR(50) UNIQUE,
        `subtotal` DECIMAL(10, 2),
        `gst_amount` DECIMAL(10, 2),
        `grand_total` DECIMAL(10, 2),
        `payment_status` ENUM('Paid', 'Unpaid', 'Partial') DEFAULT 'Unpaid',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $check_invoice_pm = $pdo->query("SHOW COLUMNS FROM `invoices` LIKE 'payment_method'");
    if ($check_invoice_pm->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `invoices` ADD `payment_method` ENUM('Cash', 'UPI', 'Bank Transfer', 'Cheque') DEFAULT 'Cash' AFTER `payment_status` text");
        echo "<div class='log success'>✅ Added `payment_method` to `invoices` table.</div>";
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `customer_id` INT,
        `invoice_id` INT NULL,
        `payment_date` DATE NOT NULL,
        `amount` DECIMAL(10, 2) NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `reference_no` VARCHAR(100),
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `invoices` and `payments` tables.</div>";

    // Drop legacy expenses table
    $pdo->exec("DROP TABLE IF EXISTS `expenses` ");
    echo "<div class='log info'>ℹ️ Legacy `expenses` table dropped.</div>";

    // 11. Create stock_categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stock_categories` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `category_name` VARCHAR(100) UNIQUE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div class='log success'>✅ Checked `stock_categories` table.</div>";

    // 12. Update products table
    // Rename item_name to product_name if exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE 'item_name'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE `products` CHANGE `item_name` `product_name` VARCHAR(255) NOT NULL");
        echo "<div class='log success'>✅ Renamed `item_name` to `product_name` in `products`.</div>";
    }

    // Rename stock_qty to current_stock if exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE 'stock_qty'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE `products` CHANGE `stock_qty` `current_stock` INT DEFAULT 0");
        echo "<div class='log success'>✅ Renamed `stock_qty` to `current_stock` in `products`.</div>";
    }

    // Rename min_stock_level to opening_stock if exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE 'min_stock_level'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE `products` CHANGE `min_stock_level` `opening_stock` INT DEFAULT 0");
        echo "<div class='log success'>✅ Renamed `min_stock_level` to `opening_stock` in `products`.</div>";
    }

    // Add new columns if not exists
    $new_cols = [
        'category_id' => "INT AFTER `id` text",
        'product_code' => "VARCHAR(100) UNIQUE AFTER `product_name` text",
        'unit' => "VARCHAR(50) DEFAULT 'Pcs' AFTER `product_code` text",
        'opening_stock' => "INT DEFAULT 0 AFTER `current_stock` text",
        'description' => "TEXT AFTER `opening_stock` text"
    ];
    
    foreach ($new_cols as $col => $def) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE '$col'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `products` ADD `$col` " . str_replace(" text", "", $def));
            echo "<div class='log success'>✅ Added `$col` to `products` table.</div>";
        }
    }
    
    // Add Foreign Key for category_id if not exists
    try {
        $pdo->exec("ALTER TABLE `products` ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `stock_categories`(`id`) ON DELETE SET NULL");
        echo "<div class='log success'>✅ Added foreign key for `category_id`.</div>";
    } catch (Exception $e) {
        // FK might already exist
    }

    // 13. Create product_serials table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `product_serials` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `product_id` INT,
        `serial_number` VARCHAR(100) UNIQUE NOT NULL,
        `status` ENUM('In Stock', 'Sold', 'Defective', 'Returned') DEFAULT 'In Stock',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    )");
    echo "<div class='log success'>✅ Checked `product_serials` table.</div>";

    // 14. Update purchases table (Enhanced to replace expenses)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchases` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `supplier_id` INT NULL,
        `purchase_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $new_purchase_cols = [
        'payee_name' => "VARCHAR(150) NULL AFTER `supplier_id`",
        'category' => "VARCHAR(100) DEFAULT 'Inventory Purchase' AFTER `payee_name`",
        'bill_no' => "VARCHAR(100) AFTER `category` text",
        'total_amount' => "DECIMAL(10, 2) AFTER `bill_no` text",
        'payment_method' => "VARCHAR(50) DEFAULT 'Cash' AFTER `total_amount` text",
        'reference_no' => "VARCHAR(100) AFTER `payment_method` text",
        'notes' => "TEXT AFTER `reference_no` text",
        'is_inventory' => "TINYINT(1) DEFAULT 1 AFTER `notes` text",
        'bank_name' => "VARCHAR(150) NULL AFTER `is_inventory` text",
        'cheque_no' => "VARCHAR(50) NULL AFTER `bank_name` text",
        'cheque_date' => "DATE NULL AFTER `cheque_no` text"
    ];

    foreach ($new_purchase_cols as $col => $def) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `purchases` LIKE '$col'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `purchases` ADD `$col` " . str_replace(" text", "", $def));
        }
    }
    echo "<div class='log success'>✅ Checked and updated `purchases` table for unified outflows.</div>";

    // 15. Create purchase_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `purchase_items` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `purchase_id` INT,
        `product_id` INT,
        `quantity` INT NOT NULL,
        `unit_price` DECIMAL(10, 2),
        `total_price` DECIMAL(10, 2),
        FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    )");

    $new_pi_cols = [
        'taxable_value' => "DECIMAL(10, 2) DEFAULT 0 AFTER `unit_price`",
        'gst_rate' => "DECIMAL(5, 2) DEFAULT 0 AFTER `taxable_value` text",
        'gst_amount' => "DECIMAL(10, 2) DEFAULT 0 AFTER `gst_rate` text",
        'discount_type' => "ENUM('Fixed', 'Percentage') DEFAULT 'Fixed' AFTER `gst_amount` text",
        'discount_value' => "DECIMAL(10, 2) DEFAULT 0 AFTER `discount_type` text",
        'discount_amount' => "DECIMAL(10, 2) DEFAULT 0 AFTER `discount_value` text"
    ];

    foreach ($new_pi_cols as $col => $def) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `purchase_items` LIKE '$col'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `purchase_items` ADD `$col` " . str_replace(" text", "", $def));
            echo "<div class='log success'>✅ Added `$col` to `purchase_items` table.</div>";
        }
    }
    echo "<div class='log success'>✅ Checked `purchase_items` table and updated GST columns.</div>";

    // 16. Create stock_movements table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stock_movements` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `product_id` INT,
        `type` ENUM('Stock In', 'Stock Out', 'Adjustment') NOT NULL,
        `quantity` INT NOT NULL,
        `reference_id` INT NULL COMMENT 'Link to purchase_items or invoice_items',
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
    )");
    echo "<div class='log success'>✅ Checked `stock_movements` table.</div>";
    
    // 17. Update vouchers table for cheque details
    $new_voucher_cols = [
        'bank_name' => "VARCHAR(150) NULL AFTER `narration`",
        'cheque_no' => "VARCHAR(50) NULL AFTER `bank_name`",
        'cheque_date' => "DATE NULL AFTER `cheque_no`"
    ];
    foreach ($new_voucher_cols as $col => $def) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `vouchers` LIKE '$col'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `vouchers` ADD `$col` " . str_replace(" text", "", $def));
            echo "<div class='log success'>✅ Added `$col` to `vouchers` table.</div>";
        }
    }

    echo "<h3 style='margin-top:2rem; color:#16a34a;'>Migration Completed Successfully!</h3>";
    echo "<p><a href='index.php' style='color:#4f46e5; text-decoration:none; font-weight:bold;'>&larr; Return to Login</a></p>";
} catch (PDOException $e) {
    echo "<h3 class='error'>❌ Migration Failed</h3>";
    echo "<div class='log error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
