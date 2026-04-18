<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ticket_helper.php';

require_role('customer');

$page_title = 'My Ticket';

$conn = getDBConnection();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();

$booking = null;
$ticket = null;
$passengers = [];
$seats = [];

if ($bookingId > 0) {
    $booking = fetch_paid_booking_for_ticket($conn, $bookingId, $currentUserId);

    if ($booking) {
        $ticket = fetch_existing_ticket($conn, $bookingId);
        $passengers = fetch_booking_passengers($conn, $bookingId);
        $seats = fetch_booking_seats($conn, $bookingId);
    }
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Bus Ticket</h2>
            <p class="text-muted mb-0">View and download your verified ticket.</p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>customer/profile.php" class="btn btn-outline-secondary">
                Back to Profile
            </a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$booking): ?>
        <div class="alert alert-danger rounded-4">
            Booking not found or you are not allowed to access it.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Ticket Details</h5>

                        <div class="mb-2"><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                        <div class="mb-2"><strong>Company:</strong> <?php echo e($booking['company_name']); ?></div>
                        <div class="mb-2">
                            <strong>Route:</strong>
                            <?php echo e($booking['from_city_name']); ?> → <?php echo e($booking['to_city_name']); ?>
                        </div>
                        <div class="mb-2"><strong>Trip Date:</strong> <?php echo e($booking['trip_date']); ?></div>
                        <div class="mb-2"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime($booking['departure_datetime']))); ?></div>
                        <div class="mb-2"><strong>Arrival:</strong> <?php echo e(date('Y-m-d H:i', strtotime($booking['arrival_datetime']))); ?></div>
                        <div class="mb-2"><strong>Bus:</strong> <?php echo e($booking['bus_number']); ?></div>

                        <div class="mb-2">
                            <strong>Passenger Names:</strong>
                            <?php echo e(implode(', ', array_map(fn($p) => $p['full_name'], $passengers))); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Seat Numbers:</strong>
                            <?php echo e(implode(', ', array_map(fn($s) => $s['seat_number'], $seats))); ?>
                        </div>

                        <div class="mb-2">
                            <strong>Booking Status:</strong>
                            <span class="badge bg-<?php echo e($booking['payment_status'] === 'paid' ? 'success' : 'warning text-dark'); ?>">
                                <?php echo e(ucfirst($booking['payment_status'])); ?>
                            </span>
                        </div>

                        <?php if ($ticket): ?>
                            <hr>
                            <div class="mb-2"><strong>Ticket Number:</strong> <?php echo e($ticket['ticket_no']); ?></div>
                            <div class="mb-2"><strong>Ticket Status:</strong> <?php echo e(ucfirst($ticket['status'])); ?></div>
                            <div class="mb-3"><strong>Generated At:</strong> <?php echo e(date('Y-m-d H:i', strtotime($ticket['created_at']))); ?></div>

                            <div class="d-flex flex-wrap gap-2">
                                <a
                                    href="<?php echo BASE_URL . e($ticket['pdf_file']); ?>"
                                    target="_blank"
                                    class="btn btn-primary"
                                >
                                    Download PDF Ticket
                                </a>

                                <a
                                    href="<?php echo BASE_URL . e($ticket['qr_image']); ?>"
                                    target="_blank"
                                    class="btn btn-outline-primary"
                                >
                                    View QR Image
                                </a>
                            </div>
                        <?php else: ?>
                            <hr>
                            <?php if ($booking['payment_status'] === 'paid' && $booking['booking_status'] === 'paid'): ?>
                                <form action="<?php echo BASE_URL; ?>actions/generate_ticket.php" method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo e($booking['booking_id']); ?>">
                                    <button type="submit" class="btn btn-success">
                                        Generate Ticket
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    Ticket can be generated only after payment verification.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 text-center">
                        <h5 class="fw-bold mb-3">QR Preview</h5>

                        <?php if ($ticket && !empty($ticket['qr_image'])): ?>
                            <img
                                src="<?php echo BASE_URL . e($ticket['qr_image']); ?>"
                                alt="Ticket QR Code"
                                class="img-fluid rounded border p-2 bg-white"
                                style="max-width: 260px;"
                            >
                            <div class="small text-muted mt-3">
                                Present this QR code during boarding.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-0">
                                QR code will appear here after ticket generation.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>