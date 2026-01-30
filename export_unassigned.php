<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once __DIR__ . "/config/database.php";

$db = new Database();
$conn = $db->getConnection();

// Based on your unassigned_devices.php, the correct columns are:
// The inventory_items table has department_id and location_id, not department and location
// Use this query that matches your unassigned_devices.php structure

$query = "
    SELECT 
        i.id,
        i.asset_tag,
        i.device_type,
        i.serial_number,
        i.model,
        i.specifications,
        i.condition,
        i.status,
        i.remarks,
        i.created_at,
        i.updated_at,
        b.brand_name AS brand,
        d.department_name AS department,
        l.location_name AS location,
        c.category_name AS category
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE (i.assigned_user IS NULL OR i.assigned_user = '') 
      AND i.status != 'retired'
    ORDER BY i.updated_at DESC
";

$result = $conn->query($query);

if (!$result) {
    die("Query error: " . $conn->error . "<br>Query: " . $query);
}

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=unassigned_devices_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fputs($output, "\xEF\xBB\xBF");

// Column headers
$headers = [
    'ID',
    'Asset Tag',
    'Device Type',
    'Serial Number',
    'Model',
    'Brand',
    'Category',
    'Specifications',
    'Condition',
    'Status',
    'Department',
    'Location',
    'Remarks',
    'Created Date',
    'Last Updated'
];

fputcsv($output, $headers);

// Data rows
while ($row = $result->fetch_assoc()) {
    $csvRow = [
        $row['id'],
        $row['asset_tag'],
        $row['device_type'],
        $row['serial_number'],
        $row['model'],
        $row['brand'] ?? '',
        $row['category'] ?? '',
        $row['specifications'] ?? '',
        $row['condition'] ?? '',
        $row['status'] ?? '',
        $row['department'] ?? '',
        $row['location'] ?? '',
        $row['remarks'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? ''
    ];

    fputcsv($output, $csvRow);
}

fclose($output);
exit();