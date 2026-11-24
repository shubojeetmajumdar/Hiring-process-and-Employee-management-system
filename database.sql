-- Database creation script for Hiring Process & Employee Portal (NexusLink)
CREATE DATABASE IF NOT EXISTS `nexuslink_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nexuslink_db`;

-- 1. Employees Table
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) UNIQUE NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `department` VARCHAR(100) DEFAULT 'Engineering',
    `designation` VARCHAR(100) DEFAULT 'Senior Software Engineer',
    `manager` VARCHAR(100) DEFAULT 'David Miller',
    `location` VARCHAR(100) DEFAULT 'Austin, TX (HQ)',
    `status` VARCHAR(20) DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Recruiters / HR Table
CREATE TABLE IF NOT EXISTS `recruiters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `department` VARCHAR(100) DEFAULT 'Human Resources',
    `designation` VARCHAR(100) DEFAULT 'Lead Talent Acquisition',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Leave Requests Table
CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NOT NULL,
    `leave_type` VARCHAR(100) NOT NULL,
    `from_date` DATE NOT NULL,
    `to_date` DATE NOT NULL,
    `num_days` INT NOT NULL,
    `reason` TEXT,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `actioned_by` VARCHAR(100) DEFAULT NULL,
    `actioned_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Hiring Requisitions Table
CREATE TABLE IF NOT EXISTS `hiring_requisitions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `requisition_id` VARCHAR(50) UNIQUE NOT NULL,
    `request_date` DATE NOT NULL,
    `employee_id` VARCHAR(50) NOT NULL,
    `department` VARCHAR(100),
    `designation` VARCHAR(100),
    `manager` VARCHAR(100),
    `location` VARCHAR(100),
    `hiring_type` VARCHAR(100),
    `job_title` VARCHAR(150) NOT NULL,
    `num_positions` INT DEFAULT 1,
    `employment_type` VARCHAR(50),
    `reason_for_hiring` TEXT,
    `required_qualification` TEXT,
    `preferred_qualification` TEXT,
    `required_skills` TEXT,
    `preferred_skills` TEXT,
    `min_experience` INT DEFAULT 0,
    `max_experience` INT DEFAULT 0,
    `job_description` TEXT,
    `key_responsibilities` TEXT,
    `min_salary` DECIMAL(12,2) DEFAULT 0.00,
    `max_salary` DECIMAL(12,2) DEFAULT 0.00,
    `salary_type` VARCHAR(20) DEFAULT 'Monthly',
    `salary_budget` DECIMAL(12,2) DEFAULT 0.00,
    `other_benefits` TEXT,
    `joining_date` DATE,
    `priority` VARCHAR(20) DEFAULT 'Medium',
    `priority_reason` TEXT,
    `business_justification` TEXT,
    `comments` TEXT,
    `status` VARCHAR(50) DEFAULT 'Draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) DEFAULT 'general',
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. Payslips Table
CREATE TABLE IF NOT EXISTS `payslips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payslip_number` VARCHAR(50) UNIQUE NOT NULL,
    `employee_id` VARCHAR(50) NOT NULL,
    `pay_period` VARCHAR(50) NOT NULL,
    `payment_date` DATE NOT NULL,
    `basic_salary` DECIMAL(10,2) DEFAULT 0.00,
    `hra` DECIMAL(10,2) DEFAULT 0.00,
    `conveyance` DECIMAL(10,2) DEFAULT 0.00,
    `allowances` DECIMAL(10,2) DEFAULT 0.00,
    `gross_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `tax_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `pf_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `insurance_deduction` DECIMAL(10,2) DEFAULT 0.00,
    `total_deductions` DECIMAL(10,2) DEFAULT 0.00,
    `net_pay` DECIMAL(10,2) DEFAULT 0.00,
    `status` VARCHAR(20) DEFAULT 'Paid',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 7. Holidays Table
CREATE TABLE IF NOT EXISTS `holidays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `date` DATE NOT NULL,
    `type` VARCHAR(50) DEFAULT 'National',
    `day` VARCHAR(20)
) ENGINE=InnoDB;

-- Seed Demo Accounts (Password for all demo accounts: password123)
INSERT INTO `employees` (`employee_id`, `name`, `email`, `password`, `status`)
VALUES 
('EMP101', 'Alex Mercer', 'alex.mercer@nexuslink.com', '$2y$10$rtNOWyuPepOSNjLuo2G2mOwGtQbm0QNOAhlxSbvj3Pquv4LqPq.Lq', 'Active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `recruiters` (`name`, `email`, `password`)
VALUES 
('Sarah Jenkins', 'hr@nexuslink.com', '$2y$10$rtNOWyuPepOSNjLuo2G2mOwGtQbm0QNOAhlxSbvj3Pquv4LqPq.Lq')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);