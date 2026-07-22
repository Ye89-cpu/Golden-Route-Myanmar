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

function create_booking_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function create_booking_clean_single_line(string $value): string
{
    $value = trim($value);
    $cleaned = preg_replace('/\s+/u', ' ', $value);
    return is_string($cleaned) ? $cleaned : $value;
}

function create_booking_valid_name(string $value): bool
{
    $length = create_booking_text_length($value);
    if ($length < 2 || $length > 120) {
        return false;
    }

    return preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u", $value) === 1;
}

function create_booking_normalize_phone(string $value): string
{
    $phone = preg_replace('/[\s()\-]+/', '', trim($value));
    $phone = is_string($phone) ? $phone : trim($value);

    if (strpos($phone, '0095') === 0) {
        $phone = '+95' . substr($phone, 4);
    }

    return $phone;
}

function create_booking_valid_phone(string $value): bool
{
    return preg_match('/^(?:09\d{7,9}|\+959\d{7,9})$/', $value) === 1;
}

function create_booking_valid_identity(string $value): bool
{
    $length = create_booking_text_length($value);
    if ($length < 4 || $length > 50) {
        return false;
    }

    return preg_match('/^[\p{L}\p{M}\p{N}\/().\-\s]+$/u', $value) === 1;
}

$tripId = (int)($_POST['trip_id'] ?? 0);
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
$selectedSeatIdsRaw = $_POST['selected_seats'] ?? [];
$customerNote = trim((string)($_POST['customer_note'] ?? ''));
$promotionCode = strtoupper(trim((string)($_POST['promotion_code'] ?? '')));

$bulkFullName = create_booking_clean_single_line((string)($_POST['booking_full_name'] ?? ''));
$bulkPhone = create_booking_normalize_phone((string)($_POST['booking_phone'] ?? ''));
$bulkNrcPassport = create_booking_clean_single_line((string)($_POST['booking_nrc_passport'] ?? ''));
$bulkGender = trim((string)($_POST['booking_gender'] ?? ''));
$bulkAge = trim((string)($_POST['booking_age'] ?? ''));
$bulkSpecialNote = create_booking_clean_single_line((string)($_POST['booking_special_note'] ?? ''));
$passengerNamesText = trim((string)($_POST['passenger_names_text'] ?? ''));

try {
    $sessionCsrfToken = (string)($_SESSION['booking_csrf_token'] ?? '');
    if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
        throw new Exception('Your booking form session has expired. Please refresh the checkout page and try again.');
    }

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

    if (count($selectedSeatIds) > 100) {
        throw new Exception('Too many seats were selected in one booking.');
    }

    if (!create_booking_valid_name($bulkFullName)) {
        throw new Exception('Please enter a valid full name (2–120 letters, without numbers).');
    }

    if (!create_booking_valid_phone($bulkPhone)) {
        throw new Exception('Please enter a valid Myanmar phone number, for example 09xxxxxxxxx or +959xxxxxxxxx.');
    }

    if (!create_booking_valid_identity($bulkNrcPassport)) {
        throw new Exception('Please enter a valid NRC or passport number (4–50 characters).');
    }

    if (!in_array($bulkGender, ['male', 'female', 'other'], true)) {
        throw new Exception('Please select a valid gender.');
    }

    if ($bulkAge === '' || !ctype_digit($bulkAge) || (int)$bulkAge > 120) {
        throw new Exception('Passenger age is required and must be between 0 and 120.');
    }

    if (create_booking_text_length($bulkSpecialNote) > 200) {
        throw new Exception('Special note must not exceed 200 characters.');
    }

    if (create_booking_text_length($customerNote) > 2000) {
        throw new Exception('Customer note must not exceed 2000 characters.');
    }

    if ($promotionCode !== '') {
        if (strlen($promotionCode) > 50 || preg_match('/^[A-Z0-9_-]+$/', $promotionCode) !== 1) {
            throw new Exception('Promotion code may contain only letters, numbers, hyphens, and underscores.');
        }
    }

    $optionalNames = [];
    if ($passengerNamesText !== '') {
        $nameLines = preg_split('/\r\n|\r|\n/', $passengerNamesText);
        foreach (is_array($nameLines) ? $nameLines : [] as $nameLine) {
            $name = create_booking_clean_single_line((string)$nameLine);
            if ($name === '') {
                continue;
            }
            if (!create_booking_valid_name($name)) {
                throw new Exception('Each passenger name must contain 2–120 letters and must not contain numbers.');
            }
            $optionalNames[] = $name;
        }
    }

    if (count($optionalNames) > count($selectedSeatIds)) {
        throw new Exception('Passenger name count cannot be greater than the selected seat count.');
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
    | Build passenger details for every selected seat
    |--------------------------------------------------------------------------
    | One verified main contact is required. Optional passenger names can
    | override the contact name line-by-line for the selected seats.
    */
    $passengerMap = [];

    foreach ($selectedSeatIds as $index => $seatId) {
        $seatNumber = (string)($seatRows[$seatId]['seat_number'] ?? '');
        $passengerName = $optionalNames[$index] ?? $bulkFullName;
        $seatLabel = $seatNumber !== '' ? 'Seat ' . $seatNumber : '';
        $specialNote = $bulkSpecialNote;

        if ($seatLabel !== '') {
            $specialNote = $specialNote !== '' ? $specialNote . ' | ' . $seatLabel : $seatLabel;
        }

        if (create_booking_text_length($specialNote) > 255) {
            throw new Exception('Special note is too long after seat information is added.');
        }

        $passengerMap[$seatId] = [
            'seat_number'  => $seatNumber,
            'full_name'    => $passengerName,
            'phone'        => $bulkPhone,
            'nrc_passport' => $bulkNrcPassport,
            'gender'       => $bulkGender,
            'age'          => $bulkAge,
            'special_note' => $specialNote,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Create booking
    |--------------------------------------------------------------------------
    */
    $bookingCode = generate_unique_booking_code($conn);
    $passengerCount = count($selectedSeatIds);
    $originalAmount = round($seatPrice * $passengerCount, 2);
    $totalAmount = $originalAmount;
    $promotionDiscount = 0.00;
    $appliedPromotion = null;

    if ($promotionCode !== '') {
        $promotionSql = "
            SELECT id, title, promo_code, discount_type, discount_value, starts_at, ends_at, status
            FROM promotions
            WHERE UPPER(promo_code) = ?
            LIMIT 1
            FOR UPDATE
        ";
        $promotionStmt = $conn->prepare($promotionSql);
        if (!$promotionStmt) {
            throw new Exception('Promotion codes are temporarily unavailable. Please continue without a code or try again.');
        }

        $promotionStmt->bind_param('s', $promotionCode);
        $promotionStmt->execute();
        $promotionResult = $promotionStmt->get_result();
        $promotion = $promotionResult ? $promotionResult->fetch_assoc() : null;
        $promotionStmt->close();

        if (!$promotion) {
            throw new Exception('Promotion code was not found. Please check the code and try again.');
        }

        $now = time();
        $startsAt = !empty($promotion['starts_at']) ? strtotime((string)$promotion['starts_at']) : false;
        $endsAt = !empty($promotion['ends_at']) ? strtotime((string)$promotion['ends_at']) : false;

        if ((string)$promotion['status'] !== 'active') {
            throw new Exception('This promotion code is not active.');
        }
        if ($startsAt !== false && $startsAt > $now) {
            throw new Exception('This promotion has not started yet.');
        }
        if ($endsAt !== false && $endsAt < $now) {
            throw new Exception('This promotion code has expired.');
        }

        $discountValue = (float)$promotion['discount_value'];
        if ((string)$promotion['discount_type'] === 'percentage') {
            if ($discountValue <= 0 || $discountValue > 100) {
                throw new Exception('This promotion has an invalid percentage value.');
            }
            $promotionDiscount = round($originalAmount * ($discountValue / 100), 2);
        } elseif ((string)$promotion['discount_type'] === 'fixed') {
            if ($discountValue <= 0) {
                throw new Exception('This promotion has an invalid discount value.');
            }
            $promotionDiscount = round($discountValue, 2);
        } else {
            throw new Exception('This promotion has an invalid discount type.');
        }

        $promotionDiscount = min($originalAmount, $promotionDiscount);
        $totalAmount = max(0, round($originalAmount - $promotionDiscount, 2));
        $appliedPromotion = $promotion;
    }

    $bookingNotes = $customerNote;
    if ($appliedPromotion) {
        $promotionNote = sprintf(
            'Promotion %s applied: -%s MMK (original %s MMK)',
            (string)$appliedPromotion['promo_code'],
            number_format($promotionDiscount, 2, '.', ''),
            number_format($originalAmount, 2, '.', '')
        );
        $bookingNotes = trim($bookingNotes);
        $bookingNotes = $bookingNotes !== '' ? $bookingNotes . PHP_EOL . $promotionNote : $promotionNote;
    }

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
        'sisiidsss',
        $bookingCode,
        $userId,
        $bookingType,
        $tripId,
        $passengerCount,
        $totalAmount,
        $bookingStatus,
        $paymentStatus,
        $bookingNotes
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
    if ($appliedPromotion) {
        $description .= ' | Promotion: ' . (string)$appliedPromotion['promo_code']
            . ' | Discount: ' . number_format($promotionDiscount, 2, '.', '') . ' MMK';
    }
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

    unset($_SESSION['booking_csrf_token']);
    clear_old_input();

    $successMessage = 'Booking created successfully. Booking Code: ' . $bookingCode . '.';
    if ($appliedPromotion) {
        $successMessage .= ' Promotion ' . (string)$appliedPromotion['promo_code']
            . ' saved you ' . number_format($promotionDiscount, 2) . ' MMK.';
    }
    $successMessage .= ' Please submit your payment proof.';
    if ($passengerCount >= 3) {
        $successMessage .= ' Reminder: Please bring NRC / ID card for all passengers.';
    }
    set_flash('success', $successMessage);
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

    save_old_input([
        'trip_id' => $tripId,
        'selected_seats' => is_array($selectedSeatIdsRaw) ? $selectedSeatIdsRaw : [],
        'booking_full_name' => $bulkFullName,
        'booking_phone' => $bulkPhone,
        'booking_nrc_passport' => $bulkNrcPassport,
        'booking_gender' => $bulkGender,
        'booking_age' => $bulkAge,
        'booking_special_note' => $bulkSpecialNote,
        'passenger_names_text' => $passengerNamesText,
        'promotion_code' => $promotionCode,
        'customer_note' => $customerNote,
    ]);

    set_flash('error', $e->getMessage());
    redirect('checkout.php?trip_id=' . $tripId);
}
