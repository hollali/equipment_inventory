<?php
session_start();

/* ================== ERROR REPORTING ================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================== DB CONNECTION ================== */
require_once "./config/database.php";

$db = new Database();
$conn = $db->getConnection();

if (!$conn) {
    die("Database connection failed");
}

/* ================== ADD INVENTORY ================== */
if (isset($_POST['save'])) {
    // Validate required fields
    $requiredFields = ['device_type', 'brand_id', 'condition', 'status', 'category_id'];
    $errors = [];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("Location: inventory.php");
        exit;
    }

    // Prepare and sanitize data
    $asset_tag = $_POST['asset_tag'] ?? '';
    $device_type = mysqli_real_escape_string($conn, trim($_POST['device_type']));
    $brand_id = (int) $_POST['brand_id'];
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $specifications = mysqli_real_escape_string($conn, trim($_POST['specifications'] ?? ''));

    // Convert empty string to NULL for integer fields
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $location_id = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $category_id = (int) $_POST['category_id'];

    // String fields
    $condition = mysqli_real_escape_string($conn, trim($_POST['condition']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $assigned_user = !empty($_POST['assigned_user']) ? (int) $_POST['assigned_user'] : null;

    // Generate asset tag if not provided
    if (empty($asset_tag)) {
        $year = date("Y");
        $q = mysqli_query($conn, "
            SELECT asset_tag 
            FROM inventory_items 
            WHERE asset_tag LIKE 'AST-$year-%' 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $next = 1;
        if ($q && mysqli_num_rows($q) > 0) {
            $last = mysqli_fetch_assoc($q)['asset_tag'];
            $next = (int) substr($last, -4) + 1;
        }
        $asset_tag = "AST-$year-" . str_pad($next, 4, "0", STR_PAD_LEFT);
    }

    // Prepare the SQL statement
    $stmt = mysqli_prepare($conn, "
        INSERT INTO inventory_items (
            asset_tag,
            device_type,
            brand_id,
            model,
            serial_number,
            specifications,
            department_id,
            location_id,
            `condition`,
            status,
            category_id,
            remarks,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        $_SESSION['error_message'] = 'Prepare failed: ' . mysqli_error($conn);
        header("Location: inventory.php?error=add_failed");
        exit;
    }

    // Bind parameters
    mysqli_stmt_bind_param(
        $stmt,
        "ssisssiissss",
        $asset_tag,
        $device_type,
        $brand_id,
        $model,
        $serial_number,
        $specifications,
        $department_id,
        $location_id,
        $condition,
        $status,
        $category_id,
        $remarks
    );

    if (mysqli_stmt_execute($stmt)) {
        $inventory_id = mysqli_stmt_insert_id($stmt);

        // If user is assigned during creation and status is 'in_use', create assignment record
        if (!empty($assigned_user) && $status == 'in_use') {
            $assignStmt = mysqli_prepare($conn, "
                INSERT INTO device_user_assignments (
                    inventory_id,
                    user_id,
                    assigned_at,
                    status
                ) VALUES (?, ?, NOW(), 'assigned')
            ");
            mysqli_stmt_bind_param($assignStmt, "ii", $inventory_id, $assigned_user);
            mysqli_stmt_execute($assignStmt);
            mysqli_stmt_close($assignStmt);
        }

        $_SESSION['success_message'] = 'Item added successfully!';
        header("Location: inventory.php?success=added");
        exit;
    } else {
        $_SESSION['error_message'] = 'Add failed: ' . mysqli_stmt_error($stmt);
        header("Location: inventory.php?error=add_failed");
        exit;
    }

    mysqli_stmt_close($stmt);
}

// Fetch active users
$users = [];
$result = mysqli_query($conn, "
    SELECT id, firstname, lastname, email, role, status
    FROM users
    WHERE status = 'active'
    ORDER BY firstname ASC
");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}

/* ================== EDIT MODES ================== */
$editMode = isset($_GET['edit']) && is_numeric($_GET['edit']);

/* ================== FETCH ITEM FOR EDIT ================== */
$item = null;
$currentAssignment = null;

if ($editMode) {
    $edit_id = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM inventory_items WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $item = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Get current active assignment
    $assignStmt = mysqli_prepare($conn, "
        SELECT dua.*, u.firstname, u.lastname 
        FROM device_user_assignments dua
        JOIN users u ON dua.user_id = u.id
        WHERE dua.inventory_id = ? AND dua.status = 'assigned'
        ORDER BY dua.assigned_at DESC 
        LIMIT 1
    ");
    mysqli_stmt_bind_param($assignStmt, "i", $edit_id);
    mysqli_stmt_execute($assignStmt);
    $assignResult = mysqli_stmt_get_result($assignStmt);
    $currentAssignment = mysqli_fetch_assoc($assignResult);
    mysqli_stmt_close($assignStmt);
}

/* ================== UPDATE INVENTORY ================== */
if (isset($_POST['update_inventory']) && is_numeric($_POST['id'])) {
    // Validate required fields
    $requiredFields = ['device_type', 'brand_id', 'condition', 'status', 'category_id'];
    $errors = [];

    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        header("Location: inventory.php");
        exit;
    }

    // Prepare and sanitize data
    $id = (int) $_POST['id'];
    $device_type = mysqli_real_escape_string($conn, trim($_POST['device_type']));
    $brand_id = (int) $_POST['brand_id'];
    $model = mysqli_real_escape_string($conn, trim($_POST['model'] ?? ''));
    $serial_number = mysqli_real_escape_string($conn, trim($_POST['serial_number'] ?? ''));
    $specifications = mysqli_real_escape_string($conn, trim($_POST['specifications'] ?? ''));
    $department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
    $location_id = !empty($_POST['location_id']) ? (int) $_POST['location_id'] : null;
    $condition = mysqli_real_escape_string($conn, trim($_POST['condition']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $category_id = (int) $_POST['category_id'];
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));
    $assignedUserId = !empty($_POST['assigned_user']) ? (int) $_POST['assigned_user'] : null;

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        $stmt = mysqli_prepare($conn, "
            UPDATE inventory_items SET
                device_type=?,
                brand_id=?,
                model=?,
                serial_number=?,
                specifications=?,
                department_id=?,
                location_id=?,
                `condition`=?,
                status=?,
                category_id=?,
                remarks=?
            WHERE id=?
        ");

        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $stmt,
            "sisssiissssi",
            $device_type,
            $brand_id,
            $model,
            $serial_number,
            $specifications,
            $department_id,
            $location_id,
            $condition,
            $status,
            $category_id,
            $remarks,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Update failed: " . mysqli_stmt_error($stmt));
        }

        // Handle assignment changes
        if (!empty($assignedUserId) && $status == 'in_use') {
            // Check if there's already an active assignment for this device
            $checkStmt = mysqli_prepare($conn, "
                SELECT id FROM device_user_assignments 
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($checkStmt, "i", $id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);

            if (mysqli_num_rows($checkResult) > 0) {
                // Update existing active assignment
                $updateAssignStmt = mysqli_prepare($conn, "
                    UPDATE device_user_assignments 
                    SET user_id = ?
                    WHERE inventory_id = ? AND status = 'assigned'
                ");
                mysqli_stmt_bind_param($updateAssignStmt, "ii", $assignedUserId, $id);
                if (!mysqli_stmt_execute($updateAssignStmt)) {
                    throw new Exception("Assignment update failed: " . mysqli_stmt_error($updateAssignStmt));
                }
                mysqli_stmt_close($updateAssignStmt);
            } else {
                // Create new assignment
                $assignStmt = mysqli_prepare($conn, "
                    INSERT INTO device_user_assignments (
                        inventory_id,
                        user_id,
                        assigned_at,
                        status
                    ) VALUES (?, ?, NOW(), 'assigned')
                ");
                mysqli_stmt_bind_param($assignStmt, "ii", $id, $assignedUserId);
                if (!mysqli_stmt_execute($assignStmt)) {
                    throw new Exception("Assignment creation failed: " . mysqli_stmt_error($assignStmt));
                }
                mysqli_stmt_close($assignStmt);
            }
            mysqli_stmt_close($checkStmt);
        } elseif (empty($assignedUserId) || $status != 'in_use') {
            // If no user assigned or status not 'in_use', end any active assignments
            $endAssignStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endAssignStmt, "i", $id);
            if (!mysqli_stmt_execute($endAssignStmt)) {
                throw new Exception("Assignment ending failed: " . mysqli_stmt_error($endAssignStmt));
            }
            mysqli_stmt_close($endAssignStmt);
        }

        // Commit transaction
        mysqli_commit($conn);
        $_SESSION['success_message'] = 'Item updated successfully!';
        header("Location: inventory.php?success=updated");
        exit;

    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header("Location: inventory.php?error=update_failed");
        exit;
    } finally {
        if (isset($stmt)) {
            mysqli_stmt_close($stmt);
        }
    }
}

/* ================== DEVICE ACTIONS ================== */
if (isset($_POST['device_action']) && isset($_POST['device_id']) && is_numeric($_POST['device_id'])) {
    $device_id = (int) $_POST['device_id'];
    $action = $_POST['action'] ?? '';

    // Debug logging
    error_log("Device action triggered - Device ID: $device_id, Action: $action");

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        if ($action === 'assign') {
            // ASSIGN DEVICE (New Assignment)
            $userId = !empty($_POST['assign_user']) ? (int) $_POST['assign_user'] : null;
            $department_id = !empty($_POST['assign_department']) ? (int) $_POST['assign_department'] : null;
            $location_id = !empty($_POST['assign_location']) ? (int) $_POST['assign_location'] : null;
            $assign_notes = mysqli_real_escape_string($conn, $_POST['assign_notes'] ?? '');

            if (empty($userId)) {
                throw new Exception('Please select a user to assign the device to.');
            }

            // First, end any existing active assignment for this device
            $endStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endStmt, "i", $device_id);
            mysqli_stmt_execute($endStmt);
            mysqli_stmt_close($endStmt);

            // Create new assignment (users can have multiple devices)
            $assignStmt = mysqli_prepare($conn, "
                INSERT INTO device_user_assignments (
                    inventory_id,
                    user_id,
                    assigned_at,
                    status
                ) VALUES (?, ?, NOW(), 'assigned')
            ");
            mysqli_stmt_bind_param($assignStmt, "ii", $device_id, $userId);
            mysqli_stmt_execute($assignStmt);
            mysqli_stmt_close($assignStmt);

            // Update inventory status and location/department
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_use',
                    department_id = ?,
                    location_id = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "iii", $department_id, $location_id, $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks if notes provided
            if (!empty($assign_notes)) {
                $notesStmt = mysqli_prepare($conn, "
                    UPDATE inventory_items 
                    SET remarks = CONCAT(IFNULL(remarks, ''), '\nAssigned on ', NOW(), ' to user ID ', ?, ': ', ?)
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($notesStmt, "isi", $userId, $assign_notes, $device_id);
                mysqli_stmt_execute($notesStmt);
                mysqli_stmt_close($notesStmt);
            }

            $_SESSION['success_message'] = 'Device assigned successfully!';

        } elseif ($action === 'reassign') {
            // REASSIGN DEVICE (Change current assignment)
            $userId = !empty($_POST['reassign_user']) ? (int) $_POST['reassign_user'] : null;
            $department_id = !empty($_POST['reassign_department']) ? (int) $_POST['reassign_department'] : null;
            $location_id = !empty($_POST['reassign_location']) ? (int) $_POST['reassign_location'] : null;
            $reassign_notes = mysqli_real_escape_string($conn, $_POST['reassign_notes'] ?? '');

            if (empty($userId)) {
                throw new Exception('Please select a user to reassign the device to.');
            }

            // Get current assignment details before changing
            $currentStmt = mysqli_prepare($conn, "
                SELECT dua.user_id, u.firstname, u.lastname 
                FROM device_user_assignments dua
                JOIN users u ON dua.user_id = u.id
                WHERE dua.inventory_id = ? AND dua.status = 'assigned'
            ");
            mysqli_stmt_bind_param($currentStmt, "i", $device_id);
            mysqli_stmt_execute($currentStmt);
            $currentResult = mysqli_stmt_get_result($currentStmt);
            $currentAssignment = mysqli_fetch_assoc($currentResult);
            mysqli_stmt_close($currentStmt);

            $previousUserId = $currentAssignment['user_id'] ?? null;
            $previousUserName = ($currentAssignment['firstname'] ?? '') . ' ' . ($currentAssignment['lastname'] ?? 'Unknown');

            // End the current active assignment
            $endStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'reassigned', returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endStmt, "i", $device_id);
            mysqli_stmt_execute($endStmt);
            mysqli_stmt_close($endStmt);

            // Create new assignment
            $assignStmt = mysqli_prepare($conn, "
                INSERT INTO device_user_assignments (
                    inventory_id,
                    user_id,
                    assigned_at,
                    status
                ) VALUES (?, ?, NOW(), 'assigned')
            ");
            mysqli_stmt_bind_param($assignStmt, "ii", $device_id, $userId);
            mysqli_stmt_execute($assignStmt);
            mysqli_stmt_close($assignStmt);

            // Update inventory status and location/department
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_use',
                    department_id = ?,
                    location_id = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "iii", $department_id, $location_id, $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            $notesStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET remarks = CONCAT(IFNULL(remarks, ''), '\nReassigned on ', NOW(), ' from user ID ', ?, ' to user ID ', ?, ': ', ?)
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($notesStmt, "iisi", $previousUserId, $userId, $reassign_notes, $device_id);
            mysqli_stmt_execute($notesStmt);
            mysqli_stmt_close($notesStmt);

            $_SESSION['success_message'] = 'Device reassigned successfully!';

        } elseif ($action === 'retrieve') {
            // RETRIEVE DEVICE (to store)
            $reason = mysqli_real_escape_string($conn, $_POST['retrieve_reason'] ?? '');

            // Get current assignment before retrieving
            $currentStmt = mysqli_prepare($conn, "
                SELECT dua.user_id, u.firstname, u.lastname 
                FROM device_user_assignments dua
                JOIN users u ON dua.user_id = u.id
                WHERE dua.inventory_id = ? AND dua.status = 'assigned'
            ");
            mysqli_stmt_bind_param($currentStmt, "i", $device_id);
            mysqli_stmt_execute($currentStmt);
            $currentResult = mysqli_stmt_get_result($currentStmt);
            $currentAssignment = mysqli_fetch_assoc($currentResult);
            mysqli_stmt_close($currentStmt);

            $previousUserId = $currentAssignment['user_id'] ?? null;

            // End the active assignment
            $stmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', 
                    returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($stmt, "i", $device_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Update inventory status to in_storage
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_storage'
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "i", $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            $notesStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET remarks = CONCAT(IFNULL(remarks, ''), '\nRetrieved to store on ', NOW(), ' from user ID ', ?, ': ', ?)
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($notesStmt, "isi", $previousUserId, $reason, $device_id);
            mysqli_stmt_execute($notesStmt);
            mysqli_stmt_close($notesStmt);

            $_SESSION['success_message'] = 'Device retrieved to store successfully!';

        } elseif ($action === 'retire') {
            // RETIRE DEVICE
            // First end any active assignment
            $endStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endStmt, "i", $device_id);
            mysqli_stmt_execute($endStmt);
            mysqli_stmt_close($endStmt);

            // Then retire the device
            $stmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'retired'
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $device_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $_SESSION['success_message'] = 'Device retired successfully!';
        } else {
            throw new Exception('Invalid action selected.');
        }

        // Commit transaction
        mysqli_commit($conn);
        header("Location: inventory.php?success=action_completed");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header("Location: inventory.php?error=action_failed");
        exit;
    }
}

/* ================== DELETE INVENTORY ================== */
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // First delete assignments
        $deleteAssignStmt = mysqli_prepare($conn, "DELETE FROM device_user_assignments WHERE inventory_id = ?");
        mysqli_stmt_bind_param($deleteAssignStmt, "i", $id);
        mysqli_stmt_execute($deleteAssignStmt);
        mysqli_stmt_close($deleteAssignStmt);

        // Then delete inventory item
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM inventory_items WHERE id = ?");
        mysqli_stmt_bind_param($deleteStmt, "i", $id);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        mysqli_commit($conn);
        $_SESSION['success_message'] = 'Device deleted successfully!';
        header("Location: inventory.php?msg=deleted");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header("Location: inventory.php?error=delete_failed");
        exit;
    }
}

/* ================== AUTO ASSET TAG ================== */
$year = date("Y");
$q = mysqli_query($conn, "
    SELECT asset_tag 
    FROM inventory_items 
    WHERE asset_tag LIKE 'AST-$year-%' 
    ORDER BY id DESC 
    LIMIT 1
");
$next = 1;
if ($q && mysqli_num_rows($q) > 0) {
    $last = mysqli_fetch_assoc($q)['asset_tag'];
    $next = (int) substr($last, -4) + 1;
}
$asset_tag_preview = "AST-$year-" . str_pad($next, 4, "0", STR_PAD_LEFT);

/* ================== ALLOWED STATUSES ================== */
$allowedStatuses = ['active', 'in_storage', 'in_use', 'repairing', 'faulty', 'retired'];
$statusLabels = [
    'active' => 'Active',
    'retired' => 'Retired',
    'in_storage' => 'Store',
    'repairing' => 'Repairing',
    'in_use' => 'In Use',
    'faulty' => 'Faulty'
];

/* ================== DROPDOWNS ================== */
$categoriesArr = [];
$categoriesResult = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name");
if ($categoriesResult) {
    $categoriesArr = mysqli_fetch_all($categoriesResult, MYSQLI_ASSOC);
}

$brandsArr = [];
$brandsResult = mysqli_query($conn, "SELECT id, brand_name FROM brands ORDER BY brand_name");
if ($brandsResult) {
    $brandsArr = mysqli_fetch_all($brandsResult, MYSQLI_ASSOC);
}

$departmentsArr = [];
$departmentsResult = mysqli_query($conn, "SELECT id, department_name FROM departments ORDER BY department_name");
if ($departmentsResult) {
    $departmentsArr = mysqli_fetch_all($departmentsResult, MYSQLI_ASSOC);
}

$locationsArr = [];
$locationsResult = mysqli_query($conn, "SELECT id, location_name FROM locations ORDER BY location_name");
if ($locationsResult) {
    $locationsArr = mysqli_fetch_all($locationsResult, MYSQLI_ASSOC);
}

/* ================== FETCH DISTINCT STATUSES ================== */
$statuses = [];
$statusQuery = mysqli_query($conn, "SELECT DISTINCT status FROM inventory_items ORDER BY status ASC");
if ($statusQuery && mysqli_num_rows($statusQuery) > 0) {
    while ($row = mysqli_fetch_assoc($statusQuery)) {
        if (in_array($row['status'], $allowedStatuses)) {
            $statuses[] = $row['status'];
        }
    }
}
// If table empty, fallback to allowed statuses
if (empty($statuses)) {
    $statuses = $allowedStatuses;
}

/* ================== LIST INVENTORY WITH JOIN FOR ASSIGNMENTS ================== */
$where = [];
$params = [];
$paramTypes = '';

if (!empty($_GET['search'])) {
    $where[] = "(i.asset_tag LIKE ? 
                OR i.device_type LIKE ? 
                OR b.brand_name LIKE ? 
                OR i.model LIKE ? 
                OR CONCAT(u.firstname, ' ', u.lastname) LIKE ? 
                OR d.department_name LIKE ? 
                OR l.location_name LIKE ?)";

    $searchTerm = '%' . $_GET['search'] . '%';

    // Add 7 times for the 7 placeholders
    $params = array_merge($params, [
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);

    $paramTypes .= str_repeat('s', 7);
}

if (!empty($_GET['status'])) {
    $where[] = "i.status=?";
    $params[] = $_GET['status'];
    $paramTypes .= 's';
}

if (!empty($_GET['department'])) {
    $where[] = "i.department_id = ?";
    $params[] = (int) $_GET['department'];
    $paramTypes .= 'i';
}

if (!empty($_GET['category'])) {
    $where[] = "i.category_id=?";
    $params[] = (int) $_GET['category'];
    $paramTypes .= 'i';
}

if (!empty($_GET['location'])) {
    $where[] = "i.location_id = ?";
    $params[] = (int) $_GET['location'];
    $paramTypes .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$orderBy = 'i.id';
$orderDir = (($_GET['sort'] ?? '') === 'asc') ? 'ASC' : 'DESC';

/* ================== PAGINATION ================== */
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 25, 50, 100])
    ? (int) $_GET['limit']
    : 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

/* ================== COUNT TOTAL RECORDS ================== */
$countQuery = mysqli_prepare($conn, "
    SELECT COUNT(*) as total
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    $whereSql
");

if ($params) {
    mysqli_stmt_bind_param($countQuery, $paramTypes, ...$params);
}
mysqli_stmt_execute($countQuery);
$countResult = mysqli_stmt_get_result($countQuery);
$totalRecords = $countResult ? mysqli_fetch_assoc($countResult)['total'] ?? 0 : 0;
mysqli_stmt_close($countQuery);
$totalPages = ceil($totalRecords / $limit);

/* ================== FETCH PAGINATED INVENTORY WITH ASSIGNMENTS ================== */
$sql = "
    SELECT 
        i.*, 
        c.category_name, 
        b.brand_name, 
        d.department_name, 
        l.location_name,
        u.firstname,
        u.lastname,
        dua.user_id as assigned_user_id,
        dua.assigned_at,
        dua.returned_at
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    LEFT JOIN (
        SELECT dua1.* 
        FROM device_user_assignments dua1
        WHERE dua1.status = 'assigned'
    ) dua ON i.id = dua.inventory_id
    LEFT JOIN users u ON dua.user_id = u.id
    $whereSql
    ORDER BY $orderBy $orderDir
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$params[] = $limit;
$params[] = $offset;
$paramTypes .= "ii";

$listQuery = mysqli_prepare($conn, $sql);
if ($params) {
    mysqli_stmt_bind_param($listQuery, $paramTypes, ...$params);
}
mysqli_stmt_execute($listQuery);
$list = mysqli_stmt_get_result($listQuery);

/* ================== ACTIVE FILTER TAGS ================== */
$activeFilters = [];
if (!empty($_GET['search'])) {
    $activeFilters[] = ['label' => 'Search: ' . htmlspecialchars($_GET['search']), 'param' => 'search'];
}
if (!empty($_GET['status'])) {
    $activeFilters[] = ['label' => 'Status: ' . htmlspecialchars($statusLabels[$_GET['status']] ?? $_GET['status']), 'param' => 'status'];
}
if (!empty($_GET['category'])) {
    $catName = '';
    foreach ($categoriesArr as $c) {
        if ($c['id'] == $_GET['category']) {
            $catName = $c['category_name'];
        }
    }
    $activeFilters[] = ['label' => 'Category: ' . htmlspecialchars($catName), 'param' => 'category'];
}

if (!empty($_GET['department'])) {
    $deptName = '';
    foreach ($departmentsArr as $d) {
        if ($d['id'] == $_GET['department']) {
            $deptName = $d['department_name'];
            break;
        }
    }

    $activeFilters[] = [
        'label' => 'Department: ' . htmlspecialchars($deptName),
        'param' => 'department'
    ];
}

if (!empty($_GET['location'])) {
    $locName = '';
    foreach ($locationsArr as $l) {
        if ($l['id'] == $_GET['location']) {
            $locName = $l['location_name'];
            break;
        }
    }

    $activeFilters[] = [
        'label' => 'Location: ' . htmlspecialchars($locName),
        'param' => 'location'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .animate-slide-down {
            animation: slideDown 0.3s ease-out;
        }

        .animate-slide-in-right {
            animation: slideInRight 0.3s ease-out;
        }

        .animate-slide-out-right {
            animation: slideOutRight 0.3s ease-out;
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        /* Toast Notification Styles */
        #toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            max-width: 400px;
            width: 100%;
        }

        .toast {
            position: relative;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            overflow: hidden;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast.hide {
            transform: translateX(100%);
            opacity: 0;
        }

        .toast-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-left: 4px solid #065f46;
        }

        .toast-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-left: 4px solid #7f1d1d;
        }

        .toast-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border-left: 4px solid #92400e;
        }

        .toast-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-left: 4px solid #1e40af;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
            font-size: 0.875rem;
        }

        .toast-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .toast-message {
            opacity: 0.9;
        }

        .toast-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 1.75rem;
            height: 1.75rem;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .toast-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.1);
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.5);
            width: 100%;
            transform-origin: left;
            animation: progress 5s linear forwards;
        }

        @keyframes progress {
            from {
                transform: scaleX(1);
            }

            to {
                transform: scaleX(0);
            }
        }

        /* Improved Table Styling */
        .table-container {
            width: 100%;
            overflow: visible;
            position: relative;
        }

        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
            padding: 1rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
            white-space: nowrap;
            z-index: 10;
        }

        .data-table tbody tr {
            transition: all 0.15s ease;
            position: relative;
        }

        .data-table tbody tr:hover {
            background-color: #f9fafb;
        }

        .data-table tbody td {
            padding: 1rem 1rem;
            font-size: 0.875rem;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .compact-column {
            max-width: 120px;
            min-width: 120px;
        }

        .compact-column-sm {
            max-width: 100px;
            min-width: 100px;
        }

        .compact-column-xs {
            max-width: 90px;
            min-width: 90px;
        }

        .actions-column {
            max-width: 140px;
            min-width: 140px;
            white-space: nowrap;
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        /* Hide scrollbar for IE, Edge and Firefox */
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .text-ellipsis {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            border: 1px solid;
            white-space: nowrap;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            outline: none;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .asset-tag-badge {
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }

        .search-glow:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-tag {
            transition: all 0.2s ease;
        }

        .filter-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        /* Dropdown styling - DYNAMIC POSITIONING */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 220px;
            box-shadow: 0px 8px 25px 0px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            border-radius: 10px;
            padding: 8px 0;
            right: 0;
            margin-bottom: 5px;
            border: 1px solid #e5e7eb;
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-content.show {
            display: block;
            animation: slideDown 0.2s ease-out;
        }

        /* Top positioning class */
        .dropdown-content.top {
            bottom: 100%;
            top: auto;
        }

        /* Bottom positioning class */
        .dropdown-content.bottom {
            top: 100%;
            bottom: auto;
            margin-top: 5px;
            margin-bottom: 0;
        }

        .dropdown-item {
            color: #374151;
            padding: 10px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .dropdown-item:hover {
            background-color: #f3f4f6;
            border-left-color: #3b82f6;
            padding-left: 20px;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 6px 0;
        }

        /* Horizontal Tab Styling */
        .tab-active {
            background-color: #f3f4f6;
            color: #1f2937;
            border-bottom: 3px solid #3b82f6;
            font-weight: 600;
        }

        .tab-inactive {
            background-color: white;
            color: #6b7280;
            border-bottom: 3px solid transparent;
        }

        .tab-inactive:hover {
            background-color: #f9fafb;
            color: #4b5563;
        }

        .tab-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f9fafb;
            color: #9ca3af;
        }

        .tab-disabled:hover {
            background-color: #f9fafb;
            color: #9ca3af;
        }

        .action-tab-content {
            animation: fadeIn 0.3s ease;
        }

        /* Tab icon colors */
        #assignTab.tab-active i {
            color: #10b981;
        }

        #reassignTab.tab-active i {
            color: #3b82f6;
        }

        #retrieveTab.tab-active i {
            color: #f59e0b;
        }

        #retireTab.tab-active i {
            color: #6b7280;
        }

        /* Read-only input styling */
        .readonly-input {
            background-color: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        /* Disabled option styling */
        .action-option.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f9fafb;
        }

        .action-option.disabled:hover {
            background-color: #f9fafb;
            border-left-color: transparent;
            padding-left: 16px;
        }

        /* Confirmation Modal */
        .confirmation-modal {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }

        .confirmation-content {
            animation: slideDown 0.3s ease-out;
        }

        /* Modal z-index adjustments */
        .modal-backdrop {
            z-index: 50;
        }

        .modal-content {
            z-index: 60;
        }

        /* Quick Actions Modal */
        .quick-action-modal {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
        }

        /* Custom scrollbar for dropdowns */
        .dropdown-content::-webkit-scrollbar {
            width: 6px;
        }

        .dropdown-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .dropdown-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .dropdown-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container"></div>

    <div class="flex">
        <?php include "sidebar.php"; ?>
        <main id="mainContent" class="w-full p-6">
            <!-- ================= HEADER ================= -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Device Inventory</h1>
                    <p class="text-gray-500">Manage all inventory items</p>
                </div>
                <button onclick="openModal('addModal')"
                    class="bg-blue-600 text-white px-4 py-2 text-sm rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa fa-plus text-xs mr-1"></i> Add Item
                </button>
            </div>

            <!-- Display Success/Error Messages (Hidden - will be converted to toasts) -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="success-toast" class="hidden">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div id="error-toast" class="hidden">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['form_errors']) && !empty($_SESSION['form_errors'])): ?>
                <div id="warning-toast" class="hidden">
                    Please fix the following errors:<br>
                    <?php foreach ($_SESSION['form_errors'] as $error): ?>
                        • <?= htmlspecialchars($error) ?><br>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION['form_errors']); ?>
            <?php endif; ?>

            <!-- Filters and Search -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                <form method="GET" class="w-full">
                    <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-end">
                        <!-- Search Bar -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Search</label>
                            <div class="relative">
                                <i
                                    class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input id="searchInput" onkeyup="searchTable()" type="text" name="search"
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                    placeholder="Search by asset, type, brand, model, or user..." autocomplete="off"
                                    class="w-full pl-11 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-gray-50 focus:bg-white">
                            </div>
                        </div>

                        <!-- Location Filter -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Location</label>
                            <div class="relative">
                                <i
                                    class="fas fa-map-marker-alt absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                                <select name="location"
                                    class="w-full pl-11 pr-10 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none bg-white cursor-pointer hover:border-gray-300">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locationsArr as $l): ?>
                                        <option value="<?= $l['id'] ?>" <?= ($_GET['location'] ?? '') == $l['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($l['location_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i
                                    class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-600 mb-1.5 ml-1">Status</label>
                            <div class="relative">
                                <i
                                    class="fas fa-flag absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                                <select name="status" id="statusFilter"
                                    class="w-full pl-11 pr-10 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all appearance-none bg-white cursor-pointer hover:border-gray-300">
                                    <option value="">All Status</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i
                                    class="fas fa-chevron-down absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <button type="submit"
                                class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all duration-200 inline-flex items-center font-medium shadow-sm hover:shadow-md whitespace-nowrap">
                                <i class="fas fa-filter mr-2"></i>
                                <span>Apply</span>
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>"
                                class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 inline-flex items-center font-medium shadow-sm hover:shadow whitespace-nowrap">
                                <i class="fas fa-redo mr-2"></i>
                                <span>Reset</span>
                            </a>
                            <!-- Export Button (inline) -->
                            <button type="button" onclick="window.location.href='export_assignments.php'"
                                class="px-6 py-3 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-700 rounded-xl hover:from-green-100 hover:to-emerald-100 hover:border-green-300 transition-all duration-200 inline-flex items-center gap-2 shadow-sm hover:shadow font-medium whitespace-nowrap">
                                <i class="fas fa-download"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ================= TABLE ================= -->
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden relative">
                <div class="table-container">
                    <table class="data-table">
                        <!-- Table Header -->
                        <thead>
                            <tr>
                                <th class="compact-column-xs">Asset</th>
                                <th class="compact-column-sm">Type</th>
                                <th class="compact-column-sm">Brand</th>
                                <th class="compact-column-sm">Model</th>
                                <th class="compact-column">User</th>
                                <th class="compact-column-sm">Location</th>
                                <th class="compact-column-xs">Status</th>
                                <th class="compact-column-sm">Category</th>
                                <th class="actions-column">Actions</th>
                            </tr>
                        </thead>

                        <!-- Table Body -->
                        <tbody id="inventoryTableBody" class="divide-y divide-gray-100">
                            <?php
                            $statusColors = [
                                'active' => 'bg-green-100 text-green-700 border-green-200',
                                'in_use' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                'in_storage' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                'repairing' => 'bg-gray-100 text-gray-700 border-gray-200',
                                'faulty' => 'bg-pink-100 text-pink-700 border-pink-200',
                                'retired' => 'bg-red-100 text-red-700 border-red-200'
                            ];

                            $statusLabels = [
                                'active' => 'Active',
                                'in_use' => 'In Use',
                                'in_storage' => 'Store',
                                'repairing' => 'Repairing',
                                'faulty' => 'Faulty',
                                'retired' => 'Retired'
                            ];
                            ?>

                            <?php if (!$list || mysqli_num_rows($list) === 0): ?>
                                <tr>
                                    <td colspan="9" class="py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div
                                                class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i class="fas fa-search text-4xl text-gray-400"></i>
                                            </div>
                                            <p class="text-gray-400 font-medium">No inventory items found</p>
                                            <p class="text-xs text-gray-400">Try different search criteria or add new items
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php while ($row = mysqli_fetch_assoc($list)): ?>
                                    <?php
                                    $fullName = '';
                                    $isAssigned = false;
                                    if (!empty($row['firstname']) && !empty($row['lastname'])) {
                                        $fullName = $row['firstname'] . ' ' . $row['lastname'];
                                        $isAssigned = true;
                                    }
                                    ?>
                                    <tr>
                                        <!-- ASSET TAG -->
                                        <td>
                                            <span class="asset-tag-badge"
                                                title="Asset Tag: <?= htmlspecialchars($row['asset_tag']) ?>">
                                                <?= htmlspecialchars($row['asset_tag']) ?>
                                            </span>
                                        </td>

                                        <!-- TYPE -->
                                        <td>
                                            <div class="text-gray-700 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($row['device_type']) ?>">
                                                <?= htmlspecialchars($row['device_type']) ?>
                                            </div>
                                        </td>

                                        <!-- BRAND -->
                                        <td>
                                            <div class="text-gray-700 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?>">
                                                <?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?>
                                            </div>
                                        </td>

                                        <!-- MODEL -->
                                        <td>
                                            <div class="text-gray-700 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($row['model']) ?>">
                                                <?= htmlspecialchars($row['model']) ?>
                                            </div>
                                        </td>

                                        <!-- USER -->
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php if ($isAssigned): ?>
                                                    <div class="user-avatar" title="<?= htmlspecialchars($fullName) ?>">
                                                        <?= strtoupper(substr($fullName, 0, 1)) ?>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-gray-700 text-sm font-medium text-ellipsis"
                                                            title="<?= htmlspecialchars($fullName) ?>">
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </p>
                                                        <?php if (!empty($row['assigned_at'])): ?>
                                                            <p class="text-xs text-gray-500">
                                                                <?= date('M d', strtotime($row['assigned_at'])) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="user-avatar bg-blue-400 text-gray-500">
                                                        <i class="fas fa-user-slash text-white text-xs"></i>
                                                    </div>
                                                    <span class="text-gray-500 text-sm">Unassigned</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- LOCATION -->
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-map-marker-alt text-gray-400 text-xs"></i>
                                                <span class="text-gray-600 text-sm text-ellipsis"
                                                    title="<?= htmlspecialchars($row['location_name'] ?? 'N/A') ?>">
                                                    <?= htmlspecialchars($row['location_name'] ?? 'N/A') ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- STATUS -->
                                        <td>
                                            <?php
                                            $statusClass = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                            ?>
                                            <span class="status-badge <?= $statusClass ?>"
                                                title="Status: <?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?>">
                                                <?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?>
                                            </span>
                                        </td>

                                        <!-- CATEGORY -->
                                        <td>
                                            <div class="text-gray-600 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($row['category_name'] ?? 'N/A') ?>">
                                                <?= htmlspecialchars($row['category_name'] ?? 'N/A') ?>
                                            </div>
                                        </td>

                                        <!-- ACTIONS -->
                                        <td>
                                            <div class="flex gap-1 relative">
                                                <!-- VIEW -->
                                                <button
                                                    onclick='openViewModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'
                                                    class="action-btn bg-blue-500 text-white hover:bg-blue-600"
                                                    title="View Details">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>

                                                <!-- EDIT -->
                                                <button onclick="openModal('editModal<?= $row['id'] ?>')"
                                                    class="action-btn bg-green-500 text-white hover:bg-green-600"
                                                    title="Edit Device">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>

                                                <!-- ACTION DROPDOWN -->
                                                <div class="dropdown">
                                                    <button
                                                        class="action-btn bg-purple-500 text-white hover:bg-purple-600 dropdown-toggle"
                                                        title="Device Actions"
                                                        onclick="toggleDropdown(event, 'dropdown-<?= $row['id'] ?>')"
                                                        data-dropdown="dropdown-<?= $row['id'] ?>">
                                                        <i class="fas fa-cog text-xs"></i>
                                                    </button>
                                                    <div class="dropdown-content" id="dropdown-<?= $row['id'] ?>">
                                                        <!-- Assign Option (only for unassigned devices) -->
                                                        <?php if (!$isAssigned && $row['status'] !== 'retired'): ?>
                                                            <div class="dropdown-item"
                                                                onclick="openActionModal(<?= $row['id'] ?>, 'assign', '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>')">
                                                                <i class="fas fa-user-plus text-green-600"></i>
                                                                <span>Assign Device</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="dropdown-item action-option disabled">
                                                                <i class="fas fa-user-plus text-gray-400"></i>
                                                                <span>Assign Device</span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Reassign Option (only for assigned devices) -->
                                                        <?php if ($isAssigned && $row['status'] !== 'retired'): ?>
                                                            <div class="dropdown-item"
                                                                onclick="openActionModal(<?= $row['id'] ?>, 'reassign', '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>')">
                                                                <i class="fas fa-user-exchange text-blue-600"></i>
                                                                <span>Reassign Device</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="dropdown-item action-option disabled">
                                                                <i class="fas fa-user-exchange text-gray-400"></i>
                                                                <span>Reassign Device</span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <!-- Retrieve Option (only for assigned devices) -->
                                                        <?php if ($isAssigned && $row['status'] !== 'retired'): ?>
                                                            <div class="dropdown-item"
                                                                onclick="openActionModal(<?= $row['id'] ?>, 'retrieve', '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>')">
                                                                <i class="fas fa-arrow-left text-amber-600"></i>
                                                                <span>Retrieve to Store</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="dropdown-item action-option disabled">
                                                                <i class="fas fa-arrow-left text-gray-400"></i>
                                                                <span>Retrieve to Store</span>
                                                            </div>
                                                        <?php endif; ?>

                                                        <div class="dropdown-divider"></div>

                                                        <!-- Retire Option -->
                                                        <?php if ($row['status'] !== 'retired'): ?>
                                                            <div class="dropdown-item"
                                                                onclick="openActionModal(<?= $row['id'] ?>, 'retire', '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>')">
                                                                <i class="fas fa-archive text-gray-600"></i>
                                                                <span>Retire Device</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="dropdown-item action-option disabled">
                                                                <i class="fas fa-archive text-gray-400"></i>
                                                                <span>Retire Device</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- DELETE -->
                                                <button onclick="openDeleteModal(<?= (int) $row['id'] ?>)"
                                                    class="action-btn bg-red-500 text-white hover:bg-red-600"
                                                    title="Delete Device">
                                                    <i class="fas fa-trash text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php mysqli_stmt_close($listQuery); ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================= PAGINATION ================= -->
            <?php if ($totalPages > 1): ?>
                <?php
                // Build query string with all current filters
                $queryParams = $_GET;
                unset($queryParams['page']); // Remove page from params to rebuild
            
                // Build the base URL with all parameters
                $baseUrl = '?' . (!empty($queryParams) ? http_build_query($queryParams) . '&' : '');
                ?>

                <div class="mt-8 pt-6 border-t border-gray-200">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <!-- Results Count -->
                        <div class="text-sm text-gray-600">
                            Showing <span
                                class="font-medium"><?= min($limit, $totalRecords - (($page - 1) * $limit)) ?></span> of
                            <span class="font-medium"><?= $totalRecords ?></span> inventory items
                        </div>

                        <!-- Pagination Controls -->
                        <div class="flex flex-col items-center gap-4">
                            <!-- Page Numbers -->
                            <div class="flex flex-wrap items-center justify-center gap-1">
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                        <i class="fas fa-chevron-left text-sm"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                // Smart pagination: Show first page, last page, and pages around current
                                $showDotsStart = false;
                                $showDotsEnd = false;

                                for ($i = 1; $i <= $totalPages; $i++):
                                    // Show first page, last page, and pages around current (within 2 pages)
                                    $shouldShow = false;

                                    if ($i == 1 || $i == $totalPages) {
                                        $shouldShow = true;
                                    } elseif ($i >= $page - 2 && $i <= $page + 2) {
                                        $shouldShow = true;
                                    }

                                    if ($shouldShow):
                                        if ($i == 1 && $page > 4):
                                            $showDotsStart = true;
                                            ?>
                                            <a href="<?= $baseUrl ?>page=1"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                1
                                            </a>
                                            <?php if ($showDotsStart): ?>
                                                <span class="px-2 text-gray-400">...</span>
                                            <?php endif; ?>
                                        <?php elseif ($i == $totalPages && $page < $totalPages - 3):
                                            $showDotsEnd = true;
                                            if ($showDotsEnd): ?>
                                                <span class="px-2 text-gray-400">...</span>
                                            <?php endif; ?>
                                            <a href="<?= $baseUrl ?>page=<?= $totalPages ?>"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                                                <?= $totalPages ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                                class="px-3 py-2 rounded-lg transition-colors font-medium text-sm <?= $i == $page
                                                    ? 'bg-gradient-to-r from-blue-600 to-blue-600 text-white shadow-lg'
                                                    : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 shadow-sm hover:shadow' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>"
                                        class="px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm hover:shadow">
                                        <i class="fas fa-chevron-right text-sm"></i>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- Page Info -->
                            <p class="text-center text-sm text-gray-500">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </p>
                        </div>

                        <!-- Items per page selector -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show:</span>
                            <select onchange="changeItemsPerPage(this)"
                                class="text-sm bg-white border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                            <span class="text-sm text-gray-600">per page</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================= EDIT MODALS ================= -->
            <?php
            if ($list && mysqli_num_rows($list) > 0) {
                mysqli_data_seek($list, 0); // Reset pointer
            
                while ($row = mysqli_fetch_assoc($list)) {
                    // Fetch assignment for this device
                    $assignStmt = mysqli_prepare($conn, "
                        SELECT dua.*, u.firstname, u.lastname
                        FROM device_user_assignments dua
                        JOIN users u ON dua.user_id = u.id
                        WHERE dua.inventory_id = ? AND dua.status = 'assigned'
                        ORDER BY dua.assigned_at DESC
                        LIMIT 1
                    ");
                    mysqli_stmt_bind_param($assignStmt, "i", $row['id']);
                    mysqli_stmt_execute($assignStmt);
                    $assignResult = mysqli_stmt_get_result($assignStmt);
                    $assignment = mysqli_fetch_assoc($assignResult);
                    mysqli_stmt_close($assignStmt);

                    $currentUserId = $assignment['user_id'] ?? '';
                    ?>
                    <div id="editModal<?= $row['id'] ?>"
                        class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4 modal-backdrop"
                        onclick="closeModalOnBackdrop(event, 'editModal<?= $row['id'] ?>')">

                        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden modal-content"
                            onclick="event.stopPropagation()">

                            <!-- Modal Header -->
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-edit text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-white">Edit Inventory Item</h2>
                                        <p class="text-blue-100 text-sm">
                                            <?= htmlspecialchars($row['asset_tag']) ?>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                    class="text-white/80 hover:text-white transition">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                                <form method="POST" action="inventory.php" id="editForm<?= $row['id'] ?>">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                    <!-- BASIC INFO -->
                                    <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-blue-600"></i>
                                            Basic Information
                                        </h3>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Asset Tag</label>
                                                <input readonly name="asset_tag"
                                                    value="<?= htmlspecialchars($row['asset_tag']) ?>"
                                                    class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Device Name
                                                    *</label>
                                                <input name="device_type" required
                                                    value="<?= htmlspecialchars($row['device_type']) ?>"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Brand *</label>
                                                <select name="brand_id" required
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <option value="">Select Brand</option>
                                                    <?php foreach ($brandsArr as $b): ?>
                                                        <option value="<?= $b['id'] ?>" <?= $row['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($b['brand_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                                <input name="model" value="<?= htmlspecialchars($row['model']) ?>"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Serial
                                                    Number</label>
                                                <input name="serial_number"
                                                    value="<?= htmlspecialchars($row['serial_number']) ?>"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                            </div>

                                            <div class="md:col-span-2">
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                                <input name="specifications"
                                                    value="<?= htmlspecialchars($row['specifications']) ?>"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ASSIGNMENT -->
                                    <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-user-tag text-blue-600"></i>
                                            Assignment Details
                                        </h3>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                                <select name="department_id"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <option value="">Select Department</option>
                                                    <?php foreach ($departmentsArr as $d): ?>
                                                        <option value="<?= $d['id'] ?>" <?= $row['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($d['department_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned
                                                    User</label>
                                                <select name="assigned_user"
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <option value="">— Not Assigned —</option>
                                                    <?php foreach ($users as $user): ?>
                                                        <?php
                                                        $fullName = $user['firstname'] . ' ' . $user['lastname'];
                                                        $selected = ($currentUserId == $user['id']) ? 'selected' : '';
                                                        ?>
                                                        <option value="<?= $user['id'] ?>" <?= $selected ?>>
                                                            <?= htmlspecialchars($fullName) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Device
                                                    Location</label>
                                                <select name="location_id" class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <option value="">Select Location</option>
                                                    <?php foreach ($locationsArr as $l): ?>
                                                        <option value="<?= $l['id'] ?>" <?= $row['location_id'] == $l['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($l['location_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CONDITION & STATUS -->
                                    <div class="bg-gray-50 rounded-xl p-4 mb-6">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-cog text-blue-600"></i>
                                            Status & Category
                                        </h3>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Condition *</label>
                                                <select name="condition" required
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <?php foreach (['Excellent', 'Good', 'Fair', 'Poor', 'New', 'Faulty'] as $c): ?>
                                                        <option value="<?= $c ?>" <?= $row['condition'] === $c ? 'selected' : '' ?>>
                                                            <?= $c ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                                                <select name="status" required
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <?php
                                                    $allowedStatuses = [
                                                        'active' => 'Active',
                                                        'in_storage' => 'In Storage',
                                                        'in_use' => 'In Use',
                                                        'repairing' => 'Repairing',
                                                        'faulty' => 'Faulty',
                                                        'retired' => 'Retired'
                                                    ];
                                                    foreach ($allowedStatuses as $value => $label):
                                                        ?>
                                                        <option value="<?= $value ?>" <?= $row['status'] === $value ? 'selected' : '' ?>>
                                                            <?= $label ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">Category *</label>
                                                <select name="category_id" required
                                                    class="w-full border border-gray-300 p-3 rounded-lg">
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categoriesArr as $c): ?>
                                                        <option value="<?= $c['id'] ?>" <?= $row['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($c['category_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- REMARKS -->
                                    <div class="bg-gray-50 rounded-xl p-4">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-sticky-note text-blue-600"></i>
                                            Additional Notes
                                        </h3>
                                        <textarea name="remarks" rows="4"
                                            class="w-full border border-gray-300 p-3 rounded-lg"><?= htmlspecialchars($row['remarks']) ?></textarea>
                                    </div>

                                </form>
                            </div>

                            <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                                <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                    class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100">
                                    Cancel
                                </button>
                                <button type="submit" form="editForm<?= $row['id'] ?>" name="update_inventory"
                                    class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Save Changes
                                </button>
                            </div>

                        </div>
                    </div>

                    <?php
                } // END WHILE LOOP
            } // END IF CHECK
            ?>

            <!-- ================= ADD MODAL ================= -->
            <div id="addModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4 modal-backdrop"
                onclick="closeModalOnBackdrop(event, 'addModal')">
                <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden modal-content"
                    onclick="event.stopPropagation()">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Add New Inventory Item</h2>
                                <p class="text-green-100 text-sm">Fill in the details below</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeModal('addModal')"
                            class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                        <form method="POST" id="addForm" autocomplete="off">
                            <!-- Basic Information -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-blue-600"></i>
                                    Basic Information
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Asset Tag</label>
                                        <input readonly name="asset_tag"
                                            value="<?= htmlspecialchars($asset_tag_preview) ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg bg-gray-100 text-gray-600">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Device Name<span
                                                class="text-red-500">*</span></label>
                                        <input name="device_type" value="<?= $item['device_type'] ?? '' ?>" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Laptop, Desktop, Monitor">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Brand <span
                                                class="text-red-500">*</span></label>
                                        <select name="brand_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Brand</option>
                                            <?php foreach ($brandsArr as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= isset($item['brand_id']) && $item['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['brand_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                                        <input name="model" value="<?= $item['model'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., XPS 15, ThinkPad X1">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Serial
                                            Number</label>
                                        <input name="serial_number" value="<?= $item['serial_number'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., SN123456789">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-2">Specifications</label>
                                        <input name="specifications" value="<?= $item['specifications'] ?? '' ?>"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                            placeholder="e.g., Intel i7, 16GB RAM, 512GB SSD">
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment Details -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user-tag text-blue-600"></i>
                                    Assignment Details
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                        <select name="department_id"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departmentsArr as $d): ?>
                                                <option value="<?= $d['id'] ?>" <?= isset($item['department_id']) && $item['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($d['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Assigned User
                                        </label>
                                        <select name="assigned_user"
                                            class="w-full border border-gray-300 p-3 rounded-lg bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">— Not Assigned —</option>
                                            <?php foreach ($users as $user): ?>
                                                <?php
                                                $fullName = $user['firstname'] . ' ' . $user['lastname'];
                                                $selected = ($currentAssignment['user_id'] ?? '') == $user['id'] ? 'selected' : '';
                                                ?>
                                                <option value="<?= $user['id'] ?>" <?= $selected ?>>
                                                    <?= htmlspecialchars($fullName) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Device
                                            Location</label>
                                        <select name="location_id"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Location</option>
                                            <?php foreach ($locationsArr as $l): ?>
                                                <option value="<?= $l['id'] ?>" <?= isset($item['location_id']) && $item['location_id'] == $l['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($l['location_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Category -->
                            <div class="bg-gray-50 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-cog text-blue-600"></i>
                                    Status & Category
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Condition <span
                                                class="text-red-500">*</span></label>
                                        <select name="condition" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Condition</option>
                                            <?php foreach (['Excellent', 'Good', 'Fair', 'Poor', 'New', 'Faulty'] as $c): ?>
                                                <option value="<?= $c ?>" <?= (isset($item['condition']) && $item['condition'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Status <span
                                                class="text-red-500">*</span></label>
                                        <select name="status" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Status</option>
                                            <option value="active" <?= (isset($item['status']) && $item['status'] == 'active') ? 'selected' : '' ?>>Active</option>
                                            <option value="in_storage" <?= (isset($item['status']) && $item['status'] == 'in_storage') ? 'selected' : '' ?>>In Storage</option>
                                            <option value="in_use" <?= (isset($item['status']) && $item['status'] == 'in_use') ? 'selected' : '' ?>>In Use</option>
                                            <option value="repairing" <?= (isset($item['status']) && $item['status'] == 'repairing') ? 'selected' : '' ?>>Repairing</option>
                                            <option value="faulty" <?= (isset($item['status']) && $item['status'] == 'faulty') ? 'selected' : '' ?>>Faulty</option>
                                            <option value="retired" <?= (isset($item['status']) && $item['status'] == 'retired') ? 'selected' : '' ?>>Retired</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Category <span
                                                class="text-red-500">*</span></label>
                                        <select name="category_id" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categoriesArr as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= isset($item['category_id']) && $item['category_id'] == $c['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Notes -->
                            <div class="bg-gray-50 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-sticky-note text-blue-600"></i>
                                    Remarks
                                </h3>
                                <textarea name="remarks" rows="4"
                                    class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                                    placeholder="Add any additional notes or remarks..."><?= $item['remarks'] ?? '' ?></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t">
                        <button type="button" onclick="closeModal('addModal')"
                            class="px-5 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 transition font-medium">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                        <button type="submit" form="addForm" name="save"
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            <i class="fas fa-plus mr-2"></i>Add Item
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= VIEW MODAL ================= -->
            <div id="viewModal"
                class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4 modal-backdrop"
                onclick="closeModalOnBackdrop(event, 'viewModal')">
                <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden modal-content"
                    onclick="event.stopPropagation()">
                    <!-- Modal Header -->
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-eye text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Inventory Details</h2>
                                <p class="text-purple-100 text-sm">View complete item information</p>
                            </div>
                        </div>
                        <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="p-6 overflow-y-auto" style="max-height: calc(95vh - 140px);">
                        <!-- Basic Information -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-50 rounded-xl p-5 mb-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-blue-600"></i>
                                Basic Information
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Asset Tag</p>
                                    <p class="font-semibold text-gray-800" id="view_asset_tag"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Device Type</p>
                                    <p class="font-semibold text-gray-800" id="view_device_type"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Brand</p>
                                    <p class="font-semibold text-gray-800" id="view_brand"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Model</p>
                                    <p class="font-semibold text-gray-800" id="view_model"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                                    <p class="font-semibold text-gray-800" id="view_serial_number"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Category</p>
                                    <p class="font-semibold text-gray-800" id="view_category"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3 md:col-span-2">
                                    <p class="text-xs text-gray-500 mb-1">Specifications</p>
                                    <p class="font-semibold text-gray-800" id="view_specifications"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment Details -->
                        <div class="bg-gradient-to-br from-green-50 to-teal-50 rounded-xl p-5 mb-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-user-tag text-blue-600"></i>
                                Assignment Details
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Department</p>
                                    <p class="font-semibold text-gray-800" id="view_department"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Assigned User</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_user"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">User ID</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_user_id"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Assigned Since</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_at"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3 md:col-span-2">
                                    <p class="text-xs text-gray-500 mb-1">Location</p>
                                    <p class="font-semibold text-gray-800" id="view_location"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Condition -->
                        <div class="bg-gradient-to-br from-blue-50 to-blue-50 rounded-xl p-5 mb-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-cog text-blue-600"></i>
                                Status & Condition
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Condition</p>
                                    <p class="font-semibold text-gray-800" id="view_condition"></p>
                                </div>
                                <div class="bg-white rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-1">Status</p>
                                    <p class="font-semibold text-gray-800" id="view_status"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="bg-gradient-to-br from-gray-50 to-slate-50 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-sticky-note text-blue-600"></i>
                                Additional Notes
                            </h3>
                            <div class="bg-white rounded-lg p-3">
                                <p class="text-gray-700" id="view_remarks"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="bg-gray-50 px-6 py-4 flex justify-end border-t">
                        <button onclick="closeViewModal()"
                            class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                            <i class="fas fa-check mr-2"></i>Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= ACTION MODAL (Horizontal Tabs) ================= -->
            <div id="actionModal"
                class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 modal-backdrop">
                <div class="bg-white w-full max-w-2xl rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Device Action</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                Device: <span id="actionAssetTag" class="font-bold text-blue-600"></span>
                            </p>
                        </div>
                        <button type="button" onclick="closeActionModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Current User Info -->
                    <div id="currentUserInfo" class="px-6 py-3 bg-blue-50 border-b hidden">
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-user mr-2 text-blue-500"></i>
                            <span class="font-medium">Current User:</span>
                            <span id="currentUserName" class="font-semibold text-blue-700 ml-1"></span>
                        </p>
                    </div>

                    <!-- Horizontal Tabs -->
                    <div class="border-b">
                        <div class="flex">
                            <!-- Assign Tab -->
                            <button type="button" id="assignTab"
                                class="flex-1 px-4 py-3 text-center text-sm font-medium transition-all"
                                onclick="showActionTab('assign')">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-user-plus text-green-600"></i>
                                    <span>Assign</span>
                                </div>
                            </button>

                            <!-- Reassign Tab -->
                            <button type="button" id="reassignTab"
                                class="flex-1 px-4 py-3 text-center text-sm font-medium transition-all"
                                onclick="showActionTab('reassign')">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-user-exchange text-blue-600"></i>
                                    <span>Reassign</span>
                                </div>
                            </button>

                            <!-- Retrieve Tab -->
                            <button type="button" id="retrieveTab"
                                class="flex-1 px-4 py-3 text-center text-sm font-medium transition-all"
                                onclick="showActionTab('retrieve')">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-arrow-left text-amber-600"></i>
                                    <span>Retrieve</span>
                                </div>
                            </button>

                            <!-- Retire Tab -->
                            <button type="button" id="retireTab"
                                class="flex-1 px-4 py-3 text-center text-sm font-medium transition-all"
                                onclick="showActionTab('retire')">
                                <div class="flex items-center justify-center gap-2">
                                    <i class="fas fa-archive text-gray-600"></i>
                                    <span>Retire</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Form Container -->
                    <form method="POST" action="inventory.php" id="actionForm">
                        <input type="hidden" name="device_id" id="deviceId">
                        <input type="hidden" name="device_action" value="1">

                        <!-- Tab Content Container -->
                        <div class="p-6">
                            <!-- Assign Content -->
                            <div id="assignContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Select User <span class="text-red-500">*</span>
                                        </label>
                                        <select name="assign_user" id="assignUserSelect" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                                <?php
                                                $fullName = $user['firstname'] . ' ' . $user['lastname'];
                                                ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Department
                                            </label>
                                            <select name="assign_department" id="assignDepartment"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departmentsArr as $d): ?>
                                                    <option value="<?= $d['id'] ?>">
                                                        <?= htmlspecialchars($d['department_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Location
                                            </label>
                                            <select name="assign_location" id="assignLocation"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                                <option value="">Select Location</option>
                                                <?php foreach ($locationsArr as $l): ?>
                                                    <option value="<?= $l['id'] ?>">
                                                        <?= htmlspecialchars($l['location_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Assignment Notes (Optional)
                                        </label>
                                        <textarea name="assign_notes" id="assignNotes" rows="2"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                                            placeholder="Add any notes about this assignment..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Reassign Content -->
                            <div id="reassignContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="p-3 bg-gray-50 rounded-lg mb-2">
                                        <p class="text-sm text-gray-700 mb-1">
                                            <i class="fas fa-user mr-2 text-blue-500"></i>
                                            <span class="font-medium">Current Assignment:</span>
                                            <span id="previousUserName" class="font-semibold text-blue-700 ml-1"></span>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            This device is currently assigned to the user above.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Select New User <span class="text-red-500">*</span>
                                        </label>
                                        <select name="reassign_user" id="reassignUserSelect" required
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                            <option value="">Select User</option>
                                            <?php foreach ($users as $user): ?>
                                                <?php
                                                $fullName = $user['firstname'] . ' ' . $user['lastname'];
                                                ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($fullName) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Department
                                            </label>
                                            <select name="reassign_department" id="reassignDepartment"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departmentsArr as $d): ?>
                                                    <option value="<?= $d['id'] ?>">
                                                        <?= htmlspecialchars($d['department_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Location
                                            </label>
                                            <select name="reassign_location" id="reassignLocation"
                                                class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                                <option value="">Select Location</option>
                                                <?php foreach ($locationsArr as $l): ?>
                                                    <option value="<?= $l['id'] ?>">
                                                        <?= htmlspecialchars($l['location_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Reassignment Notes (Optional)
                                        </label>
                                        <textarea name="reassign_notes" id="reassignNotes" rows="2"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                            placeholder="Add any notes about this reassignment..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Retrieve Content -->
                            <div id="retrieveContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="p-3 bg-amber-50 rounded-lg mb-2">
                                        <p class="text-sm text-gray-700 mb-1">
                                            <i class="fas fa-user mr-2 text-amber-600"></i>
                                            <span class="font-medium">Current Assignment:</span>
                                            <span id="retrieveUserName"
                                                class="font-semibold text-amber-700 ml-1"></span>
                                        </p>
                                        <p class="text-xs text-gray-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            This device will be retrieved and returned to store.
                                        </p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Reason for Retrieval (Optional)
                                        </label>
                                        <textarea name="retrieve_reason" id="retrieveReason" rows="3"
                                            class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent resize-none"
                                            placeholder="e.g., Device inspection, maintenance, return to inventory..."></textarea>
                                    </div>

                                    <div class="p-3 bg-gray-50 rounded-lg">
                                        <p class="text-sm text-gray-700 mb-1">
                                            <i class="fas fa-info-circle text-gray-500 mr-2"></i>
                                            After retrieval, the device status will change to "In Storage"
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Retire Content -->
                            <div id="retireContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="p-4 bg-gray-100 rounded-lg border border-gray-200">
                                        <div class="flex items-start gap-3">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-amber-500 text-xl"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-800 mb-1">Important Notice</h4>
                                                <p class="text-sm text-gray-600 mb-2">
                                                    The device will be marked as <span
                                                        class="font-semibold">retired</span> and will no longer be
                                                    available for assignment.
                                                </p>
                                                <ul class="text-xs text-gray-500 space-y-1">
                                                    <li class="flex items-center gap-2">
                                                        <i class="fas fa-check text-green-500"></i>
                                                        <span>Active assignments will be automatically ended</span>
                                                    </li>
                                                    <li class="flex items-center gap-2">
                                                        <i class="fas fa-check text-green-500"></i>
                                                        <span>Device status will be changed to "Retired"</span>
                                                    </li>
                                                    <li class="flex items-center gap-2">
                                                        <i class="fas fa-check text-green-500"></i>
                                                        <span>Device will be removed from active inventory</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-3 bg-red-50 rounded-lg border border-red-100">
                                        <p class="text-sm text-red-700">
                                            <i class="fas fa-exclamation-circle mr-2"></i>
                                            This action <span class="font-semibold">cannot be undone</span>. Please
                                            confirm this is the correct action.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-6 py-4 border-t flex justify-end gap-3">
                            <button type="button" onclick="closeActionModal()"
                                class="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 font-medium transition">
                                Cancel
                            </button>
                            <button type="button" onclick="confirmAction()" id="submitActionBtn"
                                class="px-5 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition">
                                Confirm Action
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= CONFIRMATION MODAL ================= -->
            <div id="confirmationModal"
                class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[100] p-4 confirmation-modal">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl confirmation-content"
                    onclick="event.stopPropagation()">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800" id="confirmationTitle">Confirm Action</h3>
                        <button onclick="closeConfirmationModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5 text-gray-700">
                        <p class="mb-4" id="confirmationMessage"></p>
                        <div class="p-3 bg-yellow-50 rounded-lg border border-yellow-100 mt-3" id="confirmationWarning">
                            <p class="text-sm text-yellow-700 flex items-center gap-2">
                                <i class="fas fa-exclamation-triangle"></i>
                                This action cannot be undone
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button onclick="closeConfirmationModal()"
                            class="px-4 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 font-medium transition">
                            Cancel
                        </button>
                        <button onclick="executeConfirmedAction()" id="confirmActionBtn"
                            class="px-5 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 font-medium transition">
                            Confirm
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= DELETE MODAL ================= -->
            <div id="deleteModal"
                class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4 modal-backdrop">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl modal-content">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Confirm Delete</h3>
                        <button onclick="closeDeleteModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5 text-gray-700">
                        <p class="mb-2 font-medium">Are you sure you want to delete this item?</p>
                        <p class="text-sm text-gray-500">
                            This action <span class="text-red-600 font-semibold">cannot be undone</span>.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button onclick="closeDeleteModal()"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <a id="confirmDeleteBtn" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                            Yes, Delete
                        </a>
                    </div>
                </div>
            </div>

            <!-- ================= QUICK ACTIONS MODAL ================= -->
            <div id="quickActionsModal"
                class="fixed inset-0 bg-black/50 hidden items-center justify-center z-[110] p-4 quick-action-modal">
                <div class="bg-white w-full max-w-sm rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
                        <button onclick="closeQuickActionsModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6 space-y-3">
                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-700">Device Actions</h4>
                            <button onclick="performQuickAction('refresh')"
                                class="w-full flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-sync text-blue-500"></i>
                                <span>Refresh Inventory</span>
                            </button>
                            <button onclick="performQuickAction('export')"
                                class="w-full flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-download text-green-500"></i>
                                <span>Export Data</span>
                            </button>
                        </div>

                        <div class="space-y-2">
                            <h4 class="text-sm font-medium text-gray-700">View Options</h4>
                            <button onclick="performQuickAction('toggle_view')"
                                class="w-full flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-table text-purple-500"></i>
                                <span>Toggle Table View</span>
                            </button>
                            <button onclick="performQuickAction('clear_filters')"
                                class="w-full flex items-center gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors">
                                <i class="fas fa-filter-slash text-amber-500"></i>
                                <span>Clear All Filters</span>
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t">
                        <p class="text-xs text-gray-500 text-center">
                            Use these actions for quick device management
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // ==================== TOAST NOTIFICATION FUNCTIONS ====================
        class Toast {
            constructor(type, title, message, duration = 5000) {
                this.type = type;
                this.title = title;
                this.message = message;
                this.duration = duration;
                this.id = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                this.timeout = null;
            }

            show() {
                const container = document.getElementById('toast-container');
                if (!container) {
                    console.error('Toast container not found!');
                    return;
                }

                // Create toast element
                const toast = document.createElement('div');
                toast.id = this.id;
                toast.className = `toast toast-${this.type}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        ${this.getIcon()}
                    </div>
                    <div class="toast-content">
                        <div class="toast-title">${this.title}</div>
                        <div class="toast-message">${this.message}</div>
                    </div>
                    <button class="toast-close" onclick="Toast.hide('${this.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${this.duration}ms"></div>
                `;

                // Add to container
                container.appendChild(toast);

                // Trigger animation
                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                // Auto dismiss
                this.timeout = setTimeout(() => {
                    this.hide();
                }, this.duration);
            }

            getIcon() {
                const icons = {
                    'success': '<i class="fas fa-check-circle"></i>',
                    'error': '<i class="fas fa-exclamation-circle"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle"></i>',
                    'info': '<i class="fas fa-info-circle"></i>'
                };
                return icons[this.type] || icons['info'];
            }

            static showSuccess(message, title = 'Success', duration = 5000) {
                new Toast('success', title, message, duration).show();
            }

            static showError(message, title = 'Error', duration = 5000) {
                new Toast('error', title, message, duration).show();
            }

            static showWarning(message, title = 'Warning', duration = 5000) {
                new Toast('warning', title, message, duration).show();
            }

            static showInfo(message, title = 'Info', duration = 3000) {
                new Toast('info', title, message, duration).show();
            }

            hide() {
                const toast = document.getElementById(this.id);
                if (!toast) return;

                toast.classList.remove('show');
                toast.classList.add('hide');

                clearTimeout(this.timeout);

                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }

            static hide(id) {
                const toast = document.getElementById(id);
                if (!toast) return;

                toast.classList.remove('show');
                toast.classList.add('hide');

                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }

            static clearAll() {
                const container = document.getElementById('toast-container');
                if (!container) return;
                container.innerHTML = '';
            }
        }

        // Function to show PHP session messages as toasts
        function showPHPToasts() {
            // Success toast
            const successToast = document.getElementById('success-toast');
            if (successToast) {
                Toast.showSuccess(successToast.textContent.trim(), 'Success');
            }

            // Error toast
            const errorToast = document.getElementById('error-toast');
            if (errorToast) {
                Toast.showError(errorToast.textContent.trim(), 'Error');
            }

            // Warning toast (form errors)
            const warningToast = document.getElementById('warning-toast');
            if (warningToast) {
                Toast.showWarning(warningToast.textContent.trim(), 'Validation Error', 7000);
            }
        }

        // ==================== DROPDOWN POSITIONING FUNCTIONS ====================
        function toggleDropdown(event, dropdownId) {
            event.preventDefault();
            event.stopPropagation();

            const dropdown = document.getElementById(dropdownId);
            const allDropdowns = document.querySelectorAll('.dropdown-content');

            // Close all other dropdowns
            allDropdowns.forEach(d => {
                if (d.id !== dropdownId) {
                    d.classList.remove('show');
                }
            });

            // Position dropdown based on available space
            positionDropdown(event.target, dropdown);

            // Toggle current dropdown
            dropdown.classList.toggle('show');
        }

        function positionDropdown(button, dropdown) {
            // Get button position and dimensions
            const buttonRect = button.getBoundingClientRect();

            // Get viewport dimensions
            const viewportHeight = window.innerHeight;

            // Check available space below and above the button
            const spaceBelow = viewportHeight - buttonRect.bottom;
            const spaceAbove = buttonRect.top;

            // Default is bottom
            dropdown.classList.remove('top', 'bottom');

            if (spaceBelow < dropdown.offsetHeight && spaceAbove > spaceBelow) {
                // Not enough space below, but more space above - position above
                dropdown.classList.add('top');
            } else {
                // Enough space below or more space below than above - position below
                dropdown.classList.add('bottom');
            }
        }

        // ==================== MODAL FUNCTIONS ====================
        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) {
                console.error('Modal not found:', id);
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        function closeModalOnBackdrop(event, id) {
            if (event.target === event.currentTarget) {
                closeModal(id);
            }
        }

        // ==================== VIEW MODAL ====================
        function openViewModal(item) {
            document.getElementById('view_asset_tag').textContent = item.asset_tag || '';
            document.getElementById('view_device_type').textContent = item.device_type || '';
            document.getElementById('view_brand').textContent = item.brand_name || '';
            document.getElementById('view_model').textContent = item.model || '';
            document.getElementById('view_serial_number').textContent = item.serial_number || '';
            document.getElementById('view_category').textContent = item.category_name || '';
            document.getElementById('view_specifications').textContent = item.specifications || '';
            document.getElementById('view_department').textContent = item.department_name || '';
            document.getElementById('view_assigned_user').textContent = (item.firstname && item.lastname) ? item.firstname + ' ' + item.lastname : 'Unassigned';
            document.getElementById('view_assigned_user_id').textContent = item.assigned_user_id || 'N/A';
            document.getElementById('view_assigned_at').textContent = item.assigned_at ? new Date(item.assigned_at).toLocaleDateString() : 'N/A';
            document.getElementById('view_location').textContent = item.location_name || '';
            document.getElementById('view_condition').textContent = item.condition || '';
            document.getElementById('view_status').textContent = item.status || '';
            document.getElementById('view_remarks').textContent = item.remarks || '';

            openModal('viewModal');
        }

        function closeViewModal() {
            closeModal('viewModal');
        }

        // ==================== ACTION MODAL ====================
        let currentActionModalState = {
            deviceId: null,
            assignedUser: null,
            assetTag: null,
            currentTab: 'assign',
            actionData: null
        };

        function openActionModal(id, defaultAction, assetTag, assignedUser) {
            currentActionModalState = {
                deviceId: id,
                assignedUser: assignedUser,
                assetTag: assetTag,
                currentTab: defaultAction || 'assign',
                actionData: null
            };

            document.getElementById('deviceId').value = id;
            document.getElementById('actionAssetTag').textContent = assetTag;

            // Show current user info if available
            const currentUserInfo = document.getElementById('currentUserInfo');
            const currentUserName = document.getElementById('currentUserName');
            const previousUserName = document.getElementById('previousUserName');
            const retrieveUserName = document.getElementById('retrieveUserName');

            if (assignedUser && assignedUser.trim() !== '') {
                currentUserInfo.classList.remove('hidden');
                currentUserName.textContent = assignedUser;
                previousUserName.textContent = assignedUser;
                retrieveUserName.textContent = assignedUser;
            } else {
                currentUserInfo.classList.add('hidden');
                currentUserName.textContent = '';
                previousUserName.textContent = '';
                retrieveUserName.textContent = '';
            }

            // Reset form
            document.getElementById('actionForm').reset();

            // Enable/disable tabs based on whether device is assigned
            const assignTab = document.getElementById('assignTab');
            const reassignTab = document.getElementById('reassignTab');
            const retrieveTab = document.getElementById('retrieveTab');
            const retireTab = document.getElementById('retireTab');

            if (assignedUser && assignedUser.trim() !== '') {
                // Device is assigned - enable reassign/retrieve/retire, disable assign
                assignTab.classList.add('tab-disabled');
                assignTab.disabled = true;

                reassignTab.classList.remove('tab-disabled');
                reassignTab.disabled = false;

                retrieveTab.classList.remove('tab-disabled');
                retrieveTab.disabled = false;

                retireTab.classList.remove('tab-disabled');
                retireTab.disabled = false;

                // Set default tab if provided, otherwise select reassign
                if (defaultAction && defaultAction !== 'assign') {
                    currentActionModalState.currentTab = defaultAction;
                } else {
                    currentActionModalState.currentTab = 'reassign';
                }
            } else {
                // Device is unassigned - enable assign/retire, disable reassign/retrieve
                assignTab.classList.remove('tab-disabled');
                assignTab.disabled = false;

                reassignTab.classList.add('tab-disabled');
                reassignTab.disabled = true;

                retrieveTab.classList.add('tab-disabled');
                retrieveTab.disabled = true;

                retireTab.classList.remove('tab-disabled');
                retireTab.disabled = false;

                // Set default tab if provided, otherwise select assign
                if (defaultAction && defaultAction !== 'reassign' && defaultAction !== 'retrieve') {
                    currentActionModalState.currentTab = defaultAction;
                } else {
                    currentActionModalState.currentTab = 'assign';
                }
            }

            // Show the selected tab
            showActionTab(currentActionModalState.currentTab);

            openModal('actionModal');
        }

        function showActionTab(tabName) {
            // Don't switch to disabled tabs
            const tabElement = document.getElementById(tabName + 'Tab');
            if (tabElement.classList.contains('tab-disabled')) {
                return;
            }

            // Update current tab state
            currentActionModalState.currentTab = tabName;

            // Remove active state from all tabs
            const allTabs = ['assign', 'reassign', 'retrieve', 'retire'];
            allTabs.forEach(tab => {
                const tabBtn = document.getElementById(tab + 'Tab');
                const content = document.getElementById(tab + 'Content');

                tabBtn.classList.remove('tab-active');
                tabBtn.classList.add('tab-inactive');

                if (content) {
                    content.classList.add('hidden');
                }
            });

            // Add active state to selected tab
            const selectedTab = document.getElementById(tabName + 'Tab');
            const selectedContent = document.getElementById(tabName + 'Content');

            selectedTab.classList.remove('tab-inactive');
            selectedTab.classList.add('tab-active');

            if (selectedContent) {
                selectedContent.classList.remove('hidden');
            }

            // Update submit button text based on action
            const submitBtn = document.getElementById('submitActionBtn');
            const actionLabels = {
                'assign': 'Confirm Assignment',
                'reassign': 'Confirm Reassignment',
                'retrieve': 'Confirm Retrieval',
                'retire': 'Confirm Retirement'
            };

            submitBtn.textContent = actionLabels[tabName] || 'Confirm Action';

            // Update button color based on action
            submitBtn.className = 'px-5 py-2.5 rounded-lg font-medium transition';

            switch (tabName) {
                case 'assign':
                    submitBtn.classList.add('bg-green-600', 'hover:bg-green-700', 'text-white');
                    break;
                case 'reassign':
                    submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white');
                    break;
                case 'retrieve':
                    submitBtn.classList.add('bg-amber-600', 'hover:bg-amber-700', 'text-white');
                    break;
                case 'retire':
                    submitBtn.classList.add('bg-red-600', 'hover:bg-red-700', 'text-white');
                    break;
                default:
                    submitBtn.classList.add('bg-blue-600', 'hover:bg-blue-700', 'text-white');
            }
        }

        function closeActionModal() {
            // Reset state
            currentActionModalState = {
                deviceId: null,
                assignedUser: null,
                assetTag: null,
                currentTab: 'assign',
                actionData: null
            };

            // Reset form
            document.getElementById('actionForm').reset();

            // Reset tabs to default state
            const allTabs = ['assign', 'reassign', 'retrieve', 'retire'];
            allTabs.forEach(tab => {
                const tabBtn = document.getElementById(tab + 'Tab');
                tabBtn.classList.remove('tab-active', 'tab-inactive', 'tab-disabled');
                tabBtn.disabled = false;
            });

            closeModal('actionModal');
        }

        // ==================== CONFIRMATION MODAL ====================
        function confirmAction() {
            const currentTab = currentActionModalState.currentTab;
            const deviceTag = currentActionModalState.assetTag;

            // Validate required fields based on current tab
            let isValid = true;
            let errorMessage = '';

            switch (currentTab) {
                case 'assign':
                    const assignUser = document.getElementById('assignUserSelect');
                    if (!assignUser.value) {
                        isValid = false;
                        errorMessage = 'Please select a user to assign the device to!';
                        assignUser.focus();
                    }
                    break;

                case 'reassign':
                    const reassignUser = document.getElementById('reassignUserSelect');
                    if (!reassignUser.value) {
                        isValid = false;
                        errorMessage = 'Please select a user to reassign the device to!';
                        reassignUser.focus();
                    }
                    break;
            }

            if (!isValid) {
                Toast.showError(errorMessage, 'Validation Error');
                return false;
            }

            // Prepare confirmation message based on action
            const actionNames = {
                'assign': 'assign',
                'reassign': 'reassign',
                'retrieve': 'retrieve to store',
                'retire': 'retire'
            };

            const actionTitles = {
                'assign': 'Confirm Assignment',
                'reassign': 'Confirm Reassignment',
                'retrieve': 'Confirm Retrieval',
                'retire': 'Confirm Retirement'
            };

            const actionMessages = {
                'assign': `Are you sure you want to assign device <strong>"${deviceTag}"</strong> to the selected user?`,
                'reassign': `Are you sure you want to reassign device <strong>"${deviceTag}"</strong> from the current user to the new user?`,
                'retrieve': `Are you sure you want to retrieve device <strong>"${deviceTag}"</strong> from the current user and return it to storage?`,
                'retire': `Are you sure you want to retire device <strong>"${deviceTag}"</strong>? This will permanently mark it as retired.`
            };

            const actionWarnings = {
                'assign': 'This will update the device status and assignment records.',
                'reassign': 'This will update assignment records and device status.',
                'retrieve': 'The device will be marked as "In Storage" and assignment will be ended.',
                'retire': 'This action cannot be undone. The device will be permanently marked as retired.'
            };

            // Store action data for execution
            currentActionModalState.actionData = {
                tab: currentTab,
                formData: new FormData(document.getElementById('actionForm'))
            };

            // Show confirmation modal
            document.getElementById('confirmationTitle').textContent = actionTitles[currentTab];
            document.getElementById('confirmationMessage').innerHTML = actionMessages[currentTab];

            const warningDiv = document.getElementById('confirmationWarning');
            if (currentTab === 'retire') {
                warningDiv.classList.remove('hidden');
                warningDiv.innerHTML = `
                    <p class="text-sm text-red-700 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> ${actionWarnings[currentTab]}
                    </p>
                `;
            } else {
                warningDiv.classList.remove('hidden');
                warningDiv.innerHTML = `
                    <p class="text-sm text-blue-700 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        ${actionWarnings[currentTab]}
                    </p>
                `;
            }

            // Update confirm button color based on action
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.className = 'px-5 py-2.5 rounded-lg text-white hover:opacity-90 font-medium transition';

            switch (currentTab) {
                case 'assign':
                    confirmBtn.classList.add('bg-green-600');
                    break;
                case 'reassign':
                    confirmBtn.classList.add('bg-blue-600');
                    break;
                case 'retrieve':
                    confirmBtn.classList.add('bg-amber-600');
                    break;
                case 'retire':
                    confirmBtn.classList.add('bg-red-600');
                    break;
                default:
                    confirmBtn.classList.add('bg-blue-600');
            }

            // Close action modal and open confirmation modal
            closeModal('actionModal');
            openModal('confirmationModal');
        }

        function closeConfirmationModal() {
            closeModal('confirmationModal');
            // Reopen action modal if user cancels
            setTimeout(() => {
                if (currentActionModalState.deviceId) {
                    openModal('actionModal');
                }
            }, 300);
        }

        function executeConfirmedAction() {
            const form = document.getElementById('actionForm');
            const currentTab = currentActionModalState.currentTab;

            // Set the action value based on current tab
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = currentTab;
            form.appendChild(actionInput);

            // Show success toast
            const deviceTag = currentActionModalState.assetTag;
            const successMessages = {
                'assign': `Device "${deviceTag}" assigned successfully!`,
                'reassign': `Device "${deviceTag}" reassigned successfully!`,
                'retrieve': `Device "${deviceTag}" retrieved successfully!`,
                'retire': `Device "${deviceTag}" retired successfully!`
            };

            // Submit the form
            form.submit();

            // Show toast (will be overridden by PHP session toast)
            Toast.showSuccess(successMessages[currentTab], 'Success');

            closeConfirmationModal();
        }

        // ==================== DELETE MODAL ====================
        function openDeleteModal(id) {
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.href = `inventory.php?delete=${id}`;
            openModal('deleteModal');
        }

        function closeDeleteModal() {
            closeModal('deleteModal');
        }

        // ==================== QUICK ACTIONS MODAL ====================
        function openQuickActionsModal() {
            openModal('quickActionsModal');
        }

        function closeQuickActionsModal() {
            closeModal('quickActionsModal');
        }

        function performQuickAction(action) {
            switch (action) {
                case 'refresh':
                    window.location.reload();
                    break;
                case 'export':
                    window.location.href = 'export_assignments.php';
                    break;
                case 'toggle_view':
                    Toast.showInfo('Table view toggled', 'Info', 2000);
                    break;
                case 'clear_filters':
                    window.location.href = 'inventory.php';
                    break;
            }
            closeQuickActionsModal();
        }

        // ==================== SEARCH FUNCTION ====================
        function searchTable() {
            const searchTerm = document.getElementById("searchInput").value.toLowerCase().trim();
            const rows = document.querySelectorAll("#inventoryTableBody tr");

            rows.forEach(row => {
                const assetTag = row.querySelector('.asset-tag-badge')?.textContent.toLowerCase() || '';
                const deviceType = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const brandName = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const model = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';
                const assignedUser = row.querySelector('td:nth-child(5)')?.textContent.toLowerCase() || '';

                const searchableText = `${assetTag} ${deviceType} ${brandName} ${model} ${assignedUser}`;
                row.style.display = searchableText.includes(searchTerm) ? "" : "none";
            });
        }

        // ==================== PAGINATION ====================
        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // ==================== RESPONSIVE TABLE ====================
        function adjustTableLayout() {
            const container = document.querySelector('.table-container');
            const screenWidth = window.innerWidth;

            if (screenWidth < 768) {
                container.style.overflowX = 'auto';
                container.classList.remove('no-scrollbar');
            } else {
                container.style.overflowX = 'visible';
                container.classList.add('no-scrollbar');
            }
        }

        // ==================== INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function () {
            // Show PHP toasts
            showPHPToasts();

            // Close dropdowns when clicking outside
            document.addEventListener('click', function (event) {
                if (!event.target.closest('.dropdown')) {
                    document.querySelectorAll('.dropdown-content.show').forEach(dropdown => {
                        dropdown.classList.remove('show');
                    });
                }
            });

            // Adjust table layout
            adjustTableLayout();

            // Add keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // Escape key closes modals
                if (e.key === 'Escape') {
                    if (document.getElementById('actionModal').classList.contains('flex')) {
                        closeActionModal();
                    } else if (document.getElementById('confirmationModal').classList.contains('flex')) {
                        closeConfirmationModal();
                    } else if (document.getElementById('deleteModal').classList.contains('flex')) {
                        closeDeleteModal();
                    } else if (document.getElementById('quickActionsModal').classList.contains('flex')) {
                        closeQuickActionsModal();
                    }
                }

                // Enter key in action modal
                if (e.key === 'Enter' && document.getElementById('actionModal').classList.contains('flex')) {
                    e.preventDefault();
                    confirmAction();
                }

                // Tab navigation with arrow keys in action modal
                if (document.getElementById('actionModal').classList.contains('flex')) {
                    const tabs = ['assign', 'reassign', 'retrieve', 'retire'];
                    const currentIndex = tabs.indexOf(currentActionModalState.currentTab);

                    if (e.key === 'ArrowRight' && currentIndex < tabs.length - 1) {
                        e.preventDefault();
                        let nextIndex = currentIndex + 1;
                        // Skip disabled tabs
                        while (nextIndex < tabs.length) {
                            const nextTab = document.getElementById(tabs[nextIndex] + 'Tab');
                            if (!nextTab.classList.contains('tab-disabled')) {
                                showActionTab(tabs[nextIndex]);
                                break;
                            }
                            nextIndex++;
                        }
                    }

                    if (e.key === 'ArrowLeft' && currentIndex > 0) {
                        e.preventDefault();
                        let prevIndex = currentIndex - 1;
                        // Skip disabled tabs
                        while (prevIndex >= 0) {
                            const prevTab = document.getElementById(tabs[prevIndex] + 'Tab');
                            if (!prevTab.classList.contains('tab-disabled')) {
                                showActionTab(tabs[prevIndex]);
                                break;
                            }
                            prevIndex--;
                        }
                    }
                }
            });
        });

        // Adjust on resize
        window.addEventListener('resize', adjustTableLayout);
    </script>
</body>

</html>