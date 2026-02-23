-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jan 30, 2026 at 02:37 PM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fincart`
--
CREATE DATABASE IF NOT EXISTS `fincart` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fincart`;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professional_id` int NOT NULL,
  `user_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `consultation_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `professional_id` (`professional_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `professional_id`, `user_id`, `appointment_date`, `appointment_time`, `consultation_type`, `notes`, `status`, `created_at`) VALUES
(1, 2, 4, '2026-01-13', '09:00:00', 'Initial Consultation', '', 'confirmed', '2026-01-11 12:44:02'),
(2, 2, 4, '2026-01-30', '09:00:00', 'Initial Consultation', '', 'confirmed', '2026-01-29 06:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit_amount` decimal(10,2) DEFAULT NULL,
  `spent_amount` decimal(10,2) DEFAULT '0.00',
  `month_year` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `user_id`, `category`, `limit_amount`, `spent_amount`, `month_year`) VALUES
(1, 4, 'Shopping', 500.00, 0.00, NULL),
(2, 4, 'Transportation', 200.00, 0.00, NULL),
(3, 4, 'Food', 1000.00, 0.00, NULL),
(4, 5, 'Food', 4000.00, 0.00, '2026-01');

-- --------------------------------------------------------

--
-- Table structure for table `goals`
--

DROP TABLE IF EXISTS `goals`;
CREATE TABLE IF NOT EXISTS `goals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `goal_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_amount` decimal(10,2) DEFAULT NULL,
  `current_amount` decimal(10,2) DEFAULT '0.00',
  `deadline` date DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `goals`
--

INSERT INTO `goals` (`id`, `user_id`, `goal_name`, `target_amount`, `current_amount`, `deadline`, `status`, `created_at`) VALUES
(1, 4, 'emergency funds ', 50000.00, 6000.00, '2026-08-11', NULL, '2026-01-11 07:11:20'),
(2, 4, 'bike', 1500000.00, 120000.00, '2026-04-21', NULL, '2026-01-11 07:12:35');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

DROP TABLE IF EXISTS `investments`;
CREATE TABLE IF NOT EXISTS `investments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `returns` decimal(10,2) DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`id`, `user_id`, `name`, `amount`, `returns`, `type`, `created_at`) VALUES
(8, 4, 'apple stocks', 10000.00, 8000.00, 'Other', '2026-01-11 07:07:20');

-- --------------------------------------------------------

--
-- Table structure for table `itr_submissions`
--

DROP TABLE IF EXISTS `itr_submissions`;
CREATE TABLE IF NOT EXISTS `itr_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `financial_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_income` decimal(12,2) DEFAULT NULL,
  `total_deductions` decimal(12,2) DEFAULT NULL,
  `taxable_income` decimal(12,2) DEFAULT NULL,
  `tax_payable` decimal(12,2) DEFAULT NULL,
  `surcharge` decimal(12,2) DEFAULT NULL,
  `health_cess` decimal(12,2) DEFAULT NULL,
  `total_tax` decimal(12,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `submission_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `professionals`
--

DROP TABLE IF EXISTS `professionals`;
CREATE TABLE IF NOT EXISTS `professionals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professional_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qualification` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience_years` int DEFAULT NULL,
  `specialization` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `languages` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `professionals`
--

INSERT INTO `professionals` (`id`, `professional_type`, `name`, `qualification`, `experience_years`, `specialization`, `bio`, `phone`, `email`, `address`, `city`, `state`, `languages`, `consultation_fee`, `status`, `created_at`) VALUES
(1, 'CA', 'Rajesh Kumar', 'CA (ICAI)', 12, 'Income Tax & Investment Planning', 'Experienced CA specializing in personal income tax and investment optimization for high net-worth individuals.', '9876543210', 'rajesh@example.com', NULL, 'Mumbai', 'Maharashtra', 'Hindi, English', 500.00, 'active', '2026-01-11 07:27:29'),
(2, 'CA', 'Priya Sharma', 'CA (ICAI)', 8, 'GST & Corporate Tax', 'Expert in GST compliance and corporate tax planning. Helps businesses optimize tax structure.', '9765432109', 'priya@example.com', NULL, 'Delhi', 'Delhi', 'Hindi, English', 600.00, 'active', '2026-01-11 07:27:29'),
(3, 'Financial Advisor', 'Amit Patel', 'CFP, MBA Finance', 10, 'Investment & Wealth Management', 'Certified financial planner helping clients build diversified investment portfolios.', '8765432109', 'amit@example.com', NULL, 'Bangalore', 'Karnataka', 'Hindi, English, Gujarati', 400.00, 'active', '2026-01-11 07:27:29'),
(4, 'Tax Consultant', 'Neha Singh', 'B.Com, Tax Specialist', 6, 'Personal Tax Planning', 'Specializes in helping individuals and freelancers with tax planning and returns.', '7654321098', 'neha@example.com', NULL, 'Pune', 'Maharashtra', 'Hindi, English', 350.00, 'active', '2026-01-11 07:27:29'),
(5, 'Financial Advisor', 'Vikram Reddy', 'CFA, MBA', 15, 'Retirement & Education Planning', 'Expert in long-term financial planning for retirement and education goals.', '6543210987', 'vikram@example.com', NULL, 'Hyderabad', 'Telangana', 'Hindi, English, Telugu', 700.00, 'active', '2026-01-11 07:27:29');

-- --------------------------------------------------------

--
-- Table structure for table `tax_deductions`
--

DROP TABLE IF EXISTS `tax_deductions`;
CREATE TABLE IF NOT EXISTS `tax_deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `deduction_date` date NOT NULL,
  `proof_of_expense` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_planning`
--

DROP TABLE IF EXISTS `tax_planning`;
CREATE TABLE IF NOT EXISTS `tax_planning` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `financial_year` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_income` decimal(12,2) DEFAULT NULL,
  `total_deductions` decimal(12,2) DEFAULT '0.00',
  `taxable_income` decimal(12,2) DEFAULT NULL,
  `tax_slabs_used` json DEFAULT NULL,
  `estimated_tax` decimal(12,2) DEFAULT NULL,
  `tax_bracket` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tax_planning`
--

INSERT INTO `tax_planning` (`id`, `user_id`, `financial_year`, `gross_income`, `total_deductions`, `taxable_income`, `tax_slabs_used`, `estimated_tax`, `tax_bracket`, `created_at`) VALUES
(1, 4, '2024-25', 100000.00, 1000.00, 99000.00, NULL, 0.00, 'New Regime', '2026-01-27 15:27:55'),
(2, 4, '2024-25', 100000.00, 5000.00, 95000.00, NULL, 0.00, 'Old Regime', '2026-01-27 15:28:35');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('income','expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `category`, `amount`, `description`, `date`, `created_at`) VALUES
(1, 4, 'income', 'Salary', 10000.00, '', '2026-01-11', '2026-01-11 06:34:38'),
(2, 4, 'expense', 'Transportation', 200.00, '', '2026-01-11', '2026-01-11 06:34:49'),
(3, 4, 'expense', 'Food', 500.00, '', '2026-01-11', '2026-01-11 06:35:01'),
(4, 4, 'expense', 'Shopping', 700.00, '', '2026-01-11', '2026-01-11 06:43:20'),
(5, 5, 'income', 'Salary', 5000.00, '', '2026-01-22', '2026-01-22 02:43:52'),
(6, 5, 'expense', 'Transportation', 1800.00, '', '2026-01-22', '2026-01-22 02:44:06'),
(7, 5, 'income', 'Freelance', 2000.00, '', '2026-01-22', '2026-01-22 02:44:18'),
(8, 5, 'expense', 'Utilities', 600.00, '', '2026-01-22', '2026-01-22 02:44:33'),
(9, 5, 'expense', 'Entertainment', 199.00, '', '2026-01-22', '2026-01-22 02:44:45'),
(10, 5, 'expense', 'Food', 2000.00, '', '2026-01-22', '2026-01-22 02:44:56'),
(11, 5, 'expense', 'Other Expense', 500.00, '', '2026-01-22', '2026-01-22 02:45:06'),
(12, 4, 'income', 'Salary', 100000.00, '', '2026-01-22', '2026-01-22 05:35:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('user','professional','professional_pending','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(4, 'harsh aair', 'harshaair750@gmail.com', '$2y$10$tCZey/6ixSyUZ84xpatUgejvNwK8BH3bOWe9EAmjOjrOx7FqkK0sK', '2026-01-11 05:52:01'),
(2, 'vinit', 'vinit06@gmail.com', '$2y$10$EGtZFgMKRaXu2LOMgieIYe5Yuxzd2uzNL5QWLSRqvcKTUMQTFtsR.', '2025-12-11 05:33:44'),
(3, 'rajeev', 'rajeev67@gmail.com', '$2y$10$J6E7OqlJDw8zZGC1otMx/.Jy6EsPjYqxo5/Ns8ei/.dg/6eIsr4d2', '2025-12-11 06:51:27'),
(5, 'Kunal Shetty', 'kunal567@gmail.com', '$2y$10$FSuvlD.Nja5kbe0IN4VDQeSr26GkJpUNNQVfseKqcZXFIpOBb696q', '2026-01-22 02:42:57');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
