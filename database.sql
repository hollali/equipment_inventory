-- database.sql
-- Create Database
CREATE DATABASE IF NOT EXISTS parliament_inventory;
USE parliament_inventory;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory Items Table
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(200) NOT NULL,
    item_code VARCHAR(50) UNIQUE NOT NULL,
    category_id INT,
    supplier_id INT,
    quantity INT NOT NULL DEFAULT 0,
    min_quantity INT NOT NULL DEFAULT 10,
    unit_price DECIMAL(10, 2),
    total_value DECIMAL(10, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    location VARCHAR(100),
    description TEXT,
    status ENUM('available', 'low_stock', 'out_of_stock') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
);

-- Item Requests Table
CREATE TABLE IF NOT EXISTS item_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'fulfilled') NOT NULL DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    admin_notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Activity Log Table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert Default Admin User (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@parliament.gh', 'admin');

-- Insert Sample Staff User (password: staff123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('staff', '$2y$10$EfKPXqcRE9aEPyh0CaIhx.F8PBdY.jKB9lsZnXB5EkH7GcYCZGxHu', 'Staff Member', 'staff@parliament.gh', 'staff');

-- Insert Sample Categories
INSERT INTO categories (name, description) VALUES
('Office Furniture', 'Chairs, desks, cabinets, etc.'),
('Electronics', 'Computers, printers, scanners'),
('Stationery', 'Papers, pens, folders'),
('IT Equipment', 'Servers, networking equipment');

-- Insert Sample Suppliers
INSERT INTO suppliers (name, contact_person, email, phone) VALUES
('Ghana Office Supplies Ltd', 'John Mensah', 'john@gos.com', '+233 20 123 4567'),
('Tech Solutions Ghana', 'Mary Adjei', 'mary@techgh.com', '+233 24 987 6543');

-- Insert Sample Inventory Items
INSERT INTO inventory_items (item_name, item_code, category_id, supplier_id, quantity, min_quantity, unit_price, location) VALUES
('Office Chair - Executive', 'FURN-001', 1, 1, 50, 10, 450.00, 'Warehouse A'),
('HP Laptop - ProBook 450', 'ELEC-001', 2, 2, 25, 5, 3500.00, 'IT Store'),
('A4 Paper - 500 sheets', 'STAT-001', 3, 1, 200, 50, 25.00, 'Supply Room'),
('Network Switch - 24 Port', 'IT-001', 4, 2, 15, 3, 1200.00, 'Server Room');

-- Create triggers for auto-updating item status
DELIMITER $$

CREATE TRIGGER update_item_status_after_insert
AFTER INSERT ON inventory_items
FOR EACH ROW
BEGIN
    IF NEW.quantity = 0 THEN
        UPDATE inventory_items SET status = 'out_of_stock' WHERE id = NEW.id;
    ELSEIF NEW.quantity <= NEW.min_quantity THEN
        UPDATE inventory_items SET status = 'low_stock' WHERE id = NEW.id;
    ELSE
        UPDATE inventory_items SET status = 'available' WHERE id = NEW.id;
    END IF;
END$$

CREATE TRIGGER update_item_status_after_update
AFTER UPDATE ON inventory_items
FOR EACH ROW
BEGIN
    IF NEW.quantity = 0 THEN
        UPDATE inventory_items SET status = 'out_of_stock' WHERE id = NEW.id;
    ELSEIF NEW.quantity <= NEW.min_quantity THEN
        UPDATE inventory_items SET status = 'low_stock' WHERE id = NEW.id;
    ELSE
        UPDATE inventory_items SET status = 'available' WHERE id = NEW.id;
    END IF;
END$$

DELIMITER ;