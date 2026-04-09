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
$seatTypes = $_POST['seat_type'] ?? [];
$activeSeats = $_POST['is_active'] ?? [];

if ($busId <= 0) {
    set_flash('error', 'Invalid bus ID.');
    redirect('bus_admin/dashboard.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$busSql = "
    SELECT id, company_id, bus_number
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

$existingSeats = fetch_bus_seats($conn, $busId);

if (empty($existingSeats)) {
    $conn->close();
    set_flash('error', 'No generated seats found for this bus.');
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
}

$allowedSeatTypes = ['normal', 'vip', 'sleeper'];

try {
    $conn->begin_transaction();

    $updateSql = "
        UPDATE bus_seats
        SET seat_type = ?, is_active = ?
        WHERE id = ? AND bus_id = ?
    ";
    $updateStmt = $conn->prepare($updateSql);

    foreach ($existingSeats as $seat) {
        $seatId = (int)$seat['id'];
        $newType = $seatTypes[$seatId] ?? $seat['seat_type'];

        if (!in_array($newType, $allowedSeatTypes, true)) {
            throw new Exception('Invalid seat type submitted.');
        }

        $isActive = isset($activeSeats[$seatId]) ? 1 : 0;

        $updateStmt->bind_param('siii', $newType, $isActive, $seatId, $busId);

        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update seat settings.');
        }
    }

    $updateStmt->close();

    $action = 'bus_seats_updated';
    $entityType = 'bus';
    $description = 'Updated seat settings for bus: ' . $bus['bus_number'];
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

    $conn->commit();
    $conn->close();

    set_flash('success', 'Seat layout updated successfully.');
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();

    set_flash('error', $e->getMessage());
    redirect('bus_admin/seat_layout.php?bus_id=' . $busId);
}
?>