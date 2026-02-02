-- phpMyAdmin SQL Dump
-- version 5.2.3-1.fc43
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 02, 2026 at 07:43 AM
-- Server version: 10.11.15-MariaDB
-- PHP Version: 8.4.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `device_inventory`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(10) UNSIGNED NOT NULL,
  `brand_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `brand_name`) VALUES
(1, 'HP'),
(2, 'Apple'),
(3, 'Epson'),
(4, 'BenQ'),
(5, 'Sony'),
(6, 'Lenovo'),
(7, 'Samsung'),
(8, 'LG'),
(9, 'Cisco'),
(10, 'HPE(Hewlett Packard Enterprise)'),
(11, 'Dell');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `category_name`, `created_at`) VALUES
(5, 'Camera', '2026-01-22 14:08:13'),
(6, 'Printer', '2026-01-22 16:19:29'),
(7, 'CCTV', '2026-01-23 07:55:43'),
(8, 'Desktop Computer', '2026-01-24 01:29:39'),
(9, 'HDMI', '2026-01-24 01:29:51'),
(10, 'Hard Drive', '2026-01-24 01:30:18'),
(11, 'Monitor', '2026-01-24 01:30:51'),
(12, 'Ethernet Cable', '2026-01-24 01:31:30'),
(13, 'Fiber Optic', '2026-01-24 01:32:01'),
(14, 'Projector', '2026-01-24 01:38:48'),
(15, 'Laptop', '2026-01-24 01:43:06'),
(16, 'Networking & Communication', '2026-01-24 01:47:00'),
(17, 'Speakers', '2026-01-24 01:48:05');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `department_code` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `department_code`) VALUES
(1, 'ICT / ICT Department', NULL),
(2, 'Libraray', NULL),
(3, 'PVC  / Parliamentry Vistors Center', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `device_user_assignments`
--

CREATE TABLE `device_user_assignments` (
  `id` int(11) NOT NULL,
  `inventory_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `returned_at` datetime DEFAULT NULL,
  `status` enum('assigned','retrieved') DEFAULT 'assigned'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `device_user_assignments`
--

INSERT INTO `device_user_assignments` (`id`, `inventory_id`, `user_id`, `assigned_at`, `returned_at`, `status`) VALUES
(1, 13, 1, '2026-01-29 12:46:58', '2026-01-29 13:35:40', 'retrieved'),
(2, 14, 6, '2026-01-29 12:52:10', '2026-01-29 12:57:15', 'retrieved'),
(3, 14, 6, '2026-01-29 13:07:32', '2026-01-30 10:26:35', 'retrieved'),
(4, 12, 7, '2026-01-29 13:32:47', '2026-01-30 10:01:47', 'retrieved'),
(5, 13, 2, '2026-01-29 13:35:48', NULL, 'assigned'),
(6, 1, 2, '2026-01-29 14:10:34', NULL, 'assigned'),
(7, 3, 7, '2026-01-29 14:11:41', '2026-01-30 10:28:25', 'retrieved'),
(8, 9, 7, '2026-01-29 15:00:09', '2026-01-29 15:02:40', 'retrieved'),
(9, 9, 1, '2026-01-29 16:37:51', NULL, 'assigned'),
(10, 15, 7, '2026-01-29 16:50:54', '2026-01-29 16:51:07', 'retrieved'),
(11, 8, 1, '2026-01-30 07:19:22', '2026-01-30 10:13:40', 'retrieved');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `asset_tag` varchar(50) NOT NULL,
  `device_type` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `assigned_user` varchar(100) DEFAULT NULL,
  `condition` enum('Excellent','Good','Fair','Poor','New','Faulty') NOT NULL DEFAULT 'Good',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(10) DEFAULT NULL,
  `location_id` int(10) DEFAULT NULL,
  `brand_id` int(10) DEFAULT NULL,
  `status` enum('active','in_storage','in_use','repairing','faulty','retired') NOT NULL DEFAULT 'active',
  `retired_at` datetime DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `previous_assigned_user` varchar(255) DEFAULT NULL,
  `previous_department_id` int(11) DEFAULT NULL,
  `previous_location_id` int(11) DEFAULT NULL,
  `change_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `asset_tag`, `device_type`, `model`, `serial_number`, `specifications`, `assigned_user`, `condition`, `remarks`, `created_at`, `updated_at`, `category_id`, `department_id`, `location_id`, `brand_id`, `status`, `retired_at`, `previous_status`, `previous_assigned_user`, `previous_department_id`, `previous_location_id`, `change_notes`) VALUES
(1, 'AST-001', 'Laptop', 'ProBook 450 G8', 'SN123456789', '16GB RAM, 512GB SSD, i5 Processor', 'John Mensah', 'Good', 'Issued to IT staff\nAssigned on 2026-01-29 14:10:34: ', '2026-01-21 09:01:47', '2026-01-29 14:10:34', 15, 2, 3, 1, 'in_use', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'AST-003', 'Projector', 'EB-X05', 'PJ1122334455', '3LCD, 3300 lumens, XGA resolution', 'Nana Owusu', 'Fair', '\nResigned on 2026-01-30 10:28:25: ', '2026-01-21 09:01:47', '2026-01-30 10:28:25', 14, 1, 1, 3, 'in_storage', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'AST-005', 'Network Switch', 'Catalyst 2960', 'SW9988776655', '24-Port, Gigabit Ethernet', 'Holali Kelvin', 'Good', '', '2026-01-21 09:01:47', '2026-01-30 10:01:05', 16, 1, 1, 9, 'retired', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'AST-2026-0001', 'HP Omen ', '8th Generation', 'SW9988776656', '16GB ram 128 SSD 1TB HDD', 'Holali Kelvin', 'Fair', '\nAssigned on 2026-01-30 07:19:23: \nResigned on 2026-01-30 10:13:40: ', '2026-01-23 13:54:01', '2026-01-30 10:13:40', 15, 1, 4, 1, 'in_storage', '2026-01-25 09:33:39', NULL, NULL, NULL, NULL, NULL),
(9, 'AST-2026-0002', 'MacBook', 'Pro', 'SW9988776659', '16GB RAM, 512GB SSD, M5 Processor', 'Holali Kelvin Quarshie', 'New', 'Assigned on 2026-01-29 15:00:09: \r\nResigned on 2026-01-29 15:02:40: \nAssigned on 2026-01-29 16:37:51: ', '2026-01-23 14:56:21', '2026-01-29 16:37:51', 15, 1, 2, 2, 'in_use', NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'AST-2026-0003', 'Lenovo thinkpad', 't470', 'SW99887766560', '8GB ram  1TB HDD Dual Core', 'Nadjat', 'Fair', '', '2026-01-24 06:28:20', '2026-01-30 08:37:22', 15, 3, 2, 6, 'retired', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'AST-2026-0004', 'EliteBook 840 G9 Notebook PC', ' 840 G9 ', '5CD1234567', '', 'Jordan', 'New', '', '2026-01-26 19:51:13', '2026-01-30 09:15:16', 15, 1, 4, 1, 'retired', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'AST-2026-0005', 'Omen', 'Omen 9th Generation', '85969070-0995', 'intel 17 16GB 256SSD  1TB HDD ', 'Holali Kelvin', 'New', '\nAssigned on 2026-01-29 13:32:47: \nResigned on 2026-01-30 10:01:47: ', '2026-01-28 08:19:25', '2026-01-30 10:01:47', 15, 1, 4, 1, 'in_storage', NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'AST-2026-0006', 'MacBook', 'Pro', '596u755i', 'M5 Chip 512 SSD', '2', 'New', 'Assigned on 2026-01-29 11:46:27: \r\nResigned on 2026-01-29 12:16:01: Employee left\r\nAssigned on 2026-01-29 12:46:58: \r\nResigned on 2026-01-29 13:35:40: \r\nAssigned on 2026-01-29 13:35:48: ', '2026-01-28 09:12:20', '2026-01-29 14:00:41', 15, 2, 4, 2, 'in_use', NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'AST-2026-0007', 'MacBook Air ', 'Air 2025', '30405i6ii066', 'intel i7 M5', '7', 'New', '\nAssigned on 2026-01-29 12:52:11: \nResigned on 2026-01-29 12:57:15: \nAssigned on 2026-01-29 13:07:33: \nResigned on 2026-01-30 10:26:35: ', '2026-01-29 12:51:51', '2026-01-30 10:26:35', 15, 1, 4, 2, 'in_storage', NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'AST-2026-0008', 'HP ProDesk 600 G6', 'ProDesk 600 G6', '673489-9344', 'RAM: 8 GB  Storage: 1 TB HDD / 256 GB SSD  Ports: HDMI, DisplayPort, USB 3.0', NULL, 'New', '\nAssigned on 2026-01-29 16:50:54: \nResigned on 2026-01-29 16:51:07: ', '2026-01-29 16:46:35', '2026-01-29 16:51:07', 8, 2, 4, 1, 'in_storage', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'AST-2026-0009', 'Lenovo ThinkPad T14', 'Lenovo ThinkPad T14', '5ee5e66787', 'CPU: Intel Core i5 / AMD Ryzen 5  RAM: 16 GB  Storage: 512 GB SSD  Display: 14\" FHD  Keyboard: Spill-resistant  Use case: Heavy office work', NULL, 'New', '', '2026-01-29 18:23:48', '2026-01-30 08:56:54', 15, 1, 4, 6, 'retired', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'AST-2026-0010', 'MacBook', 'Mini', '495868696', 'Intel i7 16GBRAM 256SSD', NULL, 'New', '', '2026-02-01 02:37:14', '2026-02-01 02:38:10', 15, NULL, NULL, 2, 'in_storage', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items_backup`
--

CREATE TABLE `inventory_items_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `asset_tag` varchar(50) NOT NULL,
  `device_type` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `assigned_user` varchar(100) DEFAULT NULL,
  `condition` enum('Excellent','Good','Fair','Poor','New','Faulty') NOT NULL DEFAULT 'Good',
  `status` enum('active','in_storage','under repair','retired','In_use','store','faulty') NOT NULL DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `department_id` int(10) DEFAULT NULL,
  `location_id` int(10) DEFAULT NULL,
  `brand_id` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `inventory_items_backup`
--

INSERT INTO `inventory_items_backup` (`id`, `asset_tag`, `device_type`, `model`, `serial_number`, `specifications`, `assigned_user`, `condition`, `status`, `remarks`, `created_at`, `updated_at`, `category_id`, `department_id`, `location_id`, `brand_id`) VALUES
(1, 'AST-001', 'Laptop', 'ProBook 450 G8', 'SN123456789', '16GB RAM, 512GB SSD, i5 Processor', 'John Mensah', 'Excellent', 'active', 'Issued to IT staff', '2026-01-21 09:01:47', '2026-01-24 03:59:51', 11, 2, 3, 1),
(3, 'AST-003', 'Projector', 'EB-X05', 'PJ1122334455', '3LCD, 3300 lumens, XGA resolution', 'Nana Owusu', 'Fair', 'under repair', '', '2026-01-21 09:01:47', '2026-01-24 03:34:49', 14, 1, 1, 3),
(5, 'AST-005', 'Network Switch', 'Catalyst 2960', 'SW9988776655', '24-Port, Gigabit Ethernet', 'Holali Kelvin', 'Good', 'active', '', '2026-01-21 09:01:47', '2026-01-24 03:31:26', 16, 1, 1, 9),
(8, 'AST-2026-0001', 'HP Omen ', '8th Generation', 'SW9988776656', '16GB ram 128 SSD 1TB HDD', 'Holali Kelvin', 'Fair', 'active', '', '2026-01-23 13:54:01', '2026-01-24 06:52:01', 15, 1, 4, 1),
(9, 'AST-2026-0002', 'MacBook', 'Pro', 'SW9988776659', '16GB RAM, 512GB SSD, M5 Processor', 'Holali Kelvin Quarshie', 'Excellent', 'active', '', '2026-01-23 14:56:21', '2026-01-24 03:33:31', 15, 1, 2, 2),
(10, 'AST-2026-0003', 'Lenovo thinkpad', 't470', 'SW99887766560', '8', 'Nadjat', 'Fair', 'active', '', '2026-01-24 06:28:20', '2026-01-24 06:29:48', 15, 3, 2, 6);

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`) VALUES
(1, 'Chamber'),
(2, 'PVC'),
(3, 'Library'),
(4, 'ICT'),
(5, 'HR');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','select') DEFAULT 'text',
  `category` enum('organization','inventory','system') DEFAULT 'system',
  `label` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `options` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `label`, `description`, `options`, `created_at`, `updated_at`) VALUES
(1, 'org_name', 'Parliament of Ghana ICT Directorate', 'text', 'organization', 'Organization Name', 'Set the directorate information used in reports', NULL, '2026-01-30 03:31:28', '2026-01-30 03:31:28'),
(2, 'org_contact', 'ict@parliament.gov.gh', 'text', 'organization', 'Default Report Contact', 'Default email contact for reports', NULL, '2026-01-30 03:31:28', '2026-01-30 03:31:28'),
(3, 'org_footer', 'Confidential - Internal Use Only', 'text', 'organization', 'Report Footer', 'Footer text for generated reports', NULL, '2026-01-30 03:31:28', '2026-01-30 03:31:28'),
(4, 'org_assignment', 'MP', 'select', 'organization', 'Default Assignment Type', 'Default assignment type for inventory items', 'MP,Staff,Office', '2026-01-30 03:31:28', '2026-01-30 03:31:28'),
(5, 'inv_default_status', 'Store', 'select', 'inventory', 'Default Status', 'Default status for new inventory items', 'In Use,Store,Faulty,Retired', '2026-01-30 03:31:28', '2026-01-30 03:33:34'),
(6, 'inv_retirement_threshold', '60', 'number', 'inventory', 'Retirement Threshold', 'Device retirement threshold in months', NULL, '2026-01-30 03:31:28', '2026-01-30 03:33:34'),
(7, 'inv_email_alerts', '1', 'boolean', 'inventory', 'Email Alerts', 'Enable email alerts for inventory updates', NULL, '2026-01-30 03:31:28', '2026-01-30 03:33:34'),
(8, 'inv_compliance_reminders', '0', 'boolean', 'inventory', 'Compliance Reminders', 'Enable compliance reminders', NULL, '2026-01-30 03:31:28', '2026-01-30 03:33:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `firstname` varchar(50) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','staff','mp') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `firstname`, `lastname`, `email`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Gloria', 'Moro', 'gloriamoro@parliament.gh', 'mp', 'active', '2026-01-21 08:33:46', '2026-01-29 16:36:57'),
(2, 'Mattias', 'kobbi Ket', 'staff@parliament.gh', 'staff', 'active', '2026-01-21 08:33:46', '2026-01-29 13:11:00'),
(6, 'Hollali', 'Kelvin', 'dheztinykartel@gmail.com', 'staff', 'active', '2026-01-28 08:08:12', '2026-01-29 12:48:54'),
(7, 'Doreenda Nadia', 'Abbey', 'doreendaabbey@parliament.gh', 'staff', 'active', '2026-01-29 13:09:13', '2026-01-29 13:10:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `device_user_assignments`
--
ALTER TABLE `device_user_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventory_id` (`inventory_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_tag` (`asset_tag`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`firstname`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `device_user_assignments`
--
ALTER TABLE `device_user_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `device_user_assignments`
--
ALTER TABLE `device_user_assignments`
  ADD CONSTRAINT `device_user_assignments_ibfk_1` FOREIGN KEY (`inventory_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `device_user_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;