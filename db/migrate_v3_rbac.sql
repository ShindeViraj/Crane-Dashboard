-- ============================================
-- MIGRATION: v2 → v3 (RBAC)
-- Run this on the LIVE database to upgrade
-- ============================================

-- Step 1: Change role enum
ALTER TABLE users MODIFY COLUMN role ENUM('developer','admin','user') DEFAULT 'user';

-- Step 2: Add UNIQUE constraint on email (if not already)
-- First set any duplicate/blank emails to NULL
UPDATE users SET email = NULL WHERE email = '';
ALTER TABLE users MODIFY COLUMN email VARCHAR(100) DEFAULT NULL UNIQUE;

-- Step 3: Create user_cranes table
CREATE TABLE IF NOT EXISTS user_cranes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crane_id VARCHAR(50) NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (user_id, crane_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crane_id) REFERENCES cranes(crane_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Step 4: Seed developer account (password: dev123 — CHANGE IMMEDIATELY)
INSERT INTO users (username, email, password_hash, display_name, role) VALUES
('developer', 'dev@squarewave.in', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Developer', 'developer')
ON DUPLICATE KEY UPDATE role='developer';
