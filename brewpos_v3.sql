-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2025 at 07:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `brewpos_v3`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `deduct_raw_materials` (IN `p_product_id` INT, IN `p_cup_size` VARCHAR(10), IN `p_quantity` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_item_id INT;
    DECLARE v_base_quantity DECIMAL(10,2);
    DECLARE v_cup_multiplier DECIMAL(10,2);
    DECLARE cur CURSOR FOR 
        SELECT item_id, base_quantity 
        FROM product_raw_materials 
        WHERE product_id = p_product_id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Set cup size multiplier
    SET v_cup_multiplier = CASE 
        WHEN p_cup_size = 'Small' THEN 1.0
        WHEN p_cup_size = 'Medium' THEN 1.3  -- Adjust as needed
        WHEN p_cup_size = 'Large' THEN 1.7   -- Adjust as needed
        ELSE 1.0
    END;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_item_id, v_base_quantity;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Calculate and deduct raw materials
        UPDATE inventory_items 
        SET quantity = quantity - (v_base_quantity * v_cup_multiplier * p_quantity),
            updated_at = NOW()
        WHERE id = v_item_id;
        
    END LOOP;
    
    CLOSE cur;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `quantity`, `updated_at`) VALUES
(1, 1, 41, '2025-10-07 00:49:35'),
(2, 2, 16, '2025-10-07 00:51:40'),
(3, 3, 18, '2025-10-07 00:51:40'),
(4, 4, 48, '2025-10-07 00:45:49'),
(5, 5, 48, '2025-10-06 17:45:08'),
(6, 6, 48, '2025-10-07 00:03:03'),
(7, 7, 4, '2025-10-07 00:54:57'),
(8, 8, 40, '2025-10-07 00:45:48');

--
-- Triggers `inventory`
--
DELIMITER $$
CREATE TRIGGER `prevent_negative_inventory` BEFORE UPDATE ON `inventory` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Inventory quantity cannot be negative. Not enough stock available.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(50) DEFAULT 'pcs',
  `product_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `quantity`, `unit`, `product_id`, `created_at`, `updated_at`) VALUES
(1, 'Tea Cups (Small)', 100.00, 'pcs', NULL, '2025-10-06 13:25:55', '2025-10-07 01:09:16'),
(2, 'Tea Cups (Medium)', 199.00, 'pcs', NULL, '2025-10-06 13:25:55', '2025-10-06 18:09:49'),
(3, 'Tea Cups (Large)', 197.00, 'pcs', NULL, '2025-10-06 13:25:55', '2025-10-07 00:38:52'),
(4, 'Straw and lids', 421.00, 'pcs', NULL, '2025-10-06 13:25:55', '2025-10-07 00:54:57'),
(5, 'Milk', 12500.00, 'liters', NULL, '2025-10-06 13:25:55', '2025-10-07 00:54:57'),
(6, 'Tapioca Pearls', 21.05, 'kg', NULL, '2025-10-06 13:25:55', '2025-10-07 00:54:57'),
(7, 'Brown Sugar Syrup', 27.63, 'liters', NULL, '2025-10-06 13:25:55', '2025-10-07 00:54:57'),
(8, 'Taro Powder', 5.00, 'kg', NULL, '2025-10-06 13:25:55', '2025-10-11 16:11:38'),
(10, 'Hokkaido Milk Powder', 15.00, 'kg', 2, '2025-10-06 14:07:49', '2025-10-06 14:07:49'),
(11, 'Okinawa Brown Sugar', 18.00, 'kg', 3, '2025-10-06 14:07:49', '2025-10-06 14:07:49'),
(12, 'Mocha Powder', 12.00, 'kg', 4, '2025-10-06 14:07:49', '2025-10-06 14:07:49'),
(14, 'Brown Sugar Syrup', 22.66, 'liters', 6, '2025-10-06 14:07:49', '2025-10-07 00:54:57'),
(15, 'Cookies and Cream Powder', 10.00, 'kg', 7, '2025-10-06 14:07:49', '2025-10-06 17:41:48'),
(16, 'Salted Caramel Syrup', 15.00, 'liters', 8, '2025-10-06 14:07:49', '2025-10-06 14:07:49');

--
-- Triggers `inventory_items`
--
DELIMITER $$
CREATE TRIGGER `prevent_negative_raw_inventory` BEFORE UPDATE ON `inventory_items` FOR EACH ROW BEGIN
    IF NEW.quantity < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Raw material inventory cannot be negative. Not enough supplies available.';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `payment_type` enum('cash','gcash') NOT NULL,
  `amount_received` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `gcash_ref` varchar(100) DEFAULT NULL,
  `cashier_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `subtotal`, `tax`, `total`, `payment_type`, `amount_received`, `change_amount`, `gcash_ref`, `cashier_id`, `created_at`) VALUES
(1, 'ORD20251006153527', 140.00, 16.80, 156.80, 'cash', 200.00, 43.20, NULL, 2, '2025-10-06 13:35:27'),
(2, 'ORD20251006161653', 99.00, 11.88, 110.88, 'cash', 120.00, 9.12, NULL, 2, '2025-10-06 14:16:53'),
(3, 'ORD20251006161816', 327.00, 39.24, 366.24, 'cash', 500.00, 133.76, NULL, 2, '2025-10-06 14:18:16'),
(4, 'ORD20251006161940', 99.00, 11.88, 110.88, 'cash', 120.00, 9.12, NULL, 2, '2025-10-06 14:19:40'),
(5, 'ORD20251006171258', 327.00, 39.24, 366.24, 'cash', 400.00, 33.76, NULL, 1, '2025-10-06 15:12:58'),
(6, 'ORD20251006192644', 4950.00, 594.00, 5544.00, 'cash', 6000.00, 456.00, NULL, 3, '2025-10-06 17:26:44'),
(7, 'ORD20251006194452', 99.00, 11.88, 110.88, 'cash', 900.00, 789.12, NULL, 3, '2025-10-06 17:44:52'),
(8, 'ORD20251006194508', 99.00, 11.88, 110.88, 'cash', 120.00, 9.12, NULL, 3, '2025-10-06 17:45:08'),
(9, 'ORD20251006200918', 119.00, 14.28, 133.28, 'cash', 150.00, 16.72, NULL, 3, '2025-10-06 18:09:18'),
(10, 'ORD20251006200949', 109.00, 13.08, 122.08, 'cash', 130.00, 7.92, NULL, 3, '2025-10-06 18:09:49'),
(11, 'ORD20251007020303', 297.00, 35.64, 332.64, 'cash', 350.00, 17.36, NULL, 3, '2025-10-07 00:03:03'),
(12, 'ORD20251007023852', 238.00, 28.56, 266.56, 'cash', 300.00, 33.44, NULL, 4, '2025-10-07 00:38:52'),
(16, 'ORD20251007024515', 99.00, 11.88, 110.88, 'cash', 120.00, 9.12, NULL, 4, '2025-10-07 00:45:15'),
(17, 'ORD20251007024548', 297.00, 35.64, 332.64, 'cash', 499.00, 166.36, NULL, 4, '2025-10-07 00:45:48'),
(22, 'ORD20251007024935', 99.00, 11.88, 110.88, 'cash', 1000.00, 889.12, NULL, 4, '2025-10-07 00:49:35'),
(23, 'ORD20251007025015', 99.00, 11.88, 110.88, 'cash', 120.00, 9.12, NULL, 4, '2025-10-07 00:50:15'),
(24, 'ORD20251007025140', 198.00, 23.76, 221.76, 'gcash', 221.76, 0.00, '1234567654', 4, '2025-10-07 00:51:40'),
(25, 'ORD20251007025457', 297.00, 35.64, 332.64, 'cash', 400.00, 67.36, NULL, 4, '2025-10-07 00:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `cup_size` enum('Small','Medium','Large') DEFAULT 'Medium',
  `sugar_level` enum('0%','25%','50%','75%','100%') DEFAULT '100%',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `total_price`, `cup_size`, `sugar_level`, `created_at`) VALUES
(2, 2, 7, 'Javan Cookies and Cream', 99.00, 1, 99.00, 'Small', '100%', '2025-10-06 14:16:53'),
(3, 3, 8, 'Amur Salted Caramel', 109.00, 3, 327.00, 'Medium', '75%', '2025-10-06 14:18:16'),
(4, 4, 1, 'Bengal Wintermelon', 99.00, 1, 99.00, 'Small', '0%', '2025-10-06 14:19:40'),
(5, 5, 3, 'Panthera Okinawa', 109.00, 3, 327.00, 'Medium', '75%', '2025-10-06 15:12:58'),
(6, 6, 2, 'Siberian Hokkaido', 99.00, 50, 4950.00, 'Small', '100%', '2025-10-06 17:26:44'),
(7, 7, 7, 'Javan Cookies and Cream', 99.00, 1, 99.00, 'Small', '100%', '2025-10-06 17:44:52'),
(8, 8, 5, 'Bornean Thai Milk Tea', 99.00, 1, 99.00, 'Small', '100%', '2025-10-06 17:45:08'),
(10, 9, 8, 'Amur Salted Caramel', 119.00, 1, 119.00, 'Large', '50%', '2025-10-06 18:09:18'),
(11, 10, 8, 'Amur Salted Caramel', 109.00, 1, 109.00, 'Medium', '100%', '2025-10-06 18:09:49'),
(12, 11, 1, 'Bengal Wintermelon', 99.00, 2, 198.00, 'Small', '50%', '2025-10-07 00:03:03'),
(13, 11, 6, 'Caspian Brown Sugar', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:03:03'),
(14, 12, 8, 'Amur Salted Caramel', 119.00, 2, 238.00, 'Large', '50%', '2025-10-07 00:38:52'),
(18, 16, 7, 'Javan Cookies and Cream', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:45:15'),
(19, 17, 8, 'Amur Salted Caramel', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:45:48'),
(20, 17, 1, 'Bengal Wintermelon', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:45:49'),
(21, 17, 4, 'Jacksoni Mocha', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:45:49'),
(26, 22, 1, 'Bengal Wintermelon', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:49:35'),
(27, 23, 2, 'Siberian Hokkaido', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:50:15'),
(28, 24, 2, 'Siberian Hokkaido', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:51:40'),
(29, 24, 3, 'Panthera Okinawa', 99.00, 1, 99.00, 'Small', '100%', '2025-10-07 00:51:40'),
(30, 25, 7, 'Javan Cookies and Cream', 99.00, 3, 297.00, 'Small', '100%', '2025-10-07 00:54:57');

--
-- Triggers `order_items`
--
DELIMITER $$
CREATE TRIGGER `update_inventory_after_order` AFTER INSERT ON `order_items` FOR EACH ROW BEGIN
    DECLARE new_quantity INT;
    
    -- Calculate new quantity after deduction
    SELECT (quantity - NEW.quantity) INTO new_quantity
    FROM inventory 
    WHERE product_id = NEW.product_id;
    
    -- Check if new quantity would be negative
    IF new_quantity < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot complete order: Insufficient inventory.';
    ELSE
        -- Update the inventory
        UPDATE inventory 
        SET quantity = new_quantity, 
            updated_at = NOW()
        WHERE product_id = NEW.product_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sku`, `name`, `price`, `created_at`) VALUES
(1, 'BT001', 'Bengal Wintermelon', 99.00, '2025-10-06 14:07:49'),
(2, 'BT002', 'Siberian Hokkaido', 99.00, '2025-10-06 14:07:49'),
(3, 'BT003', 'Panthera Okinawa', 99.00, '2025-10-06 14:07:49'),
(4, 'BT004', 'Jacksoni Mocha', 99.00, '2025-10-06 14:07:49'),
(5, 'BT005', 'Bornean Thai Milk Tea', 99.00, '2025-10-06 14:07:49'),
(6, 'BT006', 'Caspian Brown Sugar', 99.00, '2025-10-06 14:07:49'),
(7, 'BT007', 'Javan Cookies and Cream', 99.00, '2025-10-06 14:07:49'),
(8, 'BT008', 'Amur Salted Caramel', 99.00, '2025-10-06 14:07:49');

-- --------------------------------------------------------

--
-- Table structure for table `product_sales`
--

CREATE TABLE `product_sales` (
  `id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_sales`
--

INSERT INTO `product_sales` (`id`, `order_id`, `product_id`, `product_name`, `quantity`, `total_amount`, `created_at`) VALUES
(2, 2, 7, 'Javan Cookies and Cream', 1, 99.00, '2025-10-06 14:16:53'),
(3, 3, 8, 'Amur Salted Caramel', 3, 327.00, '2025-10-06 14:18:16'),
(4, 4, 1, 'Bengal Wintermelon', 1, 99.00, '2025-10-06 14:19:40'),
(5, 5, 3, 'Panthera Okinawa', 3, 327.00, '2025-10-06 15:12:58'),
(6, 6, 2, 'Siberian Hokkaido', 50, 4950.00, '2025-10-06 17:26:44'),
(7, 7, 7, 'Javan Cookies and Cream', 1, 99.00, '2025-10-06 17:44:52'),
(8, 8, 5, 'Bornean Thai Milk Tea', 1, 99.00, '2025-10-06 17:45:08'),
(9, 9, 8, 'Amur Salted Caramel', 1, 119.00, '2025-10-06 18:09:18'),
(10, 10, 8, 'Amur Salted Caramel', 1, 109.00, '2025-10-06 18:09:49'),
(11, 11, 1, 'Bengal Wintermelon', 2, 198.00, '2025-10-07 00:03:03'),
(12, 11, 6, 'Caspian Brown Sugar', 1, 99.00, '2025-10-07 00:03:03'),
(13, 12, 8, 'Amur Salted Caramel', 2, 238.00, '2025-10-07 00:38:52'),
(14, 16, 7, 'Javan Cookies and Cream', 1, 99.00, '2025-10-07 00:45:15'),
(15, 17, 8, 'Amur Salted Caramel', 1, 99.00, '2025-10-07 00:45:49'),
(16, 17, 1, 'Bengal Wintermelon', 1, 99.00, '2025-10-07 00:45:49'),
(17, 17, 4, 'Jacksoni Mocha', 1, 99.00, '2025-10-07 00:45:49'),
(18, 22, 1, 'Bengal Wintermelon', 1, 99.00, '2025-10-07 00:49:35'),
(19, 23, 2, 'Siberian Hokkaido', 1, 99.00, '2025-10-07 00:50:15'),
(20, 24, 2, 'Siberian Hokkaido', 1, 99.00, '2025-10-07 00:51:40'),
(21, 24, 3, 'Panthera Okinawa', 1, 99.00, '2025-10-07 00:51:40'),
(22, 25, 7, 'Javan Cookies and Cream', 3, 297.00, '2025-10-07 00:54:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier') DEFAULT 'cashier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Cashier User', 'cashier', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-10-06 13:25:55'),
(2, 'Admin User', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-10-06 13:25:55'),
(3, 'Zesrhel', 'zesrhel@gmail.com', '$2y$10$OCErhSGq4Vcv5b77PXGPEeSrrCl2cH/7NyJawRvU44ftqYqBUEE/O', 'cashier', '2025-10-06 15:52:35'),
(4, 'zes', '123@gmail.com', '$2y$10$/H0wS264CErUX9ck4U9.NupniDUdlhquJHWyP2PLeZHh0UOb9NesW', 'cashier', '2025-10-07 00:37:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product` (`product_id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inventory_items_product_id` (`product_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_orders_cashier_id` (`cashier_id`),
  ADD KEY `idx_orders_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order_id` (`order_id`),
  ADD KEY `idx_order_items_product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `product_sales`
--
ALTER TABLE `product_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_sales`
--
ALTER TABLE `product_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
