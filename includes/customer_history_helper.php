<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/customer_history_helper.php

require_once __DIR__ . '/db.php';

function customer_history_table_exists(mysqli $conn, string $table): bool
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

function customer_history_table_column_exists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Column check prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function customer_history_get_tour_batch_package_column(mysqli $conn): string
{
    if (customer_history_table_column_exists($conn, 'tour_batches', 'tour_package_id')) {
        return 'tour_package_id';
    }

    if (customer_history_table_column_exists($conn, 'tour_batches', 'package_id')) {
        return 'package_id';
    }

    return '';
}

function customer_history_has_refund_requests(mysqli $conn): bool
{
    return customer_history_table_exists($conn, 'refund_requests');
}

function customer_history_refund_select_sql(bool $hasRefundRequests): string
{
    if ($hasRefundRequests) {
        return "
            rr.id AS refund_request_id,
            rr.request_code AS refund_request_code,
            rr.status AS refund_request_status,
            rr.reason AS refund_reason,
            rr.admin_note AS refund_admin_note,
            rr.requested_amount AS refund_requested_amount,
        ";
    }

    return "
        NULL AS refund_request_id,
        NULL AS refund_request_code,
        NULL AS refund_request_status,
        NULL AS refund_reason,
        NULL AS refund_admin_note,
        NULL AS refund_requested_amount,
    ";
}

function customer_history_refund_join_sql(bool $hasRefundRequests): string
{
    if (!$hasRefundRequests) {
        return '';
    }

    return "
        LEFT JOIN (
            SELECT rr1.*
            FROM refund_requests rr1
            INNER JOIN (
                SELECT booking_id, MAX(id) AS max_id
                FROM refund_requests
                GROUP BY booking_id
            ) latest_rr ON latest_rr.max_id = rr1.id
        ) rr ON rr.booking_id = b.id
    ";
}

function customer_history_badge_class(string $status): string
{
    switch ($status) {
        case 'paid':
        case 'confirmed':
        case 'completed':
        case 'verified':
        case 'valid':
        case 'approved':
            return 'success';

        case 'pending':
        case 'pending_review':
        case 'submitted':
            return 'warning text-dark';

        case 'rejected':
        case 'failed':
        case 'cancelled':
        case 'refunded':
            return 'danger';

        case 'used':
            return 'secondary';

        default:
            return 'secondary';
    }
}

function customer_history_format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function fetch_customer_bus_booking_history(mysqli $conn, int $userId): array
{
    $hasRefundRequests = customer_history_has_refund_requests($conn);
    $refundSelect = customer_history_refund_select_sql($hasRefundRequests);
    $refundJoin = customer_history_refund_join_sql($hasRefundRequests);

    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.booking_type,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.notes,
            COALESCE(b.booked_at, b.created_at) AS booked_at,
            b.created_at,
            b.updated_at,

            c.name AS company_name,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            bus.bus_number,
            bus.bus_type,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.status AS trip_status,

            tk.id AS ticket_id,
            tk.ticket_no,
            tk.pdf_file AS ticket_pdf_file,
            tk.status AS ticket_status,

            {$refundSelect}
            'bus' AS history_type
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN tickets tk ON tk.booking_id = b.id
        {$refundJoin}
        WHERE b.user_id = ?
          AND b.booking_type = 'bus'
        ORDER BY COALESCE(b.booked_at, b.created_at) DESC, b.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Bus history query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_customer_tour_booking_history(mysqli $conn, int $userId): array
{
    $packageColumn = customer_history_get_tour_batch_package_column($conn);
    if ($packageColumn === '') {
        return [];
    }

    $hasRefundRequests = customer_history_has_refund_requests($conn);
    $refundSelect = customer_history_refund_select_sql($hasRefundRequests);
    $refundJoin = customer_history_refund_join_sql($hasRefundRequests);

    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.booking_type,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.notes,
            COALESCE(b.booked_at, b.created_at) AS booked_at,
            b.created_at,
            b.updated_at,

            c.name AS company_name,
            tp.title AS package_title,
            tp.duration_days,
            tb.start_date,
            tb.end_date,
            tb.status AS batch_status,

            v.id AS voucher_id,
            v.voucher_code,
            v.pdf_file AS voucher_pdf_file,
            v.status AS voucher_status,

            {$refundSelect}
            'tour' AS history_type
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.{$packageColumn}
        INNER JOIN companies c ON c.id = tp.company_id
        LEFT JOIN vouchers v ON v.booking_id = b.id
        {$refundJoin}
        WHERE b.user_id = ?
          AND b.booking_type = 'tour'
        ORDER BY COALESCE(b.booked_at, b.created_at) DESC, b.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour history query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function sort_customer_history_rows(array &$rows): void
{
    usort($rows, static function (array $a, array $b): int {
        $timeA = strtotime((string)($a['booked_at'] ?? $a['created_at'] ?? 'now'));
        $timeB = strtotime((string)($b['booked_at'] ?? $b['created_at'] ?? 'now'));

        if ($timeA === $timeB) {
            return ((int)($b['booking_id'] ?? 0)) <=> ((int)($a['booking_id'] ?? 0));
        }

        return $timeB <=> $timeA;
    });
}

function fetch_customer_booking_history(mysqli $conn, int $userId): array
{
    $busRows = fetch_customer_bus_booking_history($conn, $userId);
    $tourRows = fetch_customer_tour_booking_history($conn, $userId);

    $rows = array_merge($busRows, $tourRows);
    sort_customer_history_rows($rows);

    return $rows;
}

function summarize_customer_booking_history(array $rows): array
{
    $summary = [
        'total_bookings' => 0,
        'bus_bookings' => 0,
        'tour_bookings' => 0,
        'paid_bookings' => 0,
        'pending_payment' => 0,
        'total_spent' => 0.0,
        'refund_pending' => 0,
        'refund_approved' => 0,
        'refund_rejected' => 0,
    ];

    foreach ($rows as $row) {
        $summary['total_bookings']++;

        if (($row['booking_type'] ?? '') === 'bus') {
            $summary['bus_bookings']++;
        }

        if (($row['booking_type'] ?? '') === 'tour') {
            $summary['tour_bookings']++;
        }

        if (($row['payment_status'] ?? '') === 'paid') {
            $summary['paid_bookings']++;
            $summary['total_spent'] += (float)($row['total_amount'] ?? 0);
        }

        if (in_array(($row['payment_status'] ?? ''), ['unpaid', 'failed', 'rejected'], true)) {
            $summary['pending_payment']++;
        }

        if (($row['refund_request_status'] ?? '') === 'pending') {
            $summary['refund_pending']++;
        }

        if (($row['refund_request_status'] ?? '') === 'approved') {
            $summary['refund_approved']++;
        }

        if (($row['refund_request_status'] ?? '') === 'rejected') {
            $summary['refund_rejected']++;
        }
    }

    return $summary;
}

function filter_customer_booking_history(array $rows, string $typeFilter, string $paymentFilter): array
{
    return array_values(array_filter($rows, static function (array $row) use ($typeFilter, $paymentFilter): bool {
        if ($typeFilter !== 'all' && ($row['booking_type'] ?? '') !== $typeFilter) {
            return false;
        }

        if ($paymentFilter !== 'all' && ($row['payment_status'] ?? '') !== $paymentFilter) {
            return false;
        }

        return true;
    }));
}