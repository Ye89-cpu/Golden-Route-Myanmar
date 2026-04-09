<?php
// includes/booking_helper.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/seat_layout_helper.php';

function dynamic_bind_params(mysqli_stmt $stmt, string $types, array $params): void
{
    $bindValues = [];
    $bindValues[] = $types;

    foreach ($params as $key => $value) {
        $bindValues[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}

function fetch_trip_checkout_details(mysqli $conn, int $tripId): ?array
{
    $sql = "
        SELECT
            t.id AS trip_id,
            t.company_id,
            t.route_id,
            t.bus_id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.price,
            t.available_seats,
            t.status AS trip_status,

            c.name AS company_name,
            c.status AS company_status,

            b.bus_number,
            b.bus_type,
            b.layout_type,
            b.total_seats,
            b.status AS bus_status,

            r.distance_km,
            r.duration_minutes,
            r.status AS route_status,

            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM trips t
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses b ON b.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $tripId);
    $stmt->execute();
    $result = $stmt->get_result();
    $trip = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $trip;
}

function fetch_trip_seat_map(mysqli $conn, int $tripId, int $busId): array
{
    $sql = "
        SELECT
            bs.id,
            bs.seat_number,
            bs.seat_type,
            bs.row_no,
            bs.col_no,
            bs.is_active,
            bk.id AS booked_seat_row_id
        FROM bus_seats bs
        LEFT JOIN booking_seats bk
            ON bk.bus_seat_id = bs.id
           AND bk.trip_id = ?
        WHERE bs.bus_id = ?
          AND bs.is_active = 1
          AND bs.seat_type NOT IN ('driver', 'assistant')
        ORDER BY bs.row_no ASC, bs.col_no ASC, bs.id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $tripId, $busId);
    $stmt->execute();
    $result = $stmt->get_result();

    $seats = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_booked'] = !empty($row['booked_seat_row_id']);
        $seats[] = $row;
    }

    $stmt->close();
    return $seats;
}

function group_seats_by_row(array $seats): array
{
    $rows = [];

    foreach ($seats as $seat) {
        $rowNo = (int)$seat['row_no'];
        $colNo = (int)$seat['col_no'];
        $rows[$rowNo][$colNo] = $seat;
    }

    ksort($rows);
    return $rows;
}

function count_current_available_seats(array $seats): int
{
    $count = 0;

    foreach ($seats as $seat) {
        if (empty($seat['is_booked'])) {
            $count++;
        }
    }

    return $count;
}

function generate_unique_booking_code(mysqli $conn): string
{
    do {
        $code = 'MBTB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $sql = "SELECT id FROM bookings WHERE booking_code = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $code;
}

function refresh_trip_available_seats(mysqli $conn, int $tripId): void
{
    $tripSql = "SELECT id, bus_id, status FROM trips WHERE id = ? LIMIT 1";
    $tripStmt = $conn->prepare($tripSql);
    $tripStmt->bind_param('i', $tripId);
    $tripStmt->execute();
    $tripResult = $tripStmt->get_result();
    $trip = $tripResult->fetch_assoc();
    $tripStmt->close();

    if (!$trip) {
        throw new Exception('Trip not found while refreshing availability.');
    }

    $busId = (int)$trip['bus_id'];

    $seatSql = "
        SELECT COUNT(*) AS total_bookable_seats
        FROM bus_seats
        WHERE bus_id = ?
          AND is_active = 1
          AND seat_type NOT IN ('driver', 'assistant')
    ";
    $seatStmt = $conn->prepare($seatSql);
    $seatStmt->bind_param('i', $busId);
    $seatStmt->execute();
    $seatResult = $seatStmt->get_result();
    $seatRow = $seatResult->fetch_assoc();
    $seatStmt->close();

    $bookedSql = "
        SELECT COUNT(*) AS booked_count
        FROM booking_seats
        WHERE trip_id = ?
    ";
    $bookedStmt = $conn->prepare($bookedSql);
    $bookedStmt->bind_param('i', $tripId);
    $bookedStmt->execute();
    $bookedResult = $bookedStmt->get_result();
    $bookedRow = $bookedResult->fetch_assoc();
    $bookedStmt->close();

    $totalBookableSeats = (int)($seatRow['total_bookable_seats'] ?? 0);
    $bookedCount = (int)($bookedRow['booked_count'] ?? 0);
    $availableSeats = max($totalBookableSeats - $bookedCount, 0);

    $newStatus = $availableSeats > 0 ? 'open' : 'full';

    $updateSql = "UPDATE trips SET available_seats = ?, status = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('isi', $availableSeats, $newStatus, $tripId);
    $updateStmt->execute();
    $updateStmt->close();
}

function get_booking_seat_color_class(array $seat): string
{
    if (!empty($seat['is_booked'])) {
        return 'seat-booked';
    }

    switch ($seat['seat_type']) {
        case 'vip':
            return 'seat-vip';
        case 'ladies':
            return 'seat-ladies';
        case 'normal':
        default:
            return 'seat-normal';
    }
}
?>