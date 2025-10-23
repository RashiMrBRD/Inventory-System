-- Create Database
CREATE DATABASE IF NOT EXISTS inventory_system;
USE inventory_system;

-- Create inventory table
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    lifespan VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    date_added DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data
INSERT INTO inventory (barcode, name, type, lifespan, quantity, date_added) VALUES
('1090912', 'Milo 300g', 'Packed Goods', '2 to 10 years', 20, '2025-10-18'),
('12345678', 'Alaska 200g', 'Packed Goods', '2 to 10 years', 1, '2025-10-08'),
('11223344', 'Oranges', 'Fruits', '1 week', 5, '2025-10-02'),
('55667788', 'Rice Krispies 100g', 'Pastries', '1 week', 25, '2025-10-04'),
('12349876', 'Toast Bread', 'Pastries', '1 week', 30, '2025-10-14'),
('29830821', 'Rebisco', 'Packed Goods', '2 to 10 years', 32, '2025-10-13'),
('8721453', 'Piattos 50g', 'Packed Goods', '2 to 10 years', 72, '2025-10-20'),
('57831235', 'Brownies', 'Pastries', '3 days', 92, '2025-10-21');

-- Create users table for authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    access_level VARCHAR(50) NOT NULL DEFAULT 'User',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, full_name, access_level) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Gian Benedict', 'Admin');
