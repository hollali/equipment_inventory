<?php
// Start session
session_start();

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
    <title>Settings - Parliament ICT</title>
    <link rel="icon" type="image/png" href="./images/logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Custom styles */
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-message {
            background: linear-gradient(to right, #d1fae5, #ecfdf5);
            border-left: 4px solid #10b981;
        }
        
        .error-message {
            background: linear-gradient(to right, #fee2e2, #fef2f2);
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen flex">

    <!-- SIDEBAR -->
    <?php 
    $sidebarPath = './sidebar.php';
    if (file_exists($sidebarPath)) {
        include $sidebarPath; 
    } else {
        echo '<div class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg">';
        echo '<div class="p-6">';
        echo '<h2 class="text-xl font-bold text-blue-600">Parliament ICT</h2>';
        echo '</div></div>';
    }
    ?>

    <!-- MAIN CONTENT -->
    <main id="mainContent" class="flex-1 transition-all duration-300 ml-64 p-8">

        <!-- HEADER -->
        <header class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Settings</h1>
                <p class="text-slate-600 mt-1">System preferences and configuration</p>
            </div>
            <div class="text-sm text-slate-500">
                <i class="fas fa-database mr-1"></i>
                Settings stored in database
            </div>
        </header>

        <!-- MESSAGE DISPLAY -->
        <?php if ($message): ?>
        <div id="messageContainer" class="mb-6 p-4 rounded-lg fade-in <?php echo $messageType === 'success' ? 'success-message' : 'error-message'; ?>">
            <div class="flex items-center gap-3">
                <?php if ($messageType === 'success'): ?>
                    <span class="text-green-600 text-lg">✓</span>
                <?php else: ?>
                    <span class="text-red-600 text-lg">✗</span>
                <?php endif; ?>
                <div>
                    <p class="font-medium <?php echo $messageType === 'success' ? 'text-green-800' : 'text-red-800'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </p>
                    <p class="text-sm <?php echo $messageType === 'success' ? 'text-green-700' : 'text-red-700'; ?> mt-1">
                        <?php echo date('Y-m-d H:i:s'); ?>
                    </p>
                </div>
                <button onclick="document.getElementById('messageContainer').remove()" 
                        class="ml-auto text-slate-500 hover:text-slate-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- ORGANIZATION DETAILS -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6 fade-in">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">
                    <i class="fas fa-landmark text-blue-600 mr-2"></i>Organization Details
                </h2>
                <p class="text-slate-600 text-sm mt-1">Set the directorate information used in reports</p>
            </div>

            <form id="orgForm" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="save_organization">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-building text-slate-400 mr-1"></i>Organization Name
                        </label>
                        <input type="text" name="org_name" id="orgName" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="<?php echo getSettingValue($orgSettings, 'org_name', 'Parliament of Ghana ICT Directorate'); ?>">
                        <p class="text-xs text-slate-500 mt-1">This name appears on all reports and exports</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-envelope text-slate-400 mr-1"></i>Default Report Contact
                        </label>
                        <input type="email" name="org_contact" id="orgContact" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="<?php echo getSettingValue($orgSettings, 'org_contact', 'ict@parliament.gov.gh'); ?>">
                        <p class="text-xs text-slate-500 mt-1">Email address for report inquiries</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-file-alt text-slate-400 mr-1"></i>Report Footer
                        </label>
                        <input type="text" name="org_footer" id="orgFooter"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="<?php echo getSettingValue($orgSettings, 'org_footer', 'Confidential - Internal Use Only'); ?>">
                        <p class="text-xs text-slate-500 mt-1">Text displayed at the bottom of all reports</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-user-tag text-slate-400 mr-1"></i>Default Assignment Type
                        </label>
                        <select name="org_assignment" id="orgAssignment"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
                            <?php
                            $assignmentValue = getSettingValue($orgSettings, 'org_assignment', 'MP');
                            $assignmentOptions = $orgSettings['org_assignment']['options'] ?? ['MP', 'Staff', 'Office'];
                            foreach ($assignmentOptions as $option):
                            ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" 
                                <?php echo $assignmentValue === $option ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Default assignment for new inventory items</p>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm font-medium flex items-center gap-2">
                        <i class="fas fa-save"></i>Save Changes
                    </button>
                </div>
            </form>
        </section>

        <!-- INVENTORY PREFERENCES -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 fade-in">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">
                    <i class="fas fa-boxes text-blue-600 mr-2"></i>Inventory Preferences
                </h2>
                <p class="text-slate-600 text-sm mt-1">Configure default statuses and alerts</p>
            </div>

            <form id="prefsForm" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="save_inventory">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-tag text-slate-400 mr-1"></i>Default Status
                        </label>
                        <select name="inv_default_status" id="prefsStatus"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-white">
                            <?php
                            $statusValue = getSettingValue($invSettings, 'inv_default_status', 'In Use');
                            $statusOptions = $invSettings['inv_default_status']['options'] ?? ['In Use', 'Store', 'Faulty', 'Retired'];
                            foreach ($statusOptions as $option):
                            ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" 
                                <?php echo $statusValue === $option ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($option); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Status for newly added inventory items</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            <i class="fas fa-calendar-alt text-slate-400 mr-1"></i>Retirement Threshold (months)
                        </label>
                        <input type="number" name="inv_retirement_threshold" id="prefsThreshold" min="1" max="120" required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                            value="<?php echo getSettingValue($invSettings, 'inv_retirement_threshold', 36); ?>">
                        <p class="text-xs text-slate-500 mt-1">When to flag devices for retirement</p>
                    </div>

                    <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-lg border border-slate-200">
                        <input type="checkbox" name="inv_email_alerts" id="prefsEmailAlerts" 
                            class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500 mt-1"
                            <?php echo isChecked($invSettings, 'inv_email_alerts'); ?>>
                        <div>
                            <label for="prefsEmailAlerts" class="block text-sm font-medium text-slate-700">Email Alerts</label>
                            <p class="text-xs text-slate-500 mt-1">Receive email notifications for inventory updates</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-4 bg-slate-50 rounded-lg border border-slate-200">
                        <input type="checkbox" name="inv_compliance_reminders" id="prefsCompliance"
                            class="w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500 mt-1"
                            <?php echo isChecked($invSettings, 'inv_compliance_reminders'); ?>>
                        <div>
                            <label for="prefsCompliance" class="block text-sm font-medium text-slate-700">Compliance Reminders</label>
                            <p class="text-xs text-slate-500 mt-1">Enable compliance and audit reminders</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition shadow-sm font-medium flex items-center gap-2">
                        <i class="fas fa-cog"></i>Update Preferences
                    </button>
                </div>
            </form>
        </section>

        <!-- SYSTEM INFORMATION -->
        <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6 fade-in">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-slate-900">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>System Information
                </h2>
                <p class="text-slate-600 text-sm mt-1">Current system configuration and statistics</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-database text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600">Database</p>
                            <p class="font-semibold text-slate-900">Connected</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-green-50 rounded-lg border border-green-100">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-cogs text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600">Settings Loaded</p>
                            <p class="font-semibold text-slate-900"><?php echo count($allSettings); ?> settings</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4 bg-purple-50 rounded-lg border border-purple-100">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-history text-purple-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-600">Last Updated</p>
                            <p class="font-semibold text-slate-900">
                                <?php
                                $lastUpdateQuery = "SELECT MAX(updated_at) as last_update FROM settings";
                                $result = mysqli_query($conn, $lastUpdateQuery);
                                if ($result && $row = mysqli_fetch_assoc($result)) {
                                    echo $row['last_update'] ? date('Y-m-d H:i', strtotime($row['last_update'])) : 'Never';
                                } else {
                                    echo 'Unknown';
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

             <!-- Footer -->
    <?php include __DIR__ . '/footer.php'; ?>

        </section>

        

    </main>

    <!-- JS -->
    <script>
        // Handle sidebar collapse (if sidebar.php exists)
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('toggleSidebar');

        if (sidebar && toggleBtn) {
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
                if (mainContent.classList.contains('ml-64')) {
                    mainContent.classList.replace('ml-64', 'ml-20');
                }
            }

            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');

                if (sidebar.classList.contains('collapsed')) {
                    mainContent.classList.replace('ml-64', 'ml-20');
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    mainContent.classList.replace('ml-20', 'ml-64');
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            });
        }

        // Auto-hide message after 5 seconds
        const messageContainer = document.getElementById('messageContainer');
        if (messageContainer) {
            setTimeout(() => {
                messageContainer.style.opacity = '0';
                setTimeout(() => {
                    messageContainer.remove();
                }, 300);
            }, 5000);
        }

        // Form validation
        document.getElementById('orgForm')?.addEventListener('submit', function(e) {
            const orgName = document.getElementById('orgName');
            const orgContact = document.getElementById('orgContact');
            
            if (!orgName.value.trim()) {
                e.preventDefault();
                alert('Organization name is required');
                orgName.focus();
                return;
            }
            
            if (!orgContact.value.trim()) {
                e.preventDefault();
                alert('Contact email is required');
                orgContact.focus();
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.disabled = true;
            }
        });

        document.getElementById('prefsForm')?.addEventListener('submit', function(e) {
            const threshold = document.getElementById('prefsThreshold');
            
            if (!threshold.value || threshold.value < 1 || threshold.value > 120) {
                e.preventDefault();
                alert('Please enter a valid retirement threshold (1-120 months)');
                threshold.focus();
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                submitBtn.disabled = true;
            }
        });

        // Reset button states on page load
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        });

        // Real-time validation
        document.getElementById('prefsThreshold')?.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 1) {
                this.value = 1;
            } else if (value > 120) {
                this.value = 120;
            }
        });
    </script>

</body>
</html>
<?php
// Connection is managed by the Database class
?>