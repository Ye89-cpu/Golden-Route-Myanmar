<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/refund_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role(['super_admin', 'tour_admin']);

$isTourAdmin = current_user_role() === 'tour_admin';
$redirectPath = $isTourAdmin ? 'tour_admin/refund_requests.php' : 'admin/refund_requests.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectPath);
}

$refundRequestId = (int)($_POST['refund_request_id'] ?? 0);
$adminNote = trim($_POST['admin_note'] ?? '');
$actorUserId = (int)current_user_id();

if ($refundRequestId <= 0) {
    set_flash('error', 'Invalid refund request ID.');
    redirect($redirectPath);
}

$conn = getDBConnection();

try {
    $tourCompanyId = 0;
    if ($isTourAdmin) {
        require_company_permission($conn, 'manage_tour_refunds');
        $tourCompany = require_tour_admin_company($conn);
        $tourCompanyId = (int)($tourCompany['company_id'] ?? 0);
        if ($tourCompanyId <= 0) {
            throw new Exception('No approved tour company is assigned to your account.');
        }
    }

    $conn->begin_transaction();

    $request = $isTourAdmin
        ? fetch_refund_request_for_tour_admin_update($conn, $refundRequestId, $tourCompanyId)
        : fetch_refund_request_for_admin_update($conn, $refundRequestId);
    if (!$request) {
        throw new Exception('Refund request not found.');
    }

    if ($request['status'] !== 'pending') {
        throw new Exception('Only pending refund requests can be approved.');
    }

    $updateRefundSql = "
        UPDATE refund_requests
        SET status = 'approved',
            admin_note = ?,
            processed_by = ?,
            processed_at = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($updateRefundSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare refund approval statement.');
    }

    $stmt->bind_param('sii', $adminNote, $actorUserId, $refundRequestId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to approve refund request.');
    }
    $stmt->close();

    $bookingNote = 'Refund approved via request ' . $request['request_code'] . ' on ' . date('Y-m-d H:i:s');
    if ($adminNote !== '') {
        $bookingNote .= ' | Admin note: ' . $adminNote;
    }

    $updateBookingSql = "
        UPDATE bookings
        SET status = 'cancelled',
            payment_status = 'refunded',
            notes = CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE '\n' END, ?)
        WHERE id = ?
    ";
    $stmt = $conn->prepare($updateBookingSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare booking update statement.');
    }

    $stmt->bind_param('si', $bookingNote, $request['booking_id']);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update booking refund status.');
    }
    $stmt->close();

    if ($request['booking_type'] === 'bus') {
        $seatCountSql = "
            SELECT COUNT(*) AS seat_count
            FROM booking_seats
            WHERE booking_id = ?
        ";
        $stmt = $conn->prepare($seatCountSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare seat count statement.');
        }

        $stmt->bind_param('i', $request['booking_id']);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to fetch booked seat count.');
        }

        $result = $stmt->get_result();
        $seatCountRow = $result->fetch_assoc();
        $stmt->close();

        $seatCount = (int)($seatCountRow['seat_count'] ?? 0);

        if ($seatCount > 0 && !empty($request['trip_id'])) {
            $restoreSeatsSql = "
                UPDATE trips
                SET available_seats = available_seats + ?
                WHERE id = ?
            ";
            $stmt = $conn->prepare($restoreSeatsSql);
            if (!$stmt) {
                throw new Exception('Failed to prepare trip seat restore statement.');
            }

            $tripId = (int)$request['trip_id'];
            $stmt->bind_param('ii', $seatCount, $tripId);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception('Failed to restore trip seats.');
            }
            $stmt->close();
        }

        $cancelTicketSql = "
            UPDATE tickets
            SET status = 'cancelled'
            WHERE booking_id = ?
              AND status <> 'cancelled'
        ";
        $stmt = $conn->prepare($cancelTicketSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare ticket cancellation statement.');
        }

        $stmt->bind_param('i', $request['booking_id']);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to cancel related tickets.');
        }
        $stmt->close();
    }

    if ($request['booking_type'] === 'tour') {
        $restoreTourSql = "
            UPDATE tour_batches
            SET booked_count = GREATEST(booked_count - ?, 0)
            WHERE id = ?
        ";
        $stmt = $conn->prepare($restoreTourSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare tour batch restore statement.');
        }

        $passengerCount = (int)($request['passenger_count'] ?? 0);
        $tourBatchId = (int)($request['tour_batch_id'] ?? 0);
        $stmt->bind_param('ii', $passengerCount, $tourBatchId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to restore tour batch capacity.');
        }
        $stmt->close();

        $cancelVoucherSql = "
            UPDATE vouchers
            SET status = 'cancelled'
            WHERE booking_id = ?
              AND status <> 'cancelled'
        ";
        $stmt = $conn->prepare($cancelVoucherSql);
        if (!$stmt) {
            throw new Exception('Failed to prepare voucher cancellation statement.');
        }

        $stmt->bind_param('i', $request['booking_id']);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to cancel related vouchers.');
        }
        $stmt->close();
    }

    $paymentNote = 'Refund approved for booking ' . $request['booking_code'] . ' via request ' . $request['request_code'];
    if ($adminNote !== '') {
        $paymentNote .= ' | ' . $adminNote;
    }

    $updatePaymentSql = "
        UPDATE payments
        SET note = CONCAT(COALESCE(note, ''), CASE WHEN COALESCE(note, '') = '' THEN '' ELSE '\n' END, ?)
        WHERE booking_id = ?
          AND status = 'verified'
    ";
    $stmt = $conn->prepare($updatePaymentSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare payment note update statement.');
    }

    $stmt->bind_param('si', $paymentNote, $request['booking_id']);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update payment note.');
    }
    $stmt->close();

    $action = 'refund_approved';
    $entityType = 'refund_request';
    $description = 'Approved refund request ' . $request['request_code'] . ' for booking ' . $request['booking_code'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($auditSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare audit log statement.');
    }

    $stmt->bind_param('ississ', $actorUserId, $action, $entityType, $refundRequestId, $description, $ipAddress);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to write audit log.');
    }
    $stmt->close();

    // Refund approved notification
    notify_event_refund_approved_by_request_id($conn, $refundRequestId, $actorUserId);

    $conn->commit();
    $conn->close();

    set_flash('success', 'Refund request approved successfully.');
    redirect($redirectPath);
} catch (Exception $e) {
    if ($conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }

    set_flash('error', $e->getMessage());
    redirect($redirectPath);
}