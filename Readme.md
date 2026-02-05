# Equipment Inventory Management System

A comprehensive web-based inventory management system for tracking equipment, devices, and assets within an organization.

## 📋 Features

### 🏷️ Core Inventory Management

- **Device Tracking**: Track all equipment with detailed information (asset tag, brand, model, specifications)
- **Status Management**: Monitor device status (In Use, Store, Faulty, Retired)
- **Condition Tracking**: Track device condition (New, Good, Fair, Faulty)
- **Assignment System**: Assign devices to users and departments
- **Location Tracking**: Monitor device locations across the organization

### 👥 User & Department Management

- **User Management**: Add, edit, and manage system users
- **Department Management**: Organize devices by departments
- **Role-based Access**: User roles and permissions
- **Active/Inactive Status**: Manage user availability

### 🔧 Device Categories & Brands

- **Category Management**: Organize devices by type (laptops, phones, servers, etc.)
- **Brand Management**: Track device manufacturers
- **Custom Categories**: Create and manage custom device categories

### 📊 Reporting & Analytics

- **Dashboard**: Overview of inventory statistics and key metrics
- **Device History**: Track assignment history and device lifecycle
- **Retired Devices**: Archive for retired equipment
- **Export Functionality**: Export data to various formats

### 🔍 Search & Filters

- **Advanced Search**: Search across all device attributes
- **Filter System**: Filter devices by status, department, brand, category, condition
- **Quick Search**: Real-time search suggestions

### 📱 User Interface

- **Responsive Design**: Works on desktop and mobile devices
- **Dual View Mode**: Table view and card view for device listings
- **Modal Forms**: Clean, modern modal-based forms for all actions
- **Toast Notifications**: User-friendly feedback messages
- **Interactive Elements**: Hover effects, animations, and visual feedback

## 📁 File Structure

```
equipment_inventory/
├── ajax/                          # AJAX handlers for real-time operations
├── config/                        # Configuration files (database, settings)
├── images/                        # Static images and icons
├── vendor/                        # Composer dependencies
├── brands.php                     # Brand management
├── categories.php                 # Category management
├── dashboard.php                  # Main dashboard
├── departments.php                # Department management
├── device_history.php             # Device assignment history
├── export_assignments.php         # Export assignments data
├── export_device_history.php      # Export device history
├── export_unassigned.php          # Export unassigned devices
├── export_users.php               # Export users data
├── footer.php                     # Site footer
├── inventory.php                  # Main inventory management
├── process_assign.php             # Process device assignments
├── reports.php                    # Reports and analytics
├── retired_devices.php            # Retired devices archive
├── search_suggestions.php         # Search functionality
├── settings.php                   # System settings
├── sidebar.php                    # Navigation sidebar
├── unassigned_devices.php         # Unassigned & stored devices management
├── users.php                      # User management
├── composer.json                  # PHP dependencies
├── composer.lock                  # Locked dependencies
├── Readme.md                      # This documentation file
└── .gitignore                     # Git ignore rules
```

## 🚀 Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, or built-in PHP server)
- Composer (for dependency management)

### Setup Instructions

1. **Clone or download the project**

   ```bash
   git clone [repository-url]
   cd equipment_inventory
   ```

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Database Setup**
   - Create a MySQL database
   - Import the database schema from `config/database.sql` (if available)
   - Or run the SQL queries to create necessary tables

4. **Configuration**
   - Copy `config/database.example.php` to `config/database.php`
   - Update database credentials in `config/database.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'your_database_name');
     define('DB_USER', 'your_username');
     define('DB_PASSWORD', 'your_password');
     ```

5. **Web Server Configuration**
   - Point your web server to the project directory
   - Ensure PHP is configured to display errors during development

6. **Initial Setup**
   - Access the system via your web browser
   - Default admin credentials may need to be set in the database
   - Start by adding departments, users, and device categories

## 🗄️ Database Schema

### Main Tables

- **inventory_items**: Core device inventory
- **users**: System users who can be assigned devices
- **departments**: Organizational departments
- **categories**: Device categories
- **brands**: Device manufacturers
- **device_user_assignments**: Device assignment history (if using separate table)

### Key Fields in inventory_items

- `asset_tag`: Unique identifier for each device
- `device_type`: Type of device (laptop, phone, etc.)
- `brand_id`, `model`: Device make and model
- `specifications`: Technical specifications
- `department_id`: Assigned department
- `assigned_user`: Currently assigned user
- `location`: Physical location
- `condition`: Device condition (New, Good, Fair, Faulty)
- `status`: Current status (In Use, Store, Faulty, Retired)
- `remarks`: Additional notes
- `created_at`, `update_at`: Timestamps

## 🔒 Security Features

- **Session Management**: PHP sessions for user authentication
- **Input Sanitization**: Protection against SQL injection and XSS
- **Parameterized Queries**: Prepared statements for database operations
- **Role-based Access**: Different permissions for different user roles
- **Error Handling**: Custom error messages without exposing system details

## 📈 Usage Guide

### Adding a New Device

1. Navigate to Inventory Management
2. Click "Add New Device"
3. Fill in device details (asset tag, brand, model, specifications)
4. Set initial status and condition
5. Assign to department and user (optional)

### Assigning a Device

1. Go to Unassigned & Stored Devices
2. Find the device to assign
3. Click the assign button
4. Select user and department
5. Update status and condition as needed
6. Add remarks (optional)

### Managing Users

1. Navigate to Users
2. Add new users with name, email, and role
3. Set user status (active/inactive)
4. Edit existing users as needed

### Generating Reports

1. Go to Reports section
2. Select report type (assignments, inventory, etc.)
3. Apply filters as needed
4. View or export the report

## 🛠️ Technical Details

### Technologies Used

- **Backend**: PHP 7.4+, MySQL
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Styling**: Tailwind CSS
- **JavaScript Libraries**: jQuery, Select2, Font Awesome
- **Architecture**: Server-side rendered with AJAX enhancements

### Key PHP Features

- Object-oriented database connection
- Prepared statements for security
- Session-based authentication
- Modular code structure
- Comprehensive error handling

### Frontend Features

- Responsive design with Tailwind CSS
- Interactive modals for forms
- Real-time search and filtering
- Toast notifications for user feedback
- Dual view mode (table/card)
- Select2 for enhanced dropdowns

## 🔄 Workflow

1. **Device Acquisition**
   - Device is added to inventory
   - Assigned unique asset tag
   - Initial status set (usually "Store")

2. **Device Assignment**
   - Device is assigned to user and department
   - Status updated to "In Use"
   - Assignment recorded in history

3. **Device Maintenance**
   - Condition can be updated as device ages
   - Status can change (Faulty, Repairing, etc.)
   - Reassignment to different users as needed

4. **Device Retirement**
   - Device marked as "Retired"
   - Moved to retired devices archive
   - Historical data preserved

## 📱 Mobile Compatibility

The system is fully responsive and works on:

- Desktop computers
- Tablets
- Mobile phones
- Any device with a modern web browser

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in config/database.php
   - Ensure MySQL is running
   - Check user permissions

2. **Blank Page or PHP Errors**
   - Enable error reporting in PHP configuration
   - Check PHP version (requires 7.4+)
   - Verify all required extensions are enabled

3. **JavaScript Not Working**
   - Check browser console for errors
   - Ensure jQuery is loading properly
   - Verify internet connection for CDN resources

4. **Session Issues**
   - Check PHP session configuration
   - Ensure cookies are enabled in browser
   - Verify session save path permissions

### Development Tips

- Enable `display_errors` during development
- Use browser developer tools for debugging
- Check PHP error logs for server-side issues
- Test with different user roles

## 🔮 Future Enhancements

Potential features for future versions:

- **Barcode/QR Code Support**: Scan asset tags with mobile devices
- **API Integration**: REST API for external systems
- **Email Notifications**: Automated alerts for assignments
- **Bulk Operations**: Import/export multiple devices
- **Dashboard Widgets**: Customizable dashboard
- **Advanced Reporting**: Charts and graphs
- **Mobile App**: Native mobile application
- **LDAP/Active Directory Integration**: User authentication

## 📄 License

[Specify your license here - e.g., MIT, GPL, etc.]

## 👥 Support

For support, issues, or feature requests:

- [Create an issue on GitHub]
- [Contact system administrator]
- [Refer to documentation]

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📊 System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- 100MB disk space (minimum)
- Modern web browser
- Internet connection (for CDN resources)

---

_Last updated: [Current Date]_
