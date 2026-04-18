<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ticket_service.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/profile.php');
}

$bookingId = (int)($_POST['booking_id'] ?? 0);

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking ID.');
    redirect('customer/profile.php');
}

$conn = getDBConnection();
$currentUserId = (int)current_user_id();

try {
    $conn->begin_transaction();

    create_or_get_ticket_for_booking($conn, $bookingId, $currentUserId, $currentUserId);

    $conn->commit();
    $conn->close();

    set_flash('success', 'Ticket generated successfully.');
    redirect('customer/ticket.php?booking_id=' . $bookingId);

} catch (Exception $e) {
    $conn->rollback();
    $conn->close();

    set_flash('error', $e->getMessage());
    redirect('customer/ticket.php?booking_id=' . $bookingId);
}