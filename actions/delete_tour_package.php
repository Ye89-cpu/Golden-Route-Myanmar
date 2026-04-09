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

    $batchSql = "
        SELECT *
        FROM tour_batches
        WHERE id = ? AND package_id = ?
        LIMIT 1
    ";
    $batchStmt = $conn->prepare($batchSql);
    $batchStmt->bind_param('ii', $batchId, $packageId);
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    $batch = $batchResult->fetch_assoc();
    $batchStmt->close();

    if (!$batch) {
        throw new Exception('Batch not found.');
    }

    $usageSql = "
        SELECT COUNT(*) AS booking_count
        FROM bookings
        WHERE tour_batch_id = ?
    ";
    $usageStmt = $conn->prepare($usageSql);
    $usageStmt->bind_param('i', $batchId);
    $usageStmt->execute();
    $usageResult = $usageStmt->get_result();
    $usage = $usageResult->fetch_assoc();
    $usageStmt->close();

    if ((int)$usage['booking_count'] > 0) {
        throw new Exception('This batch already has bookings. Set it inactive or cancelled instead of deleting.');
    }

    $deleteSql = "DELETE FROM tour_batches WHERE id = ? AND package_id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('ii', $batchId, $packageId);

    if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        throw new Exception('Failed to delete batch.');
    }
    $deleteStmt->close();

    $conn->close();
    set_flash('success', 'Tour batch deleted successfully.');
    redirect('tour_admin/batches.php?package_id=' . $packageId);
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('tour_admin/batches.php?package_id=' . $packageId);
}