<?php
// includes/ticket_helper.php

require_once __DIR__ . '/db.php';

function generate_unique_ticket_no(mysqli $conn): string
{
    do {
        $ticketNo = 'TKT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $sql = "SELECT id FROM tickets WHERE ticket_no = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $ticketNo);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $ticketNo;
}

function generate_unique_qr_token(mysqli $conn): string
{
    do {
        $token = bin2hex(random_bytes(32));

        $sql = "SELECT id FROM tickets WHERE qr_token = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $token;
}

function ticket_qr_directory(): string
{
    return dirname(__DIR__) . '/uploads/qr_codes/';
}

function ticket_pdf_directory(): string
{
    return dirname(__DIR__) . '/uploads/tickets/';
}

function ensure_ticket_directories_exist(): void
{
    $dirs = [
        ticket_qr_directory(),
        ticket_pdf_directory()
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function fetch_paid_booking_for_ticket(mysqli $conn, int $bookingId, int $userId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.user_id,
            b.booking_type,
            b.trip_id,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.created_at AS booking_created_at,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,

            c.name AS company_name,
            bus.bus_number,
            bus.bus_type,
            r.id AS route_id,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE b.id = ?
          AND b.user_id = ?
          AND b.booking_type = 'bus'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $bookingId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $booking;
}

function fetch_booking_passengers(mysqli $conn, int $bookingId): array
{
    $sql = "
        SELECT id, full_name, phone, nrc_passport, gender, age, special_note
        FROM booking_passengers
        WHERE booking_id = ?
        ORDER BY id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_booking_seats(mysqli $conn, int $bookingId): array
{
    $sql = "
        SELECT id, seat_number, price
        FROM booking_seats
        WHERE booking_id = ?
        ORDER BY id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_existing_ticket(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT id, booking_id, ticket_no, qr_token, qr_image, pdf_file, status, used_at, created_at
        FROM tickets
        WHERE booking_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $ticket;
}
?>