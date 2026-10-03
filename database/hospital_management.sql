-- ============================================================================
-- Hospital Management System (HMS) - Database Schema & Realistic Seed Data
-- Target Database: MySQL 8.0+
-- PHP Compatibility: PHP 8.0+ with PDO
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `hospital_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hospital_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `lab_results`;
DROP TABLE IF EXISTS `lab_requests`;
DROP TABLE IF EXISTS `lab_tests`;
DROP TABLE IF EXISTS `prescription_items`;
DROP TABLE IF EXISTS `prescriptions`;
DROP TABLE IF EXISTS `medicine_stock_log`;
DROP TABLE IF EXISTS `medicines`;
DROP TABLE IF EXISTS `vital_signs`;
DROP TABLE IF EXISTS `medical_records`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `nurses`;
DROP TABLE IF EXISTS `doctors`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. ROLES
-- ----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Admin', 'Full administrative control over users, departments, inventory, and system settings'),
(2, 'Doctor', 'Manages patient consultations, medical records, diagnoses, prescriptions, and lab orders'),
(3, 'Nurse', 'Manages patient intake, vital signs, ward information, and doctor assistance'),
(4, 'Receptionist', 'Registers patients, schedules appointments, issues initial billing, and receives payments'),
(5, 'Pharmacist', 'Manages pharmacy inventory, validates prescriptions, dispenses medicines, and tracks stock'),
(6, 'Laboratory Staff', 'Manages lab test catalog, receives test orders, inputs results, and updates request status'),
(7, 'Patient', 'Accesses personal profile, appointment history, medical records, lab results, and invoices');

-- ----------------------------------------------------------------------------
-- 2. USERS
-- ----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('Active', 'Inactive', 'Suspended') DEFAULT 'Active',
    `last_login` DATETIME NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hash for password123: $2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password_hash`, `status`) VALUES
(1, 1, 'admin', 'admin@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(2, 2, 'dr.smith', 'smith@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(3, 2, 'dr.johnson', 'johnson@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(4, 3, 'nurse.mary', 'mary@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(5, 4, 'receptionist', 'reception@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(6, 5, 'pharmacist', 'pharma@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(7, 6, 'labtech', 'lab@hospital.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active'),
(8, 7, 'patient.david', 'david@example.com', '$2y$12$.2okIhvamhKUHPAH.L7mKOC2.YaVDiATnl9pXSa9gSW7T8kazAJeK', 'Active');

-- ----------------------------------------------------------------------------
-- 3. DEPARTMENTS
-- ----------------------------------------------------------------------------
CREATE TABLE `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `departments` (`id`, `name`, `code`, `description`) VALUES
(1, 'General Medicine', 'GEN-MED', 'Primary health care and internal medicine consultations'),
(2, 'Cardiology', 'CARD', 'Diagnosis and treatment of heart and cardiovascular disorders'),
(3, 'Pediatrics', 'PED', 'Medical care for infants, children, and adolescents'),
(4, 'Orthopedics & Surgery', 'SURG', 'Surgical operations, bone care, and fracture treatment'),
(5, 'Emergency', 'EMERG', '24/7 Trauma response and urgent critical care'),
(6, 'Radiology & Imaging', 'RAD', 'X-Ray, Ultrasound, CT scans, and diagnostic imaging'),
(7, 'Laboratory', 'LAB', 'Clinical pathology, blood testing, and microbiological analysis'),
(8, 'Pharmacy', 'PHARM', 'Dispensing medications, drug safety, and stock management'),
(9, 'Gynecology & Obstetrics', 'OBGYN', 'Women health, pregnancy, and maternity care'),
(10, 'Dentistry', 'DENT', 'Dental health, oral surgeries, and hygiene');

-- ----------------------------------------------------------------------------
-- 4. DOCTORS
-- ----------------------------------------------------------------------------
CREATE TABLE `doctors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `department_id` INT NOT NULL,
    `doctor_number` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `specialization` VARCHAR(100) NOT NULL,
    `license_number` VARCHAR(50) NOT NULL UNIQUE,
    `experience_years` INT DEFAULT 0,
    `availability_schedule` VARCHAR(255) DEFAULT 'Mon - Fri (08:00 AM - 04:00 PM)',
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `doctors` (`id`, `user_id`, `department_id`, `doctor_number`, `first_name`, `last_name`, `gender`, `phone`, `email`, `specialization`, `license_number`, `experience_years`, `availability_schedule`) VALUES
(1, 2, 2, 'DOC-1001', 'Robert', 'Smith', 'Male', '+1 555-0101', 'smith@hospital.com', 'Interventional Cardiology', 'LIC-CARD-8832', 12, 'Mon, Wed, Fri (09:00 AM - 03:00 PM)'),
(2, 3, 1, 'DOC-1002', 'Sarah', 'Johnson', 'Female', '+1 555-0102', 'johnson@hospital.com', 'Internal Medicine & General Health', 'LIC-MED-4419', 8, 'Mon - Thu (08:00 AM - 04:00 PM)');

-- ----------------------------------------------------------------------------
-- 5. NURSES
-- ----------------------------------------------------------------------------
CREATE TABLE `nurses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `department_id` INT NOT NULL,
    `nurse_number` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `nurses` (`id`, `user_id`, `department_id`, `nurse_number`, `first_name`, `last_name`, `gender`, `phone`, `email`) VALUES
(1, 4, 1, 'NRS-2001', 'Mary', 'Adams', 'Female', '+1 555-0104', 'mary@hospital.com');

-- ----------------------------------------------------------------------------
-- 6. PATIENTS
-- ----------------------------------------------------------------------------
CREATE TABLE `patients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `patient_number` VARCHAR(30) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `date_of_birth` DATE NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `emergency_contact` VARCHAR(150) NULL,
    `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown',
    `allergies` TEXT NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `patients` (`id`, `user_id`, `patient_number`, `first_name`, `last_name`, `gender`, `date_of_birth`, `phone`, `email`, `address`, `emergency_contact`, `blood_group`, `allergies`) VALUES
(1, 8, 'PAT-2026-0001', 'David', 'Miller', 'Male', '1988-05-14', '+1 555-0199', 'david@example.com', '742 Evergreen Terrace, Springfield', 'Wife: Sarah Miller (+1 555-0198)', 'O+', 'Penicillin, Dust'),
(2, NULL, 'PAT-2026-0002', 'Emily', 'Watson', 'Female', '1995-11-22', '+1 555-0210', 'emily.w@example.com', '104 Baker Street, Cityville', 'Father: John Watson (+1 555-0211)', 'A+', 'Latex'),
(3, NULL, 'PAT-2026-0003', 'Michael', 'Brown', 'Male', '1974-03-08', '+1 555-0340', 'mbrown@example.com', '45 Ocean Drive, Metroville', 'Brother: James Brown (+1 555-0341)', 'B-', 'None');

-- ----------------------------------------------------------------------------
-- 7. APPOINTMENTS
-- ----------------------------------------------------------------------------
CREATE TABLE `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appointment_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'No Show') DEFAULT 'Scheduled',
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_app_date_doctor` (`appointment_date`, `doctor_id`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `appointments` (`id`, `appointment_number`, `patient_id`, `doctor_id`, `department_id`, `appointment_date`, `appointment_time`, `reason`, `status`, `notes`) VALUES
(1, 'APT-2026-0001', 1, 1, 2, CURRENT_DATE(), '10:00:00', 'Chest tightness and routine heart checkup', 'Confirmed', 'Patient asked for morning slot'),
(2, 'APT-2026-0002', 2, 2, 1, CURRENT_DATE(), '11:30:00', 'Persistent fever and sore throat for 3 days', 'Completed', 'Consultation done'),
(3, 'APT-2026-0003', 3, 2, 1, DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), '09:00:00', 'Annual wellness exam', 'Scheduled', 'First visit');

-- ----------------------------------------------------------------------------
-- 8. MEDICAL RECORDS
-- ----------------------------------------------------------------------------
CREATE TABLE `medical_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `record_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `appointment_id` INT NULL,
    `visit_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `chief_complaint` TEXT NOT NULL,
    `symptoms` TEXT NOT NULL,
    `diagnosis` TEXT NOT NULL,
    `treatment_plan` TEXT NOT NULL,
    `notes` TEXT NULL,
    `follow_up_date` DATE NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medical_records` (`id`, `record_number`, `patient_id`, `doctor_id`, `appointment_id`, `chief_complaint`, `symptoms`, `diagnosis`, `treatment_plan`, `follow_up_date`) VALUES
(1, 'MR-2026-0001', 2, 2, 2, 'High fever and sore throat', 'Body aches, chills, temperature 38.8 C', 'Acute Upper Respiratory Tract Infection', 'Prescribed Amoxicillin 500mg, Paracetamol, rest for 3 days', DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY));

-- ----------------------------------------------------------------------------
-- 9. VITAL SIGNS
-- ----------------------------------------------------------------------------
CREATE TABLE `vital_signs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `patient_id` INT NOT NULL,
    `medical_record_id` INT NULL,
    `temperature` DECIMAL(4,1) NOT NULL, -- in Celsius
    `blood_pressure` VARCHAR(20) NOT NULL, -- e.g. 120/80
    `heart_rate` INT NOT NULL, -- bpm
    `respiratory_rate` INT NOT NULL, -- breaths/min
    `oxygen_saturation` INT NOT NULL, -- %
    `weight_kg` DECIMAL(5,2) NOT NULL,
    `height_cm` DECIMAL(5,2) NOT NULL,
    `bmi` DECIMAL(4,1) NOT NULL,
    `recorded_by` INT NULL,
    `recorded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `vital_signs` (`id`, `patient_id`, `medical_record_id`, `temperature`, `blood_pressure`, `heart_rate`, `respiratory_rate`, `oxygen_saturation`, `weight_kg`, `height_cm`, `bmi`, `recorded_by`) VALUES
(1, 2, 1, 38.8, '118/76', 88, 18, 98, 62.50, 168.00, 22.1, 4);

-- ----------------------------------------------------------------------------
-- 10. MEDICINES (PHARMACY INVENTORY)
-- ----------------------------------------------------------------------------
CREATE TABLE `medicines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_code` VARCHAR(30) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `generic_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `manufacturer` VARCHAR(100) NOT NULL,
    `batch_number` VARCHAR(50) NOT NULL,
    `expiry_date` DATE NOT NULL,
    `quantity` INT NOT NULL DEFAULT 0,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `reorder_level` INT NOT NULL DEFAULT 10,
    `supplier_name` VARCHAR(100) NULL,
    `status` ENUM('Active', 'Discontinued') DEFAULT 'Active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medicines` (`id`, `medicine_code`, `name`, `generic_name`, `category`, `manufacturer`, `batch_number`, `expiry_date`, `quantity`, `unit_price`, `reorder_level`, `supplier_name`) VALUES
(1, 'MED-101', 'Amoxicillin 500mg', 'Amoxicillin', 'Antibiotic', 'PharmaCare Ltd', 'BCH-8821', '2027-08-15', 120, 12.50, 20, 'Global Health Supplies'),
(2, 'MED-102', 'Paracetamol 500mg', 'Acetaminophen', 'Analgesic / Antipyretic', 'MediLabs', 'BCH-9940', '2027-12-01', 350, 3.00, 50, 'Global Health Supplies'),
(3, 'MED-103', 'Atorvastatin 20mg', 'Atorvastatin', 'Cardiovascular', 'HeartHealth Corp', 'BCH-3310', '2026-11-20', 8, 25.00, 15, 'Apex Pharma Supplier'), -- Low stock
(4, 'MED-104', 'Metformin 850mg', 'Metformin', 'Antidiabetic', 'BioPharm', 'BCH-1002', '2026-05-10', 45, 18.00, 25, 'Apex Pharma Supplier'), -- Expired demo check
(5, 'MED-105', 'Ibuprofen 400mg', 'Ibuprofen', 'NSAID', 'MediLabs', 'BCH-7741', '2027-06-30', 200, 5.50, 30, 'Global Health Supplies');

-- ----------------------------------------------------------------------------
-- 11. MEDICINE STOCK LOG
-- ----------------------------------------------------------------------------
CREATE TABLE `medicine_stock_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `medicine_id` INT NOT NULL,
    `transaction_type` ENUM('STOCK_IN', 'STOCK_OUT', 'DISPENSED', 'ADJUSTMENT') NOT NULL,
    `quantity` INT NOT NULL,
    `reference_id` VARCHAR(50) NULL,
    `notes` TEXT NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `medicine_stock_log` (`id`, `medicine_id`, `transaction_type`, `quantity`, `reference_id`, `notes`, `created_by`) VALUES
(1, 1, 'STOCK_IN', 150, 'PO-2026-01', 'Initial stock batch receipt', 6),
(2, 2, 'STOCK_IN', 400, 'PO-2026-01', 'Initial stock batch receipt', 6);

-- ----------------------------------------------------------------------------
-- 12. PRESCRIPTIONS
-- ----------------------------------------------------------------------------
CREATE TABLE `prescriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prescription_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `medical_record_id` INT NULL,
    `prescription_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Pending', 'Dispensed', 'Cancelled') DEFAULT 'Pending',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `prescriptions` (`id`, `prescription_number`, `patient_id`, `doctor_id`, `medical_record_id`, `status`, `notes`) VALUES
(1, 'RX-2026-0001', 2, 2, 1, 'Pending', 'Take medicines after meals with plenty of water.');

-- ----------------------------------------------------------------------------
-- 13. PRESCRIPTION ITEMS
-- ----------------------------------------------------------------------------
CREATE TABLE `prescription_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prescription_id` INT NOT NULL,
    `medicine_id` INT NOT NULL,
    `dosage` VARCHAR(50) NOT NULL, -- e.g. 500mg
    `frequency` VARCHAR(50) NOT NULL, -- e.g. 3 times daily
    `duration` VARCHAR(50) NOT NULL, -- e.g. 5 days
    `quantity` INT NOT NULL,
    `instructions` TEXT NULL,
    FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`medicine_id`) REFERENCES `medicines`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `prescription_items` (`id`, `prescription_id`, `medicine_id`, `dosage`, `frequency`, `duration`, `quantity`, `instructions`) VALUES
(1, 1, 1, '500mg', '3 times a day', '5 days', 15, 'Finish the full antibiotic course'),
(2, 1, 2, '500mg', 'Every 8 hours as needed', '3 days', 10, 'For fever and body pains');

-- ----------------------------------------------------------------------------
-- 14. LABORATORY TESTS (CATALOG)
-- ----------------------------------------------------------------------------
CREATE TABLE `lab_tests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `test_code` VARCHAR(30) NOT NULL UNIQUE,
    `test_name` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `normal_range` VARCHAR(100) NULL,
    `unit` VARCHAR(20) NULL,
    `status` ENUM('Active', 'Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lab_tests` (`id`, `test_code`, `test_name`, `category`, `price`, `normal_range`, `unit`) VALUES
(1, 'LAB-CBC', 'Complete Blood Count (CBC)', 'Hematology', 45.00, 'WBC: 4.5-11.0, RBC: 4.2-5.9', 'x10^3/uL'),
(2, 'LAB-LFT', 'Liver Function Test (LFT)', 'Biochemistry', 65.00, 'ALT: 7-56, AST: 10-40', 'U/L'),
(3, 'LAB-LIPID', 'Lipid Profile Screen', 'Biochemistry', 55.00, 'Total Chol: < 200, LDL: < 100', 'mg/dL'),
(4, 'LAB-COVID', 'COVID-19 RT-PCR Test', 'Virology', 80.00, 'Negative', 'Result'),
(5, 'LAB-URINE', 'Routine Urinalysis', 'Microbiology', 30.00, 'Protein: Negative, Glucose: Negative', 'Qualitative');

-- ----------------------------------------------------------------------------
-- 15. LABORATORY REQUESTS
-- ----------------------------------------------------------------------------
CREATE TABLE `lab_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `doctor_id` INT NOT NULL,
    `medical_record_id` INT NULL,
    `request_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Requested', 'Sample Collected', 'Processing', 'Completed', 'Cancelled') DEFAULT 'Requested',
    `priority` ENUM('Routine', 'Urgent', 'Emergency') DEFAULT 'Routine',
    `notes` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`doctor_id`) REFERENCES `doctors`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`medical_record_id`) REFERENCES `medical_records`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lab_requests` (`id`, `request_number`, `patient_id`, `doctor_id`, `medical_record_id`, `status`, `priority`, `notes`) VALUES
(1, 'LAB-REQ-0001', 1, 1, NULL, 'Requested', 'Urgent', 'Check lipid profile and troponin markers for chest discomfort'),
(2, 'LAB-REQ-0002', 2, 2, 1, 'Completed', 'Routine', 'CBC screen for fever diagnosis');

-- ----------------------------------------------------------------------------
-- 16. LABORATORY RESULTS
-- ----------------------------------------------------------------------------
CREATE TABLE `lab_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `lab_request_id` INT NOT NULL,
    `lab_test_id` INT NOT NULL,
    `result_value` TEXT NOT NULL,
    `normal_range` VARCHAR(100) NULL,
    `status` ENUM('Normal', 'Abnormal', 'Critical') DEFAULT 'Normal',
    `notes` TEXT NULL,
    `technician_id` INT NULL,
    `result_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`lab_request_id`) REFERENCES `lab_requests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lab_results` (`id`, `lab_request_id`, `lab_test_id`, `result_value`, `normal_range`, `status`, `notes`, `technician_id`) VALUES
(1, 2, 1, 'WBC: 12.4 (Elevated), RBC: 4.8, Hb: 14.1 g/dL, Platelets: 250k', 'WBC: 4.5-11.0', 'Abnormal', 'Mild leukocytosis consistent with active infection', 7);

-- ----------------------------------------------------------------------------
-- 17. INVOICES
-- ----------------------------------------------------------------------------
CREATE TABLE `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(30) NOT NULL UNIQUE,
    `patient_id` INT NOT NULL,
    `appointment_id` INT NULL,
    `invoice_date` DATE NOT NULL,
    `due_date` DATE NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Unpaid', 'Partially Paid', 'Paid', 'Cancelled') DEFAULT 'Unpaid',
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoices` (`id`, `invoice_number`, `patient_id`, `appointment_id`, `invoice_date`, `due_date`, `subtotal`, `discount`, `tax`, `total_amount`, `amount_paid`, `balance`, `status`, `created_by`) VALUES
(1, 'INV-2026-0001', 2, 2, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), 120.00, 10.00, 5.50, 115.50, 115.50, 0.00, 'Paid', 5),
(2, 'INV-2026-0002', 1, 1, CURRENT_DATE(), DATE_ADD(CURRENT_DATE(), INTERVAL 14 DAY), 150.00, 0.00, 7.50, 157.50, 50.00, 107.50, 'Partially Paid', 5);

-- ----------------------------------------------------------------------------
-- 18. INVOICE ITEMS
-- ----------------------------------------------------------------------------
CREATE TABLE `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `item_type` ENUM('Consultation', 'Lab Test', 'Medicine', 'Procedure', 'Other') NOT NULL,
    `item_id` INT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `invoice_items` (`id`, `invoice_id`, `item_type`, `item_id`, `description`, `quantity`, `unit_price`, `total_price`) VALUES
(1, 1, 'Consultation', 2, 'General Medicine Doctor Consultation Fee', 1, 75.00, 75.00),
(2, 1, 'Lab Test', 1, 'Complete Blood Count (CBC)', 1, 45.00, 45.00),
(3, 2, 'Consultation', 1, 'Cardiology Specialist Consultation Fee', 1, 150.00, 150.00);

-- ----------------------------------------------------------------------------
-- 19. PAYMENTS
-- ----------------------------------------------------------------------------
CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_number` VARCHAR(30) NOT NULL UNIQUE,
    `invoice_id` INT NOT NULL,
    `patient_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('Cash', 'Card', 'Mobile Money', 'Bank Transfer') NOT NULL,
    `reference_number` VARCHAR(100) NULL,
    `received_by` INT NULL,
    `payment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`received_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (`id`, `payment_number`, `invoice_id`, `patient_id`, `amount`, `payment_method`, `reference_number`, `received_by`) VALUES
(1, 'PAY-2026-0001', 1, 2, 115.50, 'Card', 'TXN-VISA-99182', 5),
(2, 'PAY-2026-0002', 2, 1, 50.00, 'Cash', 'CASH-REC-002', 5);

-- ----------------------------------------------------------------------------
-- 20. NOTIFICATIONS
-- ----------------------------------------------------------------------------
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('Info', 'Warning', 'Success', 'Danger') DEFAULT 'Info',
    `is_read` TINYINT(1) DEFAULT 0,
    `link` VARCHAR(255) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`) VALUES
(1, 1, 'Low Stock Alert', 'Medicine "Atorvastatin 20mg" stock has dropped to 8 (Reorder level: 15).', 'Warning', 'pharmacy/index.php'),
(2, 2, 'New Appointment', 'Patient David Miller scheduled an appointment for today at 10:00 AM.', 'Info', 'appointments/index.php'),
(3, 6, 'Prescription Pending', 'New prescription RX-2026-0001 created for patient Emily Watson.', 'Info', 'pharmacy/dispensing.php');

-- ----------------------------------------------------------------------------
-- 21. ACTIVITY LOGS
-- ----------------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`) VALUES
(1, 1, 'System Setup', 'Initial database schema and default realistic sample data loaded successfully.', '127.0.0.1'),
(2, 5, 'Payment Recorded', 'Recorded cash payment of $115.50 for Invoice INV-2026-0001.', '127.0.0.1');

-- ============================================================================
-- END OF SCHEMA SCRIPT
-- ============================================================================
