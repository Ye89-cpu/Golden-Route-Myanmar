<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/boarding_helper.php';

require_role('bus_admin');

$tripId = (int)($_GET['trip_id'] ?? $_POST['trip_id'] ?? 0);
if ($tripId <= 0) {
    set_flash('error', 'Invalid trip ID.');
    redirect('bus_admin/trip_boarding.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$searchValue = trim($_GET['search_value'] ?? '');
$searchTicket = null;
$searchMessage = null;
$searchState = null;

try {
    $trip = fetch_bus_admin_trip_boarding_detail($conn, (int)$company['company_id'], $tripId);
    if (!$trip) {
        $conn->close();
        set_flash('error', 'Trip not found or not allowed.');
        redirect('bus_admin/trip_boarding.php');
    }

    if ($searchValue !== '') {
        $searchTicket = find_trip_ticket_for_company($conn, (int)$company['company_id'], $tripId, $searchValue, false);

        if (!$searchTicket) {
            $searchState = 'invalid';
            $searchMessage = 'No matching ticket was found for this trip.';
        } elseif (($searchTicket['ticket_status'] ?? '') === 'cancelled') {
            $searchState = 'invalid';
            $searchMessage = 'This ticket has been cancelled.';
        } elseif (($searchTicket['ticket_status'] ?? '') === 'used' || !empty($searchTicket['used_at'])) {
            $searchState = 'used';
            $searchMessage = 'This ticket has already been checked in.';
        } elseif (($searchTicket['booking_status'] ?? '') !== 'paid' || ($searchTicket['payment_status'] ?? '') !== 'paid') {
            $searchState = 'invalid';
            $searchMessage = 'This ticket is not eligible for boarding because payment is not verified.';
        } else {
            $searchState = 'valid';
            $searchMessage = 'Valid ticket found. Ready for boarding.';
        }
    }

    $rows = fetch_bus_admin_trip_manifest_rows($conn, (int)$company['company_id'], $tripId);
} catch (Throwable $e) {
    $conn->close();
    die('Board trip error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

$page_title = 'Board Trip - #' . $tripId;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Trip Boarding / Check-in</h2>
            <p class="text-muted mb-0">
                <?php echo e($trip['from_city_name']); ?> → <?php echo e($trip['to_city_name']); ?> |
                <?php echo e($trip['trip_date']); ?> |
                Bus <?php echo e($trip['bus_number']); ?>
            </p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>bus_admin/trip_boarding.php" class="btn btn-outline-secondary">Back to Trips</a>
            <a href="<?php echo BASE_URL; ?>bus_admin/scan_ticket.php" class="btn btn-outline-dark">Generic Scan</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Paid Bookings</div>
                    <div class="fs-3 fw-bold"><?php echo e($trip['paid_bookings']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Paid Passengers</div>
                    <div class="fs-3 fw-bold text-primary"><?php echo e($trip['paid_passengers']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Issued Tickets</div>
                    <div class="fs-3 fw-bold text-info"><?php echo e($trip['issued_tickets']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Boarded</div>
                    <div class="fs-3 fw-bold text-success"><?php echo e($trip['boarded_tickets']); ?></div>
                    <div class="small text-muted mt-2">
                        Waiting: <?php echo e(max(0, (int)$trip['issued_tickets'] - (int)$trip['boarded_tickets'])); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Search Ticket for This Trip</h5>

            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="trip_id" value="<?php echo e($tripId); ?>">
                <div class="col-lg-9">
                    <label class="form-label">Ticket No or QR Token</label>
                    <input
                        type="text"
                        name="search_value"
                        class="form-control"
                        value="<?php echo e($searchValue); ?>"
                        placeholder="Enter ticket_no or qr_token"
                    >
                </div>
                <div class="col-lg-3">
                    <button type="submit" class="btn btn-primary w-100">Check Ticket</button>
                </div>
            </form>

            <?php if ($searchValue !== ''): ?>
                <hr>
                <?php if ($searchState === 'valid'): ?>
                    <div class="alert alert-success"><?php echo e($searchMessage); ?></div>
                <?php elseif ($searchState === 'used'): ?>
                    <div class="alert alert-warning"><?php echo e($searchMessage); ?></div>
                <?php else: ?>
                    <div class="alert alert-danger"><?php echo e($searchMessage); ?></div>
                <?php endif; ?>

                <?php if ($searchTicket): ?>
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Ticket No:</strong> <?php echo e($searchTicket['ticket_no']); ?></div>
                        <div class="col-md-4"><strong>Customer:</strong> <?php echo e($searchTicket['customer_name']); ?></div>
                        <div class="col-md-4"><strong>Phone:</strong> <?php echo e($searchTicket['customer_phone'] ?: '-'); ?></div>
                        <div class="col-md-4"><strong>Booking Code:</strong> <?php echo e($searchTicket['booking_code']); ?></div>
                        <div class="col-md-4"><strong>Bus:</strong> <?php echo e($searchTicket['bus_number']); ?></div>
                        <div class="col-md-4">
                            <strong>Ticket Status:</strong>
                            <span class="badge bg-<?php echo e(boarding_status_badge_class((string)$searchTicket['ticket_status'])); ?>">
                                <?php echo e(boarding_format_status((string)$searchTicket['ticket_status'])); ?>
                            </span>
                        </div>
                        <div class="col-md-4"><strong>Route:</strong> <?php echo e($searchTicket['from_city_name']); ?> → <?php echo e($searchTicket['to_city_name']); ?></div>
                        <div class="col-md-4"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$searchTicket['departure_datetime']))); ?></div>
                        <div class="col-md-4"><strong>Used At:</strong> <?php echo e($searchTicket['used_at'] ? date('Y-m-d H:i:s', strtotime((string)$searchTicket['used_at'])) : '-'); ?></div>
                    </div>

                    <?php if (boarding_can_mark_used($searchTicket)): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>actions/mark_trip_boarded.php" class="mt-3">
                            <input type="hidden" name="trip_id" value="<?php echo e($tripId); ?>">
                            <input type="hidden" name="ticket_id" value="<?php echo e($searchTicket['ticket_id']); ?>">
                            <button
                                type="submit"
                                class="btn btn-success"
                                onclick="return confirm('Confirm boarding and mark this ticket as used?');"
                            >
                                Mark as Boarded
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0">Trip Manifest</h5>
            </div>

            <?php if (empty($rows)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No bus bookings found for this trip.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Passengers</th>
                                <th>Seats</th>
                                <th>Ticket</th>
                                <th>Status</th>
                                <th style="min-width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['booking_code']); ?></div>
                                        <div class="small text-muted"><?php echo e(date('Y-m-d H:i', strtotime((string)$row['booked_at']))); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($row['customer_phone'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['passenger_count']); ?> pax</div>
                                        <div class="small text-muted"><?php echo e($row['passenger_names'] ?: '-'); ?></div>
                                    </td>
                                    <td><?php echo e($row['seat_numbers'] ?: '-'); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['ticket_no'] ?: '-'); ?></div>
                                        <?php if (!empty($row['ticket_pdf_file'])): ?>
                                            <div class="small">
                                                <a href="<?php echo BASE_URL . e($row['ticket_pdf_file']); ?>" target="_blank">PDF</a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo e(boarding_status_badge_class((string)$row['payment_status'])); ?>">
                                                <?php echo e(boarding_format_status((string)$row['payment_status'])); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($row['ticket_status'])): ?>
                                            <div class="mb-1">
                                                <span class="badge bg-<?php echo e(boarding_status_badge_class((string)$row['ticket_status'])); ?>">
                                                    <?php echo e(boarding_format_status((string)$row['ticket_status'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['refund_request_status'])): ?>
                                            <div>
                                                <span class="badge bg-<?php echo e(boarding_status_badge_class((string)$row['refund_request_status'])); ?>">
                                                    Refund: <?php echo e(boarding_format_status((string)$row['refund_request_status'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="<?php echo BASE_URL; ?>bus_admin/booking_detail.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                Detail
                                            </a>

                                            <?php if (boarding_can_mark_used($row)): ?>
                                                <form method="POST" action="<?php echo BASE_URL; ?>actions/mark_trip_boarded.php" class="d-inline">
                                                    <input type="hidden" name="trip_id" value="<?php echo e($tripId); ?>">
                                                    <input type="hidden" name="ticket_id" value="<?php echo e($row['ticket_id']); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Mark this ticket as boarded?');"
                                                    >
                                                        Check-in
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                    No Action
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($row['used_at'])): ?>
                                            <div class="small text-muted mt-2">
                                                Used at: <?php echo e(date('Y-m-d H:i:s', strtotime((string)$row['used_at']))); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>