<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('bus_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bus_admin/buses.php');
}

$conn = getDBConnection();
require_company_permission($conn, 'manage_buses');
$company = require_bus_admin_company($conn);

$busId = (int)($_POST['bus_id'] ?? 0);

if ($busId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid bus ID.');
    redirect('bus_admin/buses.php');
}

$busSql = "SELECT id, bus_number FROM buses WHERE id = ? AND company_id = ? LIMIT 1";
$busStmt = $conn->prepare($busSql);
$busStmt->bind_param('ii', $busId, $company['company_id']);
$busStmt->execute();
$busResult = $busStmt->get_result();

if ($busResult->num_rows !== 1) {
    $busStmt->close();
    $conn->close();
    set_flash('error', 'You are not allowed to delete this bus.');
    redirect('bus_admin/buses.php');
}

$bus = $busResult->fetch_assoc();
$busStmt->close();

/*
 * Safety check:
 * If this bus is already used in schedule_templates or trips,
 * block deletion to avoid removing operational data.
 */
$usageSql = "
    SELECT
        (SELECT COUNT(*) FROM schedule_templates WHERE bus_id = ?) AS schedule_count,
        (SELECT COUNT(*) FROM trips WHERE bus_id = ?) AS trip_count
";
$usageStmt = $conn->prepare($usageSql);
$usageStmt->bind_param('ii', $busId, $busId);
$usageStmt->execute();
$usageResult = $usageStmt->get_result();
$usage = $usageResult->fetch_assoc();
$usageStmt->close();

if ((int)$usage['schedule_count'] > 0 || (int)$usage['trip_count'] > 0) {
    $conn->close();
    set_flash('error', 'This bus cannot be deleted because it is already used in schedules or trips. Set it to inactive instead.');
    redirect('bus_admin/buses.php');
}

$deleteSql = "DELETE FROM buses WHERE id = ? AND company_id = ?";
$deleteStmt = $conn->prepare($deleteSql);
$deleteStmt->bind_param('ii', $busId, $company['company_id']);

if ($deleteStmt->execute()) {
    $deleteStmt->close();

    $action = 'bus_deleted';
    $entityType = 'bus';
    $description = 'Deleted bus: ' . $bus['bus_number'];
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
    set_flash('success', 'Bus deleted successfully.');
    redirect('bus_admin/buses.php');
}

$deleteStmt->close();
$conn->close();

set_flash('error', 'Failed to delete bus.');
redirect('bus_admin/buses.php');
?>