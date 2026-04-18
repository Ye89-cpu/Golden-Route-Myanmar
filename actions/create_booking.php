<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/booking_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('search_bus.php');
}

$conn = getDBConnection();

$tripId = (int)($_POST['trip_id'] ?? 0);
$selectedSeatIdsRaw = $_POST['selected_seats'] ?? [];
$customerNote = trim($_POST['customer_note'] ?? '');

$passengerSeatIds = $_POST['passenger_seat_id'] ?? [];
$passengerSeatNumbers = $_POST['passenger_seat_number'] ?? [];
$passengerFullNames = $_POST['passenger_full_name'] ?? [];
$passengerPhones = $_POST['passenger_phone'] ?? [];
$passengerNrcPassports = $_POST['passenger_nrc_passport'] ?? [];
$passengerGenders = $_POST['passenger_gender'] ?? [];
$passengerAges = $_POST['passenger_age'] ?? [];
$passengerSpecialNotes = $_POST['passenger_special_note'] ?? [];

try {
    if ($tripId <= 0) {
        throw new Exception('Invalid trip selected.');
    }

    if (!is_array($selectedSeatIdsRaw) || empty($selectedSeatIdsRaw)) {
        throw new Exception('Please select at least one seat.');
    }

    $selectedSeatIds = [];
    foreach ($selectedSeatIdsRaw as $seatId) {
        $seatId = (int)$seatId;
        if ($seatId > 0 && !in_array($seatId, $selectedSeatIds, true)) {
            $selectedSeatIds[] = $seatId;
        }
    }

    if (empty($selectedSeatIds)) {
        throw new Exception('Please select valid seats.');
    }

    if (
        !is_array($passengerSeatIds) ||
        !is_array($passengerFullNames) ||
        count($passengerSeatIds) !== count($selectedSeatIds) ||
        count($passengerFullNames) !== count($selectedSeatIds)
    ) {
        throw new Exception('Passenger details must match the number of selected seats.');
    }

    $conn->begin_transaction();

    /*
    |--------------------------------------------------------------------------
    | Lock and validate trip
    |--------------------------------------------------------------------------
    */
    $tripSql = "
        SELECT id, bus_id, price, status
        FROM trips
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $tripStmt = $conn->prepare($tripSql);
    if (!$tripStmt) {
        throw new Exception('Failed to prepare trip query.');
    }

    $tripStmt->bind_param('i', $tripId);
    $tripStmt->execute();
    $tripResult = $tripStmt->get_result();
    $trip = $tripResult ? $tripResult->fetch_assoc() : null;
    $tripStmt->close();

    if (!$trip) {
        throw new Exception('Trip not found.');
    }

    if (($trip['status'] ?? '') !== 'open') {
        throw new Exception('This trip is no longer open for booking.');
    }

    $busId = (int)$trip['bus_id'];
    $seatPrice = (float)$trip['price'];

    /*
    |--------------------------------------------------------------------------
    | Validate selected seats belong to bus and are bookable
    |--------------------------------------------------------------------------
    */
    $seatPlaceholders = implode(',', array_fill(0, count($selectedSeatIds), '?'));
    $seatSql = "
        SELECT id, seat_number, seat_type
        FROM bus_seats
        WHERE bus_id = ?
          AND is_active = 1
          AND seat_type NOT IN ('driver', 'assistant')
          AND id IN ($seatPlaceholders)
        ORDER BY row_no ASC, col_no ASC, id ASC
        FOR UPDATE
    ";
    $seatStmt = $conn->prepare($seatSql);
    if (!$seatStmt) {
        throw new Exception('Failed to prepare seat validation query.');
    }

    $seatParams = array_merge([$busId], $selectedSeatIds);
    dynamic_bind_params($seatStmt, 'i' . str_repeat('i', count($selectedSeatIds)), $seatParams);
    $seatStmt->execute();
    $seatResult = $seatStmt->get_result();

    $seatRows = [];
    while ($row = $seatResult->fetch_assoc()) {
        $seatRows[(int)$row['id']] = $row;
    }
    $seatStmt->close();

    if (count($seatRows) !== count($selectedSeatIds)) {
        throw new Exception('One or more selected seats are invalid.');
    }

    /*
    |--------------------------------------------------------------------------
    | Check already booked seats
    |--------------------------------------------------------------------------
    */
    $bookedSql = "
        SELECT bus_seat_id
        FROM booking_seats
        WHERE trip_id = ?
          AND bus_seat_id IN ($seatPlaceholders)
        FOR UPDATE
    ";
    $bookedStmt = $conn->prepare($bookedSql);
    if (!$bookedStmt) {
        throw new Exception('Failed to prepare booked seat query.');
    }

    $bookedParams = array_merge([$tripId], $selectedSeatIds);
    dynamic_bind_params($bookedStmt, 'i' . str_repeat('i', count($selectedSeatIds)), $bookedParams);
    $bookedStmt->execute();
    $bookedResult = $bookedStmt->get_result();

    if ($bookedResult && $bookedResult->num_rows > 0) {
        $bookedStmt->close();
        throw new Exception('One or more selected seats have already been booked.');
    }
    $bookedStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Validate passenger mapping
    |--------------------------------------------------------------------------
    */
    $passengerMap = [];

    for ($i = 0; $i < count($passengerSeatIds); $i++) {
        $seatId = (int)$passengerSeatIds[$i];
        $fullName = trim($passengerFullNames[$i] ?? '');

        if (!in_array($seatId, $selectedSeatIds, true)) {
            throw new Exception('Passenger seat mapping is invalid.');
        }

        if ($fullName === '') {
            throw new Exception('Each selected seat must have a passenger full name.');
        }

        if (isset($passengerMap[$seatId])) {
            throw new Exception('Duplicate passenger information detected for a seat.');
        }

        $passengerMap[$seatId] = [
            'seat_number'  => trim($passengerSeatNumbers[$i] ?? ''),
            'full_name'    => $fullName,
            'phone'        => trim($passengerPhones[$i] ?? ''),
            'nrc_passport' => trim($passengerNrcPassports[$i] ?? ''),
            'gender'       => trim($passengerGenders[$i] ?? ''),
            'age'          => trim($passengerAges[$i] ?? ''),
            'special_note' => trim($passengerSpecialNotes[$i] ?? ''),
        ];
    }

    foreach ($selectedSeatIds as $seatId) {
        if (!isset($passengerMap[$seatId])) {
            throw new Exception('Passenger details are missing for one or more seats.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create booking
    |--------------------------------------------------------------------------
    */
    $bookingCode = generate_unique_booking_code($conn);
    $passengerCount = count($selectedSeatIds);
    $totalAmount = $seatPrice * $passengerCount;
    $bookingType = 'bus';
    $bookingStatus = 'pending';
    $paymentStatus = 'unpaid';
    $userId = (int)current_user_id();

    $bookingSql = "
        INSERT INTO bookings
        (
            booking_code,
            user_id,
            booking_type,
            trip_id,
            passenger_count,
            total_amount,
            status,
            payment_status,
            notes
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $bookingStmt = $conn->prepare($bookingSql);
    if (!$bookingStmt) {
        throw new Exception('Failed to prepare booking insert.');
    }

    $bookingStmt->bind_param(
        'sisidssss',
        $bookingCode,
        $userId,
        $bookingType,
        $tripId,
        $passengerCount,
        $totalAmount,
        $bookingStatus,
        $paymentStatus,
        $customerNote
    );

    if (!$bookingStmt->execute()) {
        $bookingStmt->close();
        throw new Exception('Failed to create booking.');
    }

    $bookingId = (int)$bookingStmt->insert_id;
    $bookingStmt->close();

    if ($bookingId <= 0) {
        throw new Exception('Booking ID could not be generated.');
    }

    /*
    |--------------------------------------------------------------------------
    | Insert booking passengers
    |--------------------------------------------------------------------------
    */
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
        VALUES
        (
            ?,
            ?,
            NULLIF(?, ''),
            NULLIF(?, ''),
            NULLIF(?, ''),
            NULLIF(?, ''),
            NULLIF(?, '')
        )
    ";
    $passengerStmt = $conn->prepare($passengerSql);
    if (!$passengerStmt) {
        throw new Exception('Failed to prepare passenger insert.');
    }

    foreach ($selectedSeatIds as $seatId) {
        $p = $passengerMap[$seatId];
        $ageValue = $p['age'];

        if ($ageValue !== '' && (!ctype_digit((string)$ageValue) || (int)$ageValue < 0)) {
            $passengerStmt->close();
            throw new Exception('Passenger age must be a valid number.');
        }

        $passengerStmt->bind_param(
            'issssss',
            $bookingId,
            $p['full_name'],
            $p['phone'],
            $p['nrc_passport'],
            $p['gender'],
            $ageValue,
            $p['special_note']
        );

        if (!$passengerStmt->execute()) {
            $passengerStmt->close();
            throw new Exception('Failed to save passenger details.');
        }
    }
    $passengerStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Insert booking seats
    |--------------------------------------------------------------------------
    */
    $bookingSeatSql = "
        INSERT INTO booking_seats
        (
            booking_id,
            trip_id,
            bus_seat_id,
            seat_number,
            price
        )
        VALUES (?, ?, ?, ?, ?)
    ";
    $bookingSeatStmt = $conn->prepare($bookingSeatSql);
    if (!$bookingSeatStmt) {
        throw new Exception('Failed to prepare booking seat insert.');
    }

    foreach ($selectedSeatIds as $seatId) {
        $seat = $seatRows[$seatId];
        $seatNumber = $seat['seat_number'];

        $bookingSeatStmt->bind_param(
            'iiisd',
            $bookingId,
            $tripId,
            $seatId,
            $seatNumber,
            $seatPrice
        );

        if (!$bookingSeatStmt->execute()) {
            $bookingSeatStmt->close();
            throw new Exception('Failed to reserve one or more seats. They may already be booked.');
        }
    }
    $bookingSeatStmt->close();

    /*
    |--------------------------------------------------------------------------
    | Refresh trip availability
    |--------------------------------------------------------------------------
    */
    refresh_trip_available_seats($conn, $tripId);

    /*
    |--------------------------------------------------------------------------
    | Audit log
    |--------------------------------------------------------------------------
    */
    $auditAction = 'booking_created';
    $entityType = 'booking';
    $description = 'Created bus booking: ' . $bookingCode;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $userId, $auditAction, $entityType, $bookingId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();

    /*
    |--------------------------------------------------------------------------
    | Non-blocking booking notification / email
    |--------------------------------------------------------------------------
    | Booking must succeed even if notification or email fails.
    */
    try {
        if (function_exists('notify_event_booking_created_by_booking_id')) {
            notify_event_booking_created_by_booking_id($conn, (int)$bookingId, (int)$userId);
        }
    } catch (Throwable $e) {
        $logDir = __DIR__ . '/../storage/logs';
        $logFile = $logDir . '/booking_notification_error.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $message = '[' . date('Y-m-d H:i:s') . '] '
            . 'BOOKING_NOTIFICATION_ERROR | booking_id=' . (int)$bookingId
            . ' | user_id=' . (int)$userId
            . ' | message=' . $e->getMessage() . PHP_EOL;

        @file_put_contents($logFile, $message, FILE_APPEND);
    }

    $conn->close();

    set_flash(
        'success',
        'Booking created successfully. Booking Code: ' . $bookingCode .
        '. Status: pending. Payment status: unpaid.'
    );
    redirect('customer/profile.php');

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
    redirect('checkout.php?trip_id=' . $tripId);
}