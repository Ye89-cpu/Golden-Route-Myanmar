<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/tour_booking_helper.php';

require_role('tour_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tour_admin/packages.php');
}

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

$packageId = (int)($_POST['package_id'] ?? 0);
$batchId = (int)($_POST['batch_id'] ?? 0);

try {
    $package = fetch_tour_package_for_company($conn, $packageId, (int)$company['company_id']);
    if (!$package) {
        throw new Exception('Package not found or not allowed.');
    }

    $batch = fetch_tour_batch_for_package($conn, $batchId, $packageId);
    if (!$batch) {
        throw new Exception('Batch not found.');
    }

    if ((int)($batch['booked_count'] ?? 0) > 0) {
        throw new Exception('Cannot delete a batch that already has bookings.');
    }

    $sql = 'DELETE FROM tour_batches WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $batchId);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to delete batch.');
    }
    $stmt->close();

    $action = 'tour_batch_deleted';
    $entityType = 'tour_batch';
    $description = 'Deleted tour batch ID: ' . $batchId . ' for package ID: ' . $packageId;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $actorUserId = (int)current_user_id();
    $auditStmt->bind_param('ississ', $actorUserId, $action, $entityType, $batchId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->close();
    set_flash('success', 'Tour batch deleted successfully.');
    redirect('tour_admin/batches.php?package_id=' . $packageId);
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('tour_admin/batches.php?package_id=' . $packageId);
}
