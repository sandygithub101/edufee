-- ============================================================================
-- Student & Semester-Wise Fee Management System
-- Practical Technical Assessment - Database Script
-- Database: MySQL 8+
-- Target Engine: InnoDB, Charset: utf8mb4, Collation: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `fee_management_db` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `fee_management_db`;

-- ----------------------------------------------------------------------------
-- 1. Table: admins
-- Stores admin accounts with secure password_hash() digests
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS `fee_payments`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `course_semesters`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `admins`;

CREATE TABLE `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Table: courses
-- Stores academic course offerings, duration, semester counts, status
-- ----------------------------------------------------------------------------
CREATE TABLE `courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `duration` VARCHAR(50) NOT NULL COMMENT 'e.g. 3 Years, 2 Years',
  `total_semesters` TINYINT UNSIGNED NOT NULL DEFAULT 6,
  `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_course_code` (`code`),
  INDEX `idx_course_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Table: course_semesters
-- Stores course-wise semester fee setup (dynamic, not hard-coded in PHP)
-- ----------------------------------------------------------------------------
CREATE TABLE `course_semesters` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL,
  `semester_no` TINYINT UNSIGNED NOT NULL,
  `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_course_semester` (`course_id`, `semester_no`),
  INDEX `idx_cs_course_id` (`course_id`),
  CONSTRAINT `fk_course_semesters_course` 
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Table: students
-- Stores comprehensive student profiles
-- ----------------------------------------------------------------------------
CREATE TABLE `students` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `enrollment_no` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `father_name` VARCHAR(100) NOT NULL,
  `mobile` VARCHAR(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
  `address` TEXT NOT NULL,
  `course_id` INT UNSIGNED NOT NULL,
  `admission_date` DATE NOT NULL,
  `status` ENUM('Active', 'Inactive', 'Passed Out', 'Suspended') NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_student_enrollment` (`enrollment_no`),
  INDEX `idx_student_name` (`name`),
  INDEX `idx_student_course` (`course_id`),
  INDEX `idx_student_status` (`status`),
  CONSTRAINT `fk_students_course` 
    FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Table: fee_payments
-- Stores transaction records with multi-payment support for a semester
-- ----------------------------------------------------------------------------
CREATE TABLE `fee_payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `receipt_no` VARCHAR(30) NOT NULL UNIQUE,
  `student_id` INT UNSIGNED NOT NULL,
  `semester_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_mode` ENUM('Cash', 'UPI', 'Net Banking', 'Cheque', 'Debit/Credit Card') NOT NULL DEFAULT 'Cash',
  `transaction_no` VARCHAR(100) NULL COMMENT 'Bank/UPI Txn ID or Cheque No',
  `remarks` VARCHAR(255) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_payment_student` (`student_id`),
  INDEX `idx_payment_semester` (`semester_id`),
  INDEX `idx_payment_date` (`payment_date`),
  INDEX `idx_payment_receipt` (`receipt_no`),
  CONSTRAINT `fk_fee_payments_student` 
    FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fee_payments_semester` 
    FOREIGN KEY (`semester_id`) REFERENCES `course_semesters` (`id`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_fee_payments_admin` 
    FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) 
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- Default Admin credentials:
-- Username: admin
-- Password: password_hash('admin123', PASSWORD_BCRYPT)
-- ============================================================================

INSERT INTO `admins` (`id`, `username`, `password`, `name`, `email`, `status`) VALUES
(1, 'admin', '$2y$10$ajCq9QxrgPFL1wkBmuaz.OWIAW4mhoyfaAtCibYfC1WlSKOcHECKC', 'System Administrator', 'admin@feemanagement.edu', 'Active');

-- ----------------------------------------------------------------------------
-- Course Data
-- BCA (Bachelor of Computer Applications - 3 Years / 6 Semesters)
-- MCA (Master of Computer Applications - 2 Years / 4 Semesters)
-- B.Tech CSE (Bachelor of Technology - 4 Years / 8 Semesters)
-- BBA (Bachelor of Business Administration - 3 Years / 6 Semesters)
-- ----------------------------------------------------------------------------
INSERT INTO `courses` (`id`, `name`, `code`, `duration`, `total_semesters`, `status`) VALUES
(1, 'Bachelor of Computer Applications', 'BCA', '3 Years', 6, 'Active'),
(2, 'Master of Computer Applications', 'MCA', '2 Years', 4, 'Active'),
(3, 'Bachelor of Technology (CSE)', 'B.Tech-CSE', '4 Years', 8, 'Active'),
(4, 'Bachelor of Business Administration', 'BBA', '3 Years', 6, 'Active');

-- ----------------------------------------------------------------------------
-- Semester Fees Data (Mandatory Specification from Brief)
-- BCA Sem 1: 7,000
-- BCA Sem 2: 7,000
-- BCA Sem 3: 7,500
-- BCA Sem 4: 7,500
-- BCA Sem 5 & 6: 8,000 each
-- ----------------------------------------------------------------------------
INSERT INTO `course_semesters` (`id`, `course_id`, `semester_no`, `fee`) VALUES
-- BCA Semesters (Required in prompt)
(1, 1, 1, 7000.00),
(2, 1, 2, 7000.00),
(3, 1, 3, 7500.00),
(4, 1, 4, 7500.00),
(5, 1, 5, 8000.00),
(6, 1, 6, 8000.00),

-- MCA Semesters
(7, 2, 1, 15000.00),
(8, 2, 2, 15000.00),
(9, 2, 3, 16000.00),
(10, 2, 4, 16000.00),

-- B.Tech CSE Semesters
(11, 3, 1, 45000.00),
(12, 3, 2, 45000.00),
(13, 3, 3, 48000.00),
(14, 3, 4, 48000.00),
(15, 3, 5, 50000.00),
(16, 3, 6, 50000.00),
(17, 3, 7, 52000.00),
(18, 3, 8, 52000.00),

-- BBA Semesters
(19, 4, 1, 12000.00),
(20, 4, 2, 12000.00),
(21, 4, 3, 13000.00),
(22, 4, 4, 13000.00),
(23, 4, 5, 14000.00),
(24, 4, 6, 14000.00);

-- ----------------------------------------------------------------------------
-- Sample Students
-- ----------------------------------------------------------------------------
INSERT INTO `students` (`id`, `enrollment_no`, `name`, `father_name`, `mobile`, `email`, `gender`, `address`, `course_id`, `admission_date`, `status`) VALUES
(1, 'ENR-2024-001', 'Rahul Sharma', 'Rajendra Sharma', '9876543210', 'rahul.sharma@example.com', 'Male', '142 Civil Lines, Jaipur, Rajasthan', 1, '2024-07-15', 'Active'),
(2, 'ENR-2024-002', 'Priya Patel', 'Mahesh Patel', '9876543211', 'priya.patel@example.com', 'Female', '45 Sardar Patel Road, Ahmedabad, Gujarat', 1, '2024-07-18', 'Active'),
(3, 'ENR-2024-003', 'Aman Verma', 'Sunil Verma', '9876543212', 'aman.verma@example.com', 'Male', '78 Sector 18, Noida, Uttar Pradesh', 1, '2024-07-20', 'Active'),
(4, 'ENR-2024-004', 'Sneha Kulkarni', 'Anand Kulkarni', '9876543213', 'sneha.k@example.com', 'Female', '22 Shivaji Nagar, Pune, Maharashtra', 2, '2024-08-01', 'Active'),
(5, 'ENR-2024-005', 'Vikramaditya Rao', 'Narasimha Rao', '9876543214', 'vikram.rao@example.com', 'Male', '89 Jubilee Hills, Hyderabad, Telangana', 3, '2024-07-10', 'Active'),
(6, 'ENR-2024-006', 'Ananya Gupta', 'Ramesh Gupta', '9876543215', 'ananya.g@example.com', 'Female', '12 Mall Road, Shimla, Himachal Pradesh', 4, '2024-08-05', 'Active');

-- ----------------------------------------------------------------------------
-- Sample Payments demonstrating Multi-Payment and Status calculations
-- Example from prompt: Semester Fee 7,000 -> Paid 3,000 -> Remaining 4,000
-- ----------------------------------------------------------------------------
INSERT INTO `fee_payments` (`id`, `receipt_no`, `student_id`, `semester_id`, `amount`, `payment_date`, `payment_mode`, `transaction_no`, `remarks`, `created_by`) VALUES
-- Rahul Sharma (BCA Sem 1 fee 7000): Paid 3000 (Partial), then paid remaining 4000 (Paid in full)
(1, 'REC-2024-0001', 1, 1, 3000.00, '2024-07-20', 'UPI', 'UPI/420192837190', 'First installment via Google Pay', 1),
(2, 'REC-2024-0002', 1, 1, 4000.00, '2024-08-15', 'Net Banking', 'HDFC982319028', 'Final installment cleared', 1),

-- Priya Patel (BCA Sem 1 fee 7000): Paid 3000 -> Remaining 4000 (Partial status)
(3, 'REC-2024-0003', 2, 1, 3000.00, '2024-07-22', 'Cash', NULL, 'Initial payment at counter', 1),

-- Sneha Kulkarni (MCA Sem 1 fee 15000): Paid 15000 full (Paid)
(4, 'REC-2024-0004', 4, 7, 15000.00, '2024-08-02', 'Debit/Credit Card', 'POS-TXN-884920', 'Full semester fee paid', 1),

-- Vikramaditya Rao (B.Tech-CSE Sem 1 fee 45000): Paid 25000 -> Remaining 20000 (Partial)
(5, 'REC-2024-0005', 5, 11, 25000.00, '2024-07-12', 'Net Banking', 'SBI-NEFT-991823', 'Part admission fee paid', 1);

-- Note: Aman Verma (Student ID 3) has NO payments yet -> Status is 'Pending' with Remaining 7,000
-- Ananya Gupta (Student ID 6) has NO payments yet -> Status is 'Pending' with Remaining 12,000
