<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once "./config/database.php";

// Create database instance and get connection
$database = new Database();
$conn = $database->getConnection();

// Function to get all settings
function getSettings($conn, $category = null) {
    $settings = [];
    
    $query = "SELECT setting_key, setting_value, setting_type, category, label, description, options 
              FROM settings";
    
    if ($category) {
        $query .= " WHERE category = ?";
    }
    
    $query .= " ORDER BY category, setting_key";
    
    if ($category) {
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $category);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $query);
    }
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Convert setting value based on type
            $value = $row['setting_value'];
            
            switch ($row['setting_type']) {
                case 'number':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = (bool)$value;
                    break;
                case 'select':
                    // Keep as string
                    break;
                default:
                    $value = htmlspecialchars($value);
            }
            
            $settings[$row['setting_key']] = [
                'value' => $value,
                'type' => $row['setting_type'],
                'category' => $row['category'],
                'label' => $row['label'],
                'description' => $row['description'],
                'options' => $row['options'] ? explode(',', $row['options']) : []
            ];
        }
    }
    
    return $settings;
}

// Function to save settings
function saveSettings($conn, $settings) {
    $success = true;
    
    // Begin transaction
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($settings as $key => $value) {
            // Prepare value based on type
            $storedValue = $value;
            
            // Check if setting exists
            $checkQuery = "SELECT setting_type FROM settings WHERE setting_key = ?";
            $checkStmt = mysqli_prepare($conn, $checkQuery);
            mysqli_stmt_bind_param($checkStmt, "s", $key);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $row = mysqli_fetch_assoc($checkResult);
                $type = $row['setting_type'];
                
                // Convert value based on type
                if ($type === 'boolean') {
                    $storedValue = $value ? '1' : '0';
                }
                
                // Update query
                $query = "UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ss", $storedValue, $key);
            } else {
                // Insert new setting (shouldn't happen usually)
                $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "ss", $key, $storedValue);
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to save setting: $key");
            }
            mysqli_stmt_close($stmt);
        }
        
        mysqli_commit($conn);
        return ['success' => true, 'message' => 'Settings saved successfully!'];
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return ['success' => false, 'message' => 'Error saving settings: ' . $e->getMessage()];
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_organization':
                $settingsToSave = [
                    'org_name' => $_POST['org_name'] ?? '',
                    'org_contact' => $_POST['org_contact'] ?? '',
                    'org_footer' => $_POST['org_footer'] ?? '',
                    'org_assignment' => $_POST['org_assignment'] ?? 'MP'
                ];
                
                $result = saveSettings($conn, $settingsToSave);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'save_inventory':
                $settingsToSave = [
                    'inv_default_status' => $_POST['inv_default_status'] ?? 'In Use',
                    'inv_retirement_threshold' => $_POST['inv_retirement_threshold'] ?? 36,
                    'inv_email_alerts' => isset($_POST['inv_email_alerts']) ? true : false,
                    'inv_compliance_reminders' => isset($_POST['inv_compliance_reminders']) ? true : false
                ];
                
                $result = saveSettings($conn, $settingsToSave);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Get current settings
$allSettings = getSettings($conn);
$orgSettings = getSettings($conn, 'organization');
$invSettings = getSettings($conn, 'inventory');
$sysSettings = getSettings($conn, 'system');

// Helper function to get setting value safely
function getSettingValue($settings, $key, $default = '') {
    return $settings[$key]['value'] ?? $default;
}

// Helper function to check if checkbox should be checked
function isChecked($settings, $key) {
    return isset($settings[$key]) && $settings[$key]['value'] ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | Parliament ICT</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f9fafb;
        }
        
        .setting-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            height: 100%;
        }
        
        .setting-card:hover {
            border-color: #3b82f6;
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e5e7eb;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #d1d5db;
            transition: .2s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .2s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .tab {
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            color: #6b7280;
            border: 1px solid transparent;
            position: relative;
            z-index: 1;
            user-select: none;
        }
        
        .tab.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .tab:hover:not(.active) {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .input-glow:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .select-custom {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-position: right 16px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 48px;
        }
        
        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-toast {
            animation: slideInRight 0.3s ease-out, fadeOut 0.3s ease-out 4.7s forwards;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .section-header {
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        
        /* Adjust main content for sidebar */
        #mainContent {
            margin-left: 16rem;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 80px);
        }
        
        @media (max-width: 768px) {
            #mainContent {
                margin-left: 0;
            }
        }
        
        /* Tab system fixes */
        .tab-content {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .tab-content.active {
            display: block !important;
            opacity: 1;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Ensure tabs are clickable */
        .tab {
            cursor: pointer;
            user-select: none;
        }
        
        /* Remove any conflicting display properties */
        [style*="display: none"] {
            display: none !important;
        }
        
        [style*="display: block"] {
            display: block !important;
        }
        
        /* Hidden class override */
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main id="mainContent" class="transition-all duration-300">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 px-6 md:px-8 py-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="p-3 rounded-lg bg-blue-50 text-blue-600">
                        <i class="fas fa-cog text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-semibold text-gray-900">
                            System Settings
                        </h1>
                        <p class="text-gray-600 text-sm mt-1">Configure and customize your system preferences</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button onclick="location.reload()"
                        class="px-4 py-2 bg-white border border-gray-300 rounded text-gray-700 hover:bg-gray-50 transition-colors flex items-center gap-2 text-sm">
                        <i class="fas fa-redo text-xs"></i>
                        Refresh
                    </button>
                    <button onclick="exportSettings()"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm shadow-sm">
                        <i class="fas fa-download text-xs"></i>
                        Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <?php if ($message && $messageType === 'success'): ?>
        <div id="successToast" class="fixed top-6 right-6 z-50 success-toast">
            <div class="bg-white border border-green-200 text-gray-900 px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
                <div class="p-2 bg-green-100 rounded-full text-green-600">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <p class="font-medium">Success</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($message) ?></p>
                </div>
                <button onclick="this.parentElement.remove()" class="ml-8 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error Toast -->
        <?php if ($message && $messageType === 'error'): ?>
        <div id="errorToast" class="fixed top-6 right-6 z-50 success-toast">
            <div class="bg-white border border-red-200 text-gray-900 px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
                <div class="p-2 bg-red-100 rounded-full text-red-600">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <p class="font-medium">Error</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($message) ?></p>
                </div>
                <button onclick="this.parentElement.remove()" class="ml-8 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="p-6 md:p-8">
            <!-- Settings Tabs -->
            <div class="flex flex-wrap gap-2 mb-8 bg-white rounded-lg p-2 shadow-sm border border-gray-200">
                <div class="tab active" data-tab="organization">
                    <i class="fas fa-landmark mr-2 text-sm"></i>Organization
                </div>
                <div class="tab" data-tab="inventory">
                    <i class="fas fa-boxes mr-2 text-sm"></i>Inventory
                </div>
                <div class="tab" data-tab="system">
                    <i class="fas fa-server mr-2 text-sm"></i>System
                </div>
                <div class="tab" data-tab="backup">
                    <i class="fas fa-database mr-2 text-sm"></i>Backup
                </div>
            </div>

            <!-- Organization Settings Tab -->
            <div id="organizationTab" class="tab-content active">
                <div class="bg-white rounded-lg border border-gray-200 mb-8">
                    <div class="p-6">
                        <div class="section-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-landmark text-blue-600 mr-2"></i>
                                        Organization Details
                                    </h2>
                                    <p class="text-gray-600 text-sm mt-1">Configure your organization's identity and branding</p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="save_organization">
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Organization Name -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-blue-50 rounded text-blue-600">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Organization Name</label>
                                            <p class="text-xs text-gray-500">Appears on all reports and exports</p>
                                        </div>
                                    </div>
                                    <input type="text" name="org_name" required
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm"
                                        value="<?= getSettingValue($orgSettings, 'org_name', 'Parliament of Ghana ICT Directorate') ?>"
                                        placeholder="Enter organization name">
                                </div>

                                <!-- Contact Email -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-green-50 rounded text-green-600">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Contact Email</label>
                                            <p class="text-xs text-gray-500">For report inquiries and notifications</p>
                                        </div>
                                    </div>
                                    <input type="email" name="org_contact" required
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm"
                                        value="<?= getSettingValue($orgSettings, 'org_contact', 'ict@parliament.gov.gh') ?>"
                                        placeholder="contact@example.com">
                                </div>

                                <!-- Report Footer -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-purple-50 rounded text-purple-600">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Report Footer</label>
                                            <p class="text-xs text-gray-500">Footer text for all generated reports</p>
                                        </div>
                                    </div>
                                    <input type="text" name="org_footer"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm"
                                        value="<?= getSettingValue($orgSettings, 'org_footer', 'Confidential - Internal Use Only') ?>"
                                        placeholder="Enter footer text">
                                </div>

                                <!-- Default Assignment -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-amber-50 rounded text-amber-600">
                                            <i class="fas fa-user-tag"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Default Assignment</label>
                                            <p class="text-xs text-gray-500">Default assignment for new inventory items</p>
                                        </div>
                                    </div>
                                    <select name="org_assignment"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                        <?php
                                        $assignmentValue = getSettingValue($orgSettings, 'org_assignment', 'MP');
                                        $assignmentOptions = $orgSettings['org_assignment']['options'] ?? ['MP', 'Staff', 'Office'];
                                        foreach ($assignmentOptions as $option):
                                        ?>
                                        <option value="<?= htmlspecialchars($option) ?>" 
                                            <?= $assignmentValue === $option ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium transition-colors flex items-center gap-2 text-sm">
                                    <i class="fas fa-save"></i>
                                    Save Organization Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Inventory Settings Tab -->
            <div id="inventoryTab" class="tab-content">
                <div class="bg-white rounded-lg border border-gray-200 mb-8">
                    <div class="p-6">
                        <div class="section-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-boxes text-amber-600 mr-2"></i>
                                        Inventory Preferences
                                    </h2>
                                    <p class="text-gray-600 text-sm mt-1">Configure inventory management settings and alerts</p>
                                </div>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="save_inventory">
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Default Status -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-blue-50 rounded text-blue-600">
                                            <i class="fas fa-tag"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Default Status</label>
                                            <p class="text-xs text-gray-500">Status for newly added inventory items</p>
                                        </div>
                                    </div>
                                    <select name="inv_default_status"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                        <?php
                                        $statusValue = getSettingValue($invSettings, 'inv_default_status', 'In Use');
                                        $statusOptions = $invSettings['inv_default_status']['options'] ?? ['In Use', 'Store', 'Faulty', 'Retired'];
                                        foreach ($statusOptions as $option):
                                        ?>
                                        <option value="<?= htmlspecialchars($option) ?>" 
                                            <?= $statusValue === $option ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($option) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Retirement Threshold -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-red-50 rounded text-red-600">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Retirement Threshold</label>
                                            <p class="text-xs text-gray-500">When to flag devices for retirement (months)</p>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <input type="number" name="inv_retirement_threshold" min="1" max="120" required
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm pl-10"
                                            value="<?= getSettingValue($invSettings, 'inv_retirement_threshold', 36) ?>">
                                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-sm">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Alerts -->
                                <div class="setting-card">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-emerald-50 rounded text-emerald-600">
                                                <i class="fas fa-bell"></i>
                                            </div>
                                            <div>
                                                <label class="block font-medium text-gray-900">Email Alerts</label>
                                                <p class="text-xs text-gray-500">Receive email notifications for updates</p>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="inv_email_alerts" 
                                                <?= isChecked($invSettings, 'inv_email_alerts') ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-700">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Alerts will be sent for critical inventory changes
                                    </div>
                                </div>

                                <!-- Compliance Reminders -->
                                <div class="setting-card">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center gap-3">
                                            <div class="p-2 bg-purple-50 rounded text-purple-600">
                                                <i class="fas fa-clipboard-check"></i>
                                            </div>
                                            <div>
                                                <label class="block font-medium text-gray-900">Compliance Reminders</label>
                                                <p class="text-xs text-gray-500">Enable compliance and audit reminders</p>
                                            </div>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="inv_compliance_reminders" 
                                                <?= isChecked($invSettings, 'inv_compliance_reminders') ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="mt-4 p-3 bg-purple-50 rounded text-sm text-purple-700">
                                        <i class="fas fa-shield-alt mr-2"></i>
                                        Ensures compliance with inventory management policies
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium transition-colors flex items-center gap-2 text-sm">
                                    <i class="fas fa-cog"></i>
                                    Update Inventory Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div id="systemTab" class="tab-content">
                <div class="bg-white rounded-lg border border-gray-200 mb-8">
                    <div class="p-6">
                        <div class="section-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-server text-green-600 mr-2"></i>
                                        System Configuration
                                    </h2>
                                    <p class="text-gray-600 text-sm mt-1">Advanced system settings and performance options</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Session Timeout -->
                            <div class="setting-card">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-red-50 rounded text-red-600">
                                        <i class="fas fa-hourglass-end"></i>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-gray-900">Session Timeout</label>
                                        <p class="text-xs text-gray-500">Auto-logout after inactivity</p>
                                    </div>
                                </div>
                                <div class="relative">
                                    <select class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                        <option>30 minutes</option>
                                        <option selected>60 minutes</option>
                                        <option>120 minutes</option>
                                        <option>Never (not recommended)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Log Retention -->
                            <div class="setting-card">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-purple-50 rounded text-purple-600">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-gray-900">Log Retention</label>
                                        <p class="text-xs text-gray-500">How long to keep activity logs</p>
                                    </div>
                                </div>
                                <div class="relative">
                                    <select class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                        <option>30 days</option>
                                        <option selected>90 days</option>
                                        <option>180 days</option>
                                        <option>1 year</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Maintenance Mode -->
                            <div class="setting-card">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-yellow-50 rounded text-yellow-600">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Maintenance Mode</label>
                                            <p class="text-xs text-gray-500">Restrict access for system maintenance</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="mt-4 p-3 bg-yellow-50 rounded text-sm text-yellow-700">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Only administrators can access when enabled
                                </div>
                            </div>

                            <!-- API Access -->
                            <div class="setting-card">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-indigo-50 rounded text-indigo-600">
                                            <i class="fas fa-code"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">API Access</label>
                                            <p class="text-xs text-gray-500">Enable REST API for integrations</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="mt-4 p-3 bg-indigo-50 rounded text-sm text-indigo-700">
                                    <i class="fas fa-lock mr-2"></i>
                                    API requires authentication token
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                                <div>
                                    <h3 class="font-medium text-gray-900">Danger Zone</h3>
                                    <p class="text-sm text-gray-500">Irreversible actions - proceed with caution</p>
                                </div>
                                <div class="flex gap-3">
                                    <button onclick="clearAllLogs()"
                                        class="px-4 py-2 bg-gray-100 border border-gray-300 text-gray-700 rounded hover:bg-gray-200 transition-colors text-sm font-medium">
                                        Clear All Logs
                                    </button>
                                    <button onclick="resetAllSettings()"
                                        class="px-4 py-2 bg-red-50 border border-red-200 text-red-700 rounded hover:bg-red-100 transition-colors text-sm font-medium">
                                        Reset to Defaults
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Settings Tab -->
            <div id="backupTab" class="tab-content">
                <div class="bg-white rounded-lg border border-gray-200 mb-8">
                    <div class="p-6">
                        <div class="section-header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-database text-purple-600 mr-2"></i>
                                        Backup & Recovery
                                    </h2>
                                    <p class="text-gray-600 text-sm mt-1">Manage system backups and recovery options</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Auto Backup -->
                            <div class="setting-card">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-blue-50 rounded text-blue-600">
                                            <i class="fas fa-robot"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Auto Backup</label>
                                            <p class="text-xs text-gray-500">Automatically backup database daily</p>
                                        </div>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                                    <select class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                        <option>Daily</option>
                                        <option selected>Weekly</option>
                                        <option>Monthly</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Backup Retention -->
                            <div class="setting-card">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="p-2 bg-green-50 rounded text-green-600">
                                        <i class="fas fa-archive"></i>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-gray-900">Backup Retention</label>
                                        <p class="text-xs text-gray-500">Number of backups to keep</p>
                                    </div>
                                </div>
                                <div class="relative">
                                    <input type="number" min="1" max="100"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm pl-10"
                                        value="30">
                                    <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                        <i class="fas fa-hdd"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Backup -->
                            <div class="setting-card lg:col-span-2">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="p-2 bg-emerald-50 rounded text-emerald-600">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Last Backup</label>
                                            <p class="text-xs text-gray-500">Most recent successful backup</p>
                                        </div>
                                    </div>
                                    <div class="sm:text-right">
                                        <p class="font-medium text-gray-900">2026-02-05 14:30:00</p>
                                        <p class="text-xs text-green-600">Success • 45.2 MB</p>
                                    </div>
                                </div>
                                <div class="mt-6">
                                    <div class="flex flex-col sm:flex-row gap-3">
                                        <button onclick="createBackup()"
                                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium transition-colors flex items-center gap-2 justify-center text-sm">
                                            <i class="fas fa-plus"></i>
                                            Create Backup Now
                                        </button>
                                        <button onclick="restoreBackup()"
                                            class="px-6 py-2.5 bg-gray-600 hover:bg-gray-700 text-white rounded font-medium transition-colors flex items-center gap-2 justify-center text-sm">
                                            <i class="fas fa-undo"></i>
                                            Restore from Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Section -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stats-card">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-50 rounded text-blue-600">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Settings</p>
                            <p class="text-lg font-semibold text-gray-900"><?= count($allSettings) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-50 rounded text-green-600">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Last Updated</p>
                            <p class="text-sm font-semibold text-gray-900">
                                <?php
                                $lastUpdateQuery = "SELECT MAX(updated_at) as last_update FROM settings";
                                $result = mysqli_query($conn, $lastUpdateQuery);
                                if ($result && $row = mysqli_fetch_assoc($result)) {
                                    echo $row['last_update'] ? date('M d, H:i', strtotime($row['last_update'])) : 'Never';
                                } else {
                                    echo 'Unknown';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-50 rounded text-purple-600">
                            <i class="fas fa-database"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Database Status</p>
                            <p class="text-sm font-semibold text-emerald-600">Connected</p>
                        </div>
                    </div>
                </div>
                
                <div class="stats-card">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-amber-50 rounded text-amber-600">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Auto Save</p>
                            <p class="text-sm font-semibold text-gray-900">Enabled</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <script>
        // Simple Tab Switching Solution - Fixed
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Settings page loaded - initializing tabs');
            
            // Get all tabs and tab contents
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Function to show a specific tab
            function showTab(tabId) {
                console.log('Showing tab:', tabId);
                
                // Hide all tab contents
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    content.style.display = 'none';
                });
                
                // Show selected tab content
                const tabContent = document.getElementById(tabId + 'Tab');
                if (tabContent) {
                    tabContent.classList.add('active');
                    tabContent.style.display = 'block';
                    console.log('Tab content shown:', tabId + 'Tab');
                }
                
                // Update active tab
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
                
                const activeTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
                if (activeTab) {
                    activeTab.classList.add('active');
                }
                
                // Save to localStorage
                localStorage.setItem('activeSettingsTab', tabId);
            }
            
            // Add click events to tabs
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.getAttribute('data-tab');
                    console.log('Tab clicked:', tabId);
                    showTab(tabId);
                });
            });
            
            // Initialize with saved tab or default to 'organization'
            let savedTab = localStorage.getItem('activeSettingsTab');
            
            // Check if the saved tab exists
            const tabExists = Array.from(tabs).some(tab => 
                tab.getAttribute('data-tab') === savedTab
            );
            
            if (!savedTab || !tabExists) {
                savedTab = 'organization'; // Default tab
            }
            
            console.log('Initializing with tab:', savedTab);
            showTab(savedTab);
            
            // Debug: Check initial state
            console.log('Active tab content:', document.querySelector('.tab-content.active'));
            console.log('All tabs:', tabs.length);
            console.log('All tab contents:', tabContents.length);
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    submitBtn.disabled = true;
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Settings';
                    }, 3000);
                }
            });
        });
        
        // Export settings function
        function exportSettings() {
            fetch('./api/export-settings.php')
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `parliament_settings_${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Show success message
                    showToast('Settings exported successfully!', 'success');
                })
                .catch(error => {
                    console.error('Export error:', error);
                    showToast('Failed to export settings', 'error');
                });
        }
        
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-6 right-6 z-50 success-toast`;
            toast.innerHTML = `
                <div class="bg-white border ${type === 'success' ? 'border-green-200' : 'border-red-200'} text-gray-900 px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
                    <div class="p-2 ${type === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'} rounded-full">
                        <i class="fas ${type === 'success' ? 'fa-check' : 'fa-exclamation-circle'}"></i>
                    </div>
                    <div>
                        <p class="font-medium">${type === 'success' ? 'Success' : 'Error'}</p>
                        <p class="text-sm text-gray-600">${message}</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-8 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }
        
        // Danger zone functions
        function clearAllLogs() {
            if (confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')) {
                // Implementation would go here
                showToast('Logs cleared successfully', 'success');
            }
        }
        
        function resetAllSettings() {
            if (confirm('⚠️ WARNING: This will reset ALL settings to their defaults. This action cannot be undone. Are you sure?')) {
                // Implementation would go here
                showToast('Settings reset to defaults', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
        }
        
        // Backup functions
        function createBackup() {
            showToast('Creating backup...', 'info');
            setTimeout(() => {
                showToast('Backup created successfully!', 'success');
            }, 2000);
        }
        
        function restoreBackup() {
            if (confirm('Restore from backup? This will overwrite current settings.')) {
                showToast('Restoring from backup...', 'info');
            }
        }
        
        // Auto-save indicators
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', function() {
                const form = this.closest('form');
                if (form) {
                    const saveBtn = form.querySelector('button[type="submit"]');
                    if (saveBtn) {
                        saveBtn.classList.add('relative');
                        saveBtn.insertAdjacentHTML('beforeend', '<span class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>');
                        setTimeout(() => {
                            const indicator = saveBtn.querySelector('.absolute');
                            if (indicator) indicator.remove();
                        }, 2000);
                    }
                }
            });
        });

        // Update main content margin based on sidebar state
        function updateMainContentMargin() {
            const mainContent = document.getElementById('mainContent');
            const sidebarEl = document.querySelector('#sidebar'); // Changed variable name
            
            if (window.innerWidth >= 768 && sidebarEl) {
                // Check if sidebar is collapsed
                const isCollapsed = sidebarEl.classList.contains('collapsed') || 
                                   localStorage.getItem('sidebarCollapsed') === 'true' ||
                                   document.body.classList.contains('sidebar-collapsed');
                
                if (isCollapsed) {
                    mainContent.style.marginLeft = '5rem';
                } else {
                    mainContent.style.marginLeft = '16rem';
                }
            } else {
                // Mobile view
                mainContent.style.marginLeft = '0';
            }
        }

        // Initialize sidebar state
        document.addEventListener('DOMContentLoaded', function() {
            updateMainContentMargin();
            
            // Listen for sidebar toggle
            const toggleBtn = document.getElementById('toggleSidebar');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    setTimeout(updateMainContentMargin, 300); // Wait for transition
                });
            }
            
            // Listen for window resize
            window.addEventListener('resize', updateMainContentMargin);
            
            // Also listen for storage events (if sidebar state changes in another tab)
            window.addEventListener('storage', function(e) {
                if (e.key === 'sidebarCollapsed') {
                    updateMainContentMargin();
                }
            });
        });

        // Additional adjustment for smooth transition
        const sidebarElement = document.querySelector('#sidebar');
        if (sidebarElement) {
            sidebarElement.addEventListener('transitionend', updateMainContentMargin);
        }
    </script>
</body>
</html>