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

$busId = (int)($_POST['bus_id'] ?? 0);
$busNumber = trim($_POST['bus_number'] ?? '');
$plateNumber = trim($_POST['plate_number'] ?? '');
$busType = trim($_POST['bus_type'] ?? '');
$totalSeats = (int)($_POST['total_seats'] ?? 0);
$layoutType = trim($_POST['layout_type'] ?? '');
$status = trim($_POST['status'] ?? '');

$allowedBusTypes = ['normal', 'vip', 'sleeper', 'mini_bus'];
$allowedLayoutTypes = ['2x2', '2x1', 'sleeper', 'vip', 'custom'];
$allowedStatuses = ['active', 'maintenance', 'inactive'];

if ($busId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid bus ID.');
    redirect('bus_admin/buses.php');
}

$ownerSql = "SELECT id FROM buses WHERE id = ? AND company_id = ? LIMIT 1";
$ownerStmt = $conn->prepare($ownerSql);
$ownerStmt->bind_param('ii', $busId, $company['company_id']);
$ownerStmt->execute();
$ownerResult = $ownerStmt->get_result();

if ($ownerResult->num_rows !== 1) {
    $ownerStmt->close();
    $conn->close();
    set_flash('error', 'You are not allowed to update this bus.');
    redirect('bus_admin/buses.php');
}
$ownerStmt->close();

if ($busNumber === '') {
    $conn->close();
    set_flash('error', 'Bus number is required.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}

if (!in_array($busType, $allowedBusTypes, true)) {
    $conn->close();
    set_flash('error', 'Invalid bus type.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}

if ($totalSeats <= 0 || $totalSeats > 100) {
    $conn->close();
    set_flash('error', 'Total seats must be between 1 and 100.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}

if (!in_array($layoutType, $allowedLayoutTypes, true)) {
    $conn->close();
    set_flash('error', 'Invalid layout type.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}

if (!in_array($status, $allowedStatuses, true)) {
    $conn->close();
    set_flash('error', 'Invalid status.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}

$dupSql = "
    SELECT id
    FROM buses
    WHERE company_id = ? AND bus_number = ? AND id <> ?
    LIMIT 1
";
$dupStmt = $conn->prepare($dupSql);
$dupStmt->bind_param('isi', $company['company_id'], $busNumber, $busId);
$dupStmt->execute();
$dupResult = $dupStmt->get_result();

if ($dupResult->num_rows > 0) {
    $dupStmt->close();
    $conn->close();
    set_flash('error', 'This bus number already exists in your company.');
    redirect('bus_admin/buses.php?edit=' . $busId);
}
$dupStmt->close();

if ($plateNumber !== '') {
    $plateSql = "SELECT id FROM buses WHERE plate_number = ? AND id <> ? LIMIT 1";
    $plateStmt = $conn->prepare($plateSql);
    $plateStmt->bind_param('si', $plateNumber, $busId);
    $plateStmt->execute();
    $plateResult = $plateStmt->get_result();

    if ($plateResult->num_rows > 0) {
        $plateStmt->close();
        $conn->close();
        set_flash('error', 'This plate number already exists.');
        redirect('bus_admin/buses.php?edit=' . $busId);
    }
    $plateStmt->close();
}

$plateValue = ($plateNumber === '') ? null : $plateNumber;

$updateSql = "
    UPDATE buses
    SET bus_number = ?, plate_number = ?, bus_type = ?, total_seats = ?, layout_type = ?, status = ?
    WHERE id = ? AND company_id = ?
";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param(
    'sssissii',
    $busNumber,
    $plateValue,
    $busType,
    $totalSeats,
    $layoutType,
    $status,
    $busId,
    $company['company_id']
);

if ($updateStmt->execute()) {
    $updateStmt->close();

    $action = 'bus_updated';
    $entityType = 'bus';
    $description = 'Updated bus: ' . $busNumber;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $userId, $action, $entityType, $busId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->close();
    set_flash('success', 'Bus updated successfully.');
    redirect('bus_admin/buses.php');
}

$updateStmt->close();
$conn->close();

set_flash('error', 'Failed to update bus.');
redirect('bus_admin/buses.php?edit=' . $busId);
?>