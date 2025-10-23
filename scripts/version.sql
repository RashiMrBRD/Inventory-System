-- Create app_versions table for remote update system
-- This table stores all available application versions

CREATE TABLE IF NOT EXISTS app_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20) NOT NULL UNIQUE,
    release_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    release_notes TEXT,
    download_url VARCHAR(500),
    changelog TEXT,
    is_stable BOOLEAN DEFAULT true,
    is_latest BOOLEAN DEFAULT false,
    min_php_version VARCHAR(10) DEFAULT '7.4',
    file_size_mb DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_version (version),
    INDEX idx_is_latest (is_latest),
    INDEX idx_release_date (release_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial version
INSERT INTO app_versions (
    version, 
    release_date, 
    release_notes, 
    changelog,
    is_stable, 
    is_latest,
    file_size_mb
) VALUES (
    '0.1.4',
    NOW(),
    'Initial release with core inventory management features',
    '- Toast notification system with shadcn design\n- Inventory management\n- Journal entries\n- Multi-currency support\n- Timezone configuration\n- User authentication\n- Notification system',
    true,
    true,
    5.2
) ON DUPLICATE KEY UPDATE is_latest = true;

-- Example: Insert future version (for testing)
INSERT INTO app_versions (
    version, 
    release_date, 
    release_notes, 
    changelog,
    is_stable, 
    is_latest,
    file_size_mb
) VALUES (
    '0.2.0',
    DATE_ADD(NOW(), INTERVAL 7 DAY),
    'Major update with new features and improvements',
    '- Remote update system\n- Automated backup before updates\n- Improved dashboard analytics\n- Export/Import functionality\n- Enhanced security features\n- Performance optimizations\n- Bug fixes',
    true,
    false,
    6.5
) ON DUPLICATE KEY UPDATE version = version;

-- Example: Beta version
INSERT INTO app_versions (
    version, 
    release_date, 
    release_notes, 
    changelog,
    is_stable, 
    is_latest,
    file_size_mb
) VALUES (
    '0.3.0-beta',
    DATE_ADD(NOW(), INTERVAL 14 DAY),
    'Beta release - Testing new features',
    '- AI-powered inventory predictions\n- Advanced reporting\n- Mobile app integration\n- Barcode scanner improvements',
    false,
    false,
    7.8
) ON DUPLICATE KEY UPDATE version = version;

-- Create update_logs table to track update history
CREATE TABLE IF NOT EXISTS update_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_version VARCHAR(20) NOT NULL,
    to_version VARCHAR(20) NOT NULL,
    user_id INT,
    update_status ENUM('pending', 'in_progress', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    started_at DATETIME,
    completed_at DATETIME,
    error_message TEXT,
    backup_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (update_status),
    INDEX idx_to_version (to_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
