-- =========================
-- Database: parliament_inventory
-- =========================

-- Create Database
CREATE DATABASE IF NOT EXISTS parliament_inventory;
USE parliament_inventory;

-- =========================
-- Users Table
-- =========================
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

-- Insert Default Users
-- Passwords are bcrypt hashed (admin123 / staff123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@parliament.gh', 'admin'),
('staff', '$2y$10$EfKPXqcRE9aEPyh0CaIhx.F8PBdY.jKB9lsZnXB5EkH7GcYCZGxHu', 'Staff Member', 'staff@parliament.gh', 'staff');

-- =========================
-- Categories Table
-- =========================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample Categories
INSERT INTO categories (category_name, description) VALUES
('Office Furniture', 'Chairs, desks, cabinets, etc.'),
('Electronics', 'Computers, printers, scanners'),
('Stationery', 'Papers, pens, folders'),
('IT Equipment', 'Servers, networking equipment');

-- =========================
-- Inventory Items Table (Asset Tracking)
-- =========================
CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_tag VARCHAR(50) UNIQUE NOT NULL,                
    device_type VARCHAR(100) NOT NULL,                    
    brand VARCHAR(100),                                   
    model VARCHAR(100),                                   
    serial_number VARCHAR(100) UNIQUE,                    
    specifications TEXT,                                  
    department VARCHAR(100),                               
    assigned_user VARCHAR(100),                            
    location VARCHAR(100),                                 
    condition ENUM('New', 'Good', 'Fair', 'Poor', 'Faulty') DEFAULT 'Good',  
    status ENUM('In Use', 'Store', 'Faulty') DEFAULT 'Store',                 
    remarks TEXT,                                         
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,      
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
    category_id INT,                                     
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Sample Inventory Items
INSERT INTO inventory_items (
    asset_tag, device_type, brand, model, serial_number, specifications, department, assigned_user, location, condition, status, remarks, category_id
) VALUES
('AST-001', 'Laptop', 'HP', 'ProBook 450 G8', 'SN123456789', '16GB RAM, 512GB SSD, i5 Processor', 'IT', 'John Mensah', 'IT Store', 'New', 'In Use', 'Issued to IT staff', 2),
('AST-002', 'Office Chair', 'IKEA', 'Markus', 'CH987654321', 'Ergonomic chair with lumbar support', 'HR', 'Mary Adjei', 'Warehouse A', 'Good', 'In Use', 'Assigned to HR department', 1),
('AST-003', 'Projector', 'Epson', 'EB-X05', 'PJ1122334455', '3LCD, 3300 lumens, XGA resolution', 'Conference', 'Nana Owusu', 'Conference Room 1', 'Good', 'In Use', '', 2),
('AST-004', 'A4 Paper Pack', 'Staples', '500 sheets', 'PP5566778899', '500 sheets per pack, 80gsm', 'Admin', '', 'Supply Room', 'New', 'Store', 'For general office use', 3),
('AST-005', 'Network Switch', 'Cisco', 'Catalyst 2960', 'SW9988776655', '24-Port, Gigabit Ethernet', 'IT', 'Kwame Boateng', 'Server Room', 'Good', 'In Use', '', 4);

-- =========================
-- Item Requests Table
-- =========================
CREATE TABLE IF NOT EXISTS item_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
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

-- =========================
-- Activity Log Table
-- =========================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
