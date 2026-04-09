<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/refund_helper.php';

require_role('customer');

$page_title = 'Refund Request';

$conn = getDBConnection();
$bookingId = (int)($_GET['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();

$booking = null;
$latestRequest = null;
$blockReason = 'Invalid booking ID.';

if ($bookingId > 0) {
    $booking = fetch_customer_refundable_booking($conn, $bookingId, $currentUserId);
    if ($booking) {
        $latestRequest = fetch_latest_refund_request_by_booking($conn, $bookingId);
        $blockReason = refund_request_block_reason($booking, $latestRequest);
    } else {
        $blockReason = 'Booking not found or access denied.';
    }
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Cancellation + Refund Request</h2>
            <p class="text-muted mb-0">Paid booking ကို cancel/refund request တင်နိုင်ပါတယ်။</p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-outline-secondary">
                Back to My Bookings
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
        <div class="alert alert-danger rounded-4"><?php echo e($blockReason); ?></div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Booking Summary</h5>

                        <div class="mb-2"><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                        <div class="mb-2"><strong>Type:</strong> <?php echo e(strtoupper($booking['booking_type'])); ?></div>
                        <div class="mb-2"><strong>Passengers:</strong> <?php echo e($booking['passenger_count']); ?></div>
                        <div class="mb-2"><strong>Total Amount:</strong> <?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK</div>
                        <div class="mb-2"><strong>Booking Status:</strong> <?php echo e(ucfirst($booking['booking_status'])); ?></div>
                        <div class="mb-3"><strong>Payment Status:</strong> <?php echo e(ucfirst(str_replace('_', ' ', $booking['payment_status']))); ?></div>

                        <?php if ($booking['booking_type'] === 'bus'): ?>
                            <hr>
                            <div class="mb-2"><strong>Company:</strong> <?php echo e($booking['bus_company_name']); ?></div>
                            <div class="mb-2"><strong>Route:</strong> <?php echo e($booking['from_city_name']); ?> → <?php echo e($booking['to_city_name']); ?></div>
                            <div class="mb-2"><strong>Bus:</strong> <?php echo e($booking['bus_number']); ?></div>
                            <div class="mb-2"><strong>Trip Date:</strong> <?php echo e($booking['trip_date']); ?></div>
                            <div class="mb-0"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$booking['departure_datetime']))); ?></div>
                        <?php else: ?>
                            <hr>
                            <div class="mb-2"><strong>Company:</strong> <?php echo e($booking['tour_company_name']); ?></div>
                            <div class="mb-2"><strong>Package:</strong> <?php echo e($booking['package_title']); ?></div>
                            <div class="mb-0"><strong>Batch:</strong> <?php echo e($booking['tour_start_date']); ?> to <?php echo e($booking['tour_end_date']); ?></div>
                        <?php endif; ?>

                        <?php if ($latestRequest): ?>
                            <hr>
                            <h6 class="fw-bold mb-3">Latest Refund Request</h6>
                            <div class="mb-2"><strong>Request Code:</strong> <?php echo e($latestRequest['request_code']); ?></div>
                            <div class="mb-2">
                                <strong>Status:</strong>
                                <span class="badge bg-<?php echo refund_status_badge_class($latestRequest['status']); ?>">
                                    <?php echo e(refund_format_status($latestRequest['status'])); ?>
                                </span>
                            </div>
                            <div class="mb-2"><strong>Requested Amount:</strong> <?php echo e(number_format((float)$latestRequest['requested_amount'], 2)); ?> MMK</div>
                            <div class="mb-2"><strong>Reason:</strong> <?php echo nl2br(e($latestRequest['reason'])); ?></div>
                            <?php if (!empty($latestRequest['admin_note'])): ?>
                                <div class="mb-0"><strong>Admin Note:</strong> <?php echo nl2br(e($latestRequest['admin_note'])); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Submit Refund Request</h5>

                        <?php if ($blockReason !== null): ?>
                            <div class="alert alert-warning rounded-4 mb-0">
                                <?php echo e($blockReason); ?>
                            </div>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>actions/submit_refund_request.php" method="POST">
                                <input type="hidden" name="booking_id" value="<?php echo e($booking['id']); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Refund Amount</label>
                                    <input type="text" class="form-control" value="<?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Reason for Cancellation / Refund</label>
                                    <textarea name="reason" rows="6" class="form-control" required placeholder="Explain why you want to cancel and request refund"><?php echo e(old('reason')); ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-danger">
                                    Submit Refund Request
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>