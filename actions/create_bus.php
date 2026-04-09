<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';

require_role('bus_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bus_admin/buses.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$busNumber = trim($_POST['bus_number'] ?? '');
$plateNumber = trim($_POST['plate_number'] ?? '');
$busType = trim($_POST['bus_type'] ?? '');
$totalSeats = (int)($_POST['total_seats'] ?? 0);
$layoutType = trim($_POST['layout_type'] ?? '');
$status = trim($_POST['status'] ?? '');

save_old_input([
    'bus_number' => $busNumber,
    'plate_number' => $plateNumber,
    'bus_type' => $busType,
    'total_seats' => $totalSeats,
    'layout_type' => $layoutType,
    'status' => $status
]);

$allowedBusTypes = ['normal', 'vip', 'sleeper', 'mini_bus'];
$allowedLayoutTypes = ['2x2', '2x1', 'sleeper', 'vip', 'custom'];
$allowedStatuses = ['active', 'maintenance', 'inactive'];

if ($busNumber === '') {
    $conn->close();
    set_flash('error', 'Bus number is required.');
    redirect('bus_admin/buses.php');
}

if (!in_array($busType, $allowedBusTypes, true)) {
    $conn->close();
    set_flash('error', 'Invalid bus type.');
    redirect('bus_admin/buses.php');
}

if ($totalSeats <= 0 || $totalSeats > 100) {
    $conn->close();
    set_flash('error', 'Total seats must be between 1 and 100.');
    redirect('bus_admin/buses.php');
}

if (!in_array($layoutType, $allowedLayoutTypes, true)) {
    $conn->close();
    set_flash('error', 'Invalid layout type.');
    redirect('bus_admin/buses.php');
}

if (!in_array($status, $allowedStatuses, true)) {
    $conn->close();
    set_flash('error', 'Invalid status.');
    redirect('bus_admin/buses.php');
}

$checkSql = "SELECT id FROM buses WHERE company_id = ? AND bus_number = ? LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('is', $company['company_id'], $busNumber);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    $checkStmt->close();
    $conn->close();
    set_flash('error', 'This bus number already exists in your company.');
    redirect('bus_admin/buses.php');
}
$checkStmt->close();

if ($plateNumber !== '') {
    $plateSql = "SELECT id FROM buses WHERE plate_number = ? LIMIT 1";
    $plateStmt = $conn->prepare($plateSql);
    $plateStmt->bind_param('s', $plateNumber);
    $plateStmt->execute();
    $plateResult = $plateStmt->get_result();

    if ($plateResult->num_rows > 0) {
        $plateStmt->close();
        $conn->close();
        set_flash('error', 'This plate number already exists.');
        redirect('bus_admin/buses.php');
    }
    $plateStmt->close();
}

$plateValue = ($plateNumber === '') ? null : $plateNumber;

$sql = "
    INSERT INTO buses
    (company_id, bus_number, plate_number, bus_type, total_seats, layout_type, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'isssiss',
    $company['company_id'],
    $busNumber,
    $plateValue,
    $busType,
    $totalSeats,
    $layoutType,
    $status
);

if ($stmt->execute()) {
    $newBusId = $stmt->insert_id;
    $stmt->close();

    $action = 'bus_created';
    $entityType = 'bus';
    $description = 'Created bus: ' . $busNumber;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $userId, $action, $entityType, $newBusId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->close();
    clear_old_input();
    set_flash('success', 'Bus created successfully.');
    redirect('bus_admin/buses.php');
}

$stmt->close();
$conn->close();

set_flash('error', 'Failed to create bus.');
redirect('bus_admin/buses.php');
?>