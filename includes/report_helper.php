<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/report_helper.php

require_once __DIR__ . '/db.php';

function report_normalize_date(string $date, string $fallback): string
{
    $ts = strtotime($date);
    return $ts ? date('Y-m-d', $ts) : $fallback;
}

function report_default_start_date(): string
{
    return date('Y-m-01');
}

function report_default_end_date(): string
{
    return date('Y-m-t');
}

function report_normalize_range(string $startDate, string $endDate): array
{
    $start = report_normalize_date($startDate, report_default_start_date());
    $end = report_normalize_date($endDate, report_default_end_date());

    if ($start > $end) {
        [$start, $end] = [$end, $start];
    }

    return [$start, $end];
}

function report_fetch_summary(mysqli $conn, string $startDate, string $endDate): array
{
    $summary = [
        'total_bookings' => 0,
        'bus_bookings' => 0,
        'tour_bookings' => 0,
        'paid_bookings' => 0,
        'pending_review_bookings' => 0,
        'refunded_bookings' => 0,
        'gross_revenue' => 0.0,
        'refunded_amount' => 0.0,

        'total_payments' => 0,
        'submitted_payments' => 0,
        'verified_payments' => 0,
        'rejected_payments' => 0,
        'verified_payment_amount' => 0.0,

        'tickets_generated' => 0,
        'vouchers_generated' => 0,

        'bus_trip_count' => 0,
        'bus_capacity' => 0,
        'bus_sold_seats' => 0,
        'bus_occupancy_percent' => 0.0,

        'tour_batch_count' => 0,
        'tour_capacity' => 0,
        'tour_sold_slots' => 0,
        'tour_utilization_percent' => 0.0,
    ];

    // Booking summary
    $bookingSql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(SUM(booking_type = 'bus'), 0) AS bus_bookings,
            COALESCE(SUM(booking_type = 'tour'), 0) AS tour_bookings,
            COALESCE(SUM(payment_status = 'paid'), 0) AS paid_bookings,
            COALESCE(SUM(payment_status = 'pending_review'), 0) AS pending_review_bookings,
            COALESCE(SUM(payment_status = 'refunded'), 0) AS refunded_bookings,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS gross_revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_amount ELSE 0 END), 0) AS refunded_amount
        FROM bookings
        WHERE DATE(COALESCE(booked_at, created_at)) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($bookingSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    $stmt->close();

    // Payment summary
    $paymentSql = "
        SELECT
            COUNT(*) AS total_payments,
            COALESCE(SUM(status = 'submitted'), 0) AS submitted_payments,
            COALESCE(SUM(status = 'verified'), 0) AS verified_payments,
            COALESCE(SUM(status = 'rejected'), 0) AS rejected_payments,
            COALESCE(SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END), 0) AS verified_payment_amount
        FROM payments
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($paymentSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    $stmt->close();

    // Tickets count
    $ticketSql = "
        SELECT COUNT(*) AS tickets_generated
        FROM tickets
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($ticketSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['tickets_generated'] = (int)$row['tickets_generated'];
    }
    $stmt->close();

    // Vouchers count
    $voucherSql = "
        SELECT COUNT(*) AS vouchers_generated
        FROM vouchers
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($voucherSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['vouchers_generated'] = (int)$row['vouchers_generated'];
    }
    $stmt->close();

    // Bus capacity + trip count
    $busCapacitySql = "
        SELECT
            COUNT(*) AS bus_trip_count,
            COALESCE(SUM(bus.total_seats), 0) AS bus_capacity
        FROM trips t
        INNER JOIN buses bus ON bus.id = t.bus_id
        WHERE t.trip_date BETWEEN ? AND ?
          AND t.status <> 'cancelled'
    ";
    $stmt = $conn->prepare($busCapacitySql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['bus_trip_count'] = (int)$row['bus_trip_count'];
        $summary['bus_capacity'] = (int)$row['bus_capacity'];
    }
    $stmt->close();

    // Bus sold seats
    $busSoldSql = "
        SELECT
            COUNT(bs.id) AS bus_sold_seats
        FROM booking_seats bs
        INNER JOIN bookings b ON b.id = bs.booking_id
        INNER JOIN trips t ON t.id = bs.trip_id
        WHERE t.trip_date BETWEEN ? AND ?
          AND b.booking_type = 'bus'
          AND b.payment_status = 'paid'
          AND b.status <> 'cancelled'
    ";
    $stmt = $conn->prepare($busSoldSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['bus_sold_seats'] = (int)$row['bus_sold_seats'];
    }
    $stmt->close();

    if ((int)$summary['bus_capacity'] > 0) {
        $summary['bus_occupancy_percent'] = round(((int)$summary['bus_sold_seats'] / (int)$summary['bus_capacity']) * 100, 2);
    }

    // Tour capacity + batch count
    $tourCapacitySql = "
        SELECT
            COUNT(*) AS tour_batch_count,
            COALESCE(SUM(capacity), 0) AS tour_capacity
        FROM tour_batches
        WHERE start_date BETWEEN ? AND ?
          AND status <> 'cancelled'
    ";
    $stmt = $conn->prepare($tourCapacitySql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['tour_batch_count'] = (int)$row['tour_batch_count'];
        $summary['tour_capacity'] = (int)$row['tour_capacity'];
    }
    $stmt->close();

    // Tour sold slots
    $tourSoldSql = "
        SELECT
            COALESCE(SUM(b.passenger_count), 0) AS tour_sold_slots
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE b.booking_type = 'tour'
          AND b.payment_status = 'paid'
          AND tb.start_date BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($tourSoldSql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['tour_sold_slots'] = (int)$row['tour_sold_slots'];
    }
    $stmt->close();

    if ((int)$summary['tour_capacity'] > 0) {
        $summary['tour_utilization_percent'] = round(((int)$summary['tour_sold_slots'] / (int)$summary['tour_capacity']) * 100, 2);
    }

    return $summary;
}

function report_fetch_recent_payments(mysqli $conn, string $startDate, string $endDate, int $limit = 15): array
{
    $limit = max(1, min($limit, 50));

    $sql = "
        SELECT
            p.id,
            p.amount,
            p.payment_method,
            p.transaction_ref,
            p.status,
            p.created_at,
            b.booking_code,
            b.booking_type,
            u.name AS customer_name,
            u.email AS customer_email
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        INNER JOIN users u ON u.id = b.user_id
        WHERE DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.id DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function report_fetch_bus_route_breakdown(mysqli $conn, string $startDate, string $endDate, int $limit = 10): array
{
    $limit = max(1, min($limit, 20));

    $sql = "
        SELECT
            CONCAT(fc.name, ' → ', tc.name) AS route_name,
            c.name AS company_name,
            COUNT(DISTINCT CASE WHEN b.id IS NOT NULL THEN b.id END) AS booking_count,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_booking_rows,
            COALESCE(COUNT(CASE WHEN b.payment_status = 'paid' THEN bs.id END), 0) AS seats_sold,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN bs.price ELSE 0 END), 0) AS revenue
        FROM trips t
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN bookings b
            ON b.trip_id = t.id
           AND b.booking_type = 'bus'
        LEFT JOIN booking_seats bs
            ON bs.booking_id = b.id
        WHERE t.trip_date BETWEEN ? AND ?
        GROUP BY t.route_id, c.id, fc.name, tc.name, c.name
        ORDER BY revenue DESC, booking_count DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function report_fetch_tour_package_breakdown(mysqli $conn, string $startDate, string $endDate, int $limit = 10): array
{
    $limit = max(1, min($limit, 20));

    $sql = "
        SELECT
            tp.title AS package_title,
            c.name AS company_name,
            COUNT(DISTINCT CASE WHEN b.id IS NOT NULL THEN b.id END) AS booking_count,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.passenger_count ELSE 0 END), 0) AS passengers,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS revenue
        FROM tour_packages tp
        INNER JOIN companies c ON c.id = tp.company_id
        LEFT JOIN tour_batches tb ON tb.tour_package_id = tp.id
        LEFT JOIN bookings b
            ON b.tour_batch_id = tb.id
           AND b.booking_type = 'tour'
        WHERE tb.start_date BETWEEN ? AND ?
        GROUP BY tp.id, tp.title, c.name
        ORDER BY revenue DESC, booking_count DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function report_payment_badge_class(string $status): string
{
    switch ($status) {
        case 'verified':
            return 'success';
        case 'submitted':
            return 'warning text-dark';
        case 'rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}
function report_fetch_tour_company_summary(mysqli $conn, int $companyId, string $startDate, string $endDate): array
{
    $summary = [
        'total_bookings' => 0,
        'paid_bookings' => 0,
        'pending_review_bookings' => 0,
        'cancelled_bookings' => 0,
        'refunded_bookings' => 0,
        'gross_revenue' => 0.0,
        'refunded_amount' => 0.0,
        'total_payments' => 0,
        'submitted_payments' => 0,
        'verified_payments' => 0,
        'rejected_payments' => 0,
        'verified_payment_amount' => 0.0,
        'tour_batch_count' => 0,
        'tour_capacity' => 0,
        'tour_sold_slots' => 0,
        'tour_utilization_percent' => 0.0,
    ];

    $bookingSql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(SUM(b.payment_status = 'paid'), 0) AS paid_bookings,
            COALESCE(SUM(b.payment_status = 'pending_review'), 0) AS pending_review_bookings,
            COALESCE(SUM(b.status = 'cancelled'), 0) AS cancelled_bookings,
            COALESCE(SUM(b.payment_status = 'refunded'), 0) AS refunded_bookings,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS gross_revenue,
            COALESCE(SUM(CASE WHEN b.payment_status = 'refunded' THEN b.total_amount ELSE 0 END), 0) AS refunded_amount
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($bookingSql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    $stmt->close();

    $paymentSql = "
        SELECT
            COUNT(*) AS total_payments,
            COALESCE(SUM(p.status = 'submitted'), 0) AS submitted_payments,
            COALESCE(SUM(p.status = 'verified'), 0) AS verified_payments,
            COALESCE(SUM(p.status = 'rejected'), 0) AS rejected_payments,
            COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END), 0) AS verified_payment_amount
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
          AND DATE(p.created_at) BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($paymentSql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary = array_merge($summary, $row);
    }
    $stmt->close();

    $capacitySql = "
        SELECT
            COUNT(*) AS tour_batch_count,
            COALESCE(SUM(capacity), 0) AS tour_capacity
        FROM tour_batches
        WHERE company_id = ?
          AND start_date BETWEEN ? AND ?
          AND status <> 'cancelled'
    ";
    $stmt = $conn->prepare($capacitySql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['tour_batch_count'] = (int)($row['tour_batch_count'] ?? 0);
        $summary['tour_capacity'] = (int)($row['tour_capacity'] ?? 0);
    }
    $stmt->close();

    $soldSql = "
        SELECT
            COALESCE(SUM(b.passenger_count), 0) AS tour_sold_slots
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE b.booking_type = 'tour'
          AND b.payment_status = 'paid'
          AND tb.company_id = ?
          AND tb.start_date BETWEEN ? AND ?
    ";
    $stmt = $conn->prepare($soldSql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $summary['tour_sold_slots'] = (int)($row['tour_sold_slots'] ?? 0);
    }
    $stmt->close();

    if ((int)$summary['tour_capacity'] > 0) {
        $summary['tour_utilization_percent'] = round(((int)$summary['tour_sold_slots'] / (int)$summary['tour_capacity']) * 100, 2);
    }

    return $summary;
}

function report_fetch_tour_company_package_breakdown(mysqli $conn, int $companyId, string $startDate, string $endDate, int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $sql = "
        SELECT
            tp.title AS package_title,
            COUNT(DISTINCT b.id) AS booking_count,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.passenger_count ELSE 0 END), 0) AS passengers,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS revenue,
            COALESCE(SUM(CASE WHEN b.payment_status = 'pending_review' THEN 1 ELSE 0 END), 0) AS pending_review
        FROM tour_packages tp
        LEFT JOIN tour_batches tb ON tb.tour_package_id = tp.id
        LEFT JOIN bookings b
            ON b.tour_batch_id = tb.id
           AND b.booking_type = 'tour'
           AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
        WHERE tp.company_id = ?
        GROUP BY tp.id, tp.title
        ORDER BY revenue DESC, booking_count DESC, tp.title ASC
        LIMIT {$limit}
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $startDate, $endDate, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function report_fetch_tour_company_recent_bookings(mysqli $conn, int $companyId, string $startDate, string $endDate, int $limit = 100): array
{
    $limit = max(1, min($limit, 200));
    $sql = "
        SELECT
            b.id,
            b.booking_code,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            COALESCE(b.booked_at, b.created_at) AS booked_at,
            u.name AS customer_name,
            u.email AS customer_email,
            tp.title AS package_title,
            tb.start_date,
            tb.end_date
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
        ORDER BY b.id DESC
        LIMIT {$limit}
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

function report_fetch_tour_company_recent_payments(mysqli $conn, int $companyId, string $startDate, string $endDate, int $limit = 50): array
{
    $limit = max(1, min($limit, 100));
    $sql = "
        SELECT
            p.id,
            p.amount,
            p.payment_method,
            p.transaction_ref,
            p.status,
            p.created_at,
            b.booking_code,
            u.name AS customer_name,
            u.email AS customer_email,
            tp.title AS package_title
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
          AND DATE(p.created_at) BETWEEN ? AND ?
        ORDER BY p.id DESC
        LIMIT {$limit}
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $companyId, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}
