<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/voucher_helper.php';

require_role('customer');

$page_title = 'My Tour Voucher';

$conn = getDBConnection();
$bookingId = (int)($_GET['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();

$booking = null;
$voucher = null;
$passengers = [];

if ($bookingId > 0) {
    $booking = fetch_paid_tour_booking_for_voucher($conn, $bookingId, $currentUserId);

    if ($booking) {
        $voucher = fetch_existing_voucher($conn, $bookingId);
        $passengers = fetch_voucher_passengers($conn, $bookingId);
    }
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Tour Voucher</h2>
            <p class="text-muted mb-0">View and download your verified tour voucher.</p>
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
            Tour booking not found or you are not allowed to access it.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Voucher Details</h5>

                        <div class="mb-2"><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                        <div class="mb-2"><strong>Company:</strong> <?php echo e($booking['company_name']); ?></div>
                        <div class="mb-2"><strong>Package:</strong> <?php echo e($booking['package_title']); ?></div>
                        <div class="mb-2"><strong>Batch:</strong> <?php echo e($booking['start_date']); ?> to <?php echo e($booking['end_date']); ?></div>
                        <div class="mb-2"><strong>Passenger Count:</strong> <?php echo e((int)$booking['passenger_count']); ?></div>
                        <div class="mb-3"><strong>Total Amount:</strong> <?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK</div>

                        <div class="mb-2">
                            <strong>Traveler Names:</strong>
                            <?php echo e(implode(', ', array_map(static fn(array $p): string => $p['full_name'], $passengers))); ?>
                        </div>

                        <div class="mb-3">
                            <strong>Payment Status:</strong>
                            <span class="badge bg-<?php echo e($booking['payment_status'] === 'paid' ? 'success' : 'warning text-dark'); ?>">
                                <?php echo e(ucfirst($booking['payment_status'])); ?>
                            </span>
                        </div>

                        <?php if ($voucher): ?>
                            <hr>
                            <div class="mb-2"><strong>Voucher Code:</strong> <?php echo e($voucher['voucher_code']); ?></div>
                            <div class="mb-2"><strong>Voucher Status:</strong> <?php echo e(ucfirst($voucher['status'])); ?></div>
                            <div class="mb-3"><strong>Generated At:</strong> <?php echo e(date('Y-m-d H:i', strtotime($voucher['created_at']))); ?></div>

                            <div class="d-flex flex-wrap gap-2">
                                <?php if (!empty($voucher['pdf_file'])): ?>
                                    <a href="<?php echo BASE_URL . e($voucher['pdf_file']); ?>" target="_blank" class="btn btn-primary">
                                        Download PDF Voucher
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($voucher['qr_image'])): ?>
                                    <a href="<?php echo BASE_URL . e($voucher['qr_image']); ?>" target="_blank" class="btn btn-outline-primary">
                                        View QR Image
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <hr>
                            <?php if ($booking['payment_status'] === 'paid' && $booking['booking_status'] === 'paid'): ?>
                                <form action="<?php echo BASE_URL; ?>actions/generate_voucher.php" method="POST">
                                    <input type="hidden" name="booking_id" value="<?php echo e($booking['booking_id']); ?>">
                                    <button type="submit" class="btn btn-success">Generate Voucher</button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    Voucher can be generated only after payment verification.
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

                        <?php if ($voucher && !empty($voucher['qr_image'])): ?>
                            <img
                                src="<?php echo BASE_URL . e($voucher['qr_image']); ?>"
                                alt="Voucher QR Code"
                                class="img-fluid rounded border p-2 bg-white"
                                style="max-width: 260px;"
                            >
                            <div class="small text-muted mt-3">
                                Present this QR code during check-in or departure.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-0">
                                QR code will appear here after voucher generation.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
