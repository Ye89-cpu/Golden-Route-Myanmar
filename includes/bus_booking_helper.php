<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/bus_booking_helper.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/company_helper.php';

function bus_booking_table_exists(mysqli $conn, string $table): bool
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
        throw new Exception('Table check prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function bus_booking_has_refund_requests(mysqli $conn): bool
{
    return bus_booking_table_exists($conn, 'refund_requests');
}

function bus_booking_badge_class(string $status): string
{
    switch ($status) {
        case 'paid':
        case 'confirmed':
        case 'completed':
        case 'verified':
        case 'valid':
        case 'approved':
        case 'open':
            return 'success';

        case 'pending':
        case 'pending_review':
        case 'submitted':
            return 'warning text-dark';

        case 'rejected':
        case 'failed':
        case 'cancelled':
        case 'refunded':
        case 'full':
            return 'danger';

        case 'used':
        case 'inactive':
            return 'secondary';

        default:
            return 'secondary';
    }
}

function bus_booking_format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function bus_booking_company_scope_ids(mysqli $conn, int $companyId): array
{
    if (function_exists('get_related_bus_company_ids')) {
        $ids = get_related_bus_company_ids($conn, $companyId);
    } else {
        $ids = [$companyId];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    if (empty($ids) && $companyId > 0) {
        $ids = [$companyId];
    }

    return $ids;
}

function bus_booking_scope_sql(array $companyIds, array &$params, string &$types): string
{
    if (empty($companyIds)) {
        $companyIds = [0];
    }

    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    foreach ([$companyIds, $companyIds, $companyIds] as $ids) {
        foreach ($ids as $id) {
            $params[] = (int)$id;
            $types .= 'i';
        }
    }

    return "(
        t.company_id IN ($placeholders)
        OR bus.company_id IN ($placeholders)
        OR r.company_id IN ($placeholders)
    )";
}

function fetch_bus_admin_booking_summary(mysqli $conn, int $companyId): array
{
    $companyIds = bus_booking_company_scope_ids($conn, $companyId);
    $params = [];
    $types = '';
    $scopeSql = bus_booking_scope_sql($companyIds, $params, $types);

    $sql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(SUM(b.payment_status = 'paid'), 0) AS paid_bookings,
            COALESCE(SUM(b.payment_status = 'pending_review'), 0) AS pending_review_bookings,
            COALESCE(SUM(b.status = 'cancelled'), 0) AS cancelled_bookings,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS paid_amount
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        WHERE b.booking_type = 'bus'
          AND {$scopeSql}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Summary query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];
    $stmt->close();

    return array_merge([
        'total_bookings' => 0,
        'paid_bookings' => 0,
        'pending_review_bookings' => 0,
        'cancelled_bookings' => 0,
        'paid_amount' => 0.0,
    ], $row ?: []);
}

function fetch_bus_admin_bookings(mysqli $conn, int $companyId, array $filters = []): array
{
    $hasRefundRequests = bus_booking_has_refund_requests($conn);

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

    $companyIds = bus_booking_company_scope_ids($conn, $companyId);
    $params = [];
    $types = '';
    $scopeSql = bus_booking_scope_sql($companyIds, $params, $types);

    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            COALESCE(b.booked_at, b.created_at) AS booked_at,

            t.id AS trip_id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status AS trip_status,

            bus.bus_number,
            bus.bus_type,
            c.name AS company_name,
            fc.name AS from_city_name,
            tc.name AS to_city_name,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            tk.id AS ticket_id,
            tk.ticket_no,
            tk.pdf_file AS ticket_pdf_file,
            tk.status AS ticket_status,

            p.id AS latest_payment_id,
            p.amount AS latest_payment_amount,
            p.payment_method AS latest_payment_method,
            p.transaction_ref AS latest_payment_ref,
            p.screenshot_path AS latest_payment_screenshot_path,
            p.status AS latest_payment_status,
            p.created_at AS latest_payment_created_at,

            {$refundSelect}
            COUNT(DISTINCT bp.id) AS passenger_rows,
            COUNT(DISTINCT bs.id) AS seat_rows
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN tickets tk ON tk.booking_id = b.id
        LEFT JOIN payments p ON p.id = (
            SELECT p2.id
            FROM payments p2
            WHERE p2.booking_id = b.id
            ORDER BY p2.id DESC
            LIMIT 1
        )
        LEFT JOIN booking_passengers bp ON bp.booking_id = b.id
        LEFT JOIN booking_seats bs ON bs.booking_id = b.id
        {$refundJoin}
        WHERE b.booking_type = 'bus'
          AND {$scopeSql}
    ";

    if (!empty($filters['trip_date'])) {
        $sql .= " AND t.trip_date = ? ";
        $params[] = $filters['trip_date'];
        $types .= 's';
    }

    if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'all') {
        $sql .= " AND b.payment_status = ? ";
        $params[] = $filters['payment_status'];
        $types .= 's';
    }

    if (!empty($filters['booking_status']) && $filters['booking_status'] !== 'all') {
        $sql .= " AND b.status = ? ";
        $params[] = $filters['booking_status'];
        $types .= 's';
    }

    $sql .= "
        GROUP BY
            b.id, b.booking_code, b.passenger_count, b.total_amount, b.status, b.payment_status, booked_at,
            t.id, t.trip_date, t.departure_datetime, t.arrival_datetime, t.status,
            bus.bus_number, bus.bus_type, c.name, fc.name, tc.name,
            u.name, u.email, u.phone,
            tk.id, tk.ticket_no, tk.pdf_file, tk.status,
            p.id, p.amount, p.payment_method, p.transaction_ref, p.screenshot_path, p.status, p.created_at
    ";

    if ($hasRefundRequests) {
        $sql .= ", rr.id, rr.request_code, rr.status ";
    }

    $sql .= " ORDER BY t.trip_date DESC, t.departure_datetime DESC, b.id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Booking list query prepare failed: ' . $conn->error);
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

function fetch_bus_admin_booking_detail(mysqli $conn, int $companyId, int $bookingId): ?array
{
    $hasRefundRequests = bus_booking_has_refund_requests($conn);

    $refundSelect = $hasRefundRequests ? "
        rr.id AS refund_request_id,
        rr.request_code AS refund_request_code,
        rr.status AS refund_request_status,
        rr.reason AS refund_reason,
        rr.admin_note AS refund_admin_note,
        rr.requested_amount AS refund_requested_amount,
    " : "
        NULL AS refund_request_id,
        NULL AS refund_request_code,
        NULL AS refund_request_status,
        NULL AS refund_reason,
        NULL AS refund_admin_note,
        NULL AS refund_requested_amount,
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

    $companyIds = bus_booking_company_scope_ids($conn, $companyId);
    $params = [$bookingId];
    $types = 'i';
    $scopeSql = bus_booking_scope_sql($companyIds, $params, $types);

    $sql = "
        SELECT
            b.*,
            COALESCE(b.booked_at, b.created_at) AS booking_datetime,

            t.id AS trip_id,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.price AS trip_price,
            t.available_seats,
            t.status AS trip_status,

            bus.id AS bus_id,
            bus.bus_number,
            bus.plate_number,
            bus.bus_type,
            bus.total_seats,
            bus.layout_type,

            c.id AS company_id,
            c.name AS company_name,
            c.phone AS company_phone,
            c.email AS company_email,

            fc.name AS from_city_name,
            tc.name AS to_city_name,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            tk.id AS ticket_id,
            tk.ticket_no,
            tk.qr_token,
            tk.pdf_file AS ticket_pdf_file,
            tk.status AS ticket_status,
            tk.used_at AS ticket_used_at,

            {$refundSelect}
            p.id AS latest_payment_id,
            p.amount AS latest_payment_amount,
            p.payment_method AS latest_payment_method,
            p.transaction_ref AS latest_payment_ref,
            p.screenshot_path AS latest_payment_screenshot_path,
            p.status AS latest_payment_status,
            p.created_at AS latest_payment_created_at
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN tickets tk ON tk.booking_id = b.id
        LEFT JOIN payments p ON p.id = (
            SELECT p2.id
            FROM payments p2
            WHERE p2.booking_id = b.id
            ORDER BY p2.id DESC
            LIMIT 1
        )
        {$refundJoin}
        WHERE b.id = ?
          AND b.booking_type = 'bus'
          AND {$scopeSql}
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Booking detail query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_bus_admin_booking_passengers(mysqli $conn, int $bookingId): array
{
    $sql = "
        SELECT
            id,
            full_name,
            phone,
            nrc_passport,
            gender,
            age,
            special_note,
            created_at
        FROM booking_passengers
        WHERE booking_id = ?
        ORDER BY id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Passenger query prepare failed: ' . $conn->error);
    }

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

function fetch_bus_admin_booking_seats(mysqli $conn, int $bookingId): array
{
    $sql = "
        SELECT
            bs.id,
            bs.trip_id,
            bs.bus_seat_id,
            bs.seat_number,
            bs.price,
            busseat.row_no,
            busseat.col_no,
            busseat.seat_type
        FROM booking_seats bs
        LEFT JOIN bus_seats busseat ON busseat.id = bs.bus_seat_id
        WHERE bs.booking_id = ?
        ORDER BY bs.seat_number ASC, bs.id ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Seat query prepare failed: ' . $conn->error);
    }

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
