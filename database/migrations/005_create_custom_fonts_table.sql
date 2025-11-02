-- Create custom_fonts table for storing user-uploaded fonts
-- Migration: 005_create_custom_fonts_table
-- Created: 2025-11-03

CREATE TABLE IF NOT EXISTS custom_fonts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    font_name VARCHAR(255) NOT NULL,
    font_family VARCHAR(255) NOT NULL UNIQUE,
    font_category ENUM('sans-serif', 'serif', 'monospace', 'display', 'handwriting') DEFAULT 'sans-serif',
    font_weight VARCHAR(50) DEFAULT 'normal',
    font_style VARCHAR(50) DEFAULT 'normal',
    file_path VARCHAR(500) NOT NULL,
    file_format ENUM('woff', 'woff2', 'ttf', 'otf', 'eot') NOT NULL,
    file_size INT NOT NULL,
    uploaded_by INT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_font_family (font_family),
    INDEX idx_is_active (is_active),
    INDEX idx_font_category (font_category),
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add sample comment
COMMENT ON TABLE custom_fonts IS 'Stores user-uploaded custom font files';
