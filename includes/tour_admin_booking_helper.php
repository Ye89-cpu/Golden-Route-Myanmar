<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/tour_admin_booking_helper.php

require_once __DIR__ . '/db.php';

function tour_admin_table_exists(mysqli $conn, string $table): bool
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

function tour_admin_has_refund_requests(mysqli $conn): bool
{
    return tour_admin_table_exists($conn, 'refund_requests');
}

function tour_admin_badge_class(string $status): string
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
        case 'active':
            return 'success';

        case 'pending':
        case 'pending_review':
        case 'submitted':
            return 'warning text-dark';

        case 'rejected':
        case 'failed':
        case 'cancelled':
        case 'refunded':
        case 'closed':
        case 'full':
            return 'danger';

        default:
            return 'secondary';
    }
}

function tour_admin_format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function tour_admin_can_mark_voucher_used(array $voucher): bool
{
    if (!$voucher) {
        return false;
    }

    if (($voucher['booking_status'] ?? '') !== 'paid') {
        return false;
    }

    if (($voucher['payment_status'] ?? '') !== 'paid') {
        return false;
    }

    if (($voucher['voucher_status'] ?? '') === 'cancelled') {
        return false;
    }

    if (($voucher['voucher_status'] ?? '') === 'used') {
        return false;
    }

    if (!empty($voucher['used_at'])) {
        return false;
    }

    if (($voucher['batch_status'] ?? '') === 'cancelled') {
        return false;
    }

    return true;
}

function fetch_tour_admin_booking_summary(mysqli $conn, int $companyId): array
{
    $sql = "
        SELECT
            COUNT(*) AS total_bookings,
            COALESCE(SUM(b.payment_status = 'paid'), 0) AS paid_bookings,
            COALESCE(SUM(b.payment_status = 'pending_review'), 0) AS pending_review_bookings,
            COALESCE(SUM(b.status = 'cancelled'), 0) AS cancelled_bookings,
            COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.total_amount ELSE 0 END), 0) AS paid_amount
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour summary query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $companyId);
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

function fetch_tour_admin_bookings(mysqli $conn, int $companyId, array $filters = []): array
{
    $hasRefundRequests = tour_admin_has_refund_requests($conn);

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

            tb.id AS batch_id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price AS batch_price,
            tb.status AS batch_status,

            tp.title AS package_title,
            tp.duration_days,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            v.id AS voucher_id,
            v.voucher_code,
            v.pdf_file AS voucher_pdf_file,
            v.status AS voucher_status,
            v.used_at,

            {$refundSelect}
            COUNT(DISTINCT bp.id) AS passenger_rows
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN vouchers v ON v.booking_id = b.id
        LEFT JOIN booking_passengers bp ON bp.booking_id = b.id
        {$refundJoin}
        WHERE b.booking_type = 'tour'
          AND tb.company_id = ?
    ";

    $params = [$companyId];
    $types = 'i';

    if (!empty($filters['start_date'])) {
        $sql .= " AND tb.start_date = ? ";
        $params[] = $filters['start_date'];
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
            tb.id, tb.start_date, tb.end_date, tb.capacity, tb.booked_count, tb.price, tb.status,
            tp.title, tp.duration_days,
            u.name, u.email, u.phone,
            v.id, v.voucher_code, v.pdf_file, v.status, v.used_at
    ";

    if ($hasRefundRequests) {
        $sql .= ", rr.id, rr.request_code, rr.status ";
    }

    $sql .= " ORDER BY tb.start_date DESC, b.id DESC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour booking list query prepare failed: ' . $conn->error);
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

function fetch_tour_admin_booking_detail(mysqli $conn, int $companyId, int $bookingId): ?array
{
    $hasRefundRequests = tour_admin_has_refund_requests($conn);

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

    $sql = "
        SELECT
            b.*,
            COALESCE(b.booked_at, b.created_at) AS booking_datetime,

            tb.id AS batch_id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price AS batch_price,
            tb.status AS batch_status,

            tp.id AS package_id,
            tp.title AS package_title,
            tp.description,
            tp.duration_days,
            tp.hotel_info,
            tp.transport_info,
            tp.route_info,
            tp.itinerary,
            tp.included_services,
            tp.excluded_services,

            c.id AS company_id,
            c.name AS company_name,
            c.phone AS company_phone,
            c.email AS company_email,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            v.id AS voucher_id,
            v.voucher_code,
            v.qr_token,
            v.pdf_file AS voucher_pdf_file,
            v.status AS voucher_status,
            v.used_at,

            {$refundSelect}
            p.id AS latest_payment_id,
            p.amount AS latest_payment_amount,
            p.payment_method AS latest_payment_method,
            p.transaction_ref AS latest_payment_ref,
            p.status AS latest_payment_status,
            p.created_at AS latest_payment_created_at
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN companies c ON c.id = tb.company_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN vouchers v ON v.booking_id = b.id
        LEFT JOIN payments p ON p.id = (
            SELECT p2.id
            FROM payments p2
            WHERE p2.booking_id = b.id
            ORDER BY p2.id DESC
            LIMIT 1
        )
        {$refundJoin}
        WHERE b.id = ?
          AND b.booking_type = 'tour'
          AND tb.company_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour booking detail query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $bookingId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_tour_admin_booking_passengers(mysqli $conn, int $bookingId): array
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
        throw new Exception('Tour passenger query prepare failed: ' . $conn->error);
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

function fetch_tour_admin_batch_checkin_list(mysqli $conn, int $companyId, array $filters = []): array
{
    $startDate = trim($filters['start_date'] ?? '');
    $batchStatus = trim($filters['batch_status'] ?? 'all');

    $sql = "
        SELECT
            tb.id AS batch_id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price,
            tb.status AS batch_status,

            tp.title AS package_title,
            tp.duration_days,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'tour'
                THEN b.id
                ELSE NULL
            END) AS total_booking_rows,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.id
                ELSE NULL
            END) AS paid_bookings,

            COALESCE(SUM(CASE
                WHEN b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.passenger_count
                ELSE 0
            END), 0) AS paid_passengers,

            COUNT(DISTINCT CASE
                WHEN v.id IS NOT NULL
                 AND b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN v.id
                ELSE NULL
            END) AS issued_vouchers,

            COUNT(DISTINCT CASE
                WHEN v.status = 'used'
                 AND b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN v.id
                ELSE NULL
            END) AS checked_in_vouchers
        FROM tour_batches tb
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        LEFT JOIN bookings b
            ON b.tour_batch_id = tb.id
           AND b.booking_type = 'tour'
        LEFT JOIN vouchers v
            ON v.booking_id = b.id
        WHERE tb.company_id = ?
    ";

    $params = [$companyId];
    $types = 'i';

    if ($startDate !== '') {
        $sql .= " AND tb.start_date = ? ";
        $params[] = $startDate;
        $types .= 's';
    }

    if ($batchStatus !== '' && $batchStatus !== 'all') {
        $sql .= " AND tb.status = ? ";
        $params[] = $batchStatus;
        $types .= 's';
    }

    $sql .= "
        GROUP BY
            tb.id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price,
            tb.status,
            tp.title,
            tp.duration_days
        ORDER BY tb.start_date DESC, tb.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour batch check-in list query prepare failed: ' . $conn->error);
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

function fetch_tour_admin_batch_detail(mysqli $conn, int $companyId, int $batchId): ?array
{
    $sql = "
        SELECT
            tb.id AS batch_id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price,
            tb.status AS batch_status,

            tp.id AS package_id,
            tp.title AS package_title,
            tp.description,
            tp.duration_days,
            tp.hotel_info,
            tp.transport_info,
            tp.route_info,
            tp.itinerary,
            tp.included_services,
            tp.excluded_services,

            c.name AS company_name,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'tour'
                THEN b.id
                ELSE NULL
            END) AS total_booking_rows,

            COUNT(DISTINCT CASE
                WHEN b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.id
                ELSE NULL
            END) AS paid_bookings,

            COALESCE(SUM(CASE
                WHEN b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN b.passenger_count
                ELSE 0
            END), 0) AS paid_passengers,

            COUNT(DISTINCT CASE
                WHEN v.id IS NOT NULL
                 AND b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN v.id
                ELSE NULL
            END) AS issued_vouchers,

            COUNT(DISTINCT CASE
                WHEN v.status = 'used'
                 AND b.booking_type = 'tour'
                 AND b.payment_status = 'paid'
                 AND b.status <> 'cancelled'
                THEN v.id
                ELSE NULL
            END) AS checked_in_vouchers
        FROM tour_batches tb
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN companies c ON c.id = tb.company_id
        LEFT JOIN bookings b
            ON b.tour_batch_id = tb.id
           AND b.booking_type = 'tour'
        LEFT JOIN vouchers v
            ON v.booking_id = b.id
        WHERE tb.id = ?
          AND tb.company_id = ?
        GROUP BY
            tb.id,
            tb.start_date,
            tb.end_date,
            tb.capacity,
            tb.booked_count,
            tb.price,
            tb.status,
            tp.id,
            tp.title,
            tp.description,
            tp.duration_days,
            tp.hotel_info,
            tp.transport_info,
            tp.route_info,
            tp.itinerary,
            tp.included_services,
            tp.excluded_services,
            c.name
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour batch detail query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $batchId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_tour_admin_batch_manifest_rows(mysqli $conn, int $companyId, int $batchId): array
{
    $hasRefundRequests = tour_admin_has_refund_requests($conn);

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

            v.id AS voucher_id,
            v.voucher_code,
            v.qr_token,
            v.pdf_file AS voucher_pdf_file,
            v.status AS voucher_status,
            v.used_at,

            {$refundSelect}
            GROUP_CONCAT(DISTINCT bp.full_name ORDER BY bp.id SEPARATOR ', ') AS passenger_names
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN vouchers v ON v.booking_id = b.id
        LEFT JOIN booking_passengers bp ON bp.booking_id = b.id
        {$refundJoin}
        WHERE b.tour_batch_id = ?
          AND b.booking_type = 'tour'
          AND tb.company_id = ?
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
            v.id,
            v.voucher_code,
            v.qr_token,
            v.pdf_file,
            v.status,
            v.used_at
    ";

    if ($hasRefundRequests) {
        $sql .= ", rr.id, rr.request_code, rr.status ";
    }

    $sql .= " ORDER BY b.id ASC ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Tour batch manifest query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('ii', $batchId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function find_batch_voucher_for_company(mysqli $conn, int $companyId, int $batchId, string $searchValue, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            v.id AS voucher_id,
            v.booking_id,
            v.tour_batch_id,
            v.voucher_code,
            v.qr_token,
            v.pdf_file,
            v.status AS voucher_status,
            v.used_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.passenger_count,

            tb.status AS batch_status,
            tb.start_date,
            tb.end_date,

            tp.title AS package_title,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            c.name AS company_name
        FROM vouchers v
        INNER JOIN bookings b ON b.id = v.booking_id
        INNER JOIN tour_batches tb ON tb.id = v.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN companies c ON c.id = tb.company_id
        INNER JOIN users u ON u.id = b.user_id
        WHERE tb.company_id = ?
          AND v.tour_batch_id = ?
          AND (v.voucher_code = ? OR v.qr_token = ?)
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE ";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Find batch voucher query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('iiss', $companyId, $batchId, $searchValue, $searchValue);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function find_batch_voucher_by_id_for_company(mysqli $conn, int $companyId, int $batchId, int $voucherId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            v.id AS voucher_id,
            v.booking_id,
            v.tour_batch_id,
            v.voucher_code,
            v.qr_token,
            v.pdf_file,
            v.status AS voucher_status,
            v.used_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.passenger_count,

            tb.status AS batch_status,
            tb.start_date,
            tb.end_date,

            tp.title AS package_title,

            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone,

            c.name AS company_name
        FROM vouchers v
        INNER JOIN bookings b ON b.id = v.booking_id
        INNER JOIN tour_batches tb ON tb.id = v.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN companies c ON c.id = tb.company_id
        INNER JOIN users u ON u.id = b.user_id
        WHERE tb.company_id = ?
          AND v.tour_batch_id = ?
          AND v.id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE ";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Find voucher by ID query prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('iii', $companyId, $batchId, $voucherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function mark_batch_voucher_used(mysqli $conn, int $companyId, int $batchId, int $voucherId, int $actorUserId): array
{
    $voucher = find_batch_voucher_by_id_for_company($conn, $companyId, $batchId, $voucherId, true);

    if (!$voucher) {
        throw new Exception('Voucher not found for this batch/company.');
    }

    if (($voucher['voucher_status'] ?? '') === 'cancelled') {
        throw new Exception('This voucher has been cancelled.');
    }

    if (($voucher['booking_status'] ?? '') !== 'paid' || ($voucher['payment_status'] ?? '') !== 'paid') {
        throw new Exception('This voucher is not eligible for check-in because payment is not verified.');
    }

    if (($voucher['batch_status'] ?? '') === 'cancelled') {
        throw new Exception('This batch has been cancelled.');
    }

    if (($voucher['voucher_status'] ?? '') === 'used' || !empty($voucher['used_at'])) {
        throw new Exception('This voucher has already been used.');
    }

    $updateSql = "
        UPDATE vouchers
        SET status = 'used',
            used_at = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($updateSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare voucher update: ' . $conn->error);
    }

    $stmt->bind_param('i', $voucherId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to mark voucher as used.');
    }
    $stmt->close();

    $action = 'tour_voucher_checkin';
    $entityType = 'voucher';
    $description = 'Checked in voucher ' . $voucher['voucher_code'] . ' for batch #' . $batchId;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $actorUserId, $action, $entityType, $voucherId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    return find_batch_voucher_by_id_for_company($conn, $companyId, $batchId, $voucherId, false) ?? $voucher;
}