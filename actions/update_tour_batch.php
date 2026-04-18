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
$startDate = trim($_POST['start_date'] ?? '');
$endDate = trim($_POST['end_date'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 0);
$price = trim($_POST['price'] ?? '');
$status = trim($_POST['status'] ?? 'open');

try {
    $package = fetch_tour_package_for_company($conn, $packageId, (int)$company['company_id']);
    if (!$package) {
        throw new Exception('Package not found or not allowed.');
    }

    $batch = fetch_tour_batch_for_package($conn, $batchId, $packageId);
    if (!$batch) {
        throw new Exception('Batch not found.');
    }

    if ($startDate === '' || $endDate === '') {
        throw new Exception('Start date and end date are required.');
    }

    if ($endDate < $startDate) {
        throw new Exception('End date must be later than or equal to start date.');
    }

    if ($capacity <= 0) {
        throw new Exception('Capacity must be greater than 0.');
    }

    if ($capacity < (int)$batch['booked_count']) {
        throw new Exception('Capacity cannot be smaller than current booked count.');
    }

    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        throw new Exception('Price must be a valid number.');
    }

    $allowedStatuses = ['open', 'full', 'closed', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        throw new Exception('Invalid status.');
    }

    if ($status === 'open' && $capacity <= (int)$batch['booked_count']) {
        $status = 'full';
    }

    save_old_input($_POST);

    $priceValue = (float)$price;
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        UPDATE tour_batches
        SET start_date = ?,
            end_date = ?,
            capacity = ?,
            price = ?,
            status = ?
        WHERE id = ? AND {$packageColumn} = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssidsii',
        $startDate,
        $endDate,
        $capacity,
        $priceValue,
        $status,
        $batchId,
        $packageId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update batch.');
    }
    $stmt->close();

    $action = 'tour_batch_updated';
    $entityType = 'tour_batch';
    $description = 'Updated tour batch ID: ' . $batchId . ' for package ID: ' . $packageId;
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

    clear_old_input();
    $conn->close();

    set_flash('success', 'Tour batch updated successfully.');
    redirect('tour_admin/batches.php?package_id=' . $packageId);
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('tour_admin/batches.php?package_id=' . $packageId . '&edit=' . $batchId);
}
