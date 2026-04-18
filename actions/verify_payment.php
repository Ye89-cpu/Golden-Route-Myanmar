<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ticket_service.php';
require_once __DIR__ . '/../includes/voucher_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

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
            b.booking_code,
            b.booking_type
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
        throw new Exception('This payment has already been verified.');
    }

    if ($payment['payment_status'] === 'rejected') {
        throw new Exception('This payment was already rejected.');
    }

    if ($payment['payment_status'] !== 'submitted') {
        throw new Exception('Only submitted payments can be verified.');
    }

    $actorUserId = (int)current_user_id();

    $updatePaymentSql = "
        UPDATE payments
        SET status = 'verified',
            verified_by = ?,
            verified_at = NOW()
        WHERE id = ?
    ";
    $updatePaymentStmt = $conn->prepare($updatePaymentSql);
    $updatePaymentStmt->bind_param('ii', $actorUserId, $paymentId);

    if (!$updatePaymentStmt->execute()) {
        $updatePaymentStmt->close();
        throw new Exception('Failed to verify payment.');
    }
    $updatePaymentStmt->close();

    $updateBookingSql = "
        UPDATE bookings
        SET payment_status = 'paid',
            status = 'paid'
        WHERE id = ?
    ";
    $updateBookingStmt = $conn->prepare($updateBookingSql);
    $updateBookingStmt->bind_param('i', $payment['booking_id']);

    if (!$updateBookingStmt->execute()) {
        $updateBookingStmt->close();
        throw new Exception('Failed to update booking payment status.');
    }
    $updateBookingStmt->close();

    $message = 'Payment verified successfully.';

    if ($payment['booking_type'] === 'bus') {
        $ticketResult = create_or_get_ticket_for_booking(
            $conn,
            (int)$payment['booking_id'],
            null,
            $actorUserId
        );

        notify_event_payment_verified_bus_by_booking_id($conn, (int)$payment['booking_id'], $actorUserId);

        $conn->commit();
        $conn->close();

        $message .= ' Ticket generated successfully';
        if (!empty($ticketResult['ticket']['ticket_no'])) {
            $message .= ' (' . $ticketResult['ticket']['ticket_no'] . ')';
        }
        $message .= '.';
        set_flash('success', $message);
        redirect('admin/payments.php');
    }

    if ($payment['booking_type'] === 'tour') {
        $voucherResult = create_or_get_voucher_for_booking(
            $conn,
            (int)$payment['booking_id'],
            null,
            $actorUserId
        );

        notify_event_payment_verified_tour_by_booking_id($conn, (int)$payment['booking_id'], $actorUserId);

        $conn->commit();
        $conn->close();

        $message .= ' Tour voucher generated successfully';
        if (!empty($voucherResult['voucher']['voucher_code'])) {
            $message .= ' (' . $voucherResult['voucher']['voucher_code'] . ')';
        }
        $message .= '.';
        set_flash('success', $message);
        redirect('admin/payments.php');
    }

    $conn->commit();
    $conn->close();
    set_flash('success', $message);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }

        try {
            $conn->close();
        } catch (Throwable $closeError) {
        }
    }

    set_flash('error', $e->getMessage());
}

redirect('admin/payments.php');