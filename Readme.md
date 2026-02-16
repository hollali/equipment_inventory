# Equipment Inventory Management System

A comprehensive web-based inventory management system for tracking equipment, devices, and assets within an organization using **MySQLi** for database operations.

## 📋 Table of Contents

- [Features](#-features)
- [System Screenshots](#-system-screenshots)
- [File Structure](#-file-structure)
- [Installation](#-installation)
- [Database Schema](#-database-schema)
- [MySQLi Connection Examples](#-mysqli-connection-examples)
- [Usage Guide](#-usage-guide)
- [Technical Details](#-technical-details)
- [Security Features](#-security-features)
- [Troubleshooting](#-troubleshooting)
- [Future Enhancements](#-future-enhancements)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🖥️ System Screenshots

Here's a visual tour of the Equipment Inventory Management System:

### Dashboard Overview

![Dashboard Overview](images/dashboard.png)
_Real-time inventory monitoring and activity tracking dashboard with key metrics and recent activity_

### Inventory Management

![Inventory View](images/inventory.png)
_Complete inventory listing with quick actions, filtering capabilities, and device management_

### Unassigned & Stored Devices

![Unassigned Devices](images/03-unassigned-devices.png)
_Manage devices available for assignment with detailed filtering options_

### Device Assignment History

![Device History](images/device_history.png)
_Track the complete lifecycle and assignment history of all devices_

### Retired Devices Archive

![Retired Devices](images/retired_devices.png)
_Dedicated archive for decommissioned equipment with complete history_

### User Management

![User Management](images/users.png)
_Manage system users, roles, permissions, and access levels_

### Brand Management

![Brand Management](images/brands.png)
_Manage device brands and manufacturers with easy CRUD operations_

### Categories Management

![Categories](images/categories.png)
_Organize inventory by categories for better classification_

### Departments Management

![Departments](images/departments.png)
_Organize and manage company departments for device assignment_

### System Analytics

![System Analytics](images/reports.png)
_Monitor user activity, system performance, and role distribution_

### System Settings

![System Settings](images/settings.png)
_Configure system preferences, organization details, and backup options_

---

## 📋 Features

### 🏷️ Core Inventory Management

- **Device Tracking**: Track all equipment with detailed information including asset tag, brand, model, and specifications
- **Status Management**: Monitor device status (`active`, `in_storage`, `in_use`, `repairing`, `faulty`, `retired`)
- **Condition Tracking**: Track device condition (`Excellent`, `Good`, `Fair`, `Poor`, `New`, `Faulty`)
- **Assignment System**: Assign devices to users with complete history logging via `device_user_assignments`
- **Assignment History**: Complete audit trail of who had which device and when
- **Retirement System**: Properly retire devices with retirement date tracking

### 👥 User & Department Management

- **User Management**: Add, edit, and manage system users with first name, last name, email, and roles
- **Department Management**: Organize devices by departments (ICT, Library, PVC, etc.)
- **Role-based Access**: Pre-defined user roles (`admin`, `staff`, `mp`) with varying permissions
- **Active/Inactive Status**: Control user access to the system by toggling status (`active`, `inactive`)

### 🔧 Device Categories & Brands

- **Category Management**: Organize devices by type (Laptops, Projectors, Networking Equipment, etc.)
- **Brand Management**: Track device manufacturers (HP, Apple, Dell, Cisco, Lenovo, etc.)
- **Location Tracking**: Monitor device locations with location_id field

### 📊 Reporting & Analytics

- **Dashboard**: High-level overview of inventory statistics
- **Device History**: Complete assignment history through `device_user_assignments` table
- **Retired Devices**: Track decommissioned equipment with retirement dates
- **Export Functionality**: Export filtered data to CSV format
- **Activity Logging**: System activities tracked in `activity_log` table

### 🔍 Search & Filters

- **Global Search**: Search across device attributes
- **Advanced Filtering**: Filter by status, department, brand, category, and condition
- **Assignment Tracking**: Filter device assignments by user, date, and status

### 📱 User Interface

- **Responsive Design**: Fully functional on all devices
- **Modal Forms**: Clean modal-based forms for all CRUD operations
- **Toast Notifications**: User-friendly feedback messages
- **Interactive Elements**: Hover effects and visual feedback

---

## 📁 File Structure

```
equipment_inventory/
├── ajax/                          # AJAX handlers for real-time operations
│   ├── get_device_details.php     # Fetch device details for assignments
│   ├── search_devices.php         # Live search functionality
│   └── update_device_status.php   # Quick status updates
│
├── config/                        # Configuration files
│   ├── database.php               # MySQLi database connection settings
│   ├── database.example.php       # Example configuration template
│   └── database.sql               # Database schema dump
│
├── images/                         # Static images and screenshots
│   ├── 01-dashboard-overview.png
│   ├── 02-inventory-view.png
│   ├── 03-unassigned-devices.png
│   ├── 04-device-history.png
│   ├── 05-retired-devices.png
│   ├── 06-user-management.png
│   ├── 07-brand-management.png
│   ├── 08-categories.png
│   ├── 09-departments.png
│   ├── 10-system-analytics.png
│   └── 11-system-settings.png
│
├── includes/                       # Reusable PHP components
│   ├── auth.php                    # Authentication functions
│   ├── functions.php               # Helper functions
│   └── validation.php              # Input validation functions
│
├── brands.php                      # Brand management page
├── categories.php                  # Category management page
├── dashboard.php                   # Main dashboard with key metrics
├── departments.php                 # Department management page
├── device_history.php              # Device assignment history view
├── export_assignments.php          # Export assignments data to CSV
├── export_device_history.php       # Export device history to CSV
├── export_unassigned.php           # Export unassigned devices to CSV
├── export_users.php                # Export users data to CSV
├── footer.php                      # Site footer with common scripts
├── header.php                      # Site header with navigation
├── inventory.php                   # Main inventory management
├── login.php                       # User authentication page
├── logout.php                      # Logout handler
├── process_assign.php              # Backend handler for device assignments
├── reports.php                     # Reports and analytics center
├── retired_devices.php             # Retired devices archive
├── search_suggestions.php          # Endpoint for live search suggestions
├── settings.php                    # System settings configuration
├── sidebar.php                     # Persistent navigation sidebar
├── unassigned_devices.php          # Unassigned devices management
├── users.php                       # User management page
├── README.md                       # This documentation file
└── .gitignore                      # Git ignore rules
```

---

## 🚀 Installation

### Prerequisites

| Requirement   | Version                                    |
| ------------- | ------------------------------------------ |
| PHP           | 7.4 or higher                              |
| MySQL/MariaDB | 5.7 or higher (MariaDB 10.11+ recommended) |
| Web Server    | Apache/Nginx                               |
| Browser       | Chrome, Firefox, Safari, Edge              |

### Step-by-Step Installation

1. **Clone or download the project**

   ```bash
   git clone https://github.com/hollali/equipment-inventory.git
   cd equipment_inventory
   ```

2. **Create MySQL database**

   ```sql
   CREATE DATABASE device_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import database schema**

   ```bash
   # Using MySQL command line
   mysql -u your_username -p device_inventory < config/database.sql

   # Or import via phpMyAdmin
   # - Open phpMyAdmin
   # - Select your database
   # - Click Import
   # - Choose config/database.sql
   ```

4. **Configure database connection**

   ```bash
   # Copy the example configuration
   cp config/database.example.php config/database.php

   # Edit the file with your database credentials
   nano config/database.php
   ```

   ```php
   <?php
   // config/database.php - MySQLi version
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASSWORD', 'your_password');
   define('DB_NAME', 'device_inventory');
   define('DB_PORT', 3306); // Default MySQL port

   // Create connection
   $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

   // Check connection
   if (mysqli_connect_errno()) {
       die("Database connection failed: " . mysqli_connect_error());
   }

   // Set charset to UTF-8
   mysqli_set_charset($conn, "utf8mb4");

   // Set timezone
   date_default_timezone_set('Africa/Accra');
   ```

5. **Set proper permissions**

   ```bash
   # For Linux/Unix systems
   chmod 755 -R equipment_inventory/
   chmod 644 equipment_inventory/config/database.php
   ```

6. **Create default admin user**

   ```sql
   INSERT INTO users (firstname, lastname, email, role, status)
   VALUES ('Admin', 'User', 'admin@parliament.gov.gh', 'admin', 'active');
   ```

   > **Note**: Password functionality would need to be added to the users table or authentication system.

7. **Access the system**
   - Open your browser and navigate to: `http://localhost/equipment_inventory/`
   - Login page would need to be implemented

---

## 🗄️ Database Schema

### Entity Relationship Diagram

```
+----------------+       +----------------------+       +---------------+
|    brands      |       |   inventory_items    |       |  categories   |
+----------------+       +----------------------+       +---------------+
| id (PK)        |<------| brand_id             |       | id (PK)       |
| brand_name     |       | category_id          |------>| category_name |
+----------------+       | id (PK)              |       | created_at    |
                         | asset_tag (unique)   |       +---------------+
+----------------+       | device_type          |
|   users        |       | model                |       +---------------+
+----------------+       | serial_number(unique)|       |  departments  |
| id (PK)        |       | specifications       |       +---------------+
| firstname      |       | condition            |       | id (PK)       |
| lastname       |       | status               |       | department_name|
| email (unique) |<------| assigned_user        |       | department_code|
| role           |       | department_id         |------>|               |
| status         |       | location_id           |       +---------------+
+----------------+       | retired_at           |
                         | created_at           |
                         | updated_at           |
                         +----------------------+
                                  |
                                  |
                         +--------v--------+
                         | device_assignments (History)
                         +-----------------+
                         | id (PK)         |
                         | inventory_id(FK)|
                         | user_id (FK)    |
                         | assigned_at     |
                         | returned_at     |
                         | status          |
                         +-----------------+
```

### Table Definitions

#### 1. **users** - System users

```sql
CREATE TABLE users (
    id int(11) NOT NULL,
    firstname varchar(50) NOT NULL,
    lastname varchar(100) NOT NULL,
    email varchar(100) NOT NULL,
    role enum('admin','staff','mp') NOT NULL DEFAULT 'staff',
    status enum('active','inactive') NOT NULL DEFAULT 'active',
    created_at timestamp NULL DEFAULT current_timestamp(),
    updated_at timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 2. **brands** - Device manufacturers

```sql
CREATE TABLE brands (
    id int(10) UNSIGNED NOT NULL,
    brand_name varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 3. **categories** - Device categories

```sql
CREATE TABLE categories (
    id int(11) NOT NULL,
    category_name varchar(100) NOT NULL,
    created_at timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 4. **departments** - Organization departments

```sql
CREATE TABLE departments (
    id int(11) NOT NULL,
    department_name varchar(255) NOT NULL,
    department_code varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 5. **inventory_items** - Core device inventory

```sql
CREATE TABLE inventory_items (
    id int(11) NOT NULL,
    asset_tag varchar(50) NOT NULL,
    device_type varchar(100) NOT NULL,
    model varchar(100) DEFAULT NULL,
    serial_number varchar(100) DEFAULT NULL,
    specifications text DEFAULT NULL,
    assigned_user varchar(100) DEFAULT NULL,
    condition enum('Excellent','Good','Fair','Poor','New','Faulty') NOT NULL DEFAULT 'Good',
    remarks text DEFAULT NULL,
    created_at timestamp NULL DEFAULT current_timestamp(),
    updated_at timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    category_id int(11) DEFAULT NULL,
    department_id int(10) DEFAULT NULL,
    location_id int(10) DEFAULT NULL,
    brand_id int(10) DEFAULT NULL,
    status enum('active','in_storage','in_use','repairing','faulty','retired') NOT NULL DEFAULT 'active',
    retired_at datetime DEFAULT NULL,
    previous_status varchar(50) DEFAULT NULL,
    previous_assigned_user varchar(255) DEFAULT NULL,
    previous_department_id int(11) DEFAULT NULL,
    previous_location_id int(11) DEFAULT NULL,
    change_notes text DEFAULT NULL,
    UNIQUE KEY asset_tag (asset_tag),
    UNIQUE KEY serial_number (serial_number),
    KEY category_id (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 6. **device_user_assignments** - Assignment history

```sql
CREATE TABLE device_user_assignments (
    id int(11) NOT NULL,
    inventory_id int(11) NOT NULL,
    user_id int(11) NOT NULL,
    assigned_at datetime DEFAULT current_timestamp(),
    returned_at datetime DEFAULT NULL,
    status enum('assigned','retrieved') DEFAULT 'assigned',
    KEY inventory_id (inventory_id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

#### 7. **activity_log** - System activity tracking

```sql
CREATE TABLE activity_log (
    id int(11) NOT NULL,
    user_id int(11) NOT NULL,
    action varchar(50) NOT NULL,
    description text DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    created_at timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 8. **settings** - System configuration

```sql
CREATE TABLE settings (
    id int(11) NOT NULL,
    setting_key varchar(100) NOT NULL,
    setting_value text DEFAULT NULL,
    setting_type enum('text','number','boolean','select') DEFAULT 'text',
    category enum('organization','inventory','system') DEFAULT 'system',
    label varchar(200) DEFAULT NULL,
    description text DEFAULT NULL,
    options text DEFAULT NULL,
    created_at timestamp NULL DEFAULT current_timestamp(),
    updated_at timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    UNIQUE KEY setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
```

---

## 🔌 MySQLi Connection Examples

### 1. **Basic Database Connection**

```php
<?php
// config/database.php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'device_inventory';

// Create connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");
?>
```

### 2. **Reusable Connection Function**

```php
<?php
// includes/db_functions.php
function getDbConnection() {
    static $conn = null;

    if ($conn === null) {
        $host = 'localhost';
        $user = 'root';
        $password = '';
        $database = 'device_inventory';

        $conn = mysqli_connect($host, $user, $password, $database);

        if (!$conn) {
            error_log("Database connection failed: " . mysqli_connect_error());
            return false;
        }

        mysqli_set_charset($conn, "utf8mb4");
    }

    return $conn;
}
?>
```

### 3. **Get Dashboard Statistics**

```php
<?php
// dashboard.php - Get statistics
require_once 'config/database.php';

// Total devices
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory_items");
$total_devices = mysqli_fetch_assoc($result)['total'];

// Devices in use
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory_items WHERE status = 'in_use'");
$in_use = mysqli_fetch_assoc($result)['total'];

// Devices in storage
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory_items WHERE status = 'in_storage'");
$in_storage = mysqli_fetch_assoc($result)['total'];

// Retired devices
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventory_items WHERE status = 'retired'");
$retired = mysqli_fetch_assoc($result)['total'];

// Total users
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$active_users = mysqli_fetch_assoc($result)['total'];
?>
```

### 4. **Get Device Assignment History with JOINs**

```php
<?php
// Get assignment history for a specific device
function getDeviceHistory($conn, $device_id) {
    $sql = "SELECT dua.*,
                   u.firstname, u.lastname, u.email,
                   i.asset_tag, i.device_type
            FROM device_user_assignments dua
            JOIN users u ON dua.user_id = u.id
            JOIN inventory_items i ON dua.inventory_id = i.id
            WHERE dua.inventory_id = ?
            ORDER BY dua.assigned_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $device_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }

    return $history;
}

// Usage
$history = getDeviceHistory($conn, 17);
foreach ($history as $assignment) {
    echo $assignment['firstname'] . " " . $assignment['lastname'] .
         " - Assigned: " . $assignment['assigned_at'] . "<br>";
}
?>
```

### 5. **Assign Device to User with Transaction**

```php
<?php
// process_assign.php - Assign device with transaction
require_once 'config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inventory_id = (int)$_POST['device_id'];
    $user_id = (int)$_POST['user_id'];

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Update device status and assigned user
        $sql1 = "UPDATE inventory_items SET
                 status = 'in_use',
                 assigned_user = (SELECT CONCAT(firstname, ' ', lastname) FROM users WHERE id = ?)
                 WHERE id = ?";

        $stmt1 = mysqli_prepare($conn, $sql1);
        mysqli_stmt_bind_param($stmt1, "ii", $user_id, $inventory_id);
        mysqli_stmt_execute($stmt1);

        // Log assignment
        $sql2 = "INSERT INTO device_user_assignments (inventory_id, user_id, status)
                 VALUES (?, ?, 'assigned')";

        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "ii", $inventory_id, $user_id);
        mysqli_stmt_execute($stmt2);

        // Commit transaction
        mysqli_commit($conn);

        echo json_encode(['success' => true, 'message' => 'Device assigned successfully']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Assignment failed: ' . $e->getMessage()]);
    }
}
?>
```

### 6. **Return Device (Unassign)**

```php
<?php
// process_return.php - Return device
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $assignment_id = (int)$_POST['assignment_id'];
    $inventory_id = (int)$_POST['device_id'];

    mysqli_begin_transaction($conn);

    // Update assignment record
    $sql1 = "UPDATE device_user_assignments
             SET returned_at = NOW(), status = 'retrieved'
             WHERE id = ?";

    $stmt1 = mysqli_prepare($conn, $sql1);
    mysqli_stmt_bind_param($stmt1, "i", $assignment_id);
    mysqli_stmt_execute($stmt1);

    // Update device status
    $sql2 = "UPDATE inventory_items
             SET status = 'in_storage', assigned_user = NULL
             WHERE id = ?";

    $stmt2 = mysqli_prepare($conn, $sql2);
    mysqli_stmt_bind_param($stmt2, "i", $inventory_id);
    mysqli_stmt_execute($stmt2);

    if (mysqli_commit($conn)) {
        echo json_encode(['success' => true]);
    } else {
        mysqli_rollback($conn);
        echo json_encode(['success' => false]);
    }
}
?>
```

### 7. **Retire Device**

```php
<?php
// retire_device.php
require_once 'config/database.php';

function retireDevice($conn, $device_id, $notes = '') {
    // Get current device data for history
    $sql_select = "SELECT status, assigned_user, department_id, location_id
                   FROM inventory_items WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql_select);
    mysqli_stmt_bind_param($stmt, "i", $device_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current = mysqli_fetch_assoc($result);

    // Update to retired
    $sql_update = "UPDATE inventory_items SET
                   status = 'retired',
                   retired_at = NOW(),
                   previous_status = ?,
                   previous_assigned_user = ?,
                   previous_department_id = ?,
                   previous_location_id = ?,
                   change_notes = ?
                   WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt, "ssiiss",
        $current['status'],
        $current['assigned_user'],
        $current['department_id'],
        $current['location_id'],
        $notes,
        $device_id
    );

    return mysqli_stmt_execute($stmt);
}

// Usage
if (retireDevice($conn, 10, 'End of life - replaced with new model')) {
    echo "Device retired successfully";
}
?>
```

### 8. **Search with Multiple Filters**

```php
<?php
// Search devices with dynamic filters
function searchInventory($conn, $filters = []) {
    $sql = "SELECT i.*, b.brand_name, c.category_name, d.department_name
            FROM inventory_items i
            LEFT JOIN brands b ON i.brand_id = b.id
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN departments d ON i.department_id = d.id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($filters['search'])) {
        $sql .= " AND (i.asset_tag LIKE ? OR i.model LIKE ? OR i.serial_number LIKE ?)";
        $search_term = "%" . $filters['search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "sss";
    }

    if (!empty($filters['status'])) {
        $sql .= " AND i.status = ?";
        $params[] = $filters['status'];
        $types .= "s";
    }

    if (!empty($filters['category_id'])) {
        $sql .= " AND i.category_id = ?";
        $params[] = $filters['category_id'];
        $types .= "i";
    }

    if (!empty($filters['brand_id'])) {
        $sql .= " AND i.brand_id = ?";
        $params[] = $filters['brand_id'];
        $types .= "i";
    }

    $sql .= " ORDER BY i.created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);

    return mysqli_stmt_get_result($stmt);
}

// Usage
$filters = [
    'search' => 'MacBook',
    'status' => 'in_use',
    'category_id' => 15
];
$results = searchInventory($conn, $filters);
?>
```

### 9. **Add Activity Log Entry**

```php
<?php
// Log user activity
function logActivity($conn, $user_id, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $sql = "INSERT INTO activity_log (user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isss", $user_id, $action, $description, $ip);

    return mysqli_stmt_execute($stmt);
}

// Usage
logActivity($conn, 1, 'DEVICE_ASSIGN', 'Assigned MacBook Pro to John Doe');
?>
```

### 10. **Get System Settings**

```php
<?php
// Get setting value
function getSetting($conn, $key, $default = '') {
    $sql = "SELECT setting_value FROM settings WHERE setting_key = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }

    return $default;
}

// Update setting
function updateSetting($conn, $key, $value) {
    $sql = "INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $key, $value);

    return mysqli_stmt_execute($stmt);
}

// Usage
$org_name = getSetting($conn, 'org_name', 'Parliament of Ghana');
updateSetting($conn, 'auto_refresh', '1');
?>
```

---

## 📈 Usage Guide

### Quick Start Guide

#### 1. **Adding a New Device**

```php
<?php
// add_device.php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $asset_tag = mysqli_real_escape_string($conn, $_POST['asset_tag']);
    $device_type = mysqli_real_escape_string($conn, $_POST['device_type']);
    $brand_id = (int)$_POST['brand_id'];
    $category_id = (int)$_POST['category_id'];

    $sql = "INSERT INTO inventory_items (asset_tag, device_type, brand_id, category_id, status)
            VALUES (?, ?, ?, ?, 'in_storage')";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssii", $asset_tag, $device_type, $brand_id, $category_id);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Device added successfully";
        header("Location: inventory.php");
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}
?>
```

#### 2. **Viewing Device Assignment History**

```sql
-- Get complete assignment history for all devices
SELECT
    i.asset_tag,
    i.device_type,
    CONCAT(u.firstname, ' ', u.lastname) AS user_name,
    dua.assigned_at,
    dua.returned_at,
    dua.status AS assignment_status
FROM device_user_assignments dua
JOIN inventory_items i ON dua.inventory_id = i.id
JOIN users u ON dua.user_id = u.id
ORDER BY dua.assigned_at DESC;
```

#### 3. **Dashboard Widget: Recent Activity**

```php
<?php
// Get recent activity
$sql = "SELECT al.*, CONCAT(u.firstname, ' ', u.lastname) AS user_name
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 10";

$result = mysqli_query($conn, $sql);
while ($activity = mysqli_fetch_assoc($result)) {
    echo '<div class="activity-item">';
    echo '<strong>' . htmlspecialchars($activity['user_name']) . '</strong> ';
    echo '<span class="action">' . htmlspecialchars($activity['action']) . '</span>';
    echo '<p>' . htmlspecialchars($activity['description']) . '</p>';
    echo '<small>' . $activity['created_at'] . '</small>';
    echo '</div>';
}
?>
```

---

## 🛠️ Technical Details

### Technology Stack

| Layer                  | Technology              | Purpose                     |
| ---------------------- | ----------------------- | --------------------------- |
| **Frontend**           | HTML5, CSS3, JavaScript | Structure and interactivity |
| **Styling**            | Tailwind CSS            | Responsive design framework |
| **Icons**              | Font Awesome 6          | UI icons and symbols        |
| **JavaScript**         | jQuery 3.6+             | DOM manipulation and AJAX   |
| **Dropdowns**          | Select2                 | Enhanced select boxes       |
| **Backend**            | PHP 7.4+                | Server-side logic           |
| **Database**           | MySQL/MariaDB           | Data persistence            |
| **Database Extension** | MySQLi                  | MySQL improved extension    |

### Database Relationships

| Relationship                                  | Type        | Description                                   |
| --------------------------------------------- | ----------- | --------------------------------------------- |
| `inventory_items` → `brands`                  | Many-to-One | Each device belongs to one brand              |
| `inventory_items` → `categories`              | Many-to-One | Each device belongs to one category           |
| `inventory_items` → `departments`             | Many-to-One | Each device can be assigned to one department |
| `device_user_assignments` → `inventory_items` | Many-to-One | Each assignment record belongs to one device  |
| `device_user_assignments` → `users`           | Many-to-One | Each assignment record belongs to one user    |
| `activity_log` → `users`                      | Many-to-One | Each activity log belongs to one user         |

---

## 🔒 Security Features

### Implemented Security Measures

1. **Input Validation & Sanitization**

   ```php
   // Cast to integer
   $id = (int)$_GET['id'];

   // Escape strings
   $asset_tag = mysqli_real_escape_string($conn, $_POST['asset_tag']);

   // Validate email
   if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
       die("Invalid email format");
   }
   ```

2. **SQL Injection Prevention**

   ```php
   // Using prepared statements
   $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
   mysqli_stmt_bind_param($stmt, "s", $email);
   mysqli_stmt_execute($stmt);
   ```

3. **XSS Protection**

   ```php
   // Output escaping
   echo htmlspecialchars($device_name, ENT_QUOTES, 'UTF-8');
   ```

4. **CSRF Protection** (Recommended to add)

   ```php
   // Generate token
   $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

   // Verify token
   if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
       die('Invalid CSRF token');
   }
   ```

---

## 🚨 Troubleshooting

### Common MySQLi Issues

#### 1. **Connection Failed**

**Error:** `mysqli_connect(): (HY000/1045): Access denied`
**Solution:**

```php
// Enable error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Check connection
if (mysqli_connect_errno()) {
    die("Connection failed: " . mysqli_connect_error());
}
```

#### 2. **Character Set Issues**

**Problem:** Special characters showing as ?
**Solution:**

```php
// Set charset after connection
mysqli_set_charset($conn, "utf8mb4");

// Also set in database connection
mysqli_options($conn, MYSQLI_SET_CHARSET_NAME, "utf8mb4");
```

#### 3. **Prepared Statement Parameter Mismatch**

**Error:** `Number of variables doesn't match number of parameters`
**Solution:**

```php
// Count your parameters
$param_count = substr_count($sql, '?');
echo "Need $param_count parameters";

// Debug by printing
var_dump($params);
```

---

## 🔮 Future Enhancements

### Planned Features

| Priority  | Feature                      | Description                            |
| --------- | ---------------------------- | -------------------------------------- |
| 🚀 High   | **Authentication System**    | Add login/logout with password hashing |
| 🚀 High   | **User Roles & Permissions** | Implement role-based access control    |
| 📊 Medium | **Advanced Reports**         | Generate PDF reports with charts       |
| 📱 Medium | **Bulk Import/Export**       | CSV import/export functionality        |
| 🔔 Medium | **Email Notifications**      | Alert users on device assignment       |
| 📱 Low    | **Mobile App**               | React Native mobile application        |

---

## 🤝 Contributing

### Coding Standards

```php
<?php
/**
 * Get device by ID with related data
 *
 * @param mysqli $conn Database connection
 * @param int $id Device ID
 * @return array|null Device data or null if not found
 */
function getDeviceById($conn, $id) {
    $sql = "SELECT i.*, b.brand_name, c.category_name, d.department_name
            FROM inventory_items i
            LEFT JOIN brands b ON i.brand_id = b.id
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN departments d ON i.department_id = d.id
            WHERE i.id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($result);
}
?>
```

---

## 📄 License

MIT License

Copyright (c) 2026 Parliamentary Service of Ghana

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

---

## 📞 Support

### Contact Information

- **Project Maintainer**: ICT Directorate
- **Email**: ict@parliament.gov.gh
- **Database**: MariaDB 10.11+ / MySQL 5.7+

### Quick MySQLi Reference

```php
// Connect
$conn = mysqli_connect($host, $user, $pass, $db);

// Simple query
$result = mysqli_query($conn, "SELECT * FROM table");
while($row = mysqli_fetch_assoc($result)) { }

// Prepared statement
$stmt = mysqli_prepare($conn, "INSERT INTO table (col) VALUES (?)");
mysqli_stmt_bind_param($stmt, "s", $value);
mysqli_stmt_execute($stmt);

// Get last ID
$id = mysqli_insert_id($conn);

// Affected rows
$count = mysqli_affected_rows($conn);

// Close connection
mysqli_close($conn);
```

---

**Built with ❤️ using MySQLi by the ICT Directorate, Parliamentary Service of Ghana**

_Last updated: February 16, 2026_
