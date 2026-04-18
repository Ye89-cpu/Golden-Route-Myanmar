<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/refund_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/bookings.php');
}

$conn = getDBConnection();
$bookingId = (int)($_POST['booking_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$currentUserId = (int)current_user_id();

save_old_input([
    'reason' => $reason
]);

if ($bookingId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid booking ID.');
    redirect('customer/bookings.php');
}

if ($reason === '') {
    $conn->close();
    set_flash('error', 'Refund reason is required.');
    redirect('customer/refund_request.php?booking_id=' . $bookingId);
}

try {
    $conn->begin_transaction();

    $booking = fetch_customer_refundable_booking($conn, $bookingId, $currentUserId);
    if (!$booking) {
        throw new Exception('Booking not found or access denied.');
    }

    $latestRequest = fetch_latest_refund_request_by_booking($conn, $bookingId);
    $blockReason = refund_request_block_reason($booking, $latestRequest);
    if ($blockReason !== null) {
        throw new Exception($blockReason);
    }

    $requestCode = generate_refund_request_code();
    $bookingType = $booking['booking_type'];
    $requestedAmount = (float)$booking['total_amount'];

    $insertSql = "
        INSERT INTO refund_requests
        (booking_id, user_id, request_code, booking_type, reason, requested_amount, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ";
    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare refund insert: ' . $conn->error);
    }

    $stmt->bind_param(
        'iisssd',
        $bookingId,
        $currentUserId,
        $requestCode,
        $bookingType,
        $reason,
        $requestedAmount
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to create refund request.');
    }

    $refundRequestId = (int)$stmt->insert_id;
    notify_event_refund_submitted_by_request_id($conn, $refundRequestId, (int)current_user_id());
    $stmt->close();
    

    $action = 'refund_requested';
    $entityType = 'refund_request';
    $description = 'Customer submitted refund request ' . $requestCode . ' for booking ' . $booking['booking_code'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $currentUserId, $action, $entityType, $refundRequestId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    $conn->close();

    clear_old_input();
    set_flash('success', 'Refund request submitted successfully.');
    redirect('customer/refund_request.php?booking_id=' . $bookingId);
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();

    set_flash('error', $e->getMessage());
    redirect('customer/refund_request.php?booking_id=' . $bookingId);
}