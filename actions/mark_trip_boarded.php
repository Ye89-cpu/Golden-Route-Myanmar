<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/boarding_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role('bus_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('bus_admin/trip_boarding.php');
}

$tripId = (int)($_POST['trip_id'] ?? 0);
$ticketId = (int)($_POST['ticket_id'] ?? 0);
$actorUserId = (int)current_user_id();

if ($tripId <= 0 || $ticketId <= 0) {
    set_flash('error', 'Invalid trip or ticket ID.');
    redirect('bus_admin/trip_boarding.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

try {
    $conn->begin_transaction();

    $updatedTicket = mark_trip_ticket_used(
        $conn,
        (int)$company['company_id'],
        $tripId,
        $ticketId,
        $actorUserId
    );

    notify_event_trip_boarded_by_ticket_id(
        $conn,
        (int)$updatedTicket['ticket_id'],
        $actorUserId
    );

    $conn->commit();
    $conn->close();

    set_flash('success', 'Boarding confirmed for ticket ' . $updatedTicket['ticket_no'] . '.');
    redirect('bus_admin/board_trip.php?trip_id=' . $tripId . '&search_value=' . urlencode((string)$updatedTicket['ticket_no']));
} catch (Exception $e) {
    if ($conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }

    set_flash('error', $e->getMessage());
    redirect('bus_admin/board_trip.php?trip_id=' . $tripId);
}