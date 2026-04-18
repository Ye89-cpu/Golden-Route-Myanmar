<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/notification_helper.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_template_helper.php';
require_once __DIR__ . '/tour_admin_booking_helper.php';

function notification_table_exists(mysqli $conn): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'notifications'
        LIMIT 1
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();
    return ((int)($row['total'] ?? 0)) > 0;
}

function create_user_notification(
    mysqli $conn,
    int $userId,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $relatedType = null,
    ?int $relatedId = null,
    ?int $createdBy = null
): bool {
    if ($userId <= 0 || !notification_table_exists($conn)) {
        return false;
    }

    $sql = "
        INSERT INTO notifications
        (user_id, type, title, message, link_url, related_type, related_id, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'isssssii',
        $userId,
        $type,
        $title,
        $message,
        $linkUrl,
        $relatedType,
        $relatedId,
        $createdBy
    );

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function fetch_user_ids_by_role(mysqli $conn, string $role): array
{
    $sql = "SELECT id FROM users WHERE role = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id'];
    }

    $stmt->close();
    return $ids;
}

function create_role_notifications(
    mysqli $conn,
    string $role,
    string $type,
    string $title,
    string $message,
    ?string $linkUrl = null,
    ?string $relatedType = null,
    ?int $relatedId = null,
    ?int $createdBy = null
): void {
    $userIds = fetch_user_ids_by_role($conn, $role);

    foreach ($userIds as $userId) {
        create_user_notification($conn, $userId, $type, $title, $message, $linkUrl, $relatedType, $relatedId, $createdBy);
    }
}

function count_user_unread_notifications(mysqli $conn, int $userId): int
{
    if ($userId <= 0 || !notification_table_exists($conn)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function fetch_user_notifications(mysqli $conn, int $userId, int $limit = 50): array
{
    if ($userId <= 0 || !notification_table_exists($conn)) {
        return [];
    }

    $limit = max(1, min($limit, 100));

    $sql = "
        SELECT *
        FROM notifications
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT {$limit}
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
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

function mark_notification_as_read(mysqli $conn, int $notificationId, int $userId): bool
{
    if (!notification_table_exists($conn)) {
        return false;
    }

    $sql = "
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE id = ?
          AND user_id = ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ii', $notificationId, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function mark_all_notifications_as_read(mysqli $conn, int $userId): bool
{
    if (!notification_table_exists($conn)) {
        return false;
    }

    $sql = "
        UPDATE notifications
        SET is_read = 1,
            read_at = NOW()
        WHERE user_id = ?
          AND is_read = 0
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function notification_fetch_bus_payment_verified_payload(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.total_amount,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            tk.id AS ticket_id,
            tk.ticket_no,
            tk.pdf_file,
            c.name AS company_name,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            t.trip_date
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN tickets tk ON tk.booking_id = b.id
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE b.id = ?
          AND b.booking_type = 'bus'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notification_fetch_tour_payment_verified_payload(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.total_amount,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            v.id AS voucher_id,
            v.voucher_code,
            v.pdf_file,
            c.name AS company_name,
            tp.title AS package_title,
            CONCAT(tb.start_date, ' to ', tb.end_date) AS batch_date
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN vouchers v ON v.booking_id = b.id
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        INNER JOIN companies c ON c.id = tb.company_id
        WHERE b.id = ?
          AND b.booking_type = 'tour'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notification_fetch_refund_payload(mysqli $conn, int $refundRequestId): ?array
{
    $sql = "
        SELECT
            rr.id AS refund_request_id,
            rr.request_code,
            rr.status,
            rr.requested_amount,
            rr.booking_type,
            b.id AS booking_id,
            b.booking_code,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email
        FROM refund_requests rr
        INNER JOIN bookings b ON b.id = rr.booking_id
        INNER JOIN users u ON u.id = rr.user_id
        WHERE rr.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $refundRequestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notification_fetch_boarded_ticket_payload(mysqli $conn, int $ticketId): ?array
{
    $sql = "
        SELECT
            tk.id AS ticket_id,
            tk.ticket_no,
            b.booking_code,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM tickets tk
        INNER JOIN bookings b ON b.id = tk.booking_id
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN trips t ON t.id = tk.trip_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE tk.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notification_fetch_checked_in_voucher_payload(mysqli $conn, int $voucherId): ?array
{
    $sql = "
        SELECT
            v.id AS voucher_id,
            v.voucher_code,
            b.booking_code,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            tp.title AS package_title
        FROM vouchers v
        INNER JOIN bookings b ON b.id = v.booking_id
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN tour_batches tb ON tb.id = v.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE v.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $voucherId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notify_event_payment_verified_bus_by_booking_id(mysqli $conn, int $bookingId, int $createdBy = 0): void
{
    $payload = notification_fetch_bus_payment_verified_payload($conn, $bookingId);
    if (!$payload) {
        return;
    }

    $link = BASE_URL . 'customer/ticket.php?booking_id=' . $bookingId;
    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'success',
        'Bus ticket confirmed',
        'Your booking ' . $payload['booking_code'] . ' has been verified. Ticket No: ' . $payload['ticket_no'],
        $link,
        'booking',
        $bookingId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'booking_payment_verified_bus',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'from_city' => $payload['from_city_name'],
            'to_city' => $payload['to_city_name'],
            'trip_date' => $payload['trip_date'],
            'ticket_no' => $payload['ticket_no'],
            'amount' => number_format((float)$payload['total_amount'], 2) . ' MMK',
            'action_url' => $link,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        !empty($payload['pdf_file']) ? [__DIR__ . '/../' . ltrim((string)$payload['pdf_file'], '/')] : [],
        'Your bus booking {{booking_code}} is confirmed',
        '<p>Dear {{customer_name}}, your ticket is ready.</p>'
    );
}

function notify_event_payment_verified_tour_by_booking_id(mysqli $conn, int $bookingId, int $createdBy = 0): void
{
    $payload = notification_fetch_tour_payment_verified_payload($conn, $bookingId);
    if (!$payload) {
        return;
    }

    $link = BASE_URL . 'customer/voucher.php?booking_id=' . $bookingId;
    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'success',
        'Tour voucher confirmed',
        'Your booking ' . $payload['booking_code'] . ' has been verified. Voucher Code: ' . $payload['voucher_code'],
        $link,
        'booking',
        $bookingId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'booking_payment_verified_tour',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'package_title' => $payload['package_title'],
            'batch_date' => $payload['batch_date'],
            'voucher_code' => $payload['voucher_code'],
            'amount' => number_format((float)$payload['total_amount'], 2) . ' MMK',
            'action_url' => $link,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        !empty($payload['pdf_file']) ? [__DIR__ . '/../' . ltrim((string)$payload['pdf_file'], '/')] : [],
        'Your tour booking {{booking_code}} is confirmed',
        '<p>Dear {{customer_name}}, your voucher is ready.</p>'
    );
}

function notify_event_refund_submitted_by_request_id(mysqli $conn, int $refundRequestId, int $createdBy = 0): void
{
    $payload = notification_fetch_refund_payload($conn, $refundRequestId);
    if (!$payload) {
        return;
    }

    $customerLink = BASE_URL . 'customer/refund_request.php?booking_id=' . $payload['booking_id'];
    $adminLink = BASE_URL . 'admin/refund_requests.php';

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'warning',
        'Refund request submitted',
        'Your refund request ' . $payload['request_code'] . ' is pending review.',
        $customerLink,
        'refund_request',
        $refundRequestId,
        $createdBy > 0 ? $createdBy : null
    );

    create_role_notifications(
        $conn,
        'super_admin',
        'warning',
        'New refund request',
        'Request ' . $payload['request_code'] . ' was submitted for booking ' . $payload['booking_code'],
        $adminLink,
        'refund_request',
        $refundRequestId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'refund_request_received_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'request_code' => $payload['request_code'],
            'amount' => number_format((float)$payload['requested_amount'], 2) . ' MMK',
            'status' => ucfirst((string)$payload['status']),
            'action_url' => $customerLink,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Refund request received for {{booking_code}}',
        '<p>Dear {{customer_name}}, we received your refund request.</p>'
    );

    $adminIds = fetch_user_ids_by_role($conn, 'super_admin');
    foreach ($adminIds as $adminId) {
        $adminEmail = notification_fetch_admin_email($conn, $adminId);
        if ($adminEmail) {
            dispatch_templated_email(
                $conn,
                'refund_request_received_admin',
                $adminEmail['email'],
                $adminEmail['name'],
                [
                    'request_code' => $payload['request_code'],
                    'booking_code' => $payload['booking_code'],
                    'customer_name' => $payload['customer_name'],
                    'amount' => number_format((float)$payload['requested_amount'], 2) . ' MMK',
                    'action_url' => $adminLink,
                    'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
                ],
                [],
                'New refund request {{request_code}} for {{booking_code}}',
                '<p>Hello Admin, a new refund request was submitted.</p>'
            );
        }
    }
}

function notification_fetch_admin_email(mysqli $conn, int $userId): ?array
{
    $sql = "SELECT id, name, email FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notify_event_refund_approved_by_request_id(mysqli $conn, int $refundRequestId, int $createdBy = 0): void
{
    $payload = notification_fetch_refund_payload($conn, $refundRequestId);
    if (!$payload) {
        return;
    }

    $customerLink = BASE_URL . 'customer/refund_request.php?booking_id=' . $payload['booking_id'];

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'success',
        'Refund approved',
        'Your refund request ' . $payload['request_code'] . ' has been approved.',
        $customerLink,
        'refund_request',
        $refundRequestId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'refund_approved_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'request_code' => $payload['request_code'],
            'amount' => number_format((float)$payload['requested_amount'], 2) . ' MMK',
            'status' => ucfirst((string)$payload['status']),
            'action_url' => $customerLink,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Refund approved for {{booking_code}}',
        '<p>Dear {{customer_name}}, your refund was approved.</p>'
    );
}

function notify_event_refund_rejected_by_request_id(mysqli $conn, int $refundRequestId, int $createdBy = 0): void
{
    $payload = notification_fetch_refund_payload($conn, $refundRequestId);
    if (!$payload) {
        return;
    }

    $customerLink = BASE_URL . 'customer/refund_request.php?booking_id=' . $payload['booking_id'];

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'danger',
        'Refund rejected',
        'Your refund request ' . $payload['request_code'] . ' has been rejected.',
        $customerLink,
        'refund_request',
        $refundRequestId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'refund_rejected_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'request_code' => $payload['request_code'],
            'status' => ucfirst((string)$payload['status']),
            'action_url' => $customerLink,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Refund rejected for {{booking_code}}',
        '<p>Dear {{customer_name}}, your refund was rejected.</p>'
    );
}

function notify_event_trip_boarded_by_ticket_id(mysqli $conn, int $ticketId, int $createdBy = 0): void
{
    $payload = notification_fetch_boarded_ticket_payload($conn, $ticketId);
    if (!$payload) {
        return;
    }

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'success',
        'Bus boarding confirmed',
        'Ticket ' . $payload['ticket_no'] . ' has been checked in successfully.',
        BASE_URL . 'customer/ticket.php?booking_id=' . $payload['ticket_id'],
        'ticket',
        $ticketId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'trip_boarded_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'ticket_no' => $payload['ticket_no'],
            'from_city' => $payload['from_city_name'],
            'to_city' => $payload['to_city_name'],
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Boarding confirmed for ticket {{ticket_no}}',
        '<p>Dear {{customer_name}}, your boarding was confirmed.</p>'
    );
}

function notify_event_voucher_checked_in_by_voucher_id(mysqli $conn, int $voucherId, int $createdBy = 0): void
{
    $payload = notification_fetch_checked_in_voucher_payload($conn, $voucherId);
    if (!$payload) {
        return;
    }

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'success',
        'Tour check-in confirmed',
        'Voucher ' . $payload['voucher_code'] . ' has been checked in successfully.',
        BASE_URL . 'customer/voucher.php?booking_id=' . $payload['voucher_id'],
        'voucher',
        $voucherId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'voucher_checked_in_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'voucher_code' => $payload['voucher_code'],
            'package_title' => $payload['package_title'],
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Check-in confirmed for voucher {{voucher_code}}',
        '<p>Dear {{customer_name}}, your check-in was confirmed.</p>'
    );
    function notification_fetch_booking_created_payload(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.total_amount,
            b.status,
            b.payment_status,
            b.passenger_count,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            t.trip_date,
            GROUP_CONCAT(bs.seat_number ORDER BY bs.seat_number SEPARATOR ', ') AS seat_numbers
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN booking_seats bs ON bs.booking_id = b.id
        WHERE b.id = ?
          AND b.booking_type = 'bus'
        GROUP BY
            b.id, b.booking_code, b.total_amount, b.status, b.payment_status, b.passenger_count,
            u.id, u.name, u.email, fc.name, tc.name, t.trip_date
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}
function notify_event_booking_created_by_booking_id(mysqli $conn, int $bookingId, int $createdBy = 0): void
{
    $payload = notification_fetch_booking_created_payload($conn, $bookingId);
    if (!$payload) {
        return;
    }

    $customerEmail = trim((string)($payload['customer_email'] ?? ''));
    $customerName = trim((string)($payload['customer_name'] ?? 'Customer'));
    $link = BASE_URL . 'payment.php?booking_id=' . $bookingId;

    if (function_exists('create_user_notification')) {
        create_user_notification(
            $conn,
            (int)$payload['user_id'],
            'info',
            'Booking created',
            'Your booking ' . $payload['booking_code'] . ' was created successfully.',
            $link,
            'booking',
            $bookingId,
            $createdBy > 0 ? $createdBy : null
        );
    }

    if ($customerEmail === '') {
        return;
    }

    if (function_exists('dispatch_templated_email')) {
        dispatch_templated_email(
            $conn,
            'booking_created_customer',
            $customerEmail,
            $customerName,
            [
                'customer_name' => $customerName,
                'booking_code' => (string)($payload['booking_code'] ?? ''),
                'from_city' => (string)($payload['from_city_name'] ?? '-'),
                'to_city' => (string)($payload['to_city_name'] ?? '-'),
                'trip_date' => (string)($payload['trip_date'] ?? '-'),
                'seat_numbers' => (string)(($payload['seat_numbers'] ?? '') !== '' ? $payload['seat_numbers'] : '-'),
                'passenger_count' => (int)($payload['passenger_count'] ?? 0),
                'amount' => number_format((float)($payload['total_amount'] ?? 0), 2) . ' MMK',
                'status' => ucfirst((string)($payload['status'] ?? 'pending')) . ' / ' . ucfirst((string)($payload['payment_status'] ?? 'unpaid')),
                'action_url' => $link,
                'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
            ],
            [],
            'Your booking {{booking_code}} has been created',
            '<p>Dear {{customer_name}}, your booking {{booking_code}} has been created successfully.</p>'
        );
    }
}

function notification_fetch_payment_submitted_payload(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.total_amount,
            b.payment_status,
            u.id AS user_id,
            u.name AS customer_name,
            u.email AS customer_email,
            p.payment_method,
            p.transaction_ref,
            p.status AS payment_record_status
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        LEFT JOIN payments p ON p.id = (
            SELECT p2.id
            FROM payments p2
            WHERE p2.booking_id = b.id
            ORDER BY p2.id DESC
            LIMIT 1
        )
        WHERE b.id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function notify_event_payment_submitted_by_booking_id(mysqli $conn, int $bookingId, int $createdBy = 0): void
{
    $payload = notification_fetch_payment_submitted_payload($conn, $bookingId);
    if (!$payload) {
        return;
    }

    $link = BASE_URL . 'payment.php?booking_id=' . $bookingId;

    create_user_notification(
        $conn,
        (int)$payload['user_id'],
        'warning',
        'Payment submitted',
        'Your payment for booking ' . $payload['booking_code'] . ' is now pending review.',
        $link,
        'booking',
        $bookingId,
        $createdBy > 0 ? $createdBy : null
    );

    dispatch_templated_email(
        $conn,
        'payment_submitted_customer',
        (string)$payload['customer_email'],
        (string)$payload['customer_name'],
        [
            'customer_name' => $payload['customer_name'],
            'booking_code' => $payload['booking_code'],
            'payment_method' => ucwords(str_replace('_', ' ', (string)($payload['payment_method'] ?? ''))),
            'amount' => number_format((float)$payload['total_amount'], 2) . ' MMK',
            'transaction_ref' => (string)($payload['transaction_ref'] ?: '-'),
            'status' => ucfirst((string)($payload['payment_record_status'] ?: $payload['payment_status'])),
            'action_url' => $link,
            'app_name' => defined('APP_NAME') ? APP_NAME : 'Myanmar Bus & Tour Booking',
        ],
        [],
        'Your payment for {{booking_code}} is pending review',
        '<p>Dear {{customer_name}}, we received your payment submission for booking {{booking_code}}.</p>'
    );
}
}