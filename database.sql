-- CCTV Management System Database Schema

-- 1. Users Table (Admin & Technicians)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `fullname` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(15),
  `role` ENUM('admin', 'technician') DEFAULT 'technician',
  `status` TINYINT(1) DEFAULT 1 COMMENT '1: Active, 0: Inactive',
  `rating` DECIMAL(3, 2) DEFAULT 5.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Customers Table
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `customer_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(15) NOT NULL,
  `address` TEXT NOT NULL,
  `gst_number` VARCHAR(20),
  `has_active_amc` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Products/Inventory Table (Masters)
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `item_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) COMMENT 'Camera, DVR, HDD, Power Supply, etc.',
  `serial_number` VARCHAR(100),
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `stock_qty` INT DEFAULT 0,
  `warranty_months` INT DEFAULT 12
);

-- 4. AMC Contracts Table
CREATE TABLE IF NOT EXISTS `amc_contracts` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `customer_id` INT,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `amount` DECIMAL(10, 2),
  `status` ENUM('Active', 'Expired') DEFAULT 'Active',
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
);

-- 5. Complaints/Service Tickets Table
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `customer_id` INT,
  `assigned_tech_id` INT NULL,
  `issue_description` TEXT NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
  `status` ENUM('Open', 'Assigned', 'In-Progress', 'Completed', 'Closed') DEFAULT 'Open',
  `tech_remarks` TEXT,
  `parts_consumed` JSON NULL COMMENT 'Stores array of {product_id, qty, price}',
  `photo_before` VARCHAR(255) NULL,
  `photo_after` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `assigned_at` DATETIME NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  FOREIGN KEY (`assigned_tech_id`) REFERENCES `users`(`id`)
);

-- 6. Invoices Table
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `complaint_id` INT,
  `invoice_no` VARCHAR(50) UNIQUE NOT NULL,
  `subtotal` DECIMAL(10, 2),
  `gst_amount` DECIMAL(10, 2),
  `grand_total` DECIMAL(10, 2),
  `payment_status` ENUM('Paid', 'Unpaid', 'Partial') DEFAULT 'Unpaid',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`)
);

-- Seed Initial Admin User
-- Password is '123456' hashed with PASSWORD_DEFAULT (BCRYPT)
INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`) 
VALUES ('System Admin', 'admin@example.in', '$2y$10$GSuBNwUeWmZq5Agp4n7U3OAfncbkXLABHKmtR0NI1sicOQxtyDFI6', '1234567890', 'admin', 1);

-- Seed Initial Tech User
-- Password is '123456' hashed with PASSWORD_DEFAULT (BCRYPT)
INSERT INTO `users` (`fullname`, `email`, `password`, `phone`, `role`, `status`, `rating`) 
VALUES ('Field Technician', 'tech@example.in', '$2y$10$/dEw/u9VYQS0yzbtNkDFH.sRjdTrkBzuKAP1pRlvR2dZrqEi1zXpa', '0987654321', 'technician', 1, 4.80);
