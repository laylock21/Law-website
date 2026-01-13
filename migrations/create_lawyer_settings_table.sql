-- Create separate lawyer settings table for more flexibility
-- This allows for future expansion of lawyer-specific settings

CREATE TABLE lawyer_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_name VARCHAR(50) NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('integer', 'string', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_lawyer_setting (user_id, setting_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lawyer_settings_lookup (user_id, setting_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default settings for all existing lawyers
INSERT INTO lawyer_settings (user_id, setting_name, setting_value, setting_type)
SELECT 
    id as user_id,
    'default_booking_weeks' as setting_name,
    '52' as setting_value,
    'integer' as setting_type
FROM users WHERE role = 'lawyer';

INSERT INTO lawyer_settings (user_id, setting_name, setting_value, setting_type)
SELECT 
    id as user_id,
    'max_booking_weeks' as setting_name,
    '104' as setting_value,
    'integer' as setting_type
FROM users WHERE role = 'lawyer';

INSERT INTO lawyer_settings (user_id, setting_name, setting_value, setting_type)
SELECT 
    id as user_id,
    'booking_window_enabled' as setting_name,
    '1' as setting_value,
    'boolean' as setting_type
FROM users WHERE role = 'lawyer';
