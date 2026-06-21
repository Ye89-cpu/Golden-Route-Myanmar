<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/payments.php');
}

$paymentId = (int)($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    set_flash('error', 'Invalid payment ID.');
    redirect('admin/payments.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $sql = "
        SELECT
            p.id,
            p.booking_id,
            p.status AS payment_status,
            b.booking_code
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        WHERE p.id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception('Payment record not found.');
    }

    if ($payment['payment_status'] === 'verified') {
        throw new Exception('A verified payment cannot be rejected.');
    }

    if ($payment['payment_status'] === 'rejected') {
        throw new Exception('This payment has already been rejected.');
    }

    if ($payment['payment_status'] !== 'submitted') {
        throw new Exception('Only submitted payments can be rejected.');
    }

    $updatePaymentSql = "
        UPDATE payments
        SET status = 'rejected'
        WHERE id = ?
    ";
    $updatePaymentStmt = $conn->prepare($updatePaymentSql);
    $updatePaymentStmt->bind_param('i', $paymentId);

    if (!$updatePaymentStmt->execute()) {
        $updatePaymentStmt->close();
        throw new Exception('Failed to reject payment.');
    }
    $updatePaymentStmt->close();

    $updateBookingSql = "
        UPDATE bookings
        SET payment_status = 'failed'
        WHERE id = ?
    ";
    $updateBookingStmt = $conn->prepare($updateBookingSql);
    $updateBookingStmt->bind_param('i', $payment['booking_id']);

    if (!$updateBookingStmt->execute()) {
        $updateBookingStmt->close();
        throw new Exception('Failed to update booking payment status.');
    }
    $updateBookingStmt->close();

    $action = 'payment_rejected';
    $entityType = 'payment';
    $description = 'Rejected payment for booking: ' . $payment['booking_code'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $userId, $action, $entityType, $paymentId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    set_flash('success', 'Payment rejected successfully.');
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', $e->getMessage());
}

$conn->close();
redirect('admin/payments.php');