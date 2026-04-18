<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/seat_layout_helper.php';

require_role('bus_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bus_admin/dashboard.php');
}

$busId = (int)($_POST['bus_id'] ?? 0);

if ($busId <= 0) {
    set_flash('error', 'Invalid bus ID.');
    redirect('bus_admin/dashboard.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$busSql = "
    SELECT id, company_id, bus_number, bus_type, total_seats, layout_type
    FROM buses
    WHERE id = ? AND company_id = ?
    LIMIT 1
";
$busStmt = $conn->prepare($busSql);
$busStmt->bind_param('ii', $busId, $company['company_id']);
$busStmt->execute();
$busResult = $busStmt->get_result();
$bus = $busResult->fetch_assoc();
$busStmt->close();

if (!$bus) {
    $conn->close();
    set_flash('error', 'Bus not found or not allowed.');
    redirect('bus_admin/dashboard.php');
}

if ((int)$bus['total_seats'] <= 0) {
    $conn->close();
    set_flash('error', 'This bus has invalid total seats.');
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
}

try {
    $generatedSeats = generate_seat_records($bus);
    save_generated_seats($conn, $busId, $generatedSeats);

    $action = 'bus_seats_generated';
    $entityType = 'bus';
    $description = 'Generated seat layout for bus: ' . $bus['bus_number'];
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

    set_flash('success', count($generatedSeats) . ' seats generated successfully.');
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
}
?>