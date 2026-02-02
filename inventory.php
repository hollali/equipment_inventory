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

/* ================== RESIGN DEVICE ================== */
if (isset($_POST['resign_device']) && is_numeric($_POST['resign_id'])) {
    $id = (int) $_POST['resign_id'];
    $reason = mysqli_real_escape_string($conn, $_POST['resign_reason'] ?? '');

    // End the active assignment
    $stmt = mysqli_prepare($conn, "
        UPDATE device_user_assignments 
        SET status = 'retrieved', 
            returned_at = NOW()
        WHERE inventory_id = ? AND status = 'assigned'
    ");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update inventory status
    $updateStmt = mysqli_prepare($conn, "
        UPDATE inventory_items 
        SET status = 'in_storage',
            remarks = CONCAT(IFNULL(remarks, ''), '\nResigned on ', NOW(), ': ', ?)
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($updateStmt, "si", $reason, $id);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    $_SESSION['success_message'] = 'Device resigned/retrieved successfully!';
    header("Location: inventory.php?msg=resigned");
    exit;
}

/* ================== ASSIGN DEVICE ================== */
if (isset($_POST['assign_device']) && is_numeric($_POST['assign_id'])) {
    $id = (int) $_POST['assign_id'];
    $userId = !empty($_POST['assign_user']) ? (int) $_POST['assign_user'] : null;
    $assign_notes = mysqli_real_escape_string($conn, $_POST['assign_notes'] ?? '');

    if (empty($userId)) {
        $_SESSION['error_message'] = 'Please select a user to assign the device to.';
        header("Location: inventory.php");
        exit;
    }

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // First, end any existing active assignment for this device
        $endStmt = mysqli_prepare($conn, "
            UPDATE device_user_assignments 
            SET status = 'retrieved', returned_at = NOW()
            WHERE inventory_id = ? AND status = 'assigned'
        ");
        mysqli_stmt_bind_param($endStmt, "i", $id);
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
        mysqli_stmt_bind_param($assignStmt, "ii", $id, $userId);
        mysqli_stmt_execute($assignStmt);
        mysqli_stmt_close($assignStmt);

        // Update inventory status
        $updateStmt = mysqli_prepare($conn, "
            UPDATE inventory_items 
            SET status = 'in_use',
                remarks = CONCAT(IFNULL(remarks, ''), '\nAssigned on ', NOW(), ': ', ?)
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($updateStmt, "si", $assign_notes, $id);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);

        mysqli_commit($conn);
        $_SESSION['success_message'] = 'Device assigned successfully!';
        header("Location: inventory.php?msg=assigned");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        header("Location: inventory.php?error=assign_failed");
        exit;
    }
}

/* ================== RETRIEVE DEVICE ================== */
if (isset($_POST['retrieve_device']) && is_numeric($_POST['retrieve_id'])) {
    $id = (int) $_POST['retrieve_id'];
    $retrieve_notes = mysqli_real_escape_string($conn, $_POST['retrieve_notes'] ?? '');

    // End the active assignment
    $stmt = mysqli_prepare($conn, "
        UPDATE device_user_assignments 
        SET status = 'retrieved', 
            returned_at = NOW()
        WHERE inventory_id = ? AND status = 'assigned'
    ");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update inventory status
    $updateStmt = mysqli_prepare($conn, "
        UPDATE inventory_items 
        SET status = 'in_storage',
            remarks = CONCAT(IFNULL(remarks, ''), '\nRetrieved on ', NOW(), ': ', ?)
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($updateStmt, "si", $retrieve_notes, $id);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    $_SESSION['success_message'] = 'Device retrieved successfully!';
    header("Location: inventory.php?msg=retrieved");
    exit;
}

/* ================== RETIRE INVENTORY ================== */
if (isset($_POST['retire'], $_POST['retire_id'])) {
    $id = (int) $_POST['retire_id'];

    // First end any active assignment
    $endStmt = mysqli_prepare($conn, "
        UPDATE device_user_assignments 
        SET status = 'retrieved', returned_at = NOW()
        WHERE inventory_id = ? AND status = 'assigned'
    ");
    mysqli_stmt_bind_param($endStmt, "i", $id);
    mysqli_stmt_execute($endStmt);
    mysqli_stmt_close($endStmt);

    // Then retire the device
    $stmt = mysqli_prepare($conn, "
        UPDATE inventory_items 
        SET status = 'retired'
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['success_message'] = 'Device retired successfully!';
    header("Location: inventory.php?msg=retired");
    exit;
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

        .animate-slide-down {
            animation: slideDown 0.3s ease-out;
        }

        /* Improved Table Styling */
        .table-container {
            width: 100%;
            overflow: visible;
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
        }

        .data-table tbody tr {
            transition: all 0.15s ease;
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
            /* IE and Edge */
            scrollbar-width: none;
            /* Firefox */
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
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
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
                    class="bg-blue-600 text-white px-4 py-2 text-sm rounded-lg hover:bg-blue-700">
                    <i class="fa fa-plus text-xs mr-1"></i> Add Item
                </button>
            </div>

            <!-- Display Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg animate-slide-down">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle text-green-500"></i>
                        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="float-right text-green-700 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg animate-slide-down">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-red-500"></i>
                        <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="float-right text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['form_errors']) && !empty($_SESSION['form_errors'])): ?>
                <div class="mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded-lg animate-slide-down">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                        <span class="font-medium">Please fix the following errors:</span>
                    </div>
                    <ul class="list-disc list-inside ml-4">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button onclick="this.parentElement.remove()" class="float-right text-yellow-700 hover:text-yellow-900">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php unset($_SESSION['form_errors']); ?>
                </div>
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
            <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
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
                                    if (!empty($row['firstname']) && !empty($row['lastname'])) {
                                        $fullName = $row['firstname'] . ' ' . $row['lastname'];
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
                                                <?php if (!empty($fullName)): ?>
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
                                            <div class="flex gap-1">
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
                                                <!-- ASSIGN (only if not assigned and not retired) -->
                                                <?php if (empty($fullName) && $row['status'] !== 'retired'): ?>
                                                    <button
                                                        onclick="openAssignModal(<?= (int) $row['id'] ?>, '<?= htmlspecialchars($row['asset_tag']) ?>')"
                                                        class="action-btn bg-indigo-500 text-white hover:bg-indigo-600"
                                                        title="Assign Device">
                                                        <i class="fas fa-user-plus text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <!-- RESIGN/RETRIEVE (only if assigned and not retired) -->
                                                <?php if (!empty($fullName) && $row['status'] !== 'retired'): ?>
                                                    <button
                                                        onclick="openResignModal(<?= (int) $row['id'] ?>, '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>')"
                                                        class="action-btn bg-amber-500 text-white hover:bg-amber-600"
                                                        title="Resign/Retrieve Device">
                                                        <i class="fas fa-user-minus text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <!-- RETIRE -->
                                                <button onclick="openRetireModal(<?= (int) $row['id'] ?>)"
                                                    class="action-btn bg-gray-500 text-white hover:bg-gray-600"
                                                    title="Retire Device">
                                                    <i class="fas fa-archive text-xs"></i>
                                                </button>
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
                    $assignStmt = $conn->prepare("
            SELECT dua.*, u.firstname, u.lastname
            FROM device_user_assignments dua
            JOIN users u ON dua.user_id = u.id
            WHERE dua.inventory_id = ? AND dua.status = 'assigned'
            ORDER BY dua.assigned_at DESC
            LIMIT 1
        ");
                    $assignStmt->bind_param("i", $row['id']);
                    $assignStmt->execute();
                    $assignment = $assignStmt->get_result()->fetch_assoc();
                    $assignStmt->close();

                    $currentUserId = $assignment['user_id'] ?? '';
                    ?>
                    <div id="editModal<?= $row['id'] ?>"
                        class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                        onclick="closeModalOnBackdrop(event, 'editModal<?= $row['id'] ?>')">

                        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
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
            <div id="addModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                onclick="closeModalOnBackdrop(event, 'addModal')">
                <div class="bg-white w-full max-w-5xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
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
            <div id="viewModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50 p-4"
                onclick="closeModalOnBackdrop(event, 'viewModal')">
                <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl max-h-[95vh] overflow-hidden"
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

            <!-- ================= ASSIGN MODAL ================= -->
            <div id="assignModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Assign Device</h3>
                        <button onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5">
                        <p class="mb-4 text-gray-700">
                            Assigning: <span id="assignAssetTag" class="font-bold text-blue-600"></span>
                        </p>
                        <form method="POST" id="assignForm">
                            <input type="hidden" name="assign_id" id="assignId">

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Select User <span class="text-red-500">*</span>
                                </label>
                                <select name="assign_user" required
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

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Assignment Notes (Optional)
                                </label>
                                <textarea name="assign_notes" rows="3"
                                    class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    placeholder="Add any notes about this assignment..."></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button onclick="closeAssignModal()"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <button type="submit" form="assignForm" name="assign_device"
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            Assign Device
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= RESIGN MODAL ================= -->
            <div id="resignModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Resign/Retrieve Device</h3>
                        <button onclick="closeResignModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5">
                        <p class="mb-4 text-gray-700">
                            Device: <span id="resignAssetTag" class="font-bold text-blue-600"></span>
                        </p>
                        <p class="mb-4 text-gray-700">
                            Currently assigned to: <span id="resignAssignedUser"
                                class="font-semibold text-amber-600"></span>
                        </p>
                        <form method="POST" id="resignForm">
                            <input type="hidden" name="resign_id" id="resignId">

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Reason for Resignation/Retrieval (Optional)
                                </label>
                                <textarea name="resign_reason" rows="3"
                                    class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                    placeholder="e.g., Employee left, Device upgrade, etc..."></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button onclick="closeResignModal()"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <button type="submit" form="resignForm" name="resign_device"
                            class="px-4 py-2 rounded-lg bg-amber-600 text-white hover:bg-amber-700">
                            Resign/Retrieve
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= RETIRE MODAL ================= -->
            <div id="retireModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Retire Device</h3>
                        <button onclick="closeRetireModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5 text-gray-700">
                        <p class="mb-2 font-medium">Are you sure you want to retire this device?</p>
                        <p class="text-sm text-gray-500">
                            The device will no longer be assignable but will remain in records.
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t flex justify-end gap-3">
                        <button onclick="closeRetireModal()"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="retire_id" id="retireId">
                            <button type="submit" name="retire"
                                class="px-4 py-2 rounded-lg bg-gray-600 text-white hover:bg-gray-700">
                                Yes, Retire
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ================= DELETE MODAL ================= -->
            <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-md rounded-xl shadow-xl">
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
        </main>
    </div>

    <script>
        // ==================== MODAL FUNCTIONS ====================
        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) {
                console.error('Modal not found:', id);
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
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

            document.getElementById('viewModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }

        // ==================== ASSIGN MODAL ====================
        function openAssignModal(id, assetTag) {
            document.getElementById('assignId').value = id;
            document.getElementById('assignAssetTag').textContent = assetTag;
            openModal('assignModal');
        }

        function closeAssignModal() {
            closeModal('assignModal');
        }

        // ==================== RESIGN MODAL ====================
        function openResignModal(id, assetTag, assignedUser) {
            document.getElementById('resignId').value = id;
            document.getElementById('resignAssetTag').textContent = assetTag;
            document.getElementById('resignAssignedUser').textContent = assignedUser;
            openModal('resignModal');
        }

        function closeResignModal() {
            closeModal('resignModal');
        }

        // ==================== RETIRE MODAL ====================
        function openRetireModal(id) {
            document.getElementById('retireId').value = id;
            openModal('retireModal');
        }

        function closeRetireModal() {
            closeModal('retireModal');
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

        // Initial adjustment
        adjustTableLayout();

        // Adjust on resize
        window.addEventListener('resize', adjustTableLayout);
    </script>
</body>

</html>