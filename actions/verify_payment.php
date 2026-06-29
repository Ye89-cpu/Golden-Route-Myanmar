<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/ticket_service.php';
require_once __DIR__ . '/../includes/voucher_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role(['super_admin', 'tour_admin', 'bus_admin']);

$currentRole = current_user_role();
$isTourAdmin = $currentRole === 'tour_admin';
$isBusAdmin = $currentRole === 'bus_admin';
$redirectPath = 'admin/payments.php';

if ($isTourAdmin) {
    $redirectPath = 'tour_admin/payments.php';
} elseif ($isBusAdmin) {
    $redirectPath = 'bus_admin/bookings.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectPath);
}

$paymentId = (int)($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    set_flash('error', 'Invalid payment ID.');
    redirect($redirectPath);
}

$conn = getDBConnection();

try {
    $tourCompanyId = 0;
    if ($isTourAdmin) {
        require_company_permission($conn, 'manage_tour_payments');
        $tourCompany = require_tour_admin_company($conn);
        $tourCompanyId = (int)($tourCompany['company_id'] ?? 0);
        if ($tourCompanyId <= 0) {
            throw new Exception('No approved tour company is assigned to your account.');
        }
    }

    $busCompanyId = 0;
    if ($isBusAdmin) {
        require_company_permission($conn, 'approve_bookings');
        $busCompany = require_bus_admin_company($conn);
        $busCompanyId = (int)($busCompany['company_id'] ?? 0);
        if ($busCompanyId <= 0) {
            throw new Exception('No approved bus company is assigned to your account.');
        }
        $busCompanyScopeIds = function_exists('get_related_bus_company_ids')
            ? get_related_bus_company_ids($conn, $busCompanyId)
            : [$busCompanyId];
    }

    $conn->begin_transaction();

    $sql = "
        SELECT
            p.id,
            p.booking_id,
            p.status AS payment_status,
            b.booking_code,
            b.booking_type,
            t.company_id AS trip_company_id,
            bus.company_id AS bus_company_id,
            r.company_id AS route_company_id,
            tb.company_id AS tour_company_id,
            COALESCE(t.company_id, tb.company_id) AS company_id
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        LEFT JOIN trips t ON t.id = b.trip_id
        LEFT JOIN buses bus ON bus.id = t.bus_id
        LEFT JOIN routes r ON r.id = t.route_id
        LEFT JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE p.id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Payment query prepare failed: ' . $conn->error);
    }
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        throw new Exception('Payment record not found.');
    }

    if ($isTourAdmin) {
        if (($payment['booking_type'] ?? '') !== 'tour') {
            throw new Exception('Tour admin can only verify tour package payments.');
        }

        if ((int)($payment['tour_company_id'] ?? 0) !== $tourCompanyId) {
            throw new Exception('This payment does not belong to your tour company.');
        }
    }

    if ($isBusAdmin) {
        if (($payment['booking_type'] ?? '') !== 'bus') {
            throw new Exception('Bus admin can only verify bus booking payments.');
        }

        $belongsToBusCompany = (bool)array_intersect($busCompanyScopeIds, [
            (int)($payment['trip_company_id'] ?? 0),
            (int)($payment['bus_company_id'] ?? 0),
            (int)($payment['route_company_id'] ?? 0),
        ]);

        if (!$belongsToBusCompany) {
            throw new Exception('This payment does not belong to your bus company.');
        }
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
        redirect($redirectPath);
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
        redirect($redirectPath);
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

redirect($redirectPath);
