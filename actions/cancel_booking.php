<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_helper.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/bookings.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking selected.');
    redirect('customer/bookings.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $bookingSql = "
        SELECT *
        FROM bookings
        WHERE id = ? AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $bookingStmt = $conn->prepare($bookingSql);
    $bookingStmt->bind_param('ii', $bookingId, $currentUserId);
    $bookingStmt->execute();
    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        throw new Exception('Booking not found or not allowed.');
    }

    if (($booking['status'] ?? '') === 'cancelled') {
        throw new Exception('This booking is already cancelled.');
    }

    if (($booking['payment_status'] ?? '') === 'paid') {
        throw new Exception('Paid bookings cannot be cancelled directly. Please submit a refund request.');
    }

    if (($booking['booking_type'] ?? '') === 'bus') {
        $tripIds = [];
        $tripStmt = $conn->prepare("SELECT DISTINCT trip_id FROM booking_seats WHERE booking_id = ?");
        $tripStmt->bind_param('i', $bookingId);
        $tripStmt->execute();
        $tripResult = $tripStmt->get_result();
        while ($row = $tripResult->fetch_assoc()) {
            $tripIds[] = (int)$row['trip_id'];
        }
        $tripStmt->close();

        $deleteSeatsStmt = $conn->prepare("DELETE FROM booking_seats WHERE booking_id = ?");
        $deleteSeatsStmt->bind_param('i', $bookingId);
        $deleteSeatsStmt->execute();
        $deleteSeatsStmt->close();

        foreach ($tripIds as $tripId) {
            if ($tripId > 0) {
                refresh_trip_available_seats($conn, $tripId);
            }
        }
    }

    if (($booking['booking_type'] ?? '') === 'tour' && !empty($booking['tour_batch_id'])) {
        $batchId = (int)$booking['tour_batch_id'];
        $passengerCount = (int)$booking['passenger_count'];

        $batchSql = "SELECT id, booked_count FROM tour_batches WHERE id = ? LIMIT 1 FOR UPDATE";
        $batchStmt = $conn->prepare($batchSql);
        $batchStmt->bind_param('i', $batchId);
        $batchStmt->execute();
        $batch = $batchStmt->get_result()->fetch_assoc();
        $batchStmt->close();

        if ($batch) {
            $newBookedCount = max(0, (int)$batch['booked_count'] - $passengerCount);
            $newStatus = 'open';
            $updateBatchStmt = $conn->prepare("UPDATE tour_batches SET booked_count = ?, status = ? WHERE id = ?");
            $updateBatchStmt->bind_param('isi', $newBookedCount, $newStatus, $batchId);
            $updateBatchStmt->execute();
            $updateBatchStmt->close();
        }
    }

    $latestPaymentStmt = $conn->prepare("SELECT id, status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $latestPaymentStmt->bind_param('i', $bookingId);
    $latestPaymentStmt->execute();
    $latestPayment = $latestPaymentStmt->get_result()->fetch_assoc();
    $latestPaymentStmt->close();

    if ($latestPayment && ($latestPayment['status'] ?? '') === 'submitted') {
        $rejectPaymentStmt = $conn->prepare("UPDATE payments SET status = 'rejected', note = CONCAT(COALESCE(note, ''), '\nCancelled by customer before verification.') WHERE id = ?");
        $rejectPaymentStmt->bind_param('i', $latestPayment['id']);
        $rejectPaymentStmt->execute();
        $rejectPaymentStmt->close();
    }

    $newPaymentStatus = (($booking['payment_status'] ?? '') === 'pending_review') ? 'failed' : (string)($booking['payment_status'] ?? 'unpaid');
    if (!in_array($newPaymentStatus, ['unpaid', 'pending_review', 'paid', 'failed', 'refunded'], true)) {
        $newPaymentStatus = 'failed';
    }

    $cancelNote = trim((string)($booking['notes'] ?? '') . "\nCancelled by customer on " . date('Y-m-d H:i:s'));
    $updateBookingSql = "
        UPDATE bookings
        SET status = 'cancelled',
            payment_status = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ";
    $updateBookingStmt = $conn->prepare($updateBookingSql);
    $updateBookingStmt->bind_param('ssi', $newPaymentStatus, $cancelNote, $bookingId);
    $updateBookingStmt->execute();
    $updateBookingStmt->close();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, 'booking_cancelled_by_customer', 'booking', ?, ?, ?)
    ";
    $description = 'Customer cancelled booking: ' . ($booking['booking_code'] ?? ('#' . $bookingId));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('iiss', $currentUserId, $bookingId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $conn->close();

    set_flash('success', 'Booking cancelled successfully. Seats/slots have been released.');
    redirect('customer/bookings.php');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    try {
        $conn->close();
    } catch (Throwable $closeError) {
    }

    set_flash('error', $e->getMessage());
    redirect('customer/bookings.php');
}
