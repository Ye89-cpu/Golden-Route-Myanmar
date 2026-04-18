<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/boarding_helper.php

require_once __DIR__ . '/db.php';

function boarding_table_exists(mysqli $conn, string $table): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Boarding table check failed: ' . $conn->error);
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function boarding_has_refund_requests(mysqli $conn): bool
{
    return boarding_table_exists($conn, 'refund_requests');
}

function boarding_status_badge_class(string $status): string
{
    switch ($status) {
        case 'paid':
        case 'confirmed':
        case 'completed':
        case 'verified':
        case 'valid':
        case 'used':
        case 'approved':
        case 'open':
            return 'success';

        case 'pending':
        case 'pending_review':
        case 'submitted':
        case 'scheduled':
            return 'warning text-dark';

        case 'rejected':
        case 'failed':
        case 'cancelled':
        case 'refunded':
        case 'full':
        case 'departed':
            return 'danger';

        default:
            return 'secondary';
    }
}

function boarding_format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function boarding_can_mark_used(array $ticket): bool
{
    if (!$ticket) {
        return false;
    }

    if (($ticket['booking_status'] ?? '') !== 'paid') {
        return false;
    }

    if (($ticket['payment_status'] ?? '') !== 'paid') {
        return false;
    }

    if (($ticket['ticket_status'] ?? '') === 'cancelled') {
        return false;
    }

    if (($ticket['ticket_status'] ?? '') === 'used') {
        return false;
    }

    if (!empty($ticket['used_at'])) {
        return false;
    }

    return true;
}

function fetch_bus_admin_trip_boarding_list(mysqli $conn, int $companyId, array $filters = []): array
{
    $tripDate = trim($filters['trip_date'] ?? '');
    $tripStatus = trim($filters['trip_status'] ?? 'all');

    $sql = "
        SELECT
            t.id AS trip_id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status AS trip_status,
            t.available_seats,

            bus.bus_number,
            bus.bus_type,
            bus.total_seats,
            fc.name AS from_city_name,
            tc.name AS to_city_name,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'bus'
                THEN b.id
                ELSE NULL
            END) AS total_booking_rows,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.id
                ELSE NULL
            END) AS paid_bookings,

            COALESCE(SUM(CASE
                WHEN b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.passenger_count
                ELSE 0
            END), 0) AS paid_passengers,

            COUNT(DISTINCT CASE
                WHEN tk.id IS NOT NULL
                 AND b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN tk.id
                ELSE NULL
            END) AS issued_tickets,

            COUNT(DISTINCT CASE
                WHEN tk.status = 'used'
                 AND b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN tk.id
                ELSE NULL
            END) AS boarded_tickets
        FROM trips t
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN bookings b
            ON b.trip_id = t.id
           AND b.booking_type = 'bus'
        LEFT JOIN tickets tk
            ON tk.booking_id = b.id
        WHERE t.company_id = ?
    ";

    $params = [$companyId];
    $types = 'i';

    if ($tripDate !== '') {
        $sql .= " AND t.trip_date = ? ";
        $params[] = $tripDate;
        $types .= 's';
    }

    if ($tripStatus !== '' && $tripStatus !== 'all') {
        $sql .= " AND t.status = ? ";
        $params[] = $tripStatus;
        $types .= 's';
    }

    $sql .= "
        GROUP BY
            t.id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status,
            t.available_seats,
            bus.bus_number,
            bus.bus_type,
            bus.total_seats,
            fc.name,
            tc.name
        ORDER BY t.trip_date DESC, t.departure_datetime DESC, t.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Trip boarding list query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_bus_admin_trip_boarding_detail(mysqli $conn, int $companyId, int $tripId): ?array
{
    $sql = "
        SELECT
            t.id AS trip_id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status AS trip_status,
            t.price AS trip_price,
            t.available_seats,

            bus.id AS bus_id,
            bus.bus_number,
            bus.plate_number,
            bus.bus_type,
            bus.total_seats,
            bus.layout_type,

            c.name AS company_name,
            fc.name AS from_city_name,
            tc.name AS to_city_name,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'bus'
                THEN b.id
                ELSE NULL
            END) AS total_booking_rows,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.id
                ELSE NULL
            END) AS paid_bookings,

            COALESCE(SUM(CASE
                WHEN b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.passenger_count
                ELSE 0
            END), 0) AS paid_passengers,

            COUNT(DISTINCT CASE
                WHEN tk.id IS NOT NULL
                 AND b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN tk.id
                ELSE NULL
            END) AS issued_tickets,

            COUNT(DISTINCT CASE
                WHEN tk.status = 'used'
                 AND b.booking_type = 'bus'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN tk.id
                ELSE NULL
            END) AS boarded_tickets
        FROM trips t
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN bookings b
            ON b.trip_id = t.id
           AND b.booking_type = 'bus'
        LEFT JOIN tickets tk
            ON tk.booking_id = b.id
        WHERE t.id = ?
          AND t.company_id = ?
        GROUP BY
            t.id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status,
            t.price,
            t.available_seats,
            bus.id,
            bus.bus_number,
            bus.plate_number,
            bus.bus_type,
            bus.total_seats,
            bus.layout_type,
            c.name,
            fc.name,
            tc.name
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Trip boarding detail query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $tripId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_bus_admin_trip_manifest_rows(mysqli $conn, int $companyId, int $tripId): array
{
    $hasRefundRequests = boarding_has_refund_requests($conn);

    $refundSelect = $hasRefundRequests ? "
        rr.id AS refund_request_id,
        rr.request_code AS refund_request_code,
        rr.status AS refund_request_status,
    " : "
        NULL AS refund_request_id,
        NULL AS refund_request_code,
        NULL AS refund_request_status,
    ";

    $refundJoin = $hasRefundRequests ? "
        LEFT JOIN (
            SELECT rr1.*
            FROM refund_requests rr1
            INNER JOIN (
                SELECT booking_id, MAX(id) AS max_id
                FROM refund_requests
                GROUP BY booking_id
            ) latest_rr ON latest_rr.max_id = rr1.id
        ) rr ON rr.booking_id = b.id
    " : "";

    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            COALESCE(b.booked_at, b.created_at) AS booked_at,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            tk.id AS ticket_id,
            tk.ticket_no,
            tk.qr_token,
            tk.status AS ticket_status,
            tk.used_at,
            tk.pdf_file AS ticket_pdf_file,

            {$refundSelect}
            GROUP_CONCAT(DISTINCT bp.full_name ORDER BY bp.id SEPARATOR ', ') AS passenger_names,
            GROUP_CONCAT(DISTINCT bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') AS seat_numbers
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN tickets tk ON tk.booking_id = b.id
        LEFT JOIN booking_passengers bp ON bp.booking_id = b.id
        LEFT JOIN booking_seats bs ON bs.booking_id = b.id
        {$refundJoin}
        WHERE b.trip_id = ?
          AND b.booking_type = 'bus'
          AND t.company_id = ?
        GROUP BY
            b.id,
            b.booking_code,
            b.passenger_count,
            b.total_amount,
            b.status,
            b.payment_status,
            booked_at,
            u.name,
            u.email,
            u.phone,
            tk.id,
            tk.ticket_no,
            tk.qr_token,
            tk.status,
            tk.used_at,
            tk.pdf_file
    ";

    if ($hasRefundRequests) {
        $sql .= ", rr.id, rr.request_code, rr.status ";
    }

    $sql .= " ORDER BY b.id ASC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Trip manifest query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $tripId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function find_trip_ticket_for_company(mysqli $conn, int $companyId, int $tripId, string $searchValue, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            tk.id AS ticket_id,
            tk.booking_id,
            tk.trip_id,
            tk.ticket_no,
            tk.qr_token,
            tk.qr_image,
            tk.pdf_file,
            tk.status AS ticket_status,
            tk.used_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.passenger_count,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            c.name AS company_name
        FROM tickets tk
        INNER JOIN bookings b ON b.id = tk.booking_id
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN trips t ON t.id = tk.trip_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.company_id = ?
          AND tk.trip_id = ?
          AND (tk.ticket_no = ? OR tk.qr_token = ?)
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE ";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Find trip ticket query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('iiss', $companyId, $tripId, $searchValue, $searchValue);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function find_trip_ticket_by_id_for_company(mysqli $conn, int $companyId, int $tripId, int $ticketId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            tk.id AS ticket_id,
            tk.booking_id,
            tk.trip_id,
            tk.ticket_no,
            tk.qr_token,
            tk.qr_image,
            tk.pdf_file,
            tk.status AS ticket_status,
            tk.used_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.passenger_count,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            c.name AS company_name
        FROM tickets tk
        INNER JOIN bookings b ON b.id = tk.booking_id
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN trips t ON t.id = tk.trip_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.company_id = ?
          AND tk.trip_id = ?
          AND tk.id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE ";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Find trip ticket by ID query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('iii', $companyId, $tripId, $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function mark_trip_ticket_used(mysqli $conn, int $companyId, int $tripId, int $ticketId, int $actorUserId): array
{
    $ticket = find_trip_ticket_by_id_for_company($conn, $companyId, $tripId, $ticketId, true);

    if (!$ticket) {
        throw new Exception('Ticket not found for this trip/company.');
    }

    if (($ticket['ticket_status'] ?? '') === 'cancelled') {
        throw new Exception('This ticket has been cancelled.');
    }

    if (($ticket['booking_status'] ?? '') !== 'paid' || ($ticket['payment_status'] ?? '') !== 'paid') {
        throw new Exception('This ticket is not eligible for boarding because payment is not verified.');
    }

    if (($ticket['ticket_status'] ?? '') === 'used' || !empty($ticket['used_at'])) {
        throw new Exception('This ticket has already been used.');
    }

    $updateSql = "
        UPDATE tickets
        SET status = 'used',
            used_at = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare ticket update: ' . $conn->error);
    }

    $stmt->bind_param('i', $ticketId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to mark ticket as used.');
    }
    $stmt->close();

    $action = 'trip_boarding_checkin';
    $entityType = 'ticket';
    $description = 'Boarded ticket ' . $ticket['ticket_no'] . ' for trip #' . $tripId;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $actorUserId, $action, $entityType, $ticketId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    return find_trip_ticket_by_id_for_company($conn, $companyId, $tripId, $ticketId, false) ?? $ticket;
}