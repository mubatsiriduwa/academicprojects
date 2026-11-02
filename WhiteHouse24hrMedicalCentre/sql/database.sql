-- Create database
CREATE DATABASE IF NOT EXISTS whitehouse_medical;
USE whitehouse_medical;

-- Users table
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'receptionist') DEFAULT 'receptionist',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients table
CREATE TABLE patients (
    patient_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_number VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    address TEXT,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visits table (renamed columns: admission -> check_in, discharge -> check_out)
CREATE TABLE visits (
    visit_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    visit_number VARCHAR(20) UNIQUE NOT NULL,
    check_in_date DATE NOT NULL,
    check_in_time TIME NOT NULL,
    visit_reason TEXT,
    status ENUM('checked_in', 'checked_out') DEFAULT 'checked_in',
    check_out_date DATE NULL,
    check_out_time TIME NULL,
    check_out_notes TEXT,
    registered_by INT,
    checked_out_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (registered_by) REFERENCES users(user_id),
    FOREIGN KEY (checked_out_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inpatient admissions table (NEW - only for admitted patients)
CREATE TABLE inpatient_admissions (
    inpatient_id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT NOT NULL,
    ward_name VARCHAR(100) NOT NULL,
    bed_number VARCHAR(20) NOT NULL,
    admitting_doctor VARCHAR(100),
    admission_type ENUM('Planned', 'Emergency', 'Transfer', 'Maternity') DEFAULT 'Planned',
    expected_stay_days INT,
    admission_notes TEXT,
    actual_discharge_date DATE NULL,
    discharge_summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log table
CREATE TABLE activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 1);

-- Insert default receptionist user (password: reception123)
INSERT INTO users (username, password, full_name, role, is_active) VALUES
('reception', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Receptionist', 'receptionist', 1);

-- Insert sample patients (for testing)
INSERT INTO patients (patient_number, first_name, last_name, date_of_birth, gender, phone, address) VALUES
('WH-202604-0001', 'John', 'Dube', '1985-05-15', 'Male', '0771234567', '123 Main Street, Harare'),
('WH-202604-0002', 'Mary', 'Makota', '1990-08-22', 'Female', '0772345678', '45 Park Avenue, Bulawayo'),
('WH-202604-0003', 'Peter', 'Mukadota', '1978-12-03', 'Male', '0773456789', '78 Queen Street, Gweru');

-- Insert sample visits (for testing)
INSERT INTO visits (patient_id, visit_number, check_in_date, check_in_time, visit_reason, status, registered_by) VALUES
(1, 'VIS-20260428-001', CURDATE(), CURTIME(), 'Fever and headache', 'checked_in', 1),
(2, 'VIS-20260428-002', CURDATE(), CURTIME(), 'Regular checkup', 'checked_in', 2),
(3, 'VIS-20260423-001', DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:30:00', 'Chest pain', 'checked_out', 1);

-- Update the third visit with check-out details
UPDATE visits SET check_out_date = DATE_SUB(CURDATE(), INTERVAL 5 DAY), check_out_time = '14:00:00', check_out_notes = 'Patient stable, discharged with medication', checked_out_by = 1 WHERE visit_number = 'VIS-20260423-001';
