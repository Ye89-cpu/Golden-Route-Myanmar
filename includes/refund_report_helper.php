<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/refund_report_helper.php

require_once __DIR__ . '/db.php';

function refund_report_table_exists(mysqli $conn, string $table): bool
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
        throw new Exception('Refund report table check failed: ' . $conn->error);
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function refund_report_default_start_date(): string
{
    return date('Y-m-01');
}

function refund_report_default_end_date(): string
{
    return date('Y-m-t');
}

function refund_report_normalize_date(string $date, string $fallback): string
{
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : $fallback;
}

function refund_report_normalize_range(string $startDate, string $endDate): array
{
    $start = refund_report_normalize_date($startDate, refund_report_default_start_date());
    $end = refund_report_normalize_date($endDate, refund_report_default_end_date());

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    return [$start, $end];
}

function refund_report_summary(mysqli $conn, string $startDate, string $endDate): array
{
    $summary = [
        'table_exists' => refund_report_table_exists($conn, 'refund_requests'),
        'total_requests' => 0,
        'pending_count' => 0,
        'approved_count' => 0,
        'rejected_count' => 0,
        'cancelled_count' => 0,
        'bus_requests' => 0,
        'tour_requests' => 0,
        'requested_amount_total' => 0.0,
        'approved_amount_total' => 0.0,
        'rejected_amount_total' => 0.0,
    ];

    if (!$summary['table_exists']) {
        return $summary;
    }

    $sql = "
        SELECT
            COUNT(*) AS total_requests,
            COALESCE(SUM(status = 'pending'), 0) AS pending_count,
            COALESCE(SUM(status = 'approved'), 0) AS approved_count,
            COALESCE(SUM(status = 'rejected'), 0) AS rejected_count,
            COALESCE(SUM(status = 'cancelled'), 0) AS cancelled_count,
            COALESCE(SUM(booking_type = 'bus'), 0) AS bus_requests,
            COALESCE(SUM(booking_type = 'tour'), 0) AS tour_requests,
            COALESCE(SUM(requested_amount), 0) AS requested_amount_total,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN requested_amount ELSE 0 END), 0) AS approved_amount_total,
            COALESCE(SUM(CASE WHEN status = 'rejected' THEN requested_amount ELSE 0 END), 0) AS rejected_amount_total
        FROM refund_requests
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Refund summary prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];

    $stmt->close();

    return array_merge($summary, $row ?: []);
}

function refund_report_rows(mysqli $conn, string $startDate, string $endDate, string $statusFilter = 'all', int $limit = 50): array
{
    if (!refund_report_table_exists($conn, 'refund_requests')) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT
            rr.*,
            b.booking_code,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            u.name AS customer_name,
            u.email AS customer_email,
            admin_user.name AS processed_by_name
        FROM refund_requests rr
        INNER JOIN bookings b ON b.id = rr.booking_id
        INNER JOIN users u ON u.id = rr.user_id
        LEFT JOIN users admin_user ON admin_user.id = rr.processed_by
        WHERE DATE(rr.created_at) BETWEEN ? AND ?
    ";

    $params = [$startDate, $endDate];
    $types = 'ss';

    if ($statusFilter !== 'all') {
        $sql .= " AND rr.status = ? ";
        $params[] = $statusFilter;
        $types .= 's';
    }

    $sql .= " ORDER BY rr.id DESC LIMIT {$limit} ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Refund report rows prepare failed: ' . $conn->error);
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