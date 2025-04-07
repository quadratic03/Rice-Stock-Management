-- Rice Stock System Database Schema

-- Create the database if not exists
CREATE DATABASE IF NOT EXISTS rice_stock_system;
USE rice_stock_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'manager', 'staff') NOT NULL DEFAULT 'staff',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Warehouses table
CREATE TABLE IF NOT EXISTS warehouses (
    warehouse_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity DECIMAL(10,2) NOT NULL COMMENT 'In metric tons',
    temperature DECIMAL(5,2) DEFAULT NULL COMMENT 'In celsius',
    humidity DECIMAL(5,2) DEFAULT NULL COMMENT 'In percentage',
    manager_id INT,
    status ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Rice varieties table
CREATE TABLE IF NOT EXISTS rice_varieties (
    variety_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    origin VARCHAR(100),
    average_cost DECIMAL(10,2) NOT NULL COMMENT 'Per kilogram',
    cooking_time INT COMMENT 'In minutes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stock table
CREATE TABLE IF NOT EXISTS stocks (
    stock_id INT AUTO_INCREMENT PRIMARY KEY,
    variety_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL COMMENT 'In kilograms',
    batch_number VARCHAR(50),
    production_date DATE,
    expiry_date DATE,
    unit_price DECIMAL(10,2) NOT NULL,
    supplier_id INT,
    quality_grade ENUM('A', 'B', 'C') DEFAULT 'A',
    status ENUM('available', 'reserved', 'sold', 'damaged') NOT NULL DEFAULT 'available',
    minimum_stock_level DECIMAL(10,2) DEFAULT 100.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (variety_id) REFERENCES rice_varieties(variety_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL
);

-- Stock Transactions table
CREATE TABLE IF NOT EXISTS stock_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    transaction_type ENUM('purchase', 'sale', 'transfer', 'adjustment', 'loss') NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    from_warehouse_id INT DEFAULT NULL,
    to_warehouse_id INT DEFAULT NULL,
    user_id INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    reference_number VARCHAR(50),
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id) ON DELETE CASCADE,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE SET NULL,
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Quality Control table
CREATE TABLE IF NOT EXISTS quality_checks (
    check_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    inspector_id INT NOT NULL,
    check_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    moisture_content DECIMAL(5,2) COMMENT 'In percentage',
    purity DECIMAL(5,2) COMMENT 'In percentage',
    broken_grains DECIMAL(5,2) COMMENT 'In percentage',
    foreign_matter DECIMAL(5,2) COMMENT 'In percentage',
    aroma ENUM('excellent', 'good', 'fair', 'poor') DEFAULT NULL,
    result ENUM('pass', 'conditional', 'fail') NOT NULL,
    notes TEXT,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id) ON DELETE CASCADE,
    FOREIGN KEY (inspector_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- System Logs table
CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('alert', 'warning', 'info', 'success') NOT NULL DEFAULT 'info',
    is_read BOOLEAN NOT NULL DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    report_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    file_path VARCHAR(255),
    generated_by INT NOT NULL,
    report_type ENUM('inventory', 'sales', 'quality', 'financial', 'custom') NOT NULL,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123 - in a production environment, use a strong password)
INSERT INTO users (username, password, email, first_name, last_name, role)
VALUES ('admin', '$2y$10$8SrSHDZw.tGR38wxqIgCveh6hDFE9HPoRyAhOUVNUTxOLwDlTqJSa', 'admin@ricestock.com', 'System', 'Administrator', 'admin');

-- Insert sample data for rice varieties
INSERT INTO rice_varieties (name, description, origin, average_cost, cooking_time)
VALUES 
('Basmati', 'Long grain aromatic rice', 'India', 2.50, 20),
('Jasmine', 'Fragrant long grain rice', 'Thailand', 2.20, 18),
('Arborio', 'Short grain rice used for risotto', 'Italy', 3.00, 18),
('Brown Rice', 'Whole grain rice with bran layer intact', 'Various', 1.80, 35),
('Sticky Rice', 'Glutinous rice used in desserts', 'Southeast Asia', 2.40, 25);

-- Insert sample warehouse data
INSERT INTO warehouses (name, location, capacity, temperature, humidity, status)
VALUES 
('North Warehouse', '123 North Road, City', 5000.00, 20.00, 55.00, 'active'),
('South Storage', '456 South Avenue, Town', 3500.00, 22.00, 50.00, 'active'),
('East Facility', '789 East Street, Village', 2000.00, 21.00, 60.00, 'active');

-- Insert sample supplier data
INSERT INTO suppliers (name, contact_person, email, phone, address, status)
VALUES 
('Global Rice Suppliers', 'John Smith', 'john@globalrice.com', '+1-555-1234', '100 Main St, Business City', 'active'),
('Organic Rice Co.', 'Jane Doe', 'jane@organicrice.com', '+1-555-5678', '200 Farm Rd, Rural County', 'active'),
('Premium Grains Ltd', 'Robert Brown', 'robert@premiumgrains.com', '+1-555-9012', '300 Quality Ave, Metro City', 'active'); 