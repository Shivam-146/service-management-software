-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 11:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cctv_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `amc_contracts`
--

CREATE TABLE `amc_contracts` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Active','Expired') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amc_contracts`
--

INSERT INTO `amc_contracts` (`id`, `customer_id`, `start_date`, `end_date`, `amount`, `status`) VALUES
(1, 1, '2026-04-17', '2026-04-20', 1000.00, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `company_profile`
--

CREATE TABLE `company_profile` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `gst_number` varchar(50) DEFAULT NULL,
  `bank_details` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_profile`
--

INSERT INTO `company_profile` (`id`, `company_name`, `address`, `contact_number`, `email`, `gst_number`, `bank_details`, `logo`, `signature`, `created_at`, `updated_at`) VALUES
(1, 'My Company', 'nagarjune road,bzone 3/21', '8348872742', 'shivamdas123pl@gmail.com', '15gt159AEF', '', '', '', '2026-05-14 06:45:59', '2026-05-14 09:13:33');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `assigned_tech_id` int(11) DEFAULT NULL,
  `issue_description` text NOT NULL,
  `priority` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Open','Assigned','In-Progress','Completed','Closed') DEFAULT 'Open',
  `tech_remarks` text DEFAULT NULL,
  `parts_consumed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Stores array of {product_id, qty, price}' CHECK (json_valid(`parts_consumed`)),
  `photo_before` varchar(255) DEFAULT NULL,
  `photo_after` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `customer_id`, `assigned_tech_id`, `issue_description`, `priority`, `status`, `tech_remarks`, `parts_consumed`, `photo_before`, `photo_after`, `created_at`, `assigned_at`, `started_at`, `completed_at`) VALUES
(1, 1, 2, 'i have a problem in the spare in cctv', 'High', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"2\"}]', '/cctv/uploads/complaints/1_before_1776448121.png', '/cctv/uploads/complaints/1_after_1776448121.png', '2026-04-17 17:26:40', NULL, NULL, '2026-04-17 23:18:41'),
(2, 2, 2, 'mnbhjgvffhgbj m', 'Medium', 'Completed', '', '[]', NULL, NULL, '2026-04-17 19:06:30', '2026-04-18 00:36:36', '2026-04-18 00:37:26', '2026-04-18 00:37:53'),
(3, 2, 2, 'mh', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"2\"}]', NULL, NULL, '2026-04-17 19:09:32', '2026-04-18 00:39:37', NULL, '2026-04-18 00:40:03'),
(4, 1, 2, 'i want to chnage the service of the key can u pls fix it', 'Medium', 'Completed', '', '[]', NULL, NULL, '2026-04-18 07:11:25', '2026-04-18 12:41:34', '2026-04-18 12:44:11', '2026-04-18 12:44:38'),
(5, 1, 2, 'display issue', 'Medium', NULL, '', '[]', NULL, NULL, '2026-04-23 14:18:10', '2026-04-23 19:48:18', NULL, NULL),
(6, 1, 2, 'screen issue', 'Low', 'Completed', 'Testing update.....', '[]', NULL, NULL, '2026-04-23 14:18:44', '2026-04-23 19:54:27', '2026-04-23 19:49:08', '2026-04-23 19:59:40'),
(7, 1, 2, 'diid play', 'Medium', 'Completed', '', '[]', NULL, NULL, '2026-04-23 14:24:47', '2026-04-23 19:56:09', '2026-04-23 19:55:33', '2026-04-23 19:57:14'),
(8, 1, 2, 'score', 'Medium', 'Completed', 'kalakar', '[]', NULL, NULL, '2026-04-23 14:30:40', '2026-04-23 20:01:27', NULL, '2026-04-23 20:01:43'),
(9, 2, 2, 'kalakar problem', 'Medium', 'Completed', '', '[]', NULL, NULL, '2026-04-23 14:32:05', '2026-04-23 20:02:26', '2026-04-23 20:08:54', '2026-04-23 20:10:24'),
(10, 2, 2, 'kola issue', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"2\"}]', NULL, NULL, '2026-04-23 14:39:26', '2026-04-23 20:09:59', '2026-04-23 20:10:31', '2026-04-23 20:12:37'),
(11, 2, 2, 'bhgytfg', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"1\"}]', NULL, NULL, '2026-04-23 14:42:00', '2026-04-23 20:12:05', '2026-04-23 20:17:28', '2026-04-23 22:01:41'),
(12, 1, 2, 'bhdb', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"1\"}]', NULL, NULL, '2026-04-23 14:42:57', '2026-04-23 20:13:03', '2026-04-23 20:17:22', '2026-04-23 22:02:09'),
(13, 1, 2, 'mobilre prblm', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"1\"}]', NULL, NULL, '2026-04-23 17:02:31', '2026-04-23 22:32:37', '2026-04-23 22:32:45', '2026-04-23 22:32:58'),
(14, 3, 2, 'bhaoo', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"1\"}]', NULL, NULL, '2026-05-09 18:52:03', '2026-05-10 00:22:16', '2026-05-10 00:22:31', '2026-05-10 00:24:53'),
(15, 3, 2, 'n ewd', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"3\"}]', NULL, NULL, '2026-05-10 18:47:08', '2026-05-11 00:17:13', '2026-05-11 00:17:35', '2026-05-11 00:23:22');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `has_active_amc` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_name`, `phone`, `address`, `gst_number`, `has_active_amc`, `created_at`) VALUES
(1, 'Shivam', '8348872742', 'nagarjune road,bzone 3/21', '', 1, '2026-04-17 17:23:39'),
(2, 'Walk-in Customer', '9632587411', 'nagarjune road,bzone 3/21', '', 0, '2026-04-17 19:06:20'),
(3, 'Andaman', '9632587419', 'bzone road', '', 0, '2026-05-09 18:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `id` int(11) NOT NULL,
  `income_date` datetime DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `source` varchar(150) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `complaint_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `gst_amount` decimal(10,2) DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('Paid','Unpaid','Partial') DEFAULT 'Unpaid',
  `payment_method` enum('Cash','UPI') DEFAULT 'Cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `complaint_id`, `invoice_no`, `subtotal`, `gst_amount`, `grand_total`, `payment_status`, `payment_method`, `created_at`) VALUES
(1, 1, 'INV177644847276', 2500.00, 450.00, 2950.00, 'Unpaid', 'Cash', '2026-04-17 17:54:32'),
(2, 2, 'INV177645294165', 500.00, 90.00, 590.00, 'Unpaid', 'Cash', '2026-04-17 19:09:01'),
(3, 4, 'INV177667747694', 500.00, 90.00, 590.00, 'Unpaid', 'Cash', '2026-04-20 09:31:16'),
(4, 3, 'INV177695352659', 2500.00, 450.00, 2950.00, 'Paid', 'UPI', '2026-04-23 14:12:06'),
(5, 11, 'INV177696190172', 1000.00, 0.00, 1000.00, 'Unpaid', 'UPI', '2026-04-23 16:31:41'),
(6, 12, 'INV177696192920', 1000.00, 0.00, 1000.00, 'Paid', 'Cash', '2026-04-23 16:32:09'),
(7, 13, 'INV177696377887', 1000.00, 0.00, 1000.00, 'Unpaid', NULL, '2026-04-23 17:02:58'),
(8, 14, 'INV177835289384', 1000.00, 0.00, 1000.00, 'Paid', 'UPI', '2026-05-09 18:54:53'),
(9, 15, 'INV177843887272', 3000.00, 0.00, 3000.00, 'Paid', 'Cash', '2026-05-10 18:47:52'),
(10, 15, 'INV177843920277', 3000.00, 0.00, 3000.00, 'Paid', 'Cash', '2026-05-10 18:53:22');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `customer_id`, `invoice_id`, `payment_date`, `amount`, `payment_method`, `reference_no`, `notes`, `created_at`) VALUES
(1, 1, 7, '2026-05-09 00:00:00', 1000.00, 'Cash', '', '', '2026-05-09 17:23:03'),
(2, 3, NULL, '2026-05-10 00:00:00', 2000.00, 'Cash', '', '', '2026-05-10 18:36:41'),
(3, NULL, 9, '2026-05-11 00:17:52', 3000.00, 'Cash', NULL, 'Payment received by technician for Ticket #15', '2026-05-10 18:47:52'),
(4, 3, 10, '2026-05-11 00:23:22', 3000.00, 'Cash', NULL, 'Payment received by technician for Ticket #15', '2026-05-10 18:53:22');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_code` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'Pcs',
  `category` varchar(50) DEFAULT NULL COMMENT 'Camera, DVR, HDD, Power Supply, etc.',
  `serial_number` varchar(100) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `current_stock` int(11) DEFAULT 0,
  `opening_stock` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `product_name`, `product_code`, `unit`, `category`, `serial_number`, `unit_price`, `current_stock`, `opening_stock`, `description`, `warranty_months`) VALUES
(1, NULL, 'camera', NULL, 'Pcs', 'cam', '11', 1000.00, 16, 5, NULL, 12),
(2, NULL, 'camera', NULL, 'Pcs', 'cam', '11', 100.00, 10, 5, NULL, 12),
(3, 1, '8 Channel DVR Hickvision', '2233', 'Pcs', NULL, NULL, 0.00, 5, 5, '', 12),
(6, 1, '8 Channel DVR Hick', '1478', 'Pcs', NULL, NULL, 0.00, 12, 5, '', 12),
(8, 1, 'abc', '1596', 'Pcs', NULL, NULL, 0.00, 5, 5, '', 12),
(9, NULL, '8 Channel DVR Hickhvbf', '5566', 'Pcs', NULL, NULL, 0.00, 10, 10, '', 12);

-- --------------------------------------------------------

--
-- Table structure for table `product_serials`
--

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `status` enum('In Stock','Sold','Defective','Returned') DEFAULT 'In Stock',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_serials`
--

INSERT INTO `product_serials` (`id`, `product_id`, `serial_number`, `status`, `created_at`) VALUES
(1, 6, '1235', 'In Stock', '2026-05-14 09:20:45'),
(2, 6, '1478', 'In Stock', '2026-05-14 09:20:45'),
(3, 6, '569', 'In Stock', '2026-05-14 09:20:45');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `payee_name` varchar(150) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Inventory Purchase',
  `purchase_date` datetime DEFAULT NULL,
  `bill_no` varchar(100) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_inventory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_name` varchar(150) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `payee_name`, `category`, `purchase_date`, `bill_no`, `total_amount`, `payment_method`, `reference_no`, `notes`, `is_inventory`, `created_at`, `bank_name`, `cheque_no`, `cheque_date`) VALUES
(1, 1, '', 'Rent', '2026-05-09 00:00:00', '', 1500.00, 'Cash', '', '', 1, '2026-05-09 18:48:57', NULL, NULL, NULL),
(2, 1, '', 'Rent', '2026-05-10 00:00:00', '', 1000.00, 'Credit', '', '', 1, '2026-05-10 18:32:44', NULL, NULL, NULL),
(3, 1, '', 'Rent', '2026-05-14 11:30:00', '', 500.00, 'Cash', '', '', 1, '2026-05-14 06:00:56', NULL, NULL, NULL),
(4, 1, '', 'Rent', '2026-05-14 12:11:00', '', 3000.00, 'Cheque', '', '', 1, '2026-05-14 06:41:52', 'SBI', '1598764', '2026-05-15');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
  `taxable_value` decimal(10,2) DEFAULT 0.00,
  `discount_type` enum('Fixed','Percentage') DEFAULT 'Fixed',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `gst_rate`, `gst_amount`, `taxable_value`, `discount_type`, `discount_value`, `discount_amount`) VALUES
(1, 1, 1, 15, 100.00, 1500.00, 0.00, 0.00, 0.00, 'Fixed', 0.00, 0.00),
(2, 2, 2, 5, 200.00, 1000.00, 0.00, 0.00, 0.00, 'Fixed', 0.00, 0.00),
(3, 3, 6, 5, 100.00, 500.00, 0.00, 0.00, 0.00, 'Fixed', 0.00, 0.00),
(4, 4, 6, 2, 1500.00, 3000.00, 0.00, 0.00, 1500.00, 'Fixed', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_categories`
--

CREATE TABLE `stock_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_categories`
--

INSERT INTO `stock_categories` (`id`, `category_name`, `created_at`) VALUES
(1, 'DVR', '2026-05-14 05:48:20');

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `type` enum('Stock In','Stock Out','Adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL COMMENT 'Link to purchase_items or invoice_items',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `type`, `quantity`, `reference_id`, `notes`, `created_at`) VALUES
(1, 1, 'Stock In', 15, 1, 'Purchase Bill: ', '2026-05-09 18:48:57'),
(2, 1, 'Stock Out', 1, NULL, 'Parts consumed for Ticket #14', '2026-05-09 18:54:53'),
(3, 2, 'Stock In', 5, 2, 'Purchase Bill: ', '2026-05-10 18:32:44'),
(4, 1, 'Stock Out', 3, NULL, 'Parts consumed for Ticket #15', '2026-05-10 18:47:52'),
(5, 1, 'Stock In', 3, NULL, 'Reversing consumed parts for Ticket #15 update', '2026-05-10 18:53:22'),
(6, 1, 'Stock Out', 3, NULL, 'Parts consumed for Ticket #15', '2026-05-10 18:53:22'),
(7, 6, 'Stock In', 5, 3, 'Purchase Bill: ', '2026-05-14 06:00:56'),
(8, 6, 'Stock In', 2, 4, 'Purchase Bill: ', '2026-05-14 06:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `gst_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_name`, `phone`, `email`, `address`, `gst_number`, `created_at`) VALUES
(1, 'ahana dey', '7412589634', 'shivamdas123pl@gmail.com', 'nagarjune road,bzone 3/21', '', '2026-05-09 17:32:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `role` enum('admin','technician') DEFAULT 'technician',
  `status` tinyint(1) DEFAULT 1 COMMENT '1: Active, 0: Inactive',
  `rating` decimal(3,2) DEFAULT 5.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `email`, `address`, `password`, `phone`, `role`, `status`, `rating`, `created_at`) VALUES
(1, 'System Admin', 'admin@example.in', 'abcd road', '$2y$10$GSuBNwUeWmZq5Agp4n7U3OAfncbkXLABHKmtR0NI1sicOQxtyDFI6', '9876543211', 'admin', 1, 5.00, '2026-04-17 16:14:57'),
(2, 'Field Technician', 'tech@example.in', NULL, '$2y$10$/dEw/u9VYQS0yzbtNkDFH.sRjdTrkBzuKAP1pRlvR2dZrqEi1zXpa', '9876543210', 'technician', 1, 4.80, '2026-04-17 16:43:45'),
(3, 'crazy xyz', 'admin@demo.com', NULL, '$2y$10$.wDz/.gaBgdPJwTIHKi2b.9XVhzHoGS.HMfcVrsQGsb1JhA9GrFdO', '9632587411', 'technician', 1, 5.00, '2026-04-21 05:48:25'),
(4, 'Test Admin', 'testadmin@example.com', NULL, '$2y$10$vARk32zzJzkVS0LTnP2oeuzH.7vETyhAEYGnF3ETPxYcIL74krJKK', '1234567890', 'technician', 1, 5.00, '2026-05-13 11:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_type` enum('Payment','Receipt') NOT NULL,
  `voucher_date` datetime DEFAULT NULL,
  `account_head` varchar(100) NOT NULL,
  `payee_payer` varchar(150) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `narration` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_name` varchar(150) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `voucher_no`, `voucher_type`, `voucher_date`, `account_head`, `payee_payer`, `amount`, `payment_method`, `reference_no`, `narration`, `created_at`, `bank_name`, `cheque_no`, `cheque_date`) VALUES
(1, 'PV-2026-001', 'Payment', '2026-05-10 00:00:00', 'Supplier Payment', 'ahana dey', 500.00, 'UPI', '', '', '2026-05-10 18:33:25', NULL, NULL, NULL),
(2, 'PV-2026-002', 'Payment', '2026-05-14 14:54:00', 'Supplier Payment', 'ahana dey', 200.00, 'Cheque', '', '', '2026-05-14 09:25:12', 'SBI', '1598764', '2026-05-15'),
(3, 'PV-2026-003', 'Payment', '2026-05-14 14:54:00', 'Supplier Payment', 'ahana dey', 200.00, 'Cheque', '', '', '2026-05-14 09:25:42', 'SBI', '1598764', '2026-05-15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amc_contracts`
--
ALTER TABLE `amc_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `company_profile`
--
ALTER TABLE `company_profile`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `assigned_tech_id` (`assigned_tech_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `complaint_id` (`complaint_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_code` (`product_code`),
  ADD KEY `fk_category` (`category_id`);

--
-- Indexes for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_categories`
--
ALTER TABLE `stock_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `voucher_no` (`voucher_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `amc_contracts`
--
ALTER TABLE `amc_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `company_profile`
--
ALTER TABLE `company_profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_serials`
--
ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `stock_categories`
--
ALTER TABLE `stock_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `amc_contracts`
--
ALTER TABLE `amc_contracts`
  ADD CONSTRAINT `amc_contracts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`assigned_tech_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `stock_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_serials`
--
ALTER TABLE `product_serials`
  ADD CONSTRAINT `product_serials_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
