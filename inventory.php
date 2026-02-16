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
    $category_id = (int) $_POST['category_id'];

    // String fields
    $condition = mysqli_real_escape_string($conn, trim($_POST['condition']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));

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
            `condition`,
            status,
            category_id,
            remarks,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmt) {
        $_SESSION['error_message'] = 'Prepare failed: ' . mysqli_error($conn);
        header("Location: inventory.php?error=add_failed");
        exit;
    }

    // Bind parameters
    mysqli_stmt_bind_param(
        $stmt,
        "ssisssissss",
        $asset_tag,
        $device_type,
        $brand_id,
        $model,
        $serial_number,
        $specifications,
        $department_id,
        $condition,
        $status,
        $category_id,
        $remarks
    );

    if (mysqli_stmt_execute($stmt)) {
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
    $condition = mysqli_real_escape_string($conn, trim($_POST['condition']));
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $category_id = (int) $_POST['category_id'];
    $remarks = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));

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
            "sisssissssi",
            $device_type,
            $brand_id,
            $model,
            $serial_number,
            $specifications,
            $department_id,
            $condition,
            $status,
            $category_id,
            $remarks,
            $id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Update failed: " . mysqli_stmt_error($stmt));
        }

        // Handle status changes that affect assignments
        if ($status != 'in_use') {
            // If status is not 'in_use', end any active assignments
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

    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        if ($action === 'assign') {
            // ASSIGN DEVICE
            $user_id = !empty($_POST['assign_user']) ? (int) $_POST['assign_user'] : null;
            $assign_notes = mysqli_real_escape_string($conn, $_POST['assign_notes'] ?? '');
            $department_id = !empty($_POST['assign_department_id']) ? (int) $_POST['assign_department_id'] : null;

            if (!$user_id) {
                throw new Exception('Please select a user to assign.');
            }

            // Create assignment record
            $stmt = mysqli_prepare($conn, "
                INSERT INTO device_user_assignments (
                    inventory_id,
                    user_id,
                    assigned_at,
                    status
                ) VALUES (?, ?, NOW(), 'assigned')
            ");
            mysqli_stmt_bind_param($stmt, "ii", $device_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Update inventory item status and department
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_use', 
                    department_id = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "ii", $department_id, $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            if (!empty($assign_notes)) {
                $notesStmt = mysqli_prepare($conn, "
                    UPDATE inventory_items 
                    SET remarks = CONCAT(IFNULL(remarks, ''), '\nAssigned to user ID ', ?, ' on ', NOW(), ': ', ?)
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($notesStmt, "isi", $user_id, $assign_notes, $device_id);
                mysqli_stmt_execute($notesStmt);
                mysqli_stmt_close($notesStmt);
            }

            $_SESSION['success_message'] = 'Device assigned successfully!';

        } elseif ($action === 'reassign') {
            // REASSIGN DEVICE
            $user_id = !empty($_POST['reassign_user']) ? (int) $_POST['reassign_user'] : null;
            $reassign_notes = mysqli_real_escape_string($conn, $_POST['reassign_notes'] ?? '');
            $department_id = !empty($_POST['reassign_department_id']) ? (int) $_POST['reassign_department_id'] : null;

            if (!$user_id) {
                throw new Exception('Please select a user to reassign to.');
            }

            // End current assignment
            $endStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', 
                    returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endStmt, "i", $device_id);
            mysqli_stmt_execute($endStmt);
            mysqli_stmt_close($endStmt);

            // Create new assignment
            $stmt = mysqli_prepare($conn, "
                INSERT INTO device_user_assignments (
                    inventory_id,
                    user_id,
                    assigned_at,
                    status
                ) VALUES (?, ?, NOW(), 'assigned')
            ");
            mysqli_stmt_bind_param($stmt, "ii", $device_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Update inventory item status and department
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_use', 
                    department_id = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "ii", $department_id, $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            if (!empty($reassign_notes)) {
                $notesStmt = mysqli_prepare($conn, "
                    UPDATE inventory_items 
                    SET remarks = CONCAT(IFNULL(remarks, ''), '\nReassigned to user ID ', ?, ' on ', NOW(), ': ', ?)
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($notesStmt, "isi", $user_id, $reassign_notes, $device_id);
                mysqli_stmt_execute($notesStmt);
                mysqli_stmt_close($notesStmt);
            }

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

            // Set status to 'in_storage'
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'in_storage'
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "i", $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            if (!empty($reason)) {
                $notesStmt = mysqli_prepare($conn, "
                    UPDATE inventory_items 
                    SET remarks = CONCAT(IFNULL(remarks, ''), '\nRetrieved to store on ', NOW(), ' from user ID ', ?, ': ', ?)
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($notesStmt, "isi", $previousUserId, $reason, $device_id);
                mysqli_stmt_execute($notesStmt);
                mysqli_stmt_close($notesStmt);
            }

            $_SESSION['success_message'] = 'Device retrieved to store successfully!';

        } elseif ($action === 'retire') {
            // RETIRE DEVICE
            $retire_reason = mysqli_real_escape_string($conn, $_POST['retire_reason'] ?? '');

            // End any active assignments
            $endStmt = mysqli_prepare($conn, "
                UPDATE device_user_assignments 
                SET status = 'retrieved', 
                    returned_at = NOW()
                WHERE inventory_id = ? AND status = 'assigned'
            ");
            mysqli_stmt_bind_param($endStmt, "i", $device_id);
            mysqli_stmt_execute($endStmt);
            mysqli_stmt_close($endStmt);

            // Update device status to retired
            $updateStmt = mysqli_prepare($conn, "
                UPDATE inventory_items 
                SET status = 'retired'
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($updateStmt, "i", $device_id);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            // Update remarks
            if (!empty($retire_reason)) {
                $notesStmt = mysqli_prepare($conn, "
                    UPDATE inventory_items 
                    SET remarks = CONCAT(IFNULL(remarks, ''), '\nRetired on ', NOW(), ': ', ?)
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($notesStmt, "si", $retire_reason, $device_id);
                mysqli_stmt_execute($notesStmt);
                mysqli_stmt_close($notesStmt);
            }

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
                OR d.department_name LIKE ?)";

    $searchTerm = '%' . $_GET['search'] . '%';

    // Add 6 times for the 6 placeholders (removed location)
    $params = array_merge($params, [
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);

    $paramTypes .= str_repeat('s', 6);
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
        u.firstname,
        u.lastname,
        dua.user_id as assigned_user_id,
        dua.assigned_at,
        dua.returned_at
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
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
            break;
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Inventory Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="icon" type="image/png" href="./images/logo.png">
    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        /* Clean color palette - no gradients */
        :root {
            --primary: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fed7aa;
            --error: #ef4444;
            --error-light: #fee2e2;
            --info: #3b82f6;
            --info-light: #dbeafe;
        }

        body {
            background-color: var(--gray-50);
            color: var(--gray-800);
        }

        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
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
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            gap: 1rem;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }

        .toast-success {
            background-color: var(--success-light);
            border-left-color: var(--success);
            color: #065f46;
        }

        .toast-error {
            background-color: var(--error-light);
            border-left-color: var(--error);
            color: #991b1b;
        }

        .toast-warning {
            background-color: var(--warning-light);
            border-left-color: var(--warning);
            color: #92400e;
        }

        .toast-info {
            background-color: var(--info-light);
            border-left-color: var(--info);
            color: #1e40af;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(0, 0, 0, 0.1);
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

        /* Filter section */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* Table styles - FIXED COLUMN WIDTHS */
        .table-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow-x: auto;
            overflow-y: visible;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            width: 100%;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
            /* Prevents column compression */
        }

        .data-table thead th {
            background-color: var(--gray-50);
            padding: 0.875rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-600);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }

        /* FIXED COLUMN WIDTHS - Asset Tag column specifically */
        .data-table th:nth-child(1),
        .data-table td:nth-child(1) {
            width: 110px;
            min-width: 110px;
            max-width: 110px;
        }

        .data-table th:nth-child(2),
        .data-table td:nth-child(2) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table th:nth-child(3),
        .data-table td:nth-child(3) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        .data-table th:nth-child(4),
        .data-table td:nth-child(4) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table th:nth-child(5),
        .data-table td:nth-child(5) {
            width: 150px;
            min-width: 150px;
            max-width: 150px;
        }

        .data-table th:nth-child(6),
        .data-table td:nth-child(6) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table th:nth-child(7),
        .data-table td:nth-child(7) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
        }

        .data-table th:nth-child(8),
        .data-table td:nth-child(8) {
            width: 120px;
            min-width: 120px;
            max-width: 120px;
        }

        .data-table th:nth-child(9),
        .data-table td:nth-child(9) {
            width: 140px;
            min-width: 140px;
            max-width: 140px;
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background-color 0.15s ease;
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .data-table tbody td {
            padding: 1rem 1rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            vertical-align: middle;
        }

        /* Asset tag badge - FIXED DISPLAY */
        .asset-tag-badge {
            font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary-50);
            color: var(--primary-dark);
            border-radius: 4px;
            border: 1px solid var(--primary-200);
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .status-active {
            background-color: var(--success-light);
            color: #065f46;
            border-color: #a7f3d0;
        }

        .status-in_use {
            background-color: var(--primary-100);
            color: #1e40af;
            border-color: var(--primary-200);
        }

        .status-in_storage {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .status-repairing {
            background-color: #fff1f2;
            color: #9f1239;
            border-color: #ffe4e6;
        }

        .status-faulty {
            background-color: #fce7f3;
            color: #9d174d;
            border-color: #fbcfe8;
        }

        .status-retired {
            background-color: var(--gray-100);
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        /* Action buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .action-btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .action-btn-view {
            background-color: var(--primary);
        }

        .action-btn-edit {
            background-color: var(--success);
        }

        .action-btn-settings {
            background-color: var(--gray-600);
        }

        .action-btn-delete {
            background-color: var(--error);
        }

        /* User avatar */
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            background-color: var(--primary);
            flex-shrink: 0;
        }

        .user-avatar.unassigned {
            background-color: var(--gray-400);
        }

        /* Form inputs */
        .form-input {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: white;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-100);
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        /* Modal styles - FIXED BUTTON VISIBILITY */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 64rem;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            position: relative;
            margin: auto;
        }

        .modal-header {
            background-color: var(--primary-dark);
            color: white;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
        }

        .modal-header-default {
            background-color: var(--gray-800);
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            background-color: var(--gray-50);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            border-top: 1px solid var(--gray-200);
            flex-shrink: 0;
            border-bottom-left-radius: 16px;
            border-bottom-right-radius: 16px;
        }

        /* Button styles */
        .btn-primary {
            background-color: var(--primary);
            color: white;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: white;
            color: var(--gray-700);
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.875rem;
            border: 1px solid var(--gray-300);
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .btn-success {
            background-color: var(--success);
            color: white;
        }

        .btn-success:hover {
            background-color: #0f9e6a;
        }

        .btn-error {
            background-color: var(--error);
            color: white;
        }

        .btn-error:hover {
            background-color: #dc2626;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background-color: #d97706;
        }

        /* Tabs */
        .action-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
            color: var(--gray-600);
            background: transparent;
            cursor: pointer;
            border: none;
        }

        .action-tab:hover {
            background-color: var(--gray-50);
            color: var(--gray-900);
        }

        .action-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary-dark);
            font-weight: 600;
            background-color: var(--primary-50);
        }

        .action-tab.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: var(--gray-50);
            color: var(--gray-500);
        }

        .action-tab.disabled:hover {
            background-color: var(--gray-50);
            color: var(--gray-500);
        }

        /* Pagination */
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 0.5rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
        }

        .pagination-item:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-400);
        }

        .pagination-item.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        /* Filter tags */
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.75rem;
            background-color: var(--primary-50);
            color: var(--primary-dark);
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid var(--primary-200);
        }

        /* Quick action buttons */
        .quick-action-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: white;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .quick-action-btn:hover {
            background-color: var(--gray-50);
            border-color: var(--gray-300);
        }

        /* Text utilities */
        .text-ellipsis {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Divider */
        .divider {
            border-top: 1px solid var(--gray-200);
            margin: 1rem 0;
        }

        /* Sidebar adjustment */
        .ml-64 {
            margin-left: 16rem;
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .ml-64 {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body class="antialiased bg-gradient-to-br from-gray-50 via-blue-50 to-purple-50 min-h-screen">
    <!-- Toast Container -->
    <div id="toast-container"></div>

    <div class="flex min-h-screen">
        <?php include "sidebar.php"; ?>

        <!-- Main Content -->
        <main id="mainContent" class="flex-1 p-8 ml-64">
            <!-- Header Section -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-12 h-12 bg-primary text-white rounded-xl flex items-center justify-center shadow-sm">
                            <i class="fas fa-cubes text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Device Inventory</h1>
                            <p class="text-gray-500 mt-1 flex items-center gap-2">
                                <i class="fas fa-database text-gray-400"></i>
                                <span>Manage and track all IT assets</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Quick Actions Button -->
                    <button onclick="openQuickActionsModal()" class="btn-secondary flex items-center gap-2">
                        <i class="fas fa-bolt text-amber-500"></i>
                        <span>Quick Actions</span>
                    </button>

                    <!-- Add Item Button -->
                    <button onclick="openModal('addModal')" class="btn-primary flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add Device</span>
                    </button>
                </div>
            </div>

            <!-- Display PHP Session Messages as Toasts -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div id="success-toast" class="hidden"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div id="error-toast" class="hidden"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['form_errors']) && !empty($_SESSION['form_errors'])): ?>
                <div id="warning-toast" class="hidden">
                    <div class="font-semibold mb-1">Please fix the following errors:</div>
                    <?php foreach ($_SESSION['form_errors'] as $error): ?>
                        • <?= htmlspecialchars($error) ?><br>
                    <?php endforeach; ?>
                </div>
                <?php unset($_SESSION['form_errors']); ?>
            <?php endif; ?>

            <!-- Filters Section -->
            <div class="filter-section">
                <form method="GET" class="w-full">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <label class="form-label">
                                <i class="fas fa-search text-gray-400 mr-1"></i> Search
                            </label>
                            <div class="relative">
                                <input id="searchInput" onkeyup="searchTable()" type="text" name="search"
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                    placeholder="Search by asset, type, brand, user..." class="form-input pl-10"
                                    autocomplete="off">
                                <i
                                    class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                        </div>

                        <!-- Department Filter -->
                        <div class="flex-1">
                            <label class="form-label">
                                <i class="fas fa-building text-gray-400 mr-1"></i> Department
                            </label>
                            <div class="relative">
                                <select name="department" class="form-input appearance-none">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departmentsArr as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= ($_GET['department'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['department_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i
                                    class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Category Filter -->
                        <div class="flex-1">
                            <label class="form-label">
                                <i class="fas fa-tag text-gray-400 mr-1"></i> Category
                            </label>
                            <div class="relative">
                                <select name="category" class="form-input appearance-none">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categoriesArr as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($_GET['category'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i
                                    class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex-1">
                            <label class="form-label">
                                <i class="fas fa-flag text-gray-400 mr-1"></i> Status
                            </label>
                            <div class="relative">
                                <select name="status" id="statusFilter" class="form-input appearance-none">
                                    <option value="">All Status</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>" <?= ($_GET['status'] ?? '') === $status ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($statusLabels[$status] ?? ucfirst($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i
                                    class="fas fa-chevron-down absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-end gap-2">
                            <button type="submit" class="btn-primary flex items-center gap-2">
                                <i class="fas fa-filter"></i>
                                <span>Apply</span>
                            </button>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn-secondary flex items-center gap-2">
                                <i class="fas fa-redo-alt"></i>
                                <span>Reset</span>
                            </a>
                            <button type="button" onclick="window.location.href='export_assignments.php'"
                                class="btn-secondary flex items-center gap-2 border-emerald-200 text-emerald-700 hover:bg-emerald-50">
                                <i class="fas fa-download"></i>
                                <span>Export</span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Active Filters -->
                <?php if (!empty($activeFilters)): ?>
                    <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-gray-200">
                        <span class="text-xs font-medium text-gray-500 mr-1">Active Filters:</span>
                        <?php foreach ($activeFilters as $filter): ?>
                            <div class="filter-tag">
                                <i class="fas fa-check-circle text-primary-500 text-xs"></i>
                                <span><?= $filter['label'] ?></span>
                                <a href="?<?= http_build_query(array_diff_key($_GET, [$filter['param'] => ''])) ?>"
                                    class="ml-1 hover:text-primary-900 transition-colors">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>"
                            class="text-xs text-gray-500 hover:text-gray-700 ml-2 underline underline-offset-2">
                            Clear all
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Table Section - FIXED COLUMN WIDTHS -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Device Type</th>
                            <th>Brand</th>
                            <th>Model</th>
                            <th>Assigned To</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody">
                        <?php
                        $statusColors = [
                            'active' => 'status-active',
                            'in_use' => 'status-in_use',
                            'in_storage' => 'status-in_storage',
                            'repairing' => 'status-repairing',
                            'faulty' => 'status-faulty',
                            'retired' => 'status-retired'
                        ];
                        ?>

                        <?php if (!$list || mysqli_num_rows($list) === 0): ?>
                            <tr>
                                <td colspan="9" class="py-16 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="w-20 h-20 bg-gray-100 rounded-xl flex items-center justify-center">
                                            <i class="fas fa-search text-3xl text-gray-400"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-700 mb-1">No devices found</h3>
                                            <p class="text-sm text-gray-500">Try adjusting your filters or add a new device
                                            </p>
                                        </div>
                                        <button onclick="openModal('addModal')" class="mt-2 btn-primary text-sm">
                                            <i class="fas fa-plus mr-2"></i>Add New Device
                                        </button>
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
                                    <!-- ASSET TAG - FIXED WIDTH -->
                                    <td>
                                        <span class="asset-tag-badge" title="<?= htmlspecialchars($row['asset_tag']) ?>">
                                            <?= htmlspecialchars($row['asset_tag']) ?>
                                        </span>
                                    </td>

                                    <!-- DEVICE TYPE -->
                                    <td>
                                        <div class="font-medium text-gray-800 text-ellipsis"
                                            title="<?= htmlspecialchars($row['device_type']) ?>">
                                            <?= htmlspecialchars($row['device_type']) ?>
                                        </div>
                                    </td>

                                    <!-- BRAND -->
                                    <td>
                                        <div class="text-gray-600 text-ellipsis"
                                            title="<?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?>">
                                            <?= htmlspecialchars($row['brand_name'] ?? 'N/A') ?>
                                        </div>
                                    </td>

                                    <!-- MODEL -->
                                    <td>
                                        <div class="text-gray-600 text-ellipsis" title="<?= htmlspecialchars($row['model']) ?>">
                                            <?= htmlspecialchars($row['model']) ?>
                                        </div>
                                    </td>

                                    <!-- ASSIGNED USER -->
                                    <td>
                                        <?php if ($isAssigned): ?>
                                            <div class="flex items-center gap-2">
                                                <div class="user-avatar flex-shrink-0">
                                                    <?= strtoupper(substr($fullName, 0, 1)) ?>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-medium text-gray-800 text-sm text-ellipsis"
                                                        title="<?= htmlspecialchars($fullName) ?>">
                                                        <?= htmlspecialchars($fullName) ?>
                                                    </div>
                                                    <?php if (!empty($row['assigned_at'])): ?>
                                                        <div class="text-xs text-gray-500">
                                                            <?= date('M d, Y', strtotime($row['assigned_at'])) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex items-center gap-2">
                                                <div class="user-avatar unassigned">
                                                    <i class="fas fa-user-slash text-white text-xs"></i>
                                                </div>
                                                <span class="text-gray-500 text-sm">Unassigned</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- DEPARTMENT -->
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-building text-gray-400 text-xs"></i>
                                            <span class="text-gray-600 text-sm text-ellipsis"
                                                title="<?= htmlspecialchars($row['department_name'] ?? 'N/A') ?>">
                                                <?= htmlspecialchars($row['department_name'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- STATUS -->
                                    <td>
                                        <?php
                                        $statusClass = $statusColors[$row['status']] ?? 'status-active';
                                        ?>
                                        <span class="status-badge <?= $statusClass ?>"
                                            title="Status: <?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?>">
                                            <?= htmlspecialchars($statusLabels[$row['status']] ?? ucfirst($row['status'])) ?>
                                        </span>
                                    </td>

                                    <!-- CATEGORY -->
                                    <td>
                                        <span class="text-gray-600 text-sm text-ellipsis"
                                            title="<?= htmlspecialchars($row['category_name'] ?? 'N/A') ?>">
                                            <?= htmlspecialchars($row['category_name'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <!-- ACTIONS -->
                                    <td>
                                        <div class="flex gap-1.5">
                                            <!-- View -->
                                            <button
                                                onclick='openViewModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'
                                                class="action-btn action-btn-view" title="View Details">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>

                                            <!-- Edit -->
                                            <button onclick="openModal('editModal<?= $row['id'] ?>')"
                                                class="action-btn action-btn-edit" title="Edit Device">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>

                                            <!-- Device Actions -->
                                            <button
                                                onclick="openDeviceActionsModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['asset_tag']) ?>', '<?= htmlspecialchars($fullName) ?>', '<?= $row['status'] ?>', '<?= htmlspecialchars($row['department_name'] ?? '') ?>', '<?= $row['department_id'] ?? '' ?>')"
                                                class="action-btn action-btn-settings" title="Device Actions">
                                                <i class="fas fa-cog text-xs"></i>
                                            </button>

                                            <!-- Delete -->
                                            <button onclick="openDeleteModal(<?= (int) $row['id'] ?>)"
                                                class="action-btn action-btn-delete" title="Delete Device">
                                                <i class="fas fa-trash-alt text-xs"></i>
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

            <!-- Pagination Section -->
            <?php if ($totalPages > 1): ?>
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                $baseUrl = '?' . (!empty($queryParams) ? http_build_query($queryParams) . '&' : '');
                ?>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-gray-600">
                        Showing <span
                            class="font-semibold text-gray-900"><?= min($limit, $totalRecords - (($page - 1) * $limit)) ?></span>
                        of <span class="font-semibold text-gray-900"><?= $totalRecords ?></span> devices
                    </div>

                    <div class="flex items-center gap-4">
                        <!-- Items per page -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600">Show</span>
                            <select onchange="changeItemsPerPage(this)" class="form-input !py-1.5 !w-auto text-sm">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                            <span class="text-sm text-gray-600">per page</span>
                        </div>

                        <!-- Pagination -->
                        <div class="flex items-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="<?= $baseUrl ?>page=<?= $page - 1 ?>" class="pagination-item">
                                    <i class="fas fa-chevron-left text-xs"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1) {
                                echo '<a href="' . $baseUrl . 'page=1" class="pagination-item">1</a>';
                                if ($startPage > 2) {
                                    echo '<span class="px-2 text-gray-400">...</span>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                <a href="<?= $baseUrl ?>page=<?= $i ?>"
                                    class="pagination-item <?= $i == $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="<?= $baseUrl ?>page=<?= $totalPages ?>" class="pagination-item">
                                    <?= $totalPages ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $baseUrl ?>page=<?= $page + 1 ?>" class="pagination-item">
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================= EDIT MODALS ================= -->
            <?php
            if ($list && mysqli_num_rows($list) > 0) {
                mysqli_data_seek($list, 0);

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
                    <!-- Edit Modal - FIXED BUTTON VISIBILITY -->
                    <div id="editModal<?= $row['id'] ?>" class="fixed inset-0 bg-black/50 hidden z-50 modal-backdrop"
                        onclick="closeModalOnBackdrop(event, 'editModal<?= $row['id'] ?>')">

                        <div class="bg-white w-full max-w-4xl rounded-xl shadow-xl modal-content"
                            onclick="event.stopPropagation()">

                            <!-- Header -->
                            <div class="modal-header">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-edit text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-lg font-bold text-white">Edit Device</h2>
                                        <p class="text-white/80 text-sm">Asset: <?= htmlspecialchars($row['asset_tag']) ?></p>
                                    </div>
                                </div>
                                <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')"
                                    class="text-white/80 hover:text-white transition-colors">
                                    <i class="fas fa-times text-xl"></i>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="modal-body">
                                <form method="POST" action="inventory.php" id="editForm<?= $row['id'] ?>">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">

                                    <!-- Basic Information -->
                                    <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-info-circle text-primary"></i>
                                            Basic Information
                                        </h3>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                            <div class="md:col-span-2">
                                                <label class="form-label">Asset Tag</label>
                                                <input readonly name="asset_tag"
                                                    value="<?= htmlspecialchars($row['asset_tag']) ?>"
                                                    class="form-input bg-gray-100 cursor-not-allowed">
                                            </div>

                                            <div>
                                                <label class="form-label">Device Name <span
                                                        class="text-red-500">*</span></label>
                                                <input name="device_type" required
                                                    value="<?= htmlspecialchars($row['device_type']) ?>" class="form-input">
                                            </div>

                                            <div>
                                                <label class="form-label">Brand <span class="text-red-500">*</span></label>
                                                <select name="brand_id" required class="form-input">
                                                    <option value="">Select Brand</option>
                                                    <?php foreach ($brandsArr as $b): ?>
                                                        <option value="<?= $b['id'] ?>" <?= $row['brand_id'] == $b['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($b['brand_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label">Model</label>
                                                <input name="model" value="<?= htmlspecialchars($row['model']) ?>"
                                                    class="form-input">
                                            </div>

                                            <div>
                                                <label class="form-label">Serial Number</label>
                                                <input name="serial_number"
                                                    value="<?= htmlspecialchars($row['serial_number']) ?>" class="form-input">
                                            </div>

                                            <div class="md:col-span-2">
                                                <label class="form-label">Specifications</label>
                                                <input name="specifications"
                                                    value="<?= htmlspecialchars($row['specifications']) ?>" class="form-input">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Assignment Details -->
                                    <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-user-tag text-primary"></i>
                                            Assignment Details
                                        </h3>

                                        <div class="grid grid-cols-1 gap-5">
                                            <div>
                                                <label class="form-label">Department</label>
                                                <select name="department_id" class="form-input">
                                                    <option value="">No Department</option>
                                                    <?php foreach ($departmentsArr as $d): ?>
                                                        <option value="<?= $d['id'] ?>" <?= $row['department_id'] == $d['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($d['department_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status & Category -->
                                    <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-cog text-primary"></i>
                                            Status & Category
                                        </h3>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                            <div>
                                                <label class="form-label">Condition <span class="text-red-500">*</span></label>
                                                <select name="condition" required class="form-input">
                                                    <?php foreach (['Excellent', 'Good', 'Fair', 'Poor', 'New', 'Faulty'] as $c): ?>
                                                        <option value="<?= $c ?>" <?= $row['condition'] === $c ? 'selected' : '' ?>>
                                                            <?= $c ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label">Status <span class="text-red-500">*</span></label>
                                                <select name="status" required class="form-input">
                                                    <?php foreach ($allowedStatuses as $value): ?>
                                                        <option value="<?= $value ?>" <?= $row['status'] === $value ? 'selected' : '' ?>>
                                                            <?= $statusLabels[$value] ?? ucfirst($value) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="form-label">Category <span class="text-red-500">*</span></label>
                                                <select name="category_id" required class="form-input">
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

                                    <!-- Remarks -->
                                    <div class="bg-gray-50 rounded-lg p-5">
                                        <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                            <i class="fas fa-sticky-note text-primary"></i>
                                            Additional Notes
                                        </h3>
                                        <textarea name="remarks" rows="4"
                                            class="form-input resize-none"><?= htmlspecialchars($row['remarks']) ?></textarea>
                                    </div>
                                </form>
                            </div>

                            <!-- Footer - FIXED VISIBILITY -->
                            <div class="modal-footer">
                                <button type="button" onclick="closeModal('editModal<?= $row['id'] ?>')" class="btn-secondary">
                                    Cancel
                                </button>
                                <button type="submit" form="editForm<?= $row['id'] ?>" name="update_inventory"
                                    class="btn-primary flex items-center gap-2">
                                    <i class="fas fa-save"></i>
                                    Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                <?php }
            } ?>

            <!-- ================= ADD MODAL ================= -->
            <div id="addModal" class="fixed inset-0 bg-black/50 hidden z-50 modal-backdrop"
                onclick="closeModalOnBackdrop(event, 'addModal')">

                <div class="bg-white w-full max-w-4xl rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="modal-header">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-white">Add New Device</h2>
                                <p class="text-white/80 text-sm">Fill in the device details below</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeModal('addModal')"
                            class="text-white/80 hover:text-white transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <form method="POST" id="addForm" autocomplete="off">
                            <!-- Basic Information -->
                            <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-info-circle text-primary"></i>
                                    Basic Information
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div class="md:col-span-2">
                                        <label class="form-label">Asset Tag (Auto-generated)</label>
                                        <input readonly name="asset_tag"
                                            value="<?= htmlspecialchars($asset_tag_preview) ?>"
                                            class="form-input bg-gray-100 cursor-not-allowed">
                                    </div>

                                    <div>
                                        <label class="form-label">Device Name <span
                                                class="text-red-500">*</span></label>
                                        <input name="device_type" required class="form-input"
                                            placeholder="e.g., Laptop, Desktop">
                                    </div>

                                    <div>
                                        <label class="form-label">Brand <span class="text-red-500">*</span></label>
                                        <select name="brand_id" required class="form-input">
                                            <option value="">Select Brand</option>
                                            <?php foreach ($brandsArr as $b): ?>
                                                <option value="<?= $b['id'] ?>">
                                                    <?= htmlspecialchars($b['brand_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Model</label>
                                        <input name="model" class="form-input" placeholder="e.g., XPS 15, ThinkPad X1">
                                    </div>

                                    <div>
                                        <label class="form-label">Serial Number</label>
                                        <input name="serial_number" class="form-input" placeholder="e.g., SN123456789">
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="form-label">Specifications</label>
                                        <input name="specifications" class="form-input"
                                            placeholder="e.g., Intel i7, 16GB RAM, 512GB SSD">
                                    </div>
                                </div>
                            </div>

                            <!-- Assignment Details -->
                            <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-user-tag text-primary"></i>
                                    Assignment Details (Optional)
                                </h3>

                                <div class="grid grid-cols-1 gap-5">
                                    <div>
                                        <label class="form-label">Department</label>
                                        <select name="department_id" class="form-input">
                                            <option value="">No Department</option>
                                            <?php foreach ($departmentsArr as $d): ?>
                                                <option value="<?= $d['id'] ?>">
                                                    <?= htmlspecialchars($d['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Status & Category -->
                            <div class="bg-gray-50 rounded-lg p-5 mb-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-cog text-primary"></i>
                                    Status & Category
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div>
                                        <label class="form-label">Condition <span class="text-red-500">*</span></label>
                                        <select name="condition" required class="form-input">
                                            <option value="">Select Condition</option>
                                            <?php foreach (['New', 'Excellent', 'Good', 'Fair', 'Poor', 'Faulty'] as $c): ?>
                                                <option value="<?= $c ?>"><?= $c ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Status <span class="text-red-500">*</span></label>
                                        <select name="status" required class="form-input">
                                            <option value="">Select Status</option>
                                            <option value="active">Active</option>
                                            <option value="in_storage">In Storage</option>
                                            <option value="in_use">In Use</option>
                                            <option value="repairing">Repairing</option>
                                            <option value="faulty">Faulty</option>
                                            <option value="retired">Retired</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Category <span class="text-red-500">*</span></label>
                                        <select name="category_id" required class="form-input">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categoriesArr as $c): ?>
                                                <option value="<?= $c['id'] ?>">
                                                    <?= htmlspecialchars($c['category_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Remarks -->
                            <div class="bg-gray-50 rounded-lg p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                    <i class="fas fa-sticky-note text-primary"></i>
                                    Remarks
                                </h3>
                                <textarea name="remarks" rows="4" class="form-input resize-none"
                                    placeholder="Add any additional notes or remarks..."></textarea>
                            </div>
                        </form>
                    </div>

                    <!-- Footer - FIXED VISIBILITY -->
                    <div class="modal-footer">
                        <button type="button" onclick="closeModal('addModal')" class="btn-secondary">
                            Cancel
                        </button>
                        <button type="submit" form="addForm" name="save" class="btn-primary flex items-center gap-2">
                            <i class="fas fa-plus-circle"></i>
                            Add Device
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= VIEW MODAL ================= -->
            <div id="viewModal" class="fixed inset-0 bg-black/50 hidden z-50 modal-backdrop"
                onclick="closeModalOnBackdrop(event, 'viewModal')">

                <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="modal-header">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-eye text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-white">Device Details</h2>
                                <p class="text-white/80 text-sm" id="view_asset_tag_subtitle"></p>
                            </div>
                        </div>
                        <button onclick="closeViewModal()" class="text-white/80 hover:text-white transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body">
                        <!-- Basic Information -->
                        <div class="bg-primary-50/30 rounded-lg p-5 mb-5 border border-primary-100">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-info-circle text-primary"></i>
                                Basic Information
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Asset Tag</p>
                                    <p class="font-semibold text-gray-800" id="view_asset_tag"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Device Type</p>
                                    <p class="font-semibold text-gray-800" id="view_device_type"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Brand</p>
                                    <p class="font-semibold text-gray-800" id="view_brand"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Model</p>
                                    <p class="font-semibold text-gray-800" id="view_model"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Serial Number</p>
                                    <p class="font-semibold text-gray-800" id="view_serial_number"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Category</p>
                                    <p class="font-semibold text-gray-800" id="view_category"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200 md:col-span-2">
                                    <p class="text-xs text-gray-500 mb-1">Specifications</p>
                                    <p class="font-semibold text-gray-800" id="view_specifications"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Assignment Details -->
                        <div class="bg-green-50/30 rounded-lg p-5 mb-5 border border-green-100">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-user-tag text-green-600"></i>
                                Assignment Details
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Department</p>
                                    <p class="font-semibold text-gray-800" id="view_department"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Assigned User</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_user"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">User ID</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_user_id"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Assigned Since</p>
                                    <p class="font-semibold text-gray-800" id="view_assigned_at"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Condition -->
                        <div class="bg-amber-50/30 rounded-lg p-5 mb-5 border border-amber-100">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-cog text-amber-600"></i>
                                Status & Condition
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Condition</p>
                                    <p class="font-semibold text-gray-800" id="view_condition"></p>
                                </div>
                                <div class="bg-white rounded-lg p-4 border border-gray-200">
                                    <p class="text-xs text-gray-500 mb-1">Status</p>
                                    <p class="font-semibold text-gray-800" id="view_status"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="bg-gray-50 rounded-lg p-5">
                            <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                                <i class="fas fa-sticky-note text-gray-600"></i>
                                Additional Notes
                            </h3>
                            <div class="bg-white rounded-lg p-4 border border-gray-200">
                                <p class="text-gray-700" id="view_remarks"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer - FIXED VISIBILITY -->
                    <div class="modal-footer">
                        <button onclick="closeViewModal()" class="btn-primary flex items-center gap-2">
                            <i class="fas fa-check"></i>
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= DEVICE ACTIONS MODAL ================= -->
            <div id="deviceActionsModal" class="fixed inset-0 bg-black/50 hidden z-50 modal-backdrop">

                <div class="bg-white w-full max-w-xl rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="modal-header modal-header-default">
                        <div>
                            <h3 class="text-lg font-bold text-white">Device Actions</h3>
                            <p class="text-gray-300 text-sm mt-1">
                                Device: <span id="deviceActionsAssetTag"
                                    class="font-mono font-semibold text-white"></span>
                            </p>
                        </div>
                        <button type="button" onclick="closeDeviceActionsModal()"
                            class="text-gray-300 hover:text-white transition-colors">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Current User Info -->
                    <div id="deviceCurrentUserInfo" class="px-6 py-4 bg-primary-50 border-b border-primary-100 hidden">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-primary"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Current User:</span>
                                    <span id="deviceCurrentUserName" class="font-semibold text-primary-700 ml-1"></span>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <span class="font-medium">Department:</span>
                                    <span id="deviceCurrentDepartment"
                                        class="font-semibold text-primary-700 ml-1"></span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Tabs -->
                    <div class="border-b border-gray-200 px-6 pt-4">
                        <div class="flex gap-2">
                            <button type="button" id="deviceAssignTab" class="action-tab"
                                onclick="showDeviceActionTab('assign')">
                                <i class="fas fa-user-plus text-green-600 mr-2"></i>
                                Assign
                            </button>
                            <button type="button" id="deviceReassignTab" class="action-tab"
                                onclick="showDeviceActionTab('reassign')">
                                <i class="fas fa-recycle text-blue-600 mr-2"></i>
                                Reassign
                            </button>
                            <button type="button" id="deviceRetrieveTab" class="action-tab"
                                onclick="showDeviceActionTab('retrieve')">
                                <i class="fas fa-arrow-left text-amber-600 mr-2"></i>
                                Retrieve
                            </button>
                            <button type="button" id="deviceRetireTab" class="action-tab"
                                onclick="showDeviceActionTab('retire')">
                                <i class="fas fa-archive text-gray-600 mr-2"></i>
                                Retire
                            </button>
                        </div>
                    </div>

                    <!-- Form -->
                    <form method="POST" action="inventory.php" id="deviceActionsForm">
                        <input type="hidden" name="device_id" id="deviceActionsId">
                        <input type="hidden" name="device_action" value="1">

                        <!-- Tab Content -->
                        <div class="p-6">
                            <!-- Assign Content -->
                            <div id="deviceAssignContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div>
                                        <label class="form-label">Select User <span
                                                class="text-red-500">*</span></label>
                                        <select name="assign_user" id="deviceAssignUserSelect" required
                                            class="form-input">
                                            <option value="">Choose a user...</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Department (Optional)</label>
                                        <select name="assign_department_id" id="deviceAssignDepartmentSelect"
                                            class="form-input">
                                            <option value="">Keep current department</option>
                                            <?php foreach ($departmentsArr as $d): ?>
                                                <option value="<?= $d['id'] ?>">
                                                    <?= htmlspecialchars($d['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Assignment Notes (Optional)</label>
                                        <textarea name="assign_notes" id="deviceAssignNotes" rows="3"
                                            class="form-input resize-none"
                                            placeholder="Add any notes about this assignment..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Reassign Content -->
                            <div id="deviceReassignContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-100">
                                        <p class="text-sm text-blue-700 mb-2 flex items-center gap-2">
                                            <i class="fas fa-info-circle"></i>
                                            <span class="font-medium">Current Assignment:</span>
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user text-blue-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <span id="devicePreviousUserName"
                                                    class="font-semibold text-blue-800"></span>
                                                <span class="text-blue-600 text-xs mx-1">•</span>
                                                <span id="devicePreviousDepartment"
                                                    class="text-blue-700 text-sm"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label">Select New User <span
                                                class="text-red-500">*</span></label>
                                        <select name="reassign_user" id="deviceReassignUserSelect" required
                                            class="form-input">
                                            <option value="">Choose a user...</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Update Department (Optional)</label>
                                        <select name="reassign_department_id" id="deviceReassignDepartmentSelect"
                                            class="form-input">
                                            <option value="">Keep current department</option>
                                            <?php foreach ($departmentsArr as $d): ?>
                                                <option value="<?= $d['id'] ?>">
                                                    <?= htmlspecialchars($d['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="form-label">Reassignment Notes (Optional)</label>
                                        <textarea name="reassign_notes" id="deviceReassignNotes" rows="3"
                                            class="form-input resize-none"
                                            placeholder="Add any notes about this reassignment..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Retrieve Content -->
                            <div id="deviceRetrieveContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="bg-amber-50 rounded-lg p-4 border border-amber-100">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-user text-amber-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm text-amber-700 mb-1">
                                                    <span class="font-medium">Currently assigned to:</span>
                                                    <span id="deviceRetrieveUserName" class="font-semibold ml-1"></span>
                                                </p>
                                                <p class="text-xs text-amber-600">
                                                    This device will be retrieved and marked as "In Storage"
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label">Reason for Retrieval (Optional)</label>
                                        <textarea name="retrieve_reason" id="deviceRetrieveReason" rows="3"
                                            class="form-input resize-none"
                                            placeholder="e.g., Maintenance, Return to inventory, Device inspection..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Retire Content -->
                            <div id="deviceRetireContent" class="action-tab-content hidden">
                                <div class="space-y-4">
                                    <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-red-800 mb-1">Important Notice</p>
                                                <p class="text-xs text-red-700 leading-relaxed">
                                                    This device will be permanently marked as retired and removed from
                                                    active inventory.
                                                    Any active assignments will be automatically ended. This action
                                                    cannot be undone.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label">Retirement Reason (Optional)</label>
                                        <textarea name="retire_reason" id="deviceRetireReason" rows="3"
                                            class="form-input resize-none"
                                            placeholder="e.g., End of life, Damaged beyond repair, Replaced..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer - FIXED VISIBILITY -->
                        <div class="modal-footer">
                            <button type="button" onclick="closeDeviceActionsModal()" class="btn-secondary">
                                Cancel
                            </button>
                            <button type="button" onclick="confirmDeviceAction()" id="deviceSubmitActionBtn"
                                class="btn-primary flex items-center gap-2">
                                <i class="fas fa-check-circle"></i>
                                Confirm Action
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ================= CONFIRMATION MODAL ================= -->
            <div id="confirmationModal" class="fixed inset-0 bg-black/50 hidden z-[100] modal-backdrop">

                <div class="bg-white w-full max-w-md rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800" id="confirmationTitle">Confirm Action</h3>
                        <button onclick="closeConfirmationModal()"
                            class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5">
                        <div class="flex items-start gap-4">
                            <div
                                class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-question-circle text-amber-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-700" id="confirmationMessage"></p>
                                <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200"
                                    id="confirmationWarning">
                                    <p class="text-sm text-gray-600 flex items-center gap-2">
                                        <i class="fas fa-info-circle text-gray-400"></i>
                                        This action will be recorded in the system logs
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button onclick="closeConfirmationModal()" class="btn-secondary">
                            Cancel
                        </button>
                        <button onclick="executeConfirmedAction()" id="confirmActionBtn"
                            class="btn-primary flex items-center gap-2">
                            <i class="fas fa-check"></i>
                            Confirm
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= DELETE CONFIRMATION MODAL ================= -->
            <div id="deleteConfirmationModal" class="fixed inset-0 bg-black/50 hidden z-[100] modal-backdrop">

                <div class="bg-white w-full max-w-md rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Delete Device</h3>
                        <button onclick="closeDeleteConfirmationModal()"
                            class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-5">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-trash-alt text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-gray-700" id="deleteConfirmationMessage"></p>
                                <div class="mt-4 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <p class="text-sm text-red-700 flex items-center gap-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span><strong>Warning:</strong> This action cannot be undone. All assignment
                                            history will be permanently deleted.</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
                        <button onclick="closeDeleteConfirmationModal()" class="btn-secondary">
                            Cancel
                        </button>
                        <button onclick="executeDelete()" class="btn-error flex items-center gap-2">
                            <i class="fas fa-trash-alt"></i>
                            Delete Permanently
                        </button>
                    </div>
                </div>
            </div>

            <!-- ================= QUICK ACTIONS MODAL ================= -->
            <div id="quickActionsModal" class="fixed inset-0 bg-black/50 hidden z-[110] modal-backdrop">

                <div class="bg-white w-full max-w-sm rounded-xl shadow-xl modal-content"
                    onclick="event.stopPropagation()">

                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Quick Actions</h3>
                        <button onclick="closeQuickActionsModal()"
                            class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6">
                        <div class="space-y-3">
                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Device
                                    Management</h4>
                                <button onclick="performQuickAction('refresh')" class="quick-action-btn">
                                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-sync-alt text-blue-600"></i>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-medium text-gray-700">Refresh Inventory</p>
                                        <p class="text-xs text-gray-500">Reload current data</p>
                                    </div>
                                </button>
                                <button onclick="performQuickAction('export')" class="quick-action-btn">
                                    <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-file-export text-green-600"></i>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-medium text-gray-700">Export Data</p>
                                        <p class="text-xs text-gray-500">Download as CSV</p>
                                    </div>
                                </button>
                            </div>

                            <div class="divider"></div>

                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">View
                                    Options</h4>
                                <button onclick="performQuickAction('toggle_view')" class="quick-action-btn">
                                    <div class="w-10 h-10 bg-purple-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-table text-purple-600"></i>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-medium text-gray-700">Toggle Table View</p>
                                        <p class="text-xs text-gray-500">Switch between compact/full view</p>
                                    </div>
                                </button>
                                <button onclick="performQuickAction('clear_filters')" class="quick-action-btn">
                                    <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-filter-slash text-amber-600"></i>
                                    </div>
                                    <div class="flex-1 text-left">
                                        <p class="font-medium text-gray-700">Clear All Filters</p>
                                        <p class="text-xs text-gray-500">Reset to default view</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <p class="text-xs text-gray-500 text-center">
                            <i class="fas fa-keyboard mr-1"></i>
                            Press <kbd class="px-1.5 py-0.5 bg-white border border-gray-200 rounded text-xs">ESC</kbd>
                            to close
                        </p>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        // ==================== TOAST NOTIFICATION SYSTEM ====================
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
                if (!container) return;

                const toast = document.createElement('div');
                toast.id = this.id;
                toast.className = `toast toast-${this.type}`;

                const icons = {
                    'success': '<i class="fas fa-check-circle"></i>',
                    'error': '<i class="fas fa-exclamation-circle"></i>',
                    'warning': '<i class="fas fa-exclamation-triangle"></i>',
                    'info': '<i class="fas fa-info-circle"></i>'
                };

                toast.innerHTML = `
                    <div class="toast-icon text-xl">${icons[this.type]}</div>
                    <div class="toast-content flex-1">
                        <div class="toast-title font-semibold text-sm">${this.title}</div>
                        <div class="toast-message text-sm opacity-90">${this.message}</div>
                    </div>
                    <button class="toast-close w-8 h-8 rounded-lg hover:bg-black/5 transition-colors" onclick="Toast.hide('${this.id}')">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="toast-progress" style="animation-duration: ${this.duration}ms"></div>
                `;

                container.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('show');
                }, 10);

                this.timeout = setTimeout(() => {
                    this.hide();
                }, this.duration);
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

            static showSuccess(message, title = 'Success') {
                new Toast('success', title, message).show();
            }

            static showError(message, title = 'Error') {
                new Toast('error', title, message).show();
            }

            static showWarning(message, title = 'Warning') {
                new Toast('warning', title, message, 7000).show();
            }

            static showInfo(message, title = 'Info') {
                new Toast('info', title, message, 3000).show();
            }
        }

        // ==================== MODAL MANAGEMENT ====================
        function openModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;

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
            document.getElementById('view_asset_tag').textContent = item.asset_tag || 'N/A';
            document.getElementById('view_asset_tag_subtitle').textContent = `Asset: ${item.asset_tag || ''}`;
            document.getElementById('view_device_type').textContent = item.device_type || 'N/A';
            document.getElementById('view_brand').textContent = item.brand_name || 'N/A';
            document.getElementById('view_model').textContent = item.model || 'N/A';
            document.getElementById('view_serial_number').textContent = item.serial_number || 'N/A';
            document.getElementById('view_category').textContent = item.category_name || 'N/A';
            document.getElementById('view_specifications').textContent = item.specifications || 'No specifications provided';
            document.getElementById('view_department').textContent = item.department_name || 'Not assigned';
            document.getElementById('view_assigned_user').textContent = (item.firstname && item.lastname) ?
                `${item.firstname} ${item.lastname}` : 'Unassigned';
            document.getElementById('view_assigned_user_id').textContent = item.assigned_user_id || 'N/A';
            document.getElementById('view_assigned_at').textContent = item.assigned_at ?
                new Date(item.assigned_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                }) : 'Never';
            document.getElementById('view_condition').textContent = item.condition || 'N/A';
            document.getElementById('view_status').textContent = item.status ?
                item.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
            document.getElementById('view_remarks').textContent = item.remarks || 'No remarks';

            openModal('viewModal');
        }

        function closeViewModal() {
            closeModal('viewModal');
        }

        // ==================== DEVICE ACTIONS MODAL ====================
        let currentDeviceActionsState = {
            deviceId: null,
            assignedUser: null,
            assetTag: null,
            deviceStatus: null,
            departmentName: null,
            departmentId: null,
            currentTab: 'assign'
        };

        function openDeviceActionsModal(id, assetTag, assignedUser, deviceStatus, departmentName = '', departmentId = '') {
            currentDeviceActionsState = {
                deviceId: id,
                assignedUser: assignedUser,
                assetTag: assetTag,
                deviceStatus: deviceStatus,
                departmentName: departmentName || '',
                departmentId: departmentId || '',
                currentTab: 'assign'
            };

            document.getElementById('deviceActionsId').value = id;
            document.getElementById('deviceActionsAssetTag').textContent = assetTag;

            // Update user info displays
            const currentUserInfo = document.getElementById('deviceCurrentUserInfo');
            const currentUserName = document.getElementById('deviceCurrentUserName');
            const currentDepartment = document.getElementById('deviceCurrentDepartment');
            const previousUserName = document.getElementById('devicePreviousUserName');
            const previousDepartment = document.getElementById('devicePreviousDepartment');
            const retrieveUserName = document.getElementById('deviceRetrieveUserName');

            const isAssigned = assignedUser && assignedUser.trim() !== '' && assignedUser !== 'undefined' && assignedUser !== 'Unassigned';

            if (isAssigned) {
                currentUserInfo.classList.remove('hidden');
                currentUserName.textContent = assignedUser;
                previousUserName.textContent = assignedUser;
                retrieveUserName.textContent = assignedUser;
            } else {
                currentUserInfo.classList.add('hidden');
                currentUserName.textContent = 'Unassigned';
                previousUserName.textContent = 'Unassigned';
                retrieveUserName.textContent = 'Unassigned';
            }

            // Set department information
            const deptDisplay = departmentName || 'No Department';
            currentDepartment.textContent = deptDisplay;
            previousDepartment.textContent = deptDisplay;

            // Reset form
            document.getElementById('deviceActionsForm').reset();

            // Remove existing action input
            document.querySelectorAll('input[name="action"]').forEach(input => input.remove());

            // Configure tabs based on device status
            const assignTab = document.getElementById('deviceAssignTab');
            const reassignTab = document.getElementById('deviceReassignTab');
            const retrieveTab = document.getElementById('deviceRetrieveTab');
            const retireTab = document.getElementById('deviceRetireTab');

            // Reset all tabs
            [assignTab, reassignTab, retrieveTab, retireTab].forEach(tab => {
                tab.classList.remove('active', 'disabled');
                tab.disabled = false;
                tab.classList.add('action-tab');
            });

            // Check if device is retired
            if (deviceStatus === 'retired') {
                [assignTab, reassignTab, retrieveTab, retireTab].forEach(tab => {
                    tab.classList.add('disabled');
                    tab.disabled = true;
                });
                Toast.showWarning('This device is already retired and cannot be modified.', 'Device Retired');
                return;
            }

            // Enable/disable based on assignment status
            if (isAssigned) {
                assignTab.classList.add('disabled');
                assignTab.disabled = true;
                currentDeviceActionsState.currentTab = 'reassign';
            } else {
                reassignTab.classList.add('disabled');
                reassignTab.disabled = true;
                retrieveTab.classList.add('disabled');
                retrieveTab.disabled = true;
                currentDeviceActionsState.currentTab = 'assign';
            }

            // Show the default tab
            showDeviceActionTab(currentDeviceActionsState.currentTab);

            // Open modal
            openModal('deviceActionsModal');
        }

        function showDeviceActionTab(tabName) {
            const tabElement = document.getElementById('device' + tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Tab');
            if (tabElement.disabled || tabElement.classList.contains('disabled')) {
                return;
            }

            currentDeviceActionsState.currentTab = tabName;

            // Update all tabs
            const tabs = ['assign', 'reassign', 'retrieve', 'retire'];
            tabs.forEach(tab => {
                const tabBtn = document.getElementById('device' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Tab');
                const content = document.getElementById('device' + tab.charAt(0).toUpperCase() + tab.slice(1) + 'Content');

                if (tabBtn) {
                    tabBtn.classList.remove('active');
                }
                if (content) {
                    content.classList.add('hidden');
                }
            });

            // Activate selected tab
            tabElement.classList.add('active');
            document.getElementById('device' + tabName.charAt(0).toUpperCase() + tabName.slice(1) + 'Content').classList.remove('hidden');

            // Update submit button
            const submitBtn = document.getElementById('deviceSubmitActionBtn');
            const actionLabels = {
                'assign': 'Assign Device',
                'reassign': 'Reassign Device',
                'retrieve': 'Retrieve Device',
                'retire': 'Retire Device'
            };

            submitBtn.innerHTML = `<i class="fas fa-check-circle"></i>${actionLabels[tabName]}`;

            // Update button colors
            submitBtn.className = 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-medium transition-colors';

            const colors = {
                'assign': 'bg-green-600 hover:bg-green-700 text-white',
                'reassign': 'bg-blue-600 hover:bg-blue-700 text-white',
                'retrieve': 'bg-amber-600 hover:bg-amber-700 text-white',
                'retire': 'bg-red-600 hover:bg-red-700 text-white'
            };

            submitBtn.classList.add(...colors[tabName].split(' '));
        }

        function closeDeviceActionsModal() {
            currentDeviceActionsState = {
                deviceId: null,
                assignedUser: null,
                assetTag: null,
                deviceStatus: null,
                departmentName: null,
                departmentId: null,
                currentTab: 'assign'
            };

            closeModal('deviceActionsModal');
        }

        // ==================== CONFIRMATION MODAL ====================
        let pendingAction = null;
        let pendingDeleteUrl = null;

        function confirmDeviceAction() {
            const currentTab = currentDeviceActionsState.currentTab;
            const deviceTag = currentDeviceActionsState.assetTag;

            // Validate required fields
            let isValid = true;
            let errorMessage = '';

            switch (currentTab) {
                case 'assign':
                    const assignUser = document.getElementById('deviceAssignUserSelect');
                    if (!assignUser.value) {
                        isValid = false;
                        errorMessage = 'Please select a user to assign this device to.';
                        assignUser.focus();
                    }
                    break;
                case 'reassign':
                    const reassignUser = document.getElementById('deviceReassignUserSelect');
                    if (!reassignUser.value) {
                        isValid = false;
                        errorMessage = 'Please select a user to reassign this device to.';
                        reassignUser.focus();
                    }
                    break;
            }

            if (!isValid) {
                Toast.showError(errorMessage, 'Validation Error');
                return;
            }

            // Store pending action
            pendingAction = {
                type: 'deviceAction',
                tab: currentTab,
                formId: 'deviceActionsForm',
                deviceTag: deviceTag
            };

            // Configure confirmation modal
            const titles = {
                'assign': 'Confirm Assignment',
                'reassign': 'Confirm Reassignment',
                'retrieve': 'Confirm Retrieval',
                'retire': 'Confirm Retirement'
            };

            const messages = {
                'assign': `Are you sure you want to assign device <span class="font-semibold text-gray-900">"${deviceTag}"</span> to the selected user?`,
                'reassign': `Are you sure you want to reassign device <span class="font-semibold text-gray-900">"${deviceTag}"</span> to a new user?`,
                'retrieve': `Are you sure you want to retrieve device <span class="font-semibold text-gray-900">"${deviceTag}"</span> and return it to storage?`,
                'retire': `Are you sure you want to retire device <span class="font-semibold text-gray-900">"${deviceTag}"</span>?`
            };

            document.getElementById('confirmationTitle').textContent = titles[currentTab];
            document.getElementById('confirmationMessage').innerHTML = messages[currentTab];

            // Update warning message
            const warningDiv = document.getElementById('confirmationWarning');
            if (currentTab === 'retire') {
                warningDiv.innerHTML = `
                    <p class="text-sm text-red-700 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action cannot be undone. The device will be permanently marked as retired.
                    </p>
                `;
            } else {
                warningDiv.innerHTML = `
                    <p class="text-sm text-blue-700 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        This action will be recorded in the device history log.
                    </p>
                `;
            }

            // Update confirm button color
            const confirmBtn = document.getElementById('confirmActionBtn');
            confirmBtn.className = 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-medium transition-colors';

            const buttonColors = {
                'assign': 'bg-green-600 hover:bg-green-700 text-white',
                'reassign': 'bg-blue-600 hover:bg-blue-700 text-white',
                'retrieve': 'bg-amber-600 hover:bg-amber-700 text-white',
                'retire': 'bg-red-600 hover:bg-red-700 text-white'
            };

            confirmBtn.classList.add(...buttonColors[currentTab].split(' '));
            confirmBtn.innerHTML = `<i class="fas fa-check"></i>Confirm`;

            // Close device actions modal and open confirmation
            closeDeviceActionsModal();
            openModal('confirmationModal');
        }

        function closeConfirmationModal() {
            pendingAction = null;
            closeModal('confirmationModal');
        }

        function executeConfirmedAction() {
            if (!pendingAction) {
                closeConfirmationModal();
                return;
            }

            if (pendingAction.type === 'deviceAction') {
                const form = document.getElementById(pendingAction.formId);

                // Add or update action input
                let actionInput = form.querySelector('input[name="action"]');
                if (!actionInput) {
                    actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    form.appendChild(actionInput);
                }
                actionInput.value = pendingAction.tab;

                // Show success message
                const successMessages = {
                    'assign': `Device ${pendingAction.deviceTag} assigned successfully`,
                    'reassign': `Device ${pendingAction.deviceTag} reassigned successfully`,
                    'retrieve': `Device ${pendingAction.deviceTag} retrieved successfully`,
                    'retire': `Device ${pendingAction.deviceTag} retired successfully`
                };

                Toast.showSuccess(successMessages[pendingAction.tab], 'Success');

                // Submit form
                form.submit();
            }

            closeConfirmationModal();
            pendingAction = null;
        }

        // ==================== DELETE MODAL ====================
        function openDeleteModal(id) {
            pendingDeleteUrl = `inventory.php?delete=${id}`;

            // Get device info
            const row = document.querySelector(`[onclick*="openDeleteModal(${id})"]`)?.closest('tr');
            const assetTag = row?.querySelector('.asset-tag-badge')?.textContent || 'this device';

            document.getElementById('deleteConfirmationMessage').innerHTML =
                `Are you sure you want to permanently delete device <span class="font-semibold text-gray-900">"${assetTag}"</span>?`;

            openModal('deleteConfirmationModal');
        }

        function closeDeleteConfirmationModal() {
            pendingDeleteUrl = null;
            closeModal('deleteConfirmationModal');
        }

        function executeDelete() {
            if (pendingDeleteUrl) {
                Toast.showSuccess('Device deleted successfully', 'Success');
                window.location.href = pendingDeleteUrl;
            }
            closeDeleteConfirmationModal();
        }

        // ==================== QUICK ACTIONS ====================
        function openQuickActionsModal() {
            openModal('quickActionsModal');
        }

        function closeQuickActionsModal() {
            closeModal('quickActionsModal');
        }

        function performQuickAction(action) {
            switch (action) {
                case 'refresh':
                    Toast.showInfo('Refreshing inventory data...', 'Please wait');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                    break;
                case 'export':
                    window.location.href = 'export_assignments.php';
                    break;
                case 'toggle_view':
                    document.querySelector('.table-container').classList.toggle('compact-view');
                    Toast.showInfo('Table view toggled', 'View Updated');
                    break;
                case 'clear_filters':
                    window.location.href = 'inventory.php';
                    break;
            }
            closeQuickActionsModal();
        }

        // ==================== SEARCH FUNCTION ====================
        let searchTimeout;
        function searchTable() {
            clearTimeout(searchTimeout);

            searchTimeout = setTimeout(() => {
                const searchTerm = document.getElementById("searchInput").value.toLowerCase().trim();
                const rows = document.querySelectorAll("#inventoryTableBody tr");
                let visibleCount = 0;

                rows.forEach(row => {
                    if (row.querySelector('td[colspan]')) return;

                    const searchableText = Array.from(row.cells)
                        .slice(0, -1)
                        .map(cell => cell.textContent.toLowerCase())
                        .join(' ');

                    const isVisible = searchableText.includes(searchTerm);
                    row.style.display = isVisible ? "" : "none";
                    if (isVisible) visibleCount++;
                });

                if (searchTerm.length > 0) {
                    Toast.showInfo(`Found ${visibleCount} matching devices`, 'Search Results', 2000);
                }
            }, 300);
        }

        // ==================== PAGINATION ====================
        function changeItemsPerPage(select) {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', select.value);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        // ==================== FORM VALIDATION ====================
        function validateEditForm(formId) {
            const form = document.getElementById(formId);
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            let errorMessages = [];

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    const fieldName = field.name.replace(/_/g, ' ');
                    errorMessages.push(`${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} is required`);
                    field.classList.add('border-red-500');

                    setTimeout(() => {
                        field.classList.remove('border-red-500');
                    }, 3000);
                }
            });

            if (!isValid) {
                Toast.showError(errorMessages.join('<br>'), 'Validation Error', 7000);
                return false;
            }

            return true;
        }

        // ==================== INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function () {
            // Show PHP session messages as toasts
            const successToast = document.getElementById('success-toast');
            if (successToast && successToast.textContent.trim()) {
                Toast.showSuccess(successToast.textContent.trim(), 'Success');
            }

            const errorToast = document.getElementById('error-toast');
            if (errorToast && errorToast.textContent.trim()) {
                Toast.showError(errorToast.textContent.trim(), 'Error');
            }

            const warningToast = document.getElementById('warning-toast');
            if (warningToast && warningToast.textContent.trim()) {
                Toast.showWarning(warningToast.textContent.trim(), 'Validation Error', 7000);
            }

            // Add form validation
            document.querySelectorAll('form[id^="editForm"]').forEach(form => {
                form.addEventListener('submit', function (e) {
                    if (!validateEditForm(this.id)) {
                        e.preventDefault();
                    }
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function (e) {
                // ESC key closes modals
                if (e.key === 'Escape') {
                    const modals = ['deviceActionsModal', 'confirmationModal', 'deleteConfirmationModal',
                        'quickActionsModal', 'viewModal', 'addModal'];

                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (modal && modal.classList.contains('flex')) {
                            e.preventDefault();
                            if (modalId === 'deviceActionsModal') closeDeviceActionsModal();
                            else if (modalId === 'confirmationModal') closeConfirmationModal();
                            else if (modalId === 'deleteConfirmationModal') closeDeleteConfirmationModal();
                            else if (modalId === 'quickActionsModal') closeQuickActionsModal();
                            else if (modalId === 'viewModal') closeViewModal();
                            else closeModal(modalId);
                        }
                    });
                }

                // Arrow key navigation in device actions modal
                if (document.getElementById('deviceActionsModal').classList.contains('flex')) {
                    const tabs = ['assign', 'reassign', 'retrieve', 'retire'];
                    const currentIndex = tabs.indexOf(currentDeviceActionsState.currentTab);

                    if (e.key === 'ArrowRight' && currentIndex < tabs.length - 1) {
                        e.preventDefault();
                        let nextIndex = currentIndex + 1;
                        while (nextIndex < tabs.length) {
                            const nextTab = document.getElementById('device' + tabs[nextIndex].charAt(0).toUpperCase() + tabs[nextIndex].slice(1) + 'Tab');
                            if (!nextTab.disabled && !nextTab.classList.contains('disabled')) {
                                showDeviceActionTab(tabs[nextIndex]);
                                break;
                            }
                            nextIndex++;
                        }
                    }

                    if (e.key === 'ArrowLeft' && currentIndex > 0) {
                        e.preventDefault();
                        let prevIndex = currentIndex - 1;
                        while (prevIndex >= 0) {
                            const prevTab = document.getElementById('device' + tabs[prevIndex].charAt(0).toUpperCase() + tabs[prevIndex].slice(1) + 'Tab');
                            if (!prevTab.disabled && !prevTab.classList.contains('disabled')) {
                                showDeviceActionTab(tabs[prevIndex]);
                                break;
                            }
                            prevIndex--;
                        }
                    }
                }
            });
        });

        // Export global functions
        window.openModal = openModal;
        window.closeModal = closeModal;
        window.closeModalOnBackdrop = closeModalOnBackdrop;
        window.openViewModal = openViewModal;
        window.closeViewModal = closeViewModal;
        window.openDeviceActionsModal = openDeviceActionsModal;
        window.showDeviceActionTab = showDeviceActionTab;
        window.closeDeviceActionsModal = closeDeviceActionsModal;
        window.confirmDeviceAction = confirmDeviceAction;
        window.closeConfirmationModal = closeConfirmationModal;
        window.executeConfirmedAction = executeConfirmedAction;
        window.openDeleteModal = openDeleteModal;
        window.closeDeleteConfirmationModal = closeDeleteConfirmationModal;
        window.executeDelete = executeDelete;
        window.openQuickActionsModal = openQuickActionsModal;
        window.closeQuickActionsModal = closeQuickActionsModal;
        window.performQuickAction = performQuickAction;
        window.searchTable = searchTable;
        window.changeItemsPerPage = changeItemsPerPage;
        window.validateEditForm = validateEditForm;
        window.Toast = Toast;
    </script>
</body>

</html>