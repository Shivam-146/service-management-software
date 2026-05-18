-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2026 at 09:18 PM
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
(15, 3, 2, 'n ewd', 'Medium', 'Completed', '', '[{\"product_id\":\"1\",\"qty\":\"3\"}]', NULL, NULL, '2026-05-10 18:47:08', '2026-05-11 00:17:13', '2026-05-11 00:17:35', '2026-05-11 00:23:22'),
(16, 1, 2, 'dew', 'Medium', 'Completed', '', '[{\"product_id\":\"11\",\"qty\":\"1\"}]', NULL, NULL, '2026-05-14 16:00:43', '2026-05-14 21:30:50', '2026-05-14 21:31:25', '2026-05-15 18:16:12'),
(17, 3, 2, 'nnhtf', 'Medium', 'Completed', '', '[{\"product_id\":\"11\",\"qty\":\"1\"}]', NULL, NULL, '2026-05-15 12:49:26', '2026-05-15 18:19:31', '2026-05-15 18:19:39', '2026-05-15 18:20:19'),
(18, 1, 2, 'jrhfb', 'Medium', 'Completed', '', '[{\"product_id\":\"11\",\"qty\":\"2\"}]', NULL, NULL, '2026-05-15 12:51:59', '2026-05-15 18:22:04', '2026-05-15 18:22:13', '2026-05-15 18:22:38'),
(19, 4, 2, 'issue', 'Medium', 'In-Progress', '', '[{\"product_id\":\"11\",\"qty\":\"1\"}]', NULL, NULL, '2026-05-16 05:31:35', '2026-05-16 11:02:30', '2026-05-16 11:15:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `address` text NOT NULL,
  `due_amount` decimal(10,2) DEFAULT 0.00,
  `gst_number` varchar(20) DEFAULT NULL,
  `has_active_amc` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_name`, `phone`, `address`, `due_amount`, `gst_number`, `has_active_amc`, `created_at`) VALUES
(1, 'Shivam', '8348872742', 'nagarjune road,bzone 3/21', 0.00, '', 1, '2026-04-17 17:23:39'),
(2, 'Walk-in Customer', '9632587411', 'nagarjune road,bzone 3/21', 0.00, '', 0, '2026-04-17 19:06:20'),
(3, 'Andaman', '9632587419', 'bzone road', 0.00, '', 0, '2026-05-09 18:51:45'),
(4, 'abc', '74125896333', 'Direct Sale Entry', 1600.00, NULL, 0, '2026-05-15 14:04:52'),
(5, 'shivam', '7896541233', 'Direct Sale', 0.00, NULL, 0, '2026-05-16 06:22:14'),
(6, 'joy', '8348872742', 'Direct Sale', 2803.57, NULL, 0, '2026-05-16 07:16:53');

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
  `customer_id` int(11) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `gst_amount` decimal(10,2) DEFAULT NULL,
  `grand_total` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('Paid','Unpaid','Partial') DEFAULT 'Unpaid',
  `payment_method` enum('Cash','UPI','Bank','Pay Later') DEFAULT 'Cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gst_mode` enum('Inclusive','Exclusive') DEFAULT 'Exclusive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `complaint_id`, `customer_id`, `invoice_no`, `subtotal`, `gst_amount`, `grand_total`, `payment_status`, `payment_method`, `created_at`, `gst_mode`) VALUES
(1, 1, 1, 'INV177644847276', 2500.00, 450.00, 2950.00, 'Unpaid', 'Cash', '2026-04-17 17:54:32', 'Exclusive'),
(2, 2, 2, 'INV177645294165', 500.00, 90.00, 590.00, 'Unpaid', 'Cash', '2026-04-17 19:09:01', 'Exclusive'),
(3, 4, 1, 'INV177667747694', 500.00, 90.00, 590.00, 'Unpaid', 'Cash', '2026-04-20 09:31:16', 'Exclusive'),
(4, 3, 2, 'INV177695352659', 2500.00, 450.00, 2950.00, 'Paid', 'UPI', '2026-04-23 14:12:06', 'Exclusive'),
(5, 11, 2, 'INV177696190172', 1000.00, 0.00, 1000.00, 'Unpaid', 'UPI', '2026-04-23 16:31:41', 'Exclusive'),
(6, 12, 1, 'INV177696192920', 1000.00, 0.00, 1000.00, 'Paid', 'Cash', '2026-04-23 16:32:09', 'Exclusive'),
(7, 13, 1, 'INV177696377887', 1000.00, 0.00, 1000.00, 'Unpaid', NULL, '2026-04-23 17:02:58', 'Exclusive'),
(8, 14, 3, 'INV177835289384', 1000.00, 0.00, 1000.00, 'Paid', 'UPI', '2026-05-09 18:54:53', 'Exclusive'),
(9, 15, 3, 'INV177843887272', 3000.00, 0.00, 3000.00, 'Paid', 'Cash', '2026-05-10 18:47:52', 'Exclusive'),
(10, 15, 3, 'INV177843920277', 3000.00, 0.00, 3000.00, 'Paid', 'Cash', '2026-05-10 18:53:22', 'Exclusive'),
(11, 16, 1, 'INV177884907149', 100.00, 0.00, 100.00, 'Paid', 'Cash', '2026-05-15 12:44:31', 'Exclusive'),
(12, 16, 1, 'INV177884917248', 100.00, 0.00, 100.00, 'Paid', 'Cash', '2026-05-15 12:46:12', 'Exclusive'),
(13, 17, 3, 'INV177884941966', 100.00, 0.00, 100.00, 'Paid', '', '2026-05-15 12:50:19', 'Exclusive'),
(14, 18, 1, 'INV177884955834', 250.00, 0.00, 250.00, 'Paid', 'Cash', '2026-05-15 12:52:38', 'Exclusive'),
(15, NULL, 1, 'SALE177885286492', 100.00, 18.00, 118.00, 'Paid', 'Cash', '2026-05-15 13:47:44', 'Exclusive'),
(16, NULL, 4, 'SALE1778911768', 2928.57, 146.43, 3075.00, 'Paid', 'Cash', '2026-05-16 06:08:00', 'Exclusive'),
(17, NULL, 3, 'SALE1778911998', 1500.00, 75.00, 1575.00, 'Unpaid', 'Pay Later', '2026-05-16 06:12:00', 'Exclusive'),
(18, NULL, 5, 'SALE1778912555', 1428.57, 71.43, 1500.00, 'Unpaid', 'Pay Later', '2026-05-16 06:22:00', 'Inclusive'),
(19, NULL, 4, 'SALE1778912856', 1428.57, 71.43, 1500.00, 'Paid', 'Cash', '2026-05-16 06:27:00', 'Exclusive'),
(21, NULL, 4, 'SALE1778912973', 1428.57, 171.43, 1600.00, 'Unpaid', 'Pay Later', '2026-05-16 06:27:00', 'Exclusive'),
(22, NULL, 2, 'SALE1778915667', 1428.57, 71.43, 1500.00, 'Paid', 'Cash', '2026-05-16 07:14:00', 'Exclusive'),
(23, NULL, 6, 'SALE1778915828', 1428.57, 71.43, 1500.00, 'Paid', 'Cash', '2026-05-16 07:16:00', 'Exclusive'),
(24, NULL, 6, 'SALE1778916057', 928.57, 46.43, 975.00, 'Unpaid', 'Pay Later', '2026-05-16 07:20:00', 'Exclusive'),
(25, NULL, 6, 'SALE1778933342', 1428.57, 71.43, 1500.00, 'Paid', 'Cash', '2026-05-16 12:08:00', 'Exclusive'),
(26, NULL, 6, 'SALE1778933361', 1428.57, 257.14, 1685.71, 'Paid', 'Cash', '2026-05-16 12:09:00', 'Exclusive'),
(27, NULL, 6, 'SALE1778933409', 1428.57, 400.00, 1828.57, 'Unpaid', 'Pay Later', '2026-05-16 12:09:00', 'Exclusive');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `taxable_value` decimal(10,2) DEFAULT NULL,
  `gst_rate` decimal(5,2) DEFAULT NULL,
  `gst_amount` decimal(10,2) DEFAULT NULL,
  `gst_mode` enum('Exclusive','Inclusive') DEFAULT 'Exclusive',
  `discount_type` enum('Fixed','Percentage') DEFAULT 'Fixed',
  `discount_value` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `taxable_value`, `gst_rate`, `gst_amount`, `gst_mode`, `discount_type`, `discount_value`, `discount_amount`, `total_price`, `created_at`) VALUES
(1, 16, 6, 1, 1428.57, 1428.57, 5.00, 71.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 06:09:28'),
(2, 16, 12, 1, 1500.00, 1500.00, 5.00, 75.00, 'Exclusive', 'Fixed', 0.00, 0.00, 1575.00, '2026-05-16 06:09:28'),
(3, 17, 6, 1, 1500.00, 1500.00, 5.00, 75.00, 'Exclusive', 'Fixed', 0.00, 0.00, 1575.00, '2026-05-16 06:13:18'),
(4, 18, 6, 1, 1500.00, 1428.57, 5.00, 71.43, 'Inclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 06:22:35'),
(5, 19, 6, 1, 1428.57, 1428.57, 5.00, 71.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 06:27:36'),
(7, 21, 6, 1, 1428.57, 1428.57, 12.00, 171.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1600.00, '2026-05-16 06:29:33'),
(8, 22, 6, 1, 1428.57, 1428.57, 5.00, 71.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 07:14:27'),
(9, 23, 12, 1, 1428.57, 1428.57, 5.00, 71.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 07:17:08'),
(10, 24, 6, 1, 1428.57, 928.57, 5.00, 46.43, 'Exclusive', 'Fixed', 500.00, 500.00, 975.00, '2026-05-16 07:20:57'),
(11, 25, 6, 1, 1428.57, 1428.57, 5.00, 71.43, 'Exclusive', 'Fixed', 0.00, 0.00, 1500.00, '2026-05-16 12:09:02'),
(12, 26, 6, 1, 1428.57, 1428.57, 18.00, 257.14, 'Exclusive', 'Fixed', 0.00, 0.00, 1685.71, '2026-05-16 12:09:21'),
(13, 27, 6, 1, 1428.57, 1428.57, 28.00, 400.00, 'Exclusive', 'Fixed', 0.00, 0.00, 1828.57, '2026-05-16 12:10:09');

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
  `payment_method` enum('Cash','UPI','Bank','Pay Later') DEFAULT 'Cash',
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
(4, 3, 10, '2026-05-11 00:23:22', 3000.00, 'Cash', NULL, 'Payment received by technician for Ticket #15', '2026-05-10 18:53:22'),
(5, 1, 11, '2026-05-15 18:14:31', 100.00, 'Cash', NULL, 'Payment received by technician for Ticket #16', '2026-05-15 12:44:31'),
(6, 1, 12, '2026-05-15 18:16:12', 100.00, 'Cash', NULL, 'Payment received by technician for Ticket #16', '2026-05-15 12:46:12'),
(7, 3, 13, '2026-05-15 18:20:19', 100.00, 'Bank', NULL, 'Payment received by technician for Ticket #17', '2026-05-15 12:50:19'),
(8, 1, 14, '2026-05-15 18:22:38', 250.00, 'Cash', NULL, 'Payment received by technician for Ticket #18', '2026-05-15 12:52:38'),
(9, 1, 15, '2026-05-15 19:17:44', 118.00, 'Cash', NULL, 'Payment for Direct Sale #SALE177885286492. ', '2026-05-15 13:47:44'),
(10, 4, 16, '2026-05-16 11:38:00', 3075.00, 'Cash', NULL, 'Direct Sale #SALE1778911768. ', '2026-05-16 06:09:28'),
(11, 3, 17, '2026-05-16 11:42:00', 1575.00, 'Pay Later', NULL, 'Direct Sale #SALE1778911998. ', '2026-05-16 06:13:18'),
(12, 5, 18, '2026-05-16 11:52:00', 1500.00, 'Pay Later', NULL, 'Direct Sale #SALE1778912555. ', '2026-05-16 06:22:35'),
(13, 4, 19, '2026-05-16 11:57:00', 1500.00, 'Cash', NULL, 'Direct Sale #SALE1778912856. ', '2026-05-16 06:27:36'),
(14, 2, 22, '2026-05-16 12:44:00', 1500.00, 'Cash', NULL, 'Direct Sale #SALE1778915667. ', '2026-05-16 07:14:27'),
(15, 6, 23, '2026-05-16 12:46:00', 1500.00, 'Cash', NULL, 'Direct Sale #SALE1778915828. ', '2026-05-16 07:17:08'),
(16, 6, 25, '2026-05-16 17:38:00', 1500.00, 'Cash', NULL, 'Direct Sale #SALE1778933342. ', '2026-05-16 12:09:02'),
(17, 6, 26, '2026-05-16 17:39:00', 1685.71, 'Cash', NULL, 'Direct Sale #SALE1778933361. ', '2026-05-16 12:09:21');

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
  `gst_rate` decimal(5,2) DEFAULT 0.00,
  `current_stock` int(11) DEFAULT 0,
  `opening_stock` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `warranty_months` int(11) DEFAULT 12
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `product_name`, `product_code`, `unit`, `category`, `serial_number`, `unit_price`, `gst_rate`, `current_stock`, `opening_stock`, `description`, `warranty_months`) VALUES
(1, NULL, 'camera', NULL, 'Pcs', 'cam', '11', 1000.00, 12.00, 26, 5, NULL, 12),
(2, NULL, 'camera', NULL, 'Pcs', 'cam', '11', 100.00, 0.00, 10, 5, NULL, 12),
(3, 1, '8 Channel DVR Hickvision', '2233', 'Pcs', NULL, NULL, 0.00, 12.00, 4, 5, '', 12),
(6, 1, '8 Channel DVR Hick', '1478', 'Pcs', NULL, NULL, 0.00, 0.00, 22, 5, '', 12),
(8, 1, 'abc', '1596', 'Pcs', NULL, NULL, 0.00, 0.00, 4, 5, '', 12),
(9, NULL, '8 Channel DVR Hickhvbf', '5566', 'Pcs', NULL, NULL, 0.00, 0.00, 8, 10, '', 12),
(11, 1, 'abcd', '15', 'Pcs', NULL, NULL, 100.00, 28.00, 57, 50, '', 12),
(12, 1, 'english', '147', 'Pcs', NULL, NULL, 0.00, 12.00, 28, 15, '', 12);

-- --------------------------------------------------------

--
-- Table structure for table `product_serials`
--

CREATE TABLE `product_serials` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `purchase_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `gst_rate` decimal(5,2) DEFAULT 0.00,
  `sale_price` decimal(10,2) DEFAULT 0.00,
  `invoice_id` int(11) DEFAULT NULL,
  `technician_id` int(11) DEFAULT NULL,
  `allocated_at` timestamp NULL DEFAULT NULL,
  `status` enum('In Stock','Sold','Defective','Returned') DEFAULT 'In Stock',
  `sold_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_serials`
--

INSERT INTO `product_serials` (`id`, `product_id`, `purchase_id`, `serial_number`, `purchase_price`, `gst_rate`, `sale_price`, `invoice_id`, `technician_id`, `allocated_at`, `status`, `sold_at`, `created_at`) VALUES
(1, 6, NULL, '1235', 0.00, 0.00, 0.00, NULL, 2, '2026-05-15 13:21:06', 'In Stock', NULL, '2026-05-14 09:20:45'),
(2, 6, NULL, '1478', 0.00, 0.00, 0.00, 16, NULL, NULL, 'Sold', '2026-05-16 11:39:28', '2026-05-14 09:20:45'),
(3, 6, NULL, '569', 0.00, 0.00, 0.00, NULL, 2, '2026-05-15 13:21:06', 'In Stock', NULL, '2026-05-14 09:20:45'),
(4, 11, NULL, '100', 150.00, 0.00, 0.00, 12, NULL, NULL, 'Sold', '2026-05-15 18:16:12', '2026-05-15 12:28:20'),
(5, 11, NULL, '101', 150.00, 0.00, 0.00, 13, NULL, NULL, 'Sold', '2026-05-15 18:20:19', '2026-05-15 12:28:21'),
(6, 11, NULL, '102', 150.00, 0.00, 0.00, 14, NULL, NULL, 'Sold', '2026-05-15 18:22:38', '2026-05-15 12:28:21'),
(7, 11, NULL, '50', 100.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 12:28:21'),
(8, 11, NULL, '51', 100.00, 0.00, 0.00, 14, NULL, NULL, 'Sold', '2026-05-15 18:22:38', '2026-05-15 12:28:21'),
(9, 11, NULL, '52', 100.00, 0.00, 100.00, 15, NULL, NULL, 'Sold', '2026-05-15 19:17:44', '2026-05-15 12:28:21'),
(10, 11, NULL, '1000', 150.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(11, 11, NULL, '1001', 150.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(13, 11, NULL, '1005', 150.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(14, 11, NULL, '52896', 170.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(15, 11, NULL, '741258', 170.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(16, 11, NULL, '78564', 170.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-15 14:07:09'),
(17, 12, NULL, 'ab', 1000.00, 0.00, 0.00, 16, NULL, NULL, 'Sold', '2026-05-16 11:39:28', '2026-05-16 05:20:56'),
(18, 12, NULL, '45', 1000.00, 0.00, 0.00, 23, NULL, NULL, 'Sold', '2026-05-16 12:47:08', '2026-05-16 05:20:56'),
(19, 12, NULL, '77', 1000.00, 0.00, 0.00, NULL, NULL, NULL, 'In Stock', NULL, '2026-05-16 05:20:56');

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
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `gst_amount` decimal(10,2) DEFAULT 0.00,
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

INSERT INTO `purchases` (`id`, `supplier_id`, `payee_name`, `category`, `purchase_date`, `bill_no`, `subtotal`, `gst_amount`, `total_amount`, `payment_method`, `reference_no`, `notes`, `is_inventory`, `created_at`, `bank_name`, `cheque_no`, `cheque_date`) VALUES
(1, 1, '', 'Rent', '2026-05-09 00:00:00', '', 0.00, 0.00, 1500.00, 'Cash', '', '', 1, '2026-05-09 18:48:57', NULL, NULL, NULL),
(2, 1, '', 'Rent', '2026-05-10 00:00:00', '', 0.00, 0.00, 1000.00, 'Credit', '', '', 1, '2026-05-10 18:32:44', NULL, NULL, NULL),
(3, 1, '', 'Rent', '2026-05-14 11:30:00', '', 0.00, 0.00, 500.00, 'Cash', '', '', 1, '2026-05-14 06:00:56', NULL, NULL, NULL),
(4, 1, '', 'Rent', '2026-05-14 12:11:00', '', 0.00, 0.00, 3000.00, 'Cheque', '', '', 1, '2026-05-14 06:41:52', 'SBI', '1598764', '2026-05-15'),
(5, NULL, '', 'Rent', '2026-05-14 21:28:00', '', 0.00, 0.00, 525.00, 'Cash', '', '', 1, '2026-05-14 15:59:28', '', '', NULL),
(6, 1, '', 'Rent', '2026-05-14 21:31:00', '', 0.00, 0.00, 300.00, 'Cash', '', '', 1, '2026-05-14 16:02:10', '', '', NULL),
(7, NULL, '', 'Rent', '2026-05-14 22:04:00', '', 0.00, 0.00, 2625.00, 'Credit', '', '', 1, '2026-05-14 16:34:55', '', '', NULL),
(8, NULL, '', 'Rent', '2026-05-14 22:05:00', '', 0.00, 0.00, 1785.00, 'Cash', '', '', 1, '2026-05-14 16:35:57', '', '', NULL),
(9, 1, '', 'Rent', '2026-05-15 18:36:00', '', 0.00, 0.00, 15000.00, 'Cash', '', '', 1, '2026-05-15 13:06:56', '', '', NULL),
(10, NULL, '', 'Rent', '2026-05-15 19:49:00', '', 0.00, 0.00, 11200.00, 'Cash', '', '', 1, '2026-05-15 14:20:50', '', '', NULL),
(11, 1, '', 'Rent', '2026-05-16 10:56:00', '', 0.00, 0.00, 3000.00, 'Cash', '', '', 1, '2026-05-16 05:27:10', '', '', NULL),
(12, NULL, 'abc', 'Credit Sale', '2026-05-16 00:00:00', NULL, 1428.57, 171.43, 1600.00, 'Pay Later', NULL, 'Credit sale for Invoice #SALE1778912973', 0, '2026-05-16 06:29:33', NULL, NULL, NULL),
(13, NULL, 'joy', 'Credit Sale', '2026-05-16 00:00:00', NULL, 928.57, 46.43, 975.00, 'Pay Later', NULL, 'Credit sale for Invoice #SALE1778916057', 0, '2026-05-16 07:20:57', NULL, NULL, NULL),
(14, 2, '', 'Rent', '2026-05-16 12:52:00', '', 0.00, 0.00, 4468.80, 'Credit', '', '', 1, '2026-05-16 07:23:24', '', '', NULL),
(15, NULL, 'joy', 'Credit Sale', '2026-05-16 00:00:00', NULL, 1428.57, 400.00, 1828.57, 'Pay Later', NULL, 'Credit sale for Invoice #SALE1778933409', 0, '2026-05-16 12:10:09', NULL, NULL, NULL),
(16, 2, '', 'Rent', '2026-05-16 17:44:00', '', 0.00, 0.00, 735.00, 'Credit', '', '', 1, '2026-05-16 12:15:19', '', '', NULL),
(17, 2, '', 'Rent', '2026-05-16 17:48:00', 'PUR1778933922', 0.00, 0.00, 950.00, 'Cash', '', '', 1, '2026-05-16 12:18:42', '', '', NULL),
(18, 2, '', 'Rent', '2026-05-16 17:49:00', 'PUR1778933988', 0.00, 0.00, 10000.00, 'Cash', '', '', 1, '2026-05-16 12:19:48', '', '', NULL),
(19, 2, '', 'Rent', '2026-05-16 17:53:00', 'PUR1778934290', 0.00, 0.00, 1500.00, 'Cash', '', '', 1, '2026-05-16 12:24:50', '', '', NULL),
(20, 2, '', 'Rent', '2026-05-16 17:57:00', 'PUR1778934479', 0.00, 0.00, 1560.00, 'Credit', '', '', 1, '2026-05-16 12:27:59', '', '', NULL);

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
(4, 4, 6, 2, 1500.00, 3000.00, 0.00, 0.00, 1500.00, 'Fixed', 0.00, 0.00),
(5, 5, 11, 5, 100.00, 525.00, 5.00, 5.00, 100.00, 'Fixed', 0.00, 0.00),
(6, 6, 11, 2, 150.00, 300.00, 5.00, 7.14, 142.86, 'Fixed', 0.00, 0.00),
(7, 7, 6, 5, 500.00, 2625.00, 5.00, 25.00, 500.00, 'Fixed', 0.00, 0.00),
(8, 8, 6, 10, 170.00, 1785.00, 5.00, 8.50, 170.00, 'Fixed', 0.00, 0.00),
(9, 9, 6, 10, 1500.00, 15000.00, 5.00, 71.43, 1428.57, 'Fixed', 0.00, 0.00),
(10, 10, 12, 10, 1000.00, 11200.00, 12.00, 120.00, 1000.00, 'Fixed', 0.00, 0.00),
(11, 11, 12, 2, 1500.00, 3000.00, 5.00, 71.43, 1428.57, 'Fixed', 0.00, 0.00),
(12, 14, 12, 3, 1400.00, 4468.80, 12.00, 159.60, 1330.00, 'Percentage', 5.00, 70.00),
(13, 16, 11, 5, 147.00, 735.00, 12.00, 15.75, 131.25, 'Fixed', 0.00, 0.00),
(14, 17, 8, 5, 190.00, 950.00, 0.00, 0.00, 190.00, 'Fixed', 0.00, 0.00),
(15, 18, 11, 10, 1000.00, 10000.00, 28.00, 218.75, 781.25, 'Fixed', 0.00, 0.00),
(16, 19, 3, 5, 300.00, 1500.00, 12.00, 32.14, 267.86, 'Fixed', 0.00, 0.00),
(17, 20, 1, 10, 139.29, 1560.00, 12.00, 16.71, 139.29, 'Fixed', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_returns`
--

CREATE TABLE `purchase_returns` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `return_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_returns`
--

INSERT INTO `purchase_returns` (`id`, `purchase_id`, `supplier_id`, `return_date`, `total_amount`, `notes`, `created_at`) VALUES
(1, 17, 2, '2026-05-16 00:00:00', 380.00, '', '2026-05-16 12:19:09'),
(2, 19, 2, '2026-05-16 00:00:00', 600.00, '', '2026-05-16 12:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_return_items`
--

CREATE TABLE `purchase_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_return_items`
--

INSERT INTO `purchase_return_items` (`id`, `return_id`, `product_id`, `serial_number`, `quantity`, `price`, `total`) VALUES
(1, 1, 8, NULL, 2, 190.00, 380.00),
(2, 2, 3, NULL, 2, 300.00, 600.00);

-- --------------------------------------------------------

--
-- Table structure for table `sales_returns`
--

CREATE TABLE `sales_returns` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `return_date` datetime NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_return_items`
--

CREATE TABLE `sales_return_items` (
  `id` int(11) NOT NULL,
  `return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(8, 6, 'Stock In', 2, 4, 'Purchase Bill: ', '2026-05-14 06:41:52'),
(9, 11, 'Stock In', 5, 5, 'Purchase Bill: ', '2026-05-14 15:59:28'),
(10, 11, 'Stock In', 2, 6, 'Purchase Bill: ', '2026-05-14 16:02:10'),
(11, 6, 'Stock In', 5, 7, 'Purchase Bill: ', '2026-05-14 16:34:55'),
(12, 6, 'Stock In', 10, 8, 'Purchase Bill: ', '2026-05-14 16:35:57'),
(13, 11, 'Stock Out', 1, NULL, 'Parts consumed for Ticket #16', '2026-05-15 12:44:14'),
(14, 11, 'Stock In', 1, NULL, 'Reversing consumed parts for Ticket #16 update', '2026-05-15 12:44:31'),
(15, 11, 'Stock Out', 1, NULL, 'Parts consumed for Ticket #16', '2026-05-15 12:44:31'),
(16, 11, 'Stock In', 1, NULL, 'Reversing consumed parts for Ticket #16 update', '2026-05-15 12:46:12'),
(17, 11, 'Stock Out', 1, NULL, 'Parts consumed for Ticket #16', '2026-05-15 12:46:12'),
(18, 11, 'Stock Out', 1, NULL, 'Parts consumed for Ticket #17', '2026-05-15 12:50:19'),
(19, 11, 'Stock Out', 2, NULL, 'Parts consumed for Ticket #18', '2026-05-15 12:52:38'),
(20, 6, 'Stock In', 10, 9, 'Purchase Bill: ', '2026-05-15 13:06:56'),
(21, 6, 'Stock Out', 1, NULL, 'Allotted SN: 569 to Tech ID: 2', '2026-05-15 13:21:06'),
(22, 6, 'Stock Out', 1, NULL, 'Allotted SN: 1235 to Tech ID: 2', '2026-05-15 13:21:06'),
(23, 6, 'Stock Out', 5, NULL, 'Allotted quantity to Tech ID: 2', '2026-05-15 13:21:06'),
(24, 9, 'Stock Out', 2, NULL, 'Allotted quantity to Tech ID: 2', '2026-05-15 13:21:06'),
(25, 3, 'Stock Out', 4, NULL, 'Allotted quantity to Tech ID: 2', '2026-05-15 13:21:06'),
(26, 8, 'Stock Out', 4, NULL, 'Allotted quantity to Tech ID: 2', '2026-05-15 13:21:06'),
(27, 11, 'Stock Out', 10, NULL, 'Allotted quantity to Tech ID: 2', '2026-05-15 13:21:06'),
(32, 11, 'Stock Out', 1, NULL, 'Direct Sale to Customer ID #1', '2026-05-15 13:47:44'),
(33, 12, 'Stock In', 10, 10, 'Purchase Bill: ', '2026-05-15 14:20:50'),
(34, 12, 'Stock In', 2, 11, 'Purchase Bill: ', '2026-05-16 05:27:10'),
(35, 11, 'Stock Out', 1, NULL, 'Parts consumed by Technician (Tech ID: 2) for Ticket #19', '2026-05-16 05:32:20'),
(36, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778911768', '2026-05-16 06:09:28'),
(37, 12, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778911768', '2026-05-16 06:09:28'),
(38, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778911998', '2026-05-16 06:13:18'),
(39, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778912555', '2026-05-16 06:22:35'),
(40, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778912856', '2026-05-16 06:27:36'),
(42, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778912973', '2026-05-16 06:29:33'),
(43, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778915667', '2026-05-16 07:14:27'),
(44, 12, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778915828', '2026-05-16 07:17:08'),
(45, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778916057', '2026-05-16 07:20:57'),
(46, 12, 'Stock In', 3, 12, 'Purchase Bill: ', '2026-05-16 07:23:24'),
(47, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778933342', '2026-05-16 12:09:02'),
(48, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778933361', '2026-05-16 12:09:21'),
(49, 6, 'Stock Out', 1, NULL, 'Direct Sale Bill #SALE1778933409', '2026-05-16 12:10:09'),
(50, 11, 'Stock In', 5, 13, 'Purchase Bill: ', '2026-05-16 12:15:19'),
(51, 8, 'Stock In', 5, 14, 'Purchase Bill: PUR1778933922', '2026-05-16 12:18:42'),
(52, 11, 'Stock In', 10, 15, 'Purchase Bill: PUR1778933988', '2026-05-16 12:19:48'),
(53, 3, 'Stock In', 5, 16, 'Purchase Bill: PUR1778934290', '2026-05-16 12:24:50'),
(54, 1, 'Stock In', 10, 17, 'Purchase Bill: PUR1778934479', '2026-05-16 12:27:59');

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
(1, 'ahana dey', '7412589634', 'shivamdas123pl@gmail.com', 'nagarjune road,bzone 3/21', '', '2026-05-09 17:32:38'),
(2, 'Shivam Das', '7908893644', 'admin@example.in', 'sukna,sit', '', '2026-05-16 07:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `technician_stock`
--

CREATE TABLE `technician_stock` (
  `id` int(11) NOT NULL,
  `technician_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `allocated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `technician_stock`
--

INSERT INTO `technician_stock` (`id`, `technician_id`, `product_id`, `quantity`, `allocated_at`) VALUES
(1, 2, 6, 5.00, '2026-05-15 13:21:06'),
(2, 2, 9, 2.00, '2026-05-15 13:21:06'),
(3, 2, 3, 4.00, '2026-05-15 13:21:06'),
(4, 2, 8, 4.00, '2026-05-15 13:21:06'),
(5, 2, 11, 9.00, '2026-05-15 13:21:06');

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
(3, 'PV-2026-003', 'Payment', '2026-05-14 14:54:00', 'Supplier Payment', 'ahana dey', 200.00, 'Cheque', '', '', '2026-05-14 09:25:42', 'SBI', '1598764', '2026-05-15'),
(4, 'PV-2026-004', 'Payment', '2026-05-16 13:00:00', 'Supplier Payment', 'Shivam Das', 500.00, 'Cash', '', '', '2026-05-16 07:30:59', '', '', NULL);

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
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

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
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_purchase` (`purchase_id`),
  ADD KEY `fk_invoice` (`invoice_id`);

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
-- Indexes for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`);

--
-- Indexes for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

--
-- Indexes for table `sales_returns`
--
ALTER TABLE `sales_returns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `sales_return_items`
--
ALTER TABLE `sales_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `return_id` (`return_id`);

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
-- Indexes for table `technician_stock`
--
ALTER TABLE `technician_stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `technician_id` (`technician_id`),
  ADD KEY `product_id` (`product_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `product_serials`
--
ALTER TABLE `product_serials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sales_returns`
--
ALTER TABLE `sales_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sales_return_items`
--
ALTER TABLE `sales_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_categories`
--
ALTER TABLE `stock_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `technician_stock`
--
ALTER TABLE `technician_stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

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
  ADD CONSTRAINT `fk_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
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
-- Constraints for table `purchase_returns`
--
ALTER TABLE `purchase_returns`
  ADD CONSTRAINT `purchase_returns_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_return_items`
--
ALTER TABLE `purchase_return_items`
  ADD CONSTRAINT `purchase_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `purchase_returns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_returns`
--
ALTER TABLE `sales_returns`
  ADD CONSTRAINT `sales_returns_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_return_items`
--
ALTER TABLE `sales_return_items`
  ADD CONSTRAINT `sales_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `sales_returns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
