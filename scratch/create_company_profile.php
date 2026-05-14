<?php
require_once 'includes/db_connect.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_profile (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL,
        address TEXT,
        contact_number VARCHAR(50),
        email VARCHAR(100),
        gst_number VARCHAR(50),
        bank_details TEXT,
        logo VARCHAR(255),
        signature VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Check if empty, insert default row
    $count = $pdo->query("SELECT COUNT(*) FROM company_profile")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO company_profile (company_name) VALUES ('My Company')");
    }
    
    echo "company_profile table created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
