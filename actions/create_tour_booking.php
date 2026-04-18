<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_booking_helper.php';
require_once __DIR__ . '/../includes/booking_helper.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tours.php');
}

$conn = getDBConnection();

$packageId = (int)($_POST['package_id'] ?? 0);
$batchId = (int)($_POST['batch_id'] ?? 0);
$passengerCount = (int)($_POST['passenger_count'] ?? 0);
$travelerNamesText = trim($_POST['traveler_names'] ?? '');
$customerNote = trim($_POST['customer_note'] ?? '');
$currentUserId = (int)current_user_id();

try {
    if ($packageId <= 0 || $batchId <= 0) {
        throw new Exception('Invalid package or batch.');
    }

    if ($passengerCount <= 0) {
        throw new Exception('Passenger count must be greater than 0.');
    }

    $travelerNames = array_values(
        array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $travelerNamesText))
        )
    );

    if (count($travelerNames) !== $passengerCount) {
        throw new Exception('Traveler names count must match passenger count.');
    }

    $conn->begin_transaction();

    $package = fetch_public_tour_package_detail($conn, $packageId);
    if (!$package) {
        throw new Exception('Tour package not found.');
    }

    $packageColumn = tour_booking_get_batch_package_column($conn);
    $batchSql = "
        SELECT *
        FROM tour_batches
        WHERE id = ?
          AND {$packageColumn} = ?
        LIMIT 1
        FOR UPDATE
    ";
    $batchStmt = $conn->prepare($batchSql);
    $batchStmt->bind_param('ii', $batchId, $packageId);
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();
    $batch = $batchResult->fetch_assoc();
    $batchStmt->close();

    if (!$batch) {
        throw new Exception('Tour batch not found.');
    }

    if (!tour_batch_is_bookable($batch)) {
        throw new Exception('This batch is not open for booking.');
    }

    $remaining = tour_batch_remaining_slots($batch);
    if ($remaining < $passengerCount) {
        throw new Exception('Not enough seats available in this batch.');
    }

    $bookingCode = generate_unique_booking_code($conn);
    $totalAmount = (float)$batch['price'] * $passengerCount;
    $bookingType = 'tour';
    $bookingStatus = 'pending';
    $paymentStatus = 'unpaid';

    $insertBookingSql = "
        INSERT INTO bookings
        (
            booking_code,
            user_id,
            booking_type,
            trip_id,
            tour_batch_id,
            passenger_count,
            total_amount,
            status,
            payment_status,
            notes
        )
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)
    ";
    $insertBookingStmt = $conn->prepare($insertBookingSql);
    $insertBookingStmt->bind_param(
        'sisiidsss',
        $bookingCode,
        $currentUserId,
        $bookingType,
        $batchId,
        $passengerCount,
        $totalAmount,
        $bookingStatus,
        $paymentStatus,
        $customerNote
    );

    if (!$insertBookingStmt->execute()) {
        $insertBookingStmt->close();
        throw new Exception('Failed to create tour booking.');
    }

    $bookingId = (int)$insertBookingStmt->insert_id;
    $insertBookingStmt->close();

    $passengerSql = "
        INSERT INTO booking_passengers
        (
            booking_id,
            full_name,
            phone,
            nrc_passport,
            gender,
            age,
            special_note
        )
        VALUES (?, ?, NULL, NULL, NULL, NULL, NULL)
    ";
    $passengerStmt = $conn->prepare($passengerSql);

    foreach ($travelerNames as $travelerName) {
        $passengerStmt->bind_param('is', $bookingId, $travelerName);
        if (!$passengerStmt->execute()) {
            $passengerStmt->close();
            throw new Exception('Failed to save traveler names.');
        }
    }
    $passengerStmt->close();

    $newBookedCount = (int)$batch['booked_count'] + $passengerCount;
    $newBatchStatus = $newBookedCount >= (int)$batch['capacity'] ? 'full' : 'open';

    $updateBatchSql = "
        UPDATE tour_batches
        SET booked_count = ?,
            status = ?
        WHERE id = ?
    ";
    $updateBatchStmt = $conn->prepare($updateBatchSql);
    $updateBatchStmt->bind_param('isi', $newBookedCount, $newBatchStatus, $batchId);

    if (!$updateBatchStmt->execute()) {
        $updateBatchStmt->close();
        throw new Exception('Failed to update batch availability.');
    }
    $updateBatchStmt->close();

    $auditAction = 'tour_booking_created';
    $entityType = 'booking';
    $description = 'Created tour booking: ' . $bookingCode;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $currentUserId, $auditAction, $entityType, $bookingId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    $conn->close();

    set_flash('success', 'Tour booking created successfully. Please submit payment.');
    redirect('payment.php?booking_id=' . $bookingId);
} catch (Exception $e) {
    if ($conn->errno === 0) {
        $conn->rollback();
    } else {
        $conn->rollback();
    }
    $conn->close();

    set_flash('error', $e->getMessage());
    redirect('tour_package.php?package_id=' . $packageId);
}
