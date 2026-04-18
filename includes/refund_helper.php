<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/refund_helper.php

require_once __DIR__ . '/db.php';

function refund_status_badge_class(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'success';
        case 'pending':
            return 'warning text-dark';
        case 'rejected':
            return 'danger';
        case 'cancelled':
        default:
            return 'secondary';
    }
}

function refund_format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function generate_refund_request_code(): string
{
    return 'RFD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function fetch_customer_refundable_booking(mysqli $conn, int $bookingId, int $userId): ?array
{
    $sql = "
        SELECT
            b.id,
            b.booking_code,
            b.user_id,
            b.booking_type,
            b.trip_id,
            b.tour_batch_id,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.booked_at,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status AS trip_status,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            bc.name AS bus_company_name,

            tb.start_date AS tour_start_date,
            tb.end_date AS tour_end_date,
            tb.status AS batch_status,
            tp.title AS package_title,
            tc2.name AS tour_company_name
        FROM bookings b
        LEFT JOIN trips t ON t.id = b.trip_id
        LEFT JOIN buses bus ON bus.id = t.bus_id
        LEFT JOIN routes r ON r.id = t.route_id
        LEFT JOIN cities fc ON fc.id = r.from_city_id
        LEFT JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN companies bc ON bc.id = t.company_id

        LEFT JOIN tour_batches tb ON tb.id = b.tour_batch_id
        LEFT JOIN tour_packages tp ON tp.id = tb.tour_package_id
        LEFT JOIN companies tc2 ON tc2.id = tp.company_id
        WHERE b.id = ?
          AND b.user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare booking query: ' . $conn->error);
    }

    $stmt->bind_param('ii', $bookingId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function fetch_latest_refund_request_by_booking(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT *
        FROM refund_requests
        WHERE booking_id = ?
        ORDER BY id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare refund request lookup: ' . $conn->error);
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function refund_request_block_reason(array $booking, ?array $existingRequest = null): ?string
{
    if (!$booking) {
        return 'Booking not found.';
    }

    if ($booking['payment_status'] !== 'paid' || $booking['booking_status'] !== 'paid') {
        return 'Only paid bookings can request refund.';
    }

    if ($booking['booking_type'] === 'bus') {
        if (in_array((string)($booking['trip_status'] ?? ''), ['departed', 'completed', 'cancelled'], true)) {
            return 'This bus trip is no longer refundable.';
        }
    }

    if ($booking['booking_type'] === 'tour') {
        if (in_array((string)($booking['batch_status'] ?? ''), ['closed', 'cancelled'], true)) {
            return 'This tour batch is no longer refundable.';
        }
    }

    if ($existingRequest && in_array((string)$existingRequest['status'], ['pending', 'approved'], true)) {
        return 'A refund request already exists for this booking.';
    }

    return null;
}

function fetch_all_refund_requests(mysqli $conn, string $statusFilter = 'all'): array
{
    $sql = "
        SELECT
            rr.*,
            b.booking_code,
            b.booking_type,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            u.name AS customer_name,
            u.email AS customer_email
        FROM refund_requests rr
        INNER JOIN bookings b ON b.id = rr.booking_id
        INNER JOIN users u ON u.id = rr.user_id
    ";

    $params = [];
    $types = '';

    if ($statusFilter !== 'all') {
        $sql .= " WHERE rr.status = ? ";
        $params[] = $statusFilter;
        $types .= 's';
    }

    $sql .= " ORDER BY rr.id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare refund list query: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_refund_request_for_admin_update(mysqli $conn, int $refundRequestId): ?array
{
    $sql = "
        SELECT
            rr.*,
            b.booking_code,
            b.booking_type,
            b.trip_id,
            b.tour_batch_id,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status
        FROM refund_requests rr
        INNER JOIN bookings b ON b.id = rr.booking_id
        WHERE rr.id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare admin refund query: ' . $conn->error);
    }

    $stmt->bind_param('i', $refundRequestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}