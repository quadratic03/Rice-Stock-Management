-- Add latitude and longitude columns to warehouses table
ALTER TABLE warehouses 
ADD COLUMN latitude DECIMAL(10, 8) NULL,
ADD COLUMN longitude DECIMAL(11, 8) NULL,
ADD COLUMN manager_name VARCHAR(100) NULL,
ADD COLUMN contact_info VARCHAR(255) NULL;

-- Create transactions table for tracking all stock movements
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('purchase', 'sale', 'transfer', 'adjustment') NOT NULL,
    reference_id INT NOT NULL,
    stock_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create purchases table
CREATE TABLE IF NOT EXISTS purchases (
    purchase_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    supplier_id INT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create sales table
CREATE TABLE IF NOT EXISTS sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    customer_name VARCHAR(100) NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    sale_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    profit_loss DECIMAL(12, 2) NULL,
    sale_date DATE NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create stock_transfers table
CREATE TABLE IF NOT EXISTS stock_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    from_warehouse_id INT NOT NULL,
    to_warehouse_id INT NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    reason TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id),
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create stock_adjustments table
CREATE TABLE IF NOT EXISTS stock_adjustments (
    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
    stock_id INT NOT NULL,
    adjustment_type ENUM('add', 'remove') NOT NULL,
    quantity DECIMAL(10, 2) NOT NULL,
    previous_quantity DECIMAL(10, 2) NOT NULL,
    new_quantity DECIMAL(10, 2) NOT NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_id) REFERENCES stocks(stock_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create settings table for system-wide settings
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    description VARCHAR(255) NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description, is_public) VALUES
('site_name', 'Rice Stock Management System', 'Name of the site', 1),
('company_name', 'Rice Company Inc.', 'Company name', 1),
('company_address', '123 Rice Street, Grain City', 'Company address', 1),
('company_phone', '+1234567890', 'Company phone number', 1),
('company_email', 'info@ricecompany.com', 'Company email address', 1),
('google_maps_api_key', '', 'Google Maps API Key for mapping features', 0),
('default_currency', 'USD', 'Default currency for the system', 1),
('low_stock_threshold', '100', 'Threshold for low stock warnings (kg)', 0);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    type VARCHAR(50) DEFAULT 'info',
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create function to get setting value
DELIMITER //
CREATE FUNCTION IF NOT EXISTS get_setting(setting_key VARCHAR(50)) 
RETURNS TEXT DETERMINISTIC
BEGIN
    DECLARE setting_value TEXT;
    SELECT s.setting_value INTO setting_value 
    FROM settings s 
    WHERE s.setting_key = setting_key 
    LIMIT 1;
    RETURN setting_value;
END //
DELIMITER ;

-- Create trigger to record stock purchases in transactions
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_purchase_insert
AFTER INSERT ON purchases
FOR EACH ROW
BEGIN
    INSERT INTO transactions (
        transaction_type, reference_id, stock_id, 
        quantity, notes, created_by
    )
    SELECT 
        'purchase', NEW.purchase_id, NEW.stock_id, 
        s.quantity, CONCAT('Purchase invoice: ', NEW.invoice_number), NEW.created_by
    FROM stocks s
    WHERE s.stock_id = NEW.stock_id;
END //
DELIMITER ;

-- Create trigger to record stock adjustments in transactions
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_adjustment_insert
AFTER INSERT ON stock_adjustments
FOR EACH ROW
BEGIN
    INSERT INTO transactions (
        transaction_type, reference_id, stock_id, 
        quantity, notes, created_by
    )
    VALUES (
        'adjustment', NEW.adjustment_id, NEW.stock_id,
        CASE 
            WHEN NEW.adjustment_type = 'add' THEN NEW.quantity
            ELSE -NEW.quantity
        END,
        NEW.reason, NEW.created_by
    );
END //
DELIMITER ;

-- Create trigger to record stock transfers in transactions
DELIMITER //
CREATE TRIGGER IF NOT EXISTS after_transfer_insert
AFTER INSERT ON stock_transfers
FOR EACH ROW
BEGIN
    INSERT INTO transactions (
        transaction_type, reference_id, stock_id, 
        quantity, notes, created_by
    )
    VALUES (
        'transfer', NEW.transfer_id, NEW.stock_id,
        -NEW.quantity, 
        CONCAT('Transfer from warehouse ID ', NEW.from_warehouse_id, ' to warehouse ID ', NEW.to_warehouse_id, ': ', COALESCE(NEW.reason, '')),
        NEW.created_by
    );
END //
DELIMITER ;

-- SQL Updates for Reporting Functionality

-- Transactions table for tracking all inventory movements
CREATE TABLE IF NOT EXISTS `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('purchase','sale','transfer','adjustment') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transaction_id`),
  KEY `stock_id` (`stock_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `transactions_stock_fk` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`stock_id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales table for tracking sales transactions
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `profit_loss` decimal(10,2) NOT NULL,
  `sale_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sale_id`),
  KEY `stock_id` (`stock_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `sales_stock_fk` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`stock_id`) ON DELETE CASCADE,
  CONSTRAINT `sales_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Purchases table for tracking purchase transactions
CREATE TABLE IF NOT EXISTS `purchases` (
  `purchase_id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `purchase_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  KEY `stock_id` (`stock_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchases_stock_fk` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`stock_id`) ON DELETE CASCADE,
  CONSTRAINT `purchases_supplier_fk` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON DELETE SET NULL,
  CONSTRAINT `purchases_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock transfers table for tracking transfers between warehouses
CREATE TABLE IF NOT EXISTS `stock_transfers` (
  `transfer_id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` int(11) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `transfer_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`transfer_id`),
  KEY `stock_id` (`stock_id`),
  KEY `from_warehouse_id` (`from_warehouse_id`),
  KEY `to_warehouse_id` (`to_warehouse_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `transfers_stock_fk` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`stock_id`) ON DELETE CASCADE,
  CONSTRAINT `transfers_from_warehouse_fk` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`warehouse_id`),
  CONSTRAINT `transfers_to_warehouse_fk` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`warehouse_id`),
  CONSTRAINT `transfers_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock adjustments table for tracking inventory adjustments
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `adjustment_id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_id` int(11) NOT NULL,
  `adjustment_type` enum('increase','decrease') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reason` varchar(100) NOT NULL,
  `adjustment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`adjustment_id`),
  KEY `stock_id` (`stock_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `adjustments_stock_fk` FOREIGN KEY (`stock_id`) REFERENCES `stocks` (`stock_id`) ON DELETE CASCADE,
  CONSTRAINT `adjustments_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger to create transaction record after sale
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_sale_insert
AFTER INSERT ON sales
FOR EACH ROW
BEGIN
  INSERT INTO transactions (transaction_type, reference_id, stock_id, quantity, transaction_date, notes, created_by)
  VALUES ('sale', NEW.sale_id, NEW.stock_id, -NEW.quantity, NEW.sale_date, CONCAT('Sale: ', NEW.invoice_number), NEW.created_by);
END$$
DELIMITER ;

-- Trigger to create transaction record after purchase
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_purchase_insert
AFTER INSERT ON purchases
FOR EACH ROW
BEGIN
  INSERT INTO transactions (transaction_type, reference_id, stock_id, quantity, transaction_date, notes, created_by)
  VALUES ('purchase', NEW.purchase_id, NEW.stock_id, (SELECT quantity FROM stocks WHERE stock_id = NEW.stock_id), NEW.purchase_date, CONCAT('Purchase: ', NEW.invoice_number), NEW.created_by);
END$$
DELIMITER ;

-- Trigger to create transaction record after transfer
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_transfer_insert
AFTER INSERT ON stock_transfers
FOR EACH ROW
BEGIN
  INSERT INTO transactions (transaction_type, reference_id, stock_id, quantity, transaction_date, notes, created_by)
  VALUES ('transfer', NEW.transfer_id, NEW.stock_id, -NEW.quantity, NEW.transfer_date, CONCAT('Transfer from warehouse #', NEW.from_warehouse_id, ' to warehouse #', NEW.to_warehouse_id), NEW.created_by);
END$$
DELIMITER ;

-- Trigger to create transaction record after adjustment
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS after_adjustment_insert
AFTER INSERT ON stock_adjustments
FOR EACH ROW
BEGIN
  DECLARE qty DECIMAL(10,2);
  
  IF NEW.adjustment_type = 'increase' THEN
    SET qty = NEW.quantity;
  ELSE
    SET qty = -NEW.quantity;
  END IF;
  
  INSERT INTO transactions (transaction_type, reference_id, stock_id, quantity, transaction_date, notes, created_by)
  VALUES ('adjustment', NEW.adjustment_id, NEW.stock_id, qty, NEW.adjustment_date, CONCAT('Adjustment: ', NEW.reason), NEW.created_by);
END$$
DELIMITER ;

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('company_name', 'Rice Stock System', 'Company name for reports'),
('company_address', '123 Rice Mill Road, Manila', 'Company address for reports'),
('company_phone', '+63 912 345 6789', 'Company phone for reports'),
('company_email', 'info@ricestocksystem.com', 'Company email for reports'),
('default_currency', 'PHP', 'Default currency symbol'),
('low_stock_threshold', '100', 'Low stock alert threshold (kg)'),
('report_logo', '', 'Logo URL for reports'),
('enable_notifications', '1', 'Enable system notifications (0/1)'),
('date_format', 'Y-m-d', 'Default date format');

-- Add sample data
INSERT INTO transactions (transaction_type, stock_id, quantity, notes, created_by) 
SELECT 'purchase', stock_id, quantity, 'Initial stock import', 1 FROM stocks
WHERE NOT EXISTS (SELECT 1 FROM transactions); 