-- BML IOT VFD Monitoring Database Schema (v2)
-- Compatible with ProFreeHost / unaux.com hosting
-- Supports: Auth, Crane Management, Massive Data, Reporting

-- ============================================
-- USERS TABLE (Authentication)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) DEFAULT 'Admin User',
    role ENUM('admin','operator','viewer') DEFAULT 'admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default admin user (password: admin123)
INSERT INTO users (username, email, password_hash, display_name, role) VALUES
('admin', 'admin@bmliot.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- ============================================
-- CRANES TABLE (Crane Management)
-- ============================================
CREATE TABLE IF NOT EXISTS cranes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    crane_id VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200) DEFAULT '',
    description TEXT DEFAULT NULL,
    status ENUM('online','offline','maintenance') DEFAULT 'offline',
    last_data_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default crane
INSERT INTO cranes (crane_id, name, location) VALUES
('1', 'Crane 1', 'SA3')
ON DUPLICATE KEY UPDATE id=id;

-- ============================================
-- CRANE DATA TABLE (VFD Parameters)
-- ============================================
CREATE TABLE IF NOT EXISTS crane_data (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    Timestamp DATETIME NOT NULL,
    crane_id VARCHAR(50) NOT NULL DEFAULT '1',
    
    -- Main Hoist (MH) Parameters
    MH_Drive_status VARCHAR(10) DEFAULT NULL,
    MH_Output_frequency FLOAT DEFAULT NULL,
    MH_Motor_current FLOAT DEFAULT NULL,
    MH_Motor_torque FLOAT DEFAULT NULL,
    MH_Mains_voltage FLOAT DEFAULT NULL,
    MH_Motor_voltage FLOAT DEFAULT NULL,
    MH_Motor_power FLOAT DEFAULT NULL,
    MH_Drive_temp FLOAT DEFAULT NULL,
    MH_Motion_run_time FLOAT DEFAULT NULL,
    MH_Logic_input INT DEFAULT NULL,
    MH_Logic_output INT DEFAULT NULL,
    MH_Altivar_fault_code VARCHAR(10) DEFAULT NULL,
    MH_Encoder FLOAT DEFAULT NULL,
    MH_Load_data FLOAT DEFAULT NULL,
    MH_di INT DEFAULT NULL,
    
    -- Cross Travel (CT) Parameters
    CT_Drive_status VARCHAR(10) DEFAULT NULL,
    CT_Output_frequency FLOAT DEFAULT NULL,
    CT_Motor_current FLOAT DEFAULT NULL,
    CT_Motor_torque FLOAT DEFAULT NULL,
    CT_Mains_voltage FLOAT DEFAULT NULL,
    CT_Motor_voltage FLOAT DEFAULT NULL,
    CT_Motor_power FLOAT DEFAULT NULL,
    CT_Drive_temp FLOAT DEFAULT NULL,
    CT_Motion_run_time FLOAT DEFAULT NULL,
    CT_Logic_input INT DEFAULT NULL,
    CT_Logic_output INT DEFAULT NULL,
    CT_Altivar_fault_code VARCHAR(10) DEFAULT NULL,
    CT_Encoder FLOAT DEFAULT NULL,
    CT_Load_data FLOAT DEFAULT NULL,
    CT_di INT DEFAULT NULL,
    
    -- Long Travel (LT) Parameters
    LT_Drive_status VARCHAR(10) DEFAULT NULL,
    LT_Output_frequency FLOAT DEFAULT NULL,
    LT_Motor_current FLOAT DEFAULT NULL,
    LT_Motor_torque FLOAT DEFAULT NULL,
    LT_Mains_voltage FLOAT DEFAULT NULL,
    LT_Motor_voltage FLOAT DEFAULT NULL,
    LT_Motor_power FLOAT DEFAULT NULL,
    LT_Drive_temp FLOAT DEFAULT NULL,
    LT_Motion_run_time FLOAT DEFAULT NULL,
    LT_Logic_input INT DEFAULT NULL,
    LT_Logic_output INT DEFAULT NULL,
    LT_Altivar_fault_code VARCHAR(10) DEFAULT NULL,
    LT_Encoder FLOAT DEFAULT NULL,
    LT_Load_data FLOAT DEFAULT NULL,
    LT_di INT DEFAULT NULL,
    
    -- Auxiliary Hoist (AH) Parameters
    AH_Drive_status VARCHAR(10) DEFAULT NULL,
    AH_Output_frequency FLOAT DEFAULT NULL,
    AH_Motor_current FLOAT DEFAULT NULL,
    AH_Motor_torque FLOAT DEFAULT NULL,
    AH_Mains_voltage FLOAT DEFAULT NULL,
    AH_Motor_voltage FLOAT DEFAULT NULL,
    AH_Motor_power FLOAT DEFAULT NULL,
    AH_Drive_temp FLOAT DEFAULT NULL,
    AH_Motion_run_time FLOAT DEFAULT NULL,
    AH_Logic_input INT DEFAULT NULL,
    AH_Logic_output INT DEFAULT NULL,
    AH_Altivar_fault_code VARCHAR(10) DEFAULT NULL,
    AH_Encoder FLOAT DEFAULT NULL,
    AH_Load_data FLOAT DEFAULT NULL,
    AH_di INT DEFAULT NULL,
    
    INDEX idx_timestamp (Timestamp),
    INDEX idx_crane_id (crane_id),
    INDEX idx_crane_timestamp (crane_id, Timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
