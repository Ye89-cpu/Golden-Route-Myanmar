<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/bus_booking_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('bus_admin');

$bookingId = (int)($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking ID.');
    redirect('bus_admin/bookings.php');
}

$conn = getDBConnection();
require_company_permission($conn, 'manage_bookings');
$company = require_bus_admin_company($conn);
$canApproveBookings = user_has_company_permission($conn, 'approve_bookings');

try {
    $booking = fetch_bus_admin_booking_detail($conn, (int)$company['company_id'], $bookingId);
    if (!$booking) {
        $conn->close();
        set_flash('error', 'Booking not found or not allowed.');
        redirect('bus_admin/bookings.php');
    }

    $passengers = fetch_bus_admin_booking_passengers($conn, $bookingId);
    $seats = fetch_bus_admin_booking_seats($conn, $bookingId);
} catch (Throwable $e) {
    $conn->close();
    die('Booking detail error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

$page_title = 'Booking Detail - ' . $booking['booking_code'];
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Booking Detail</h2>
            <p class="text-muted mb-0"><?php echo e($booking['booking_code']); ?></p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>bus_admin/bookings.php" class="btn btn-outline-secondary">Back to Bookings</a>
            <a href="<?php echo BASE_URL; ?>bus_admin/manifest.php?booking_id=<?php echo e($booking['id']); ?>" class="btn btn-outline-dark">Open Manifest</a>
            <?php if (!empty($booking['ticket_pdf_file'])): ?>
                <a href="<?php echo BASE_URL . e($booking['ticket_pdf_file']); ?>" target="_blank" class="btn btn-success">Ticket PDF</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Booking Info</h5>
                    <div class="mb-2"><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                    <div class="mb-2"><strong>Booked At:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$booking['booking_datetime']))); ?></div>
                    <div class="mb-2"><strong>Passengers:</strong> <?php echo e($booking['passenger_count']); ?></div>
                    <div class="mb-2"><strong>Total Amount:</strong> <?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK</div>
                    <div class="mb-2">
                        <strong>Booking Status:</strong>
                        <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['status'])); ?>">
                            <?php echo e(bus_booking_format_status((string)$booking['status'])); ?>
                        </span>
                    </div>
                    <div class="mb-2">
                        <strong>Payment Status:</strong>
                        <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['payment_status'])); ?>">
                            <?php echo e(bus_booking_format_status((string)$booking['payment_status'])); ?>
                        </span>
                    </div>
                    <?php if (!empty($booking['refund_request_status'])): ?>
                        <div class="mb-0">
                            <strong>Refund Status:</strong>
                            <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['refund_request_status'])); ?>">
                                <?php echo e(bus_booking_format_status((string)$booking['refund_request_status'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Trip Info</h5>
                    <div class="mb-2"><strong>Company:</strong> <?php echo e($booking['company_name']); ?></div>
                    <div class="mb-2"><strong>Route:</strong> <?php echo e($booking['from_city_name']); ?> → <?php echo e($booking['to_city_name']); ?></div>
                    <div class="mb-2"><strong>Trip Date:</strong> <?php echo e($booking['trip_date']); ?></div>
                    <div class="mb-2"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$booking['departure_datetime']))); ?></div>
                    <div class="mb-2"><strong>Arrival:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$booking['arrival_datetime']))); ?></div>
                    <div class="mb-2"><strong>Bus:</strong> <?php echo e($booking['bus_number']); ?> (<?php echo e($booking['bus_type']); ?>)</div>
                    <div class="mb-2"><strong>Plate:</strong> <?php echo e($booking['plate_number'] ?: '-'); ?></div>
                    <div class="mb-0">
                        <strong>Trip Status:</strong>
                        <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['trip_status'])); ?>">
                            <?php echo e(bus_booking_format_status((string)$booking['trip_status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Customer Info</h5>
                    <div class="mb-2"><strong>Name:</strong> <?php echo e($booking['customer_name']); ?></div>
                    <div class="mb-2"><strong>Email:</strong> <?php echo e($booking['customer_email']); ?></div>
                    <div class="mb-0"><strong>Phone:</strong> <?php echo e($booking['customer_phone'] ?: '-'); ?></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Ticket / Payment</h5>
                    <div class="mb-2"><strong>Ticket No:</strong> <?php echo e($booking['ticket_no'] ?: '-'); ?></div>
                    <div class="mb-2">
                        <strong>Ticket Status:</strong>
                        <?php if (!empty($booking['ticket_status'])): ?>
                            <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['ticket_status'])); ?>">
                                <?php echo e(bus_booking_format_status((string)$booking['ticket_status'])); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <div class="mb-2"><strong>Payment Method:</strong> <?php echo e($booking['latest_payment_method'] ? bus_booking_format_status((string)$booking['latest_payment_method']) : '-'); ?></div>
                    <div class="mb-2"><strong>Payment Ref:</strong> <?php echo e($booking['latest_payment_ref'] ?: '-'); ?></div>
                    <div class="mb-2"><strong>Payment Amount:</strong> <?php echo e($booking['latest_payment_amount'] !== null ? number_format((float)$booking['latest_payment_amount'], 2) . ' MMK' : '-'); ?></div>
                    <div class="mb-3">
                        <strong>Latest Payment Status:</strong>
                        <?php if (!empty($booking['latest_payment_status'])): ?>
                            <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$booking['latest_payment_status'])); ?>">
                                <?php echo e(bus_booking_format_status((string)$booking['latest_payment_status'])); ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($booking['latest_payment_screenshot_path'])): ?>
                        <div class="mb-3">
                            <a href="<?php echo BASE_URL . e($booking['latest_payment_screenshot_path']); ?>" target="_blank" class="btn btn-sm btn-outline-warning">
                                View Payment Proof
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($canApproveBookings && !empty($booking['latest_payment_id']) && (string)$booking['latest_payment_status'] === 'submitted'): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <form action="<?php echo BASE_URL; ?>actions/verify_payment.php" method="POST" class="d-inline">
                                <input type="hidden" name="payment_id" value="<?php echo e($booking['latest_payment_id']); ?>">
                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Verify this payment and generate ticket?');">
                                    Approve Payment
                                </button>
                            </form>
                            <form action="<?php echo BASE_URL; ?>actions/reject_payment.php" method="POST" class="d-inline">
                                <input type="hidden" name="payment_id" value="<?php echo e($booking['latest_payment_id']); ?>">
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this payment?');">
                                    Reject Payment
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($booking['refund_request_code'])): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Refund Info</h5>
                <div class="mb-2"><strong>Request Code:</strong> <?php echo e($booking['refund_request_code']); ?></div>
                <div class="mb-2"><strong>Status:</strong> <?php echo e(bus_booking_format_status((string)$booking['refund_request_status'])); ?></div>
                <div class="mb-2"><strong>Requested Amount:</strong> <?php echo e(number_format((float)($booking['refund_requested_amount'] ?? 0), 2)); ?> MMK</div>
                <div class="mb-2"><strong>Reason:</strong> <?php echo nl2br(e((string)($booking['refund_reason'] ?? ''))); ?></div>
                <?php if (!empty($booking['refund_admin_note'])): ?>
                    <div class="mb-0"><strong>Admin Note:</strong> <?php echo nl2br(e((string)$booking['refund_admin_note'])); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Passengers</h5>
                    </div>

                    <?php if (empty($passengers)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No passenger rows found.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>NRC / Passport</th>
                                        <th>Gender</th>
                                        <th>Age</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($passengers as $index => $passenger): ?>
                                        <tr>
                                            <td><?php echo e($index + 1); ?></td>
                                            <td class="fw-semibold"><?php echo e($passenger['full_name']); ?></td>
                                            <td><?php echo e($passenger['phone'] ?: '-'); ?></td>
                                            <td><?php echo e($passenger['nrc_passport'] ?: '-'); ?></td>
                                            <td><?php echo e($passenger['gender'] ?: '-'); ?></td>
                                            <td><?php echo e($passenger['age'] ?: '-'); ?></td>
                                            <td><?php echo e($passenger['special_note'] ?: '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Seats</h5>
                    </div>

                    <?php if (empty($seats)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No seat rows found.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Seat</th>
                                        <th>Type</th>
                                        <th>Row</th>
                                        <th>Col</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seats as $seat): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($seat['seat_number']); ?></td>
                                            <td><?php echo e($seat['seat_type'] ?: '-'); ?></td>
                                            <td><?php echo e($seat['row_no'] ?: '-'); ?></td>
                                            <td><?php echo e($seat['col_no'] ?: '-'); ?></td>
                                            <td><?php echo e(number_format((float)$seat['price'], 2)); ?> MMK</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>