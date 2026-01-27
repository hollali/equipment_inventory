<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/config/database.php";
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* Database connection */
$db = new Database();
$conn = $db->getConnection();

/* Get filters from GET */
$filterStatus = $_GET['status'] ?? '';
$filterDepartment = $_GET['department'] ?? '';
$filterLocation = $_GET['location'] ?? '';
$filterDate = $_GET['date'] ?? '';

/* Build dynamic query */
$sql = "
    SELECT 
        i.asset_tag,
        i.device_type,
        i.model,
        i.serial_number,
        i.assigned_user,
        i.status,
        d.department_name,
        l.location_name,
        b.brand_name,
        i.created_at
    FROM inventory_items i
    LEFT JOIN brands b ON i.brand_id = b.id
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN locations l ON i.location_id = l.id
    WHERE 1
";

$params = [];
$types = "";

if ($filterStatus !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $filterStatus;
    $types .= "s";
}

if ($filterDepartment !== '') {
    $sql .= " AND i.department_id = ?";
    $params[] = (int) $filterDepartment;
    $types .= "i";
}

if ($filterLocation !== '') {
    $sql .= " AND i.location_id = ?";
    $params[] = (int) $filterLocation;
    $types .= "i";
}

if ($filterDate !== '') {
    $sql .= " AND DATE(i.created_at) = ?";
    $params[] = $filterDate;
    $types .= "s";
}

$sql .= " ORDER BY i.updated_at DESC";

/* Execute query */
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

/* Create spreadsheet */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/* Header row */
$headers = ['Asset Tag', 'Device Type', 'Model', 'Serial Number', 'Assigned User', 'Status', 'Department', 'Location', 'Brand', 'Created At'];
$sheet->fromArray($headers, NULL, 'A1');

/* Style header row */
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1D4ED8']
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
    ]
];
$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

/* Fill data rows */
$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue("A$rowNum", $row['asset_tag']);
    $sheet->setCellValue("B$rowNum", $row['device_type']);
    $sheet->setCellValue("C$rowNum", $row['model']);
    $sheet->setCellValue("D$rowNum", $row['serial_number']);
    $sheet->setCellValue("E$rowNum", $row['assigned_user']);
    $sheet->setCellValue("F$rowNum", $row['status']);
    $sheet->setCellValue("G$rowNum", $row['department_name']);
    $sheet->setCellValue("H$rowNum", $row['location_name']);
    $sheet->setCellValue("I$rowNum", $row['brand_name']);
    $sheet->setCellValue("J$rowNum", $row['created_at']);

    /* Optional: alternate row colors */
    if ($rowNum % 2 == 0) {
        $sheet->getStyle("A$rowNum:J$rowNum")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB'); // light gray
    }

    $rowNum++;
}

/* Auto-size columns */
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

/* Output to browser */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="assignments.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
