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

$trip1Id = (int)($_POST['trip1_id'] ?? 0);
$trip2Id = (int)($_POST['trip2_id'] ?? 0);
$leg1SeatIdsRaw = $_POST['leg1_selected_seats'] ?? [];
$leg2SeatIdsRaw = $_POST['leg2_selected_seats'] ?? [];

$bulkFullName = trim($_POST['booking_full_name'] ?? '');
$bulkPhone = trim($_POST['booking_phone'] ?? '');
$bulkNrcPassport = trim($_POST['booking_nrc_passport'] ?? '');
$bulkGender = trim($_POST['booking_gender'] ?? '');
$bulkAge = trim($_POST['booking_age'] ?? '');
$passengerNamesText = trim($_POST['passenger_names_text'] ?? '');
$customerNote = trim($_POST['customer_note'] ?? '');

function normalize_seat_ids($raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function lock_trip_for_multi(mysqli $conn, int $tripId): array
{
    $sql = "
        SELECT
            t.id,
            t.bus_id,
            t.route_id,
            t.price,
            t.status,
            t.departure_datetime,
            t.arrival_datetime,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM trips t
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare trip query.');
    }
    $stmt->bind_param('i', $tripId);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip) {
        throw new Exception('One selected trip was not found.');
    }

    if (($trip['status'] ?? '') !== 'open') {
        throw new Exception('One selected trip is no longer open.');
    }

    return $trip;
}

function lock_and_validate_multi_seats(mysqli $conn, int $tripId, int $busId, array $seatIds): array
{
    if (empty($seatIds)) {
        throw new Exception('Please select seats for both buses.');
    }

    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $seatSql = "
        SELECT id, seat_number, seat_type
        FROM bus_seats
        WHERE bus_id = ?
          AND is_active = 1
          AND seat_type NOT IN ('driver', 'assistant')
          AND id IN ($placeholders)
        ORDER BY row_no ASC, col_no ASC, id ASC
        FOR UPDATE
    ";
    $seatStmt = $conn->prepare($seatSql);
    if (!$seatStmt) {
        throw new Exception('Failed to prepare seat validation query.');
    }

    $params = array_merge([$busId], $seatIds);
    dynamic_bind_params($seatStmt, 'i' . str_repeat('i', count($seatIds)), $params);
    $seatStmt->execute();
    $seatResult = $seatStmt->get_result();

    $seats = [];
    while ($row = $seatResult->fetch_assoc()) {
        $seats[(int)$row['id']] = $row;
    }
    $seatStmt->close();

    if (count($seats) !== count($seatIds)) {
        throw new Exception('One or more selected seats are invalid.');
    }

    $bookedSql = "
        SELECT bus_seat_id
        FROM booking_seats
        WHERE trip_id = ?
          AND bus_seat_id IN ($placeholders)
        FOR UPDATE
    ";
    $bookedStmt = $conn->prepare($bookedSql);
    if (!$bookedStmt) {
        throw new Exception('Failed to prepare booked seat query.');
    }

    $params = array_merge([$tripId], $seatIds);
    dynamic_bind_params($bookedStmt, 'i' . str_repeat('i', count($seatIds)), $params);
    $bookedStmt->execute();
    $bookedResult = $bookedStmt->get_result();

    if ($bookedResult && $bookedResult->num_rows > 0) {
        $bookedStmt->close();
        throw new Exception('One or more selected seats have already been booked.');
    }
    $bookedStmt->close();

    return $seats;
}

try {
    if ($trip1Id <= 0 || $trip2Id <= 0 || $trip1Id === $trip2Id) {
        throw new Exception('Invalid two-step trip selected.');
    }

    if ($bulkFullName === '') {
        throw new Exception('Main contact full name is required.');
    }

    if ($bulkGender !== '' && !in_array($bulkGender, ['male', 'female', 'other'], true)) {
        throw new Exception('Invalid gender selected.');
    }

    if ($bulkAge !== '' && (!ctype_digit((string)$bulkAge) || (int)$bulkAge < 0)) {
        throw new Exception('Passenger age must be a valid number.');
    }

    $leg1SeatIds = normalize_seat_ids($leg1SeatIdsRaw);
    $leg2SeatIds = normalize_seat_ids($leg2SeatIdsRaw);

    if (empty($leg1SeatIds) || empty($leg2SeatIds)) {
        throw new Exception('Please select seats for both buses.');
    }

    if (count($leg1SeatIds) !== count($leg2SeatIds)) {
        throw new Exception('Bus 1 and Bus 2 selected seat counts must be the same.');
    }

    $conn->begin_transaction();

    $leg1 = lock_trip_for_multi($conn, $trip1Id);
    $leg2 = lock_trip_for_multi($conn, $trip2Id);

    if (($leg1['to_city_name'] ?? '') !== ($leg2['from_city_name'] ?? '')) {
        throw new Exception('Transfer route is not valid.');
    }

    if (strtotime((string)$leg2['departure_datetime']) < strtotime((string)$leg1['arrival_datetime'])) {
        throw new Exception('Second bus must depart after the first bus arrives.');
    }

    $leg1Seats = lock_and_validate_multi_seats($conn, $trip1Id, (int)$leg1['bus_id'], $leg1SeatIds);
    $leg2Seats = lock_and_validate_multi_seats($conn, $trip2Id, (int)$leg2['bus_id'], $leg2SeatIds);

    $passengerCount = count($leg1SeatIds);
    $totalAmount = ((float)$leg1['price'] + (float)$leg2['price']) * $passengerCount;
    $bookingCode = generate_unique_booking_code($conn);
    $userId = (int)current_user_id();
    $bookingType = 'bus';
    $bookingStatus = 'pending';
    $paymentStatus = 'unpaid';

    $routeNote = 'Two-step route: ' . $leg1['from_city_name'] . ' -> ' . $leg1['to_city_name'] . ' -> ' . $leg2['to_city_name'];
    $notes = trim($routeNote . "\n" . $customerNote);

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
        $trip1Id,
        $passengerCount,
        $totalAmount,
        $bookingStatus,
        $paymentStatus,
        $notes
    );

    if (!$bookingStmt->execute()) {
        $bookingStmt->close();
        throw new Exception('Failed to create two-step booking.');
    }

    $bookingId = (int)$bookingStmt->insert_id;
    $bookingStmt->close();

    if ($bookingId <= 0) {
        throw new Exception('Booking ID could not be generated.');
    }

    $optionalNames = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $passengerNamesText))));

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
        VALUES (?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''))
    ";
    $passengerStmt = $conn->prepare($passengerSql);
    if (!$passengerStmt) {
        throw new Exception('Failed to prepare passenger insert.');
    }

    for ($i = 0; $i < $passengerCount; $i++) {
        $name = $optionalNames[$i] ?? $bulkFullName;
        $leg1SeatNumber = (string)$leg1Seats[$leg1SeatIds[$i]]['seat_number'];
        $leg2SeatNumber = (string)$leg2Seats[$leg2SeatIds[$i]]['seat_number'];
        $specialNote = 'Bus1 seat: ' . $leg1SeatNumber . ' | Bus2 seat: ' . $leg2SeatNumber;

        $passengerStmt->bind_param(
            'issssss',
            $bookingId,
            $name,
            $bulkPhone,
            $bulkNrcPassport,
            $bulkGender,
            $bulkAge,
            $specialNote
        );

        if (!$passengerStmt->execute()) {
            $passengerStmt->close();
            throw new Exception('Failed to save passenger details.');
        }
    }
    $passengerStmt->close();

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

    foreach ($leg1SeatIds as $seatId) {
        $seatNumber = (string)$leg1Seats[$seatId]['seat_number'];
        $price = (float)$leg1['price'];
        $bookingSeatStmt->bind_param('iiisd', $bookingId, $trip1Id, $seatId, $seatNumber, $price);
        if (!$bookingSeatStmt->execute()) {
            $bookingSeatStmt->close();
            throw new Exception('Failed to reserve bus 1 seats.');
        }
    }

    foreach ($leg2SeatIds as $seatId) {
        $seatNumber = (string)$leg2Seats[$seatId]['seat_number'];
        $price = (float)$leg2['price'];
        $bookingSeatStmt->bind_param('iiisd', $bookingId, $trip2Id, $seatId, $seatNumber, $price);
        if (!$bookingSeatStmt->execute()) {
            $bookingSeatStmt->close();
            throw new Exception('Failed to reserve bus 2 seats.');
        }
    }
    $bookingSeatStmt->close();

    refresh_trip_available_seats($conn, $trip1Id);
    refresh_trip_available_seats($conn, $trip2Id);

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, 'multi_hop_booking_created', 'booking', ?, ?, ?)
    ";
    $description = 'Created two-step bus booking: ' . $bookingCode;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('iiss', $userId, $bookingId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $conn->close();

    $message = 'Two-step booking created successfully. Booking Code: ' . $bookingCode . '. Please submit payment.';
    if ($passengerCount >= 3) {
        $message .= ' Reminder: Please bring NRC / ID card for all passengers.';
    }

    set_flash('success', $message);
    redirect('payment.php?booking_id=' . $bookingId);
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
    redirect('checkout_multi.php?trip1_id=' . $trip1Id . '&trip2_id=' . $trip2Id);
}
