<?php
// process_assign_minimal.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/config/database.php";

header('Content-Type: application/json');

// Just do basic assignment without any complications
$device_id = isset($_POST['device_id']) ? intval($_POST['device_id']) : 0;
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$new_status = isset($_POST['new_status']) ? $_POST['new_status'] : 'in_use';
$new_condition = isset($_POST['new_condition']) ? $_POST['new_condition'] : null;

if ($device_id === 0 || $user_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Simple update query
    if ($new_condition) {
        $sql = "UPDATE inventory_items 
                SET assigned_user = '$user_id', 
                    status = '$new_status', 
                    `condition` = '$new_condition',
                    updated_at = NOW()
                WHERE id = $device_id";
    } else {
        $sql = "UPDATE inventory_items 
                SET assigned_user = '$user_id', 
                    status = '$new_status',
                    updated_at = NOW()
                WHERE id = $device_id";
    }

    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            'success' => true,
            'message' => 'Device assigned successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $conn->error
        ]);
    }

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>