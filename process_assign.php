<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/config/database.php";

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get POST data
$deviceId = $_POST['device_id'] ?? null;
$userId = $_POST['user_id'] ?? null;
$assignDate = $_POST['assign_date'] ?? date('Y-m-d');
$assignRemarks = $_POST['assign_remarks'] ?? '';
$newStatus = $_POST['new_status'] ?? 'In Use';
$newCondition = $_POST['new_condition'] ?? 'Good';

// Validate required fields
if (empty($deviceId) || empty($userId)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Device ID and User ID are required']);
    exit();
}

// Validate status
$validStatuses = ['In Use', 'Store', 'Faulty'];
if (!in_array($newStatus, $validStatuses)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

// Validate condition
$validConditions = ['New', 'Good', 'Fair', 'Faulty'];
if (!in_array($newCondition, $validConditions)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid condition value']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Get device information
    $deviceQuery = "SELECT i.*, b.brand_name, d.department_name, l.location_name, c.category_name 
                    FROM inventory_items i
                    LEFT JOIN brands b ON i.brand_id = b.id
                    LEFT JOIN departments d ON i.department_id = d.id
                    LEFT JOIN locations l ON i.location_id = l.id
                    LEFT JOIN categories c ON i.category_id = c.id
                    WHERE i.id = ?";
    $deviceStmt = $conn->prepare($deviceQuery);
    $deviceStmt->bind_param("i", $deviceId);
    $deviceStmt->execute();
    $deviceResult = $deviceStmt->get_result();
    $device = $deviceResult->fetch_assoc();

    if (!$device) {
        throw new Exception("Device not found");
    }

    // Check if device is already assigned
    if (!empty($device['assigned_user'])) {
        throw new Exception("Device is already assigned to another user");
    }

    // Get user information
    $userQuery = "SELECT firstname, lastname, email, department_id FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    $user = $userResult->fetch_assoc();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Update the device
    $updateQuery = "UPDATE inventory_items SET 
                    assigned_user = ?,
                    status = ?,
                    condition = ?,
                    remarks = CONCAT(IFNULL(remarks, ''), '\n', ?),
                    updated_at = NOW()
                    WHERE id = ?";

    // Combine new remarks with existing ones
    $newRemarks = "[" . date('Y-m-d H:i:s') . "] Assigned to " . $user['firstname'] . ' ' . $user['lastname'] . " (" . $user['email'] . ")";
    if (!empty($assignRemarks)) {
        $newRemarks .= " - " . $assignRemarks;
    }

    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("isssi", $userId, $newStatus, $newCondition, $newRemarks, $deviceId);

    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update device: " . $conn->error);
    }

    // Get admin user info
    $adminQuery = "SELECT firstname, lastname FROM users WHERE id = ?";
    $adminStmt = $conn->prepare($adminQuery);
    $adminId = $_SESSION['user_id'];
    $adminStmt->bind_param("i", $adminId);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();
    $admin = $adminResult->fetch_assoc();

    // Create activity log entry
    $activityQuery = "INSERT INTO activity_log (user_id, action, description, ip_address, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";

    // Build description
    $description = "DEVICE ASSIGNMENT\n";
    $description .= "Device: {$device['asset_tag']} ({$device['brand_name']} {$device['model']})\n";
    $description .= "Assigned to: {$user['firstname']} {$user['lastname']} ({$user['email']})\n";
    $description .= "Department: {$device['department_name']}\n";
    $description .= "Location: {$device['location_name']}\n";
    $description .= "Status changed to: {$newStatus}\n";
    $description .= "Condition: {$newCondition}\n";
    if (!empty($assignRemarks)) {
        $description .= "Remarks: {$assignRemarks}\n";
    }
    $description .= "Assigned by: {$admin['firstname']} {$admin['lastname']}";

    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $action = 'assign_device';
    $activityStmt = $conn->prepare($activityQuery);
    $activityStmt->bind_param("isss", $adminId, $action, $description, $ipAddress);

    if (!$activityStmt->execute()) {
        throw new Exception("Failed to log activity: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();

    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Device assigned successfully',
        'device' => [
            'asset_tag' => $device['asset_tag'],
            'brand' => $device['brand_name'],
            'model' => $device['model'],
            'new_status' => $newStatus,
            'new_condition' => $newCondition
        ],
        'user' => [
            'name' => $user['firstname'] . ' ' . $user['lastname'],
            'email' => $user['email']
        ],
        'assignment_date' => $assignDate,
        'activity_log_id' => $conn->insert_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>