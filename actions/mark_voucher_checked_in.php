<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/tour_admin_booking_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role('tour_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tour_admin/voucher_checkin.php');
}

$batchId = (int)($_POST['batch_id'] ?? 0);
$voucherId = (int)($_POST['voucher_id'] ?? 0);
$actorUserId = (int)current_user_id();

if ($batchId <= 0 || $voucherId <= 0) {
    set_flash('error', 'Invalid batch or voucher ID.');
    redirect('tour_admin/voucher_checkin.php');
}

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

try {
    $conn->begin_transaction();

    $updatedVoucher = mark_batch_voucher_used(
        $conn,
        (int)$company['company_id'],
        $batchId,
        $voucherId,
        $actorUserId
    );

    notify_event_voucher_checked_in_by_voucher_id(
        $conn,
        (int)$updatedVoucher['voucher_id'],
        $actorUserId
    );

    $conn->commit();
    $conn->close();

    set_flash('success', 'Check-in confirmed for voucher ' . $updatedVoucher['voucher_code'] . '.');
    redirect('tour_admin/voucher_checkin.php?batch_id=' . $batchId . '&search_value=' . urlencode((string)$updatedVoucher['voucher_code']));
} catch (Exception $e) {
    if ($conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }

    set_flash('error', $e->getMessage());
    redirect('tour_admin/voucher_checkin.php?batch_id=' . $batchId);
}