-- phpMyAdmin SQL Dump
-- version 5.2.3
-- FinCart Complete Unified Database Schema
-- Server version: 8.4+ / MySQL 8.0+ / MariaDB 10.4+
-- PHP Version: 8.0+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS=0;

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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('user','professional','professional_pending','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample users
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `verified`, `created_at`) VALUES
(2, 'vinit', 'vinit06@gmail.com', '$2y$10$EGtZFgMKRaXu2LOMgieIYe5Yuxzd2uzNL5QWLSRqvcKTUMQTFtsR.', 'user', 1, '2025-12-11 05:33:44'),
(3, 'rajeev', 'rajeev67@gmail.com', '$2y$10$J6E7OqlJDw8zZGC1otMx/.Jy6EsPjYqxo5/Ns8ei/.dg/6eIsr4d2', 'user', 1, '2025-12-11 06:51:27'),
(4, 'harsh aair', 'harshaair750@gmail.com', '$2y$10$tCZey/6ixSyUZ84xpatUgejvNwK8BH3bOWe9EAmjOjrOx7FqkK0sK', 'user', 1, '2026-01-11 05:52:01'),
(5, 'Kunal Shetty', 'kunal567@gmail.com', '$2y$10$FSuvlD.Nja5kbe0IN4VDQeSr26GkJpUNNQVfseKqcZXFIpOBb696q', 'user', 1, '2026-01-22 02:42:57'),
(6, 'System Admin', 'admin@example.com', '$2y$10$oSlffB33uFWlwqzHLeFo1.7G4hymOBSyVqttFFS26d4c1JlkNXUF6', 'admin', 1, '2026-01-31 06:15:34'),
(7, 'Vinit Singh', 'vs0021634@gmail.com', '$2y$10$poka8xe4bxHCKVOG25HXzekgCiNcmjtI3641cL.XujGOqEHzc2J3O', 'professional', 1, '2026-02-01 11:38:50'),
(8, 'shreyash yadav', 'shreyashyadav25@gmail.com', '$2y$10$bdp0FPiVwOwB.mCvbinalu7HMTbcRLfIqIwW/j1J6cup2qEQAmemm', 'professional', 1, '2026-02-05 06:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `professionals`
--

DROP TABLE IF EXISTS `professionals`;
CREATE TABLE IF NOT EXISTS `professionals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `professional_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qualification` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience_years` int DEFAULT NULL,
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `languages` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` decimal(15,2) DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_prof_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample professionals
--

INSERT INTO `professionals` (`id`, `user_id`, `professional_type`, `name`, `qualification`, `experience_years`, `specialization`, `bio`, `phone`, `email`, `address`, `city`, `state`, `languages`, `consultation_fee`, `status`, `created_at`) VALUES
(1, NULL, 'CA', 'Rajesh Kumar', 'CA (ICAI)', 12, 'Income Tax & Investment Planning', 'Experienced CA specializing in personal income tax and investment optimization for high net-worth individuals.', '9876543210', 'rajesh@example.com', NULL, 'Mumbai', 'Maharashtra', 'Hindi, English', 500.00, 'active', '2026-01-11 07:27:29'),
(2, NULL, 'CA', 'Priya Sharma', 'CA (ICAI)', 8, 'GST & Corporate Tax', 'Expert in GST compliance, business taxation, and corporate restructuring for startups and SMEs.', '9876543211', 'priya@example.com', NULL, 'Bangalore', 'Karnataka', 'English, Kannada, Hindi', 750.00, 'active', '2026-01-11 07:27:29'),
(3, NULL, 'Financial Advisor', 'Amit Patel', 'CFP (FPSB)', 10, 'Wealth Management & Retirement', 'Certified Financial Planner helping individuals build balanced portfolios and achieve financial freedom.', '9876543212', 'amit@example.com', NULL, 'Delhi', 'Delhi', 'Hindi, English, Gujarati', 600.00, 'active', '2026-01-11 07:27:29'),
(4, NULL, 'Financial Advisor', 'Sneha Reddy', 'MBA (Finance)', 6, 'Mutual Funds & Equity', 'Mutual fund distributor and equity research specialist with focus on systematic wealth creation.', '9876543213', 'sneha@example.com', NULL, 'Hyderabad', 'Telangana', 'English, Telugu, Hindi', 400.00, 'active', '2026-01-11 07:27:29'),
(5, NULL, 'Tax Consultant', 'Vikram Singh', 'LLB (Taxation)', 15, 'Tax Litigation & Assessment', 'Senior tax consultant dealing with income tax scrutiny, appeals, and complex assessment cases.', '9876543214', 'vikram@example.com', NULL, 'Pune', 'Maharashtra', 'Hindi, Marathi, English', 1000.00, 'active', '2026-01-11 07:27:29'),
(6, NULL, 'Tax Consultant', 'Ananya Das', 'M.Com, Tax Specialist', 5, 'NRI Taxation & Remittances', 'Specialized in NRI tax compliance, DTAA benefits, and foreign remittance procedures.', '9876543215', 'ananya@example.com', NULL, 'Kolkata', 'West Bengal', 'Bengali, Hindi, English', 500.00, 'active', '2026-01-11 07:27:29'),
(7, 7, 'CA', 'Vinit Singh', 'M.com', 2, 'Financial Planning', 'M.com in accounting and finance with focus on personal tax filing.', '09172422770', 'vs0021634@gmail.com', NULL, 'Mumbai', 'Maharashtra', 'English, Hindi', 500.00, 'active', '2026-02-01 11:42:07'),
(8, 8, 'CA', 'shreyash yadav', 'B.com', 2, 'Financial Planning', 'Expert financial consultant for personal budgeting and tax savings.', '9876543210', 'shreyashyadav25@gmail.com', NULL, 'mumbai', 'Maharashtra', 'English, Hindi', 200.00, 'active', '2026-02-05 06:40:48');

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
  `consultation_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'online',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `professional_id` (`professional_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_apt_prof` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_apt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample appointments
--

INSERT INTO `appointments` (`id`, `professional_id`, `user_id`, `appointment_date`, `appointment_time`, `consultation_type`, `consultation_mode`, `notes`, `status`, `created_at`) VALUES
(1, 2, 4, '2026-01-13', '09:00:00', 'Initial Consultation', 'online', 'Reviewing last year ITR', 'confirmed', '2026-01-11 12:44:02'),
(2, 2, 4, '2026-01-30', '09:00:00', 'Initial Consultation', 'online', 'Tax planning review', 'confirmed', '2026-01-29 06:24:54');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `limit_amount` decimal(15,2) DEFAULT NULL,
  `spent_amount` decimal(15,2) DEFAULT '0.00',
  `month_year` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample budgets
--

INSERT INTO `budgets` (`id`, `user_id`, `category`, `limit_amount`, `spent_amount`, `month_year`) VALUES
(1, 4, 'Shopping', 500.00, 0.00, NULL),
(2, 4, 'Transportation', 200.00, 0.00, NULL),
(3, 4, 'Food', 1000.00, 0.00, NULL),
(4, 5, 'Food', 1000.00, 0.00, '2026-01');

-- --------------------------------------------------------

--
-- Table structure for table `goals`
--

DROP TABLE IF EXISTS `goals`;
CREATE TABLE IF NOT EXISTS `goals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `goal_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_amount` decimal(15,2) DEFAULT NULL,
  `current_amount` decimal(15,2) DEFAULT '0.00',
  `deadline` date DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_goals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample goals
--

INSERT INTO `goals` (`id`, `user_id`, `goal_name`, `target_amount`, `current_amount`, `deadline`, `status`, `created_at`) VALUES
(1, 4, 'house', 5000000.00, 20000.00, '2026-01-30', 'in_progress', '2026-01-22 05:37:25'),
(2, 5, 'buy a sports car', 10000000.00, 10000.00, '2026-01-31', 'in_progress', '2026-01-22 02:46:11');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

DROP TABLE IF EXISTS `investments`;
CREATE TABLE IF NOT EXISTS `investments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `returns` decimal(15,2) DEFAULT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_investments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample investments
--

INSERT INTO `investments` (`id`, `user_id`, `name`, `amount`, `returns`, `type`, `created_at`) VALUES
(1, 5, 'gold', 10000.00, 1000.00, 'Real Estate', '2026-01-22 02:45:34'),
(2, 4, 'tata motors', 50000.00, 5000.00, 'Stocks', '2026-01-22 05:36:58');

-- --------------------------------------------------------

--
-- Table structure for table `itr_submissions`
--

DROP TABLE IF EXISTS `itr_submissions`;
CREATE TABLE IF NOT EXISTS `itr_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `financial_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_income` decimal(15,2) NOT NULL,
  `total_deductions` decimal(15,2) DEFAULT '0.00',
  `tax_paid` decimal(15,2) DEFAULT '0.00',
  `submission_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `submission_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_itr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample itr_submissions
--

INSERT INTO `itr_submissions` (`id`, `user_id`, `financial_year`, `total_income`, `total_deductions`, `tax_paid`, `submission_notes`, `status`, `submission_date`) VALUES
(1, 4, '2024-25', 100000.00, 0.00, 0.00, 'Initial submission draft', 'Draft', '2026-01-22 05:40:27');

-- --------------------------------------------------------

--
-- Table structure for table `tax_deductions`
--

DROP TABLE IF EXISTS `tax_deductions`;
CREATE TABLE IF NOT EXISTS `tax_deductions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `amount` decimal(15,2) NOT NULL,
  `deduction_date` date NOT NULL,
  `proof_of_expense` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tax_ded_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample tax_deductions
--

INSERT INTO `tax_deductions` (`id`, `user_id`, `category`, `description`, `amount`, `deduction_date`, `proof_of_expense`, `created_at`) VALUES
(1, 4, 'Section 80C - Insurance & Investment', 'Life insurance premium', 25000.00, '2026-01-15', NULL, '2026-01-22 05:38:00');

-- --------------------------------------------------------

--
-- Table structure for table `tax_planning`
--

DROP TABLE IF EXISTS `tax_planning`;
CREATE TABLE IF NOT EXISTS `tax_planning` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `financial_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gross_income` decimal(15,2) DEFAULT NULL,
  `total_deductions` decimal(15,2) DEFAULT '0.00',
  `taxable_income` decimal(15,2) DEFAULT NULL,
  `tax_slabs_used` json DEFAULT NULL,
  `estimated_tax` decimal(15,2) DEFAULT NULL,
  `tax_bracket` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_tax_plan_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('income','expense') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping sample transactions
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `category`, `amount`, `description`, `date`, `created_at`) VALUES
(1, 4, 'expense', 'Food', 200.00, 'Lunch with team', '2026-01-11', '2026-01-11 05:54:19'),
(2, 4, 'income', 'Salary', 1000.00, 'Part-time freelance', '2026-01-11', '2026-01-11 05:54:33'),
(3, 4, 'expense', 'Shopping', 500.00, 'Clothing', '2026-01-11', '2026-01-11 06:17:15'),
(4, 4, 'expense', 'Food', 500.00, 'Groceries', '2026-01-11', '2026-01-11 06:33:04'),
(5, 4, 'expense', 'Other Expense', 100.00, 'Stationery', '2026-01-11', '2026-01-11 07:11:51'),
(6, 4, 'expense', 'Transportation', 200.00, 'Fuel', '2026-01-11', '2026-01-11 07:12:12'),
(7, 4, 'income', 'Investment Return', 50.00, 'Dividend', '2026-01-11', '2026-01-11 07:12:28'),
(8, 5, 'income', 'Salary', 100000.00, 'Monthly salary', '2026-01-22', '2026-01-22 02:44:31'),
(9, 5, 'expense', 'Entertainment', 199.00, 'Streaming subscription', '2026-01-22', '2026-01-22 02:44:45'),
(10, 5, 'expense', 'Food', 2000.00, 'Dining out', '2026-01-22', '2026-01-22 02:44:56'),
(11, 5, 'expense', 'Other Expense', 500.00, 'Household supplies', '2026-01-22', '2026-01-22 02:45:06'),
(12, 4, 'income', 'Salary', 100000.00, 'Monthly Salary January', '2026-01-22', '2026-01-22 05:35:38'),
(13, 4, 'income', 'Salary', 100000.00, 'Monthly Salary February', '2026-02-05', '2026-02-05 06:19:56');

-- --------------------------------------------------------

--
-- Table structure for table `professional_applications`
--

DROP TABLE IF EXISTS `professional_applications`;
CREATE TABLE IF NOT EXISTS `professional_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `professional_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `qualification` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience_years` int DEFAULT NULL,
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `languages` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` decimal(15,2) DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reviewed_by_admin` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `reviewed_by_admin` (`reviewed_by_admin`),
  CONSTRAINT `fk_applications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_applications_admin_reviewer` FOREIGN KEY (`reviewed_by_admin`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `professional_ratings`
--

DROP TABLE IF EXISTS `professional_ratings`;
CREATE TABLE IF NOT EXISTS `professional_ratings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `professional_id` int NOT NULL,
  `user_id` int NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `rating` int NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `review_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `professional_id` (`professional_id`),
  KEY `user_id` (`user_id`),
  KEY `appointment_id` (`appointment_id`),
  UNIQUE KEY `unique_rating_per_user_prof` (`professional_id`, `user_id`),
  CONSTRAINT `fk_ratings_professional` FOREIGN KEY (`professional_id`) REFERENCES `professionals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ratings_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int DEFAULT NULL,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `action_type` (`action_type`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `email` (`email`),
  KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Re-enable Foreign Key Checks and Commit
--
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
