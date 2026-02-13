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

// Get current user ID for audit trail
$userId = $_SESSION['user_id'] ?? null;

// Simple encryption functions (without external dependency)
function encryptSetting($value) {
    if (empty($value)) return $value;
    return base64_encode($value); // Simple encoding for now
}

function decryptSetting($value) {
    if (empty($value)) return $value;
    return base64_decode($value); // Simple decoding for now
}

// Function to get all settings
function getSettings($conn, $category = null) {
    $settings = [];
    
    try {
        // Check if new columns exist, if not use basic query
        $checkQuery = "SHOW COLUMNS FROM settings LIKE 'is_encrypted'";
        $checkResult = mysqli_query($conn, $checkQuery);
        $hasNewColumns = mysqli_num_rows($checkResult) > 0;
        
        if ($hasNewColumns) {
            $query = "SELECT setting_key, setting_value, setting_type, is_encrypted, category, label, 
                             description, options, validation_rules, sort_order, is_required, placeholder,
                             created_at, updated_at, created_by, updated_by
                      FROM settings";
        } else {
            $query = "SELECT setting_key, setting_value, setting_type, category, label, description, options 
                      FROM settings";
        }
        
        if ($category) {
            $query .= " WHERE category = ?";
        }
        
        if ($hasNewColumns) {
            $query .= " ORDER BY category, sort_order ASC, setting_key ASC";
        } else {
            $query .= " ORDER BY category, setting_key";
        }
        
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
                // Handle encrypted values
                $value = $row['setting_value'];
                if ($hasNewColumns && isset($row['is_encrypted']) && $row['is_encrypted'] && !empty($value)) {
                    $value = decryptSetting($value);
                }
                
                // Convert setting value based on type
                switch ($row['setting_type'] ?? 'text') {
                    case 'number':
                        $value = $value !== null ? (int)$value : 0;
                        break;
                    case 'boolean':
                        $value = (bool)$value;
                        break;
                    case 'json':
                        $value = !empty($value) ? json_decode($value, true) : [];
                        break;
                    default:
                        $value = htmlspecialchars($value ?? '');
                }
                
                $settings[$row['setting_key']] = [
                    'value' => $value,
                    'type' => $row['setting_type'] ?? 'text',
                    'category' => $row['category'] ?? 'general',
                    'label' => $row['label'] ?? ucfirst(str_replace('_', ' ', $row['setting_key'])),
                    'description' => $row['description'] ?? '',
                    'options' => isset($row['options']) && $row['options'] ? explode(',', $row['options']) : []
                ];
                
                // Add new columns if they exist
                if ($hasNewColumns) {
                    $settings[$row['setting_key']]['is_encrypted'] = (bool)($row['is_encrypted'] ?? false);
                    $settings[$row['setting_key']]['validation_rules'] = !empty($row['validation_rules']) ? json_decode($row['validation_rules'], true) : [];
                    $settings[$row['setting_key']]['sort_order'] = (int)($row['sort_order'] ?? 0);
                    $settings[$row['setting_key']]['is_required'] = (bool)($row['is_required'] ?? false);
                    $settings[$row['setting_key']]['placeholder'] = $row['placeholder'] ?? '';
                    $settings[$row['setting_key']]['updated_at'] = $row['updated_at'] ?? null;
                    $settings[$row['setting_key']]['updated_by'] = $row['updated_by'] ?? null;
                }
            }
        }
    } catch (Exception $e) {
        // Fallback to simple query if there's an error
        error_log("Settings error: " . $e->getMessage());
    }
    
    return $settings;
}

// Function to save settings
function saveSettings($conn, $settings, $userId = null) {
    if (empty($settings)) {
        return ['success' => false, 'message' => 'No settings to save'];
    }
    
    $success = true;
    $message = '';
    
    try {
        mysqli_begin_transaction($conn);
        
        foreach ($settings as $key => $value) {
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
                $storedValue = $value;
                if ($type === 'boolean') {
                    $storedValue = $value ? '1' : '0';
                }
                
                // Update query
                $query = "UPDATE settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP";
                
                // Add updated_by if column exists and userId provided
                $checkUserColumn = mysqli_query($conn, "SHOW COLUMNS FROM settings LIKE 'updated_by'");
                if (mysqli_num_rows($checkUserColumn) > 0 && $userId) {
                    $query .= ", updated_by = ?";
                }
                
                $query .= " WHERE setting_key = ?";
                
                $stmt = mysqli_prepare($conn, $query);
                
                if (mysqli_num_rows($checkUserColumn) > 0 && $userId) {
                    mysqli_stmt_bind_param($stmt, "sis", $storedValue, $userId, $key);
                } else {
                    mysqli_stmt_bind_param($stmt, "ss", $storedValue, $key);
                }
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to save setting: $key");
                }
                mysqli_stmt_close($stmt);
            }
            mysqli_stmt_close($checkStmt);
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
                    'org_name' => trim($_POST['org_name'] ?? ''),
                    'org_contact' => trim($_POST['org_contact'] ?? ''),
                    'org_footer' => trim($_POST['org_footer'] ?? ''),
                    'org_assignment' => $_POST['org_assignment'] ?? 'MP'
                ];
                
                $result = saveSettings($conn, $settingsToSave, $userId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'save_inventory':
                $settingsToSave = [
                    'inv_default_status' => $_POST['inv_default_status'] ?? 'In Use',
                    'inv_retirement_threshold' => (int)($_POST['inv_retirement_threshold'] ?? 36),
                    'inv_email_alerts' => isset($_POST['inv_email_alerts']) ? true : false,
                    'inv_compliance_reminders' => isset($_POST['inv_compliance_reminders']) ? true : false
                ];
                
                $result = saveSettings($conn, $settingsToSave, $userId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'save_system':
                $settingsToSave = [
                    'session_timeout' => (int)($_POST['session_timeout'] ?? 60),
                    'log_retention' => $_POST['log_retention'] ?? '90_days',
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? true : false,
                    'api_access' => isset($_POST['api_access']) ? true : false
                ];
                
                $result = saveSettings($conn, $settingsToSave, $userId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'save_backup':
                $settingsToSave = [
                    'auto_backup' => isset($_POST['auto_backup']) ? true : false,
                    'backup_frequency' => $_POST['backup_frequency'] ?? 'weekly',
                    'backup_retention' => (int)($_POST['backup_retention'] ?? 30)
                ];
                
                $result = saveSettings($conn, $settingsToSave, $userId);
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
$backupSettings = getSettings($conn, 'backup');

// Helper function to get setting value safely
function getSettingValue($settings, $key, $default = '') {
    return isset($settings[$key]) ? $settings[$key]['value'] : $default;
}

// Helper function to check if checkbox should be checked
function isChecked($settings, $key) {
    return isset($settings[$key]) && $settings[$key]['value'] ? 'checked' : '';
}

// Helper function to get options
function getSettingOptions($settings, $key, $default = []) {
    return isset($settings[$key]['options']) && !empty($settings[$key]['options']) 
           ? $settings[$key]['options'] 
           : $default;
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
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f9fafb; }
        .setting-card { background: white; border-radius: 8px; padding: 24px; border: 1px solid #e5e7eb; transition: all 0.2s ease; height: 100%; }
        .setting-card:hover { border-color: #3b82f6; }
        .stats-card { background: white; border-radius: 8px; padding: 20px; border: 1px solid #e5e7eb; }
        .toggle-switch { position: relative; display: inline-block; width: 52px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #d1d5db; transition: .2s; border-radius: 34px; }
        .toggle-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .2s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: #10b981; }
        input:checked + .toggle-slider:before { transform: translateX(26px); }
        .tab { padding: 12px 24px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; font-weight: 500; color: #6b7280; border: 1px solid transparent; user-select: none; }
        .tab.active { background-color: #3b82f6; color: white; border-color: #3b82f6; }
        .tab:hover:not(.active) { background-color: #f3f4f6; color: #374151; }
        .input-glow:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        .select-custom { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-position: right 16px center; background-repeat: no-repeat; background-size: 16px; padding-right: 48px; }
        .section-header { border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; margin-bottom: 24px; }
        #mainContent { margin-left: 16rem; transition: margin-left 0.3s ease; min-height: calc(100vh - 80px); }
        @media (max-width: 768px) { #mainContent { margin-left: 0; } }
        .tab-content { display: none; }
        .tab-content.active { display: block !important; }
        .hidden { display: none !important; }
        .success-toast { animation: slideInRight 0.3s ease-out, fadeOut 0.3s ease-out 4.7s forwards; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { to { opacity: 0; transform: translateX(100%); } }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
    <!-- Sidebar - Single Include -->
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
                </div>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if ($message): ?>
        <div id="toast" class="fixed top-6 right-6 z-50 success-toast">
            <div class="bg-white border <?= $messageType === 'success' ? 'border-green-200' : 'border-red-200' ?> text-gray-900 px-6 py-4 rounded-lg shadow-lg flex items-center gap-3">
                <div class="p-2 <?= $messageType === 'success' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600' ?> rounded-full">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check' : 'fa-exclamation-circle' ?>"></i>
                </div>
                <div>
                    <p class="font-medium"><?= $messageType === 'success' ? 'Success' : 'Error' ?></p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($message) ?></p>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-8 text-gray-400 hover:text-gray-600">
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
                            <h2 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-landmark text-blue-600 mr-2"></i>
                                Organization Details
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Configure your organization's identity and branding</p>
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
                                        value="<?= htmlspecialchars(getSettingValue($orgSettings, 'org_name', 'Parliament of Ghana ICT Directorate')) ?>"
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
                                        value="<?= htmlspecialchars(getSettingValue($orgSettings, 'org_contact', 'ict@parliament.gov.gh')) ?>"
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
                                        value="<?= htmlspecialchars(getSettingValue($orgSettings, 'org_footer', 'Confidential - Internal Use Only')) ?>"
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
                                        $assignmentOptions = getSettingOptions($orgSettings, 'org_assignment', ['MP', 'Staff', 'Office']);
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
                            <h2 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-boxes text-amber-600 mr-2"></i>
                                Inventory Preferences
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Configure inventory management settings and alerts</p>
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
                                        $statusOptions = getSettingOptions($invSettings, 'inv_default_status', ['In Use', 'Store', 'Faulty', 'Retired']);
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
                            <h2 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-server text-green-600 mr-2"></i>
                                System Configuration
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Advanced system settings and performance options</p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="save_system">
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <!-- Session Timeout -->
                                <div class="setting-card">
                                    <div class="flex items-center gap-3 mb-4">
                                        <div class="p-2 bg-red-50 rounded text-red-600">
                                            <i class="fas fa-hourglass-end"></i>
                                        </div>
                                        <div>
                                            <label class="block font-medium text-gray-900">Session Timeout</label>
                                            <p class="text-xs text-gray-500">Auto-logout after inactivity (minutes)</p>
                                        </div>
                                    </div>
                                    <div class="relative">
                                        <select name="session_timeout" 
                                                class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                            <option value="30" <?= getSettingValue($sysSettings, 'session_timeout', 60) == 30 ? 'selected' : '' ?>>30 minutes</option>
                                            <option value="60" <?= getSettingValue($sysSettings, 'session_timeout', 60) == 60 ? 'selected' : '' ?>>60 minutes</option>
                                            <option value="120" <?= getSettingValue($sysSettings, 'session_timeout', 60) == 120 ? 'selected' : '' ?>>120 minutes</option>
                                            <option value="0" <?= getSettingValue($sysSettings, 'session_timeout', 60) == 0 ? 'selected' : '' ?>>Never (not recommended)</option>
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
                                        <select name="log_retention"
                                                class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                            <option value="30_days" <?= getSettingValue($sysSettings, 'log_retention', '90_days') == '30_days' ? 'selected' : '' ?>>30 days</option>
                                            <option value="90_days" <?= getSettingValue($sysSettings, 'log_retention', '90_days') == '90_days' ? 'selected' : '' ?>>90 days</option>
                                            <option value="180_days" <?= getSettingValue($sysSettings, 'log_retention', '90_days') == '180_days' ? 'selected' : '' ?>>180 days</option>
                                            <option value="1_year" <?= getSettingValue($sysSettings, 'log_retention', '90_days') == '1_year' ? 'selected' : '' ?>>1 year</option>
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
                                            <input type="checkbox" name="maintenance_mode" 
                                                <?= isChecked($sysSettings, 'maintenance_mode') ?>>
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
                                            <input type="checkbox" name="api_access" 
                                                <?= isChecked($sysSettings, 'api_access') ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="mt-4 p-3 bg-indigo-50 rounded text-sm text-indigo-700">
                                        <i class="fas fa-lock mr-2"></i>
                                        API requires authentication token
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium transition-colors flex items-center gap-2 text-sm">
                                    <i class="fas fa-cog"></i>
                                    Update System Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Backup Settings Tab -->
            <div id="backupTab" class="tab-content">
                <div class="bg-white rounded-lg border border-gray-200 mb-8">
                    <div class="p-6">
                        <div class="section-header">
                            <h2 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-database text-purple-600 mr-2"></i>
                                Backup & Recovery
                            </h2>
                            <p class="text-gray-600 text-sm mt-1">Manage system backups and recovery options</p>
                        </div>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="save_backup">
                            
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
                                            <input type="checkbox" name="auto_backup" 
                                                <?= isChecked($backupSettings, 'auto_backup') ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                                        <select name="backup_frequency"
                                                class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow select-custom bg-white text-sm">
                                            <option value="daily" <?= getSettingValue($backupSettings, 'backup_frequency', 'weekly') == 'daily' ? 'selected' : '' ?>>Daily</option>
                                            <option value="weekly" <?= getSettingValue($backupSettings, 'backup_frequency', 'weekly') == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                            <option value="monthly" <?= getSettingValue($backupSettings, 'backup_frequency', 'weekly') == 'monthly' ? 'selected' : '' ?>>Monthly</option>
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
                                        <input type="number" name="backup_retention" min="1" max="100"
                                            class="w-full px-4 py-2.5 border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500 input-glow transition text-sm pl-10"
                                            value="<?= getSettingValue($backupSettings, 'backup_retention', 30) ?>">
                                        <div class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">
                                            <i class="fas fa-hdd"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end pt-6 border-t border-gray-200">
                                <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium transition-colors flex items-center gap-2 text-sm">
                                    <i class="fas fa-save"></i>
                                    Save Backup Settings
                                </button>
                            </div>
                        </form>
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
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            function showTab(tabId) {
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    content.style.display = 'none';
                });
                
                const tabContent = document.getElementById(tabId + 'Tab');
                if (tabContent) {
                    tabContent.classList.add('active');
                    tabContent.style.display = 'block';
                }
                
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
                
                const activeTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
                if (activeTab) {
                    activeTab.classList.add('active');
                }
                
                localStorage.setItem('activeSettingsTab', tabId);
            }
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    showTab(this.getAttribute('data-tab'));
                });
            });
            
            let savedTab = localStorage.getItem('activeSettingsTab');
            if (!savedTab) {
                savedTab = 'organization';
            }
            
            showTab(savedTab);
        });

        // Auto-hide toast after 5 seconds
        setTimeout(() => {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.remove();
            }
        }, 5000);
    </script>
</body>
</html>