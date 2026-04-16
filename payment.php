<?php
require_once __DIR__ . '/includes/role_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_history_helper.php';

require_role('customer');

$page_title = 'Payment Submission - Golden Route Myanmar';

$conn = getDBConnection();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();
$packageColumn = customer_history_get_tour_batch_package_column($conn);

$booking = null;
$latestPayment = null;

if ($bookingId > 0) {
    $bookingSql = "
        SELECT
            b.*,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            bc.name AS bus_company_name,
            tb.start_date AS tour_start_date,
            tb.end_date AS tour_end_date,
            tp.title AS tour_title,
            tc2.name AS tour_company_name
        FROM bookings b
        LEFT JOIN trips t ON t.id = b.trip_id
        LEFT JOIN buses bus ON bus.id = t.bus_id
        LEFT JOIN routes r ON r.id = t.route_id
        LEFT JOIN cities fc ON fc.id = r.from_city_id
        LEFT JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN companies bc ON bc.id = t.company_id
        LEFT JOIN tour_batches tb ON tb.id = b.tour_batch_id
        LEFT JOIN tour_packages tp ON tp.id = tb.{$packageColumn}
        LEFT JOIN companies tc2 ON tc2.id = tp.company_id
        WHERE b.id = ?
          AND b.user_id = ?
        LIMIT 1
    ";
    $bookingStmt = $conn->prepare($bookingSql);
    $bookingStmt->bind_param('ii', $bookingId, $currentUserId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult->fetch_assoc() ?: null;
    $bookingStmt->close();

    if ($booking) {
        $paymentSql = "
            SELECT *
            FROM payments
            WHERE booking_id = ?
            ORDER BY id DESC
            LIMIT 1
        ";
        $paymentStmt = $conn->prepare($paymentSql);
        $paymentStmt->bind_param('i', $bookingId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $latestPayment = $paymentResult->fetch_assoc() ?: null;
        $paymentStmt->close();
    }
}

$conn->close();

$paymentMethods = [
    'wave_money' => 'Wave Money',
    'kbzpay' => 'KBZ Pay',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
];

function payment_badge_class(string $status): string
{
    switch ($status) {
        case 'verified':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'submitted':
        default:
            return 'warning text-dark';
    }
}

$formBlocked = false;
$blockMessage = '';

if ($booking) {
    if (($booking['payment_status'] ?? '') === 'paid') {
        $formBlocked = true;
        $blockMessage = 'This booking is already paid.';
    } elseif (($latestPayment['status'] ?? '') === 'submitted') {
        $formBlocked = true;
        $blockMessage = 'A payment proof is already submitted and waiting for review.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="checkout-header mb-4">
        <div>
            <span class="section-kicker">Payment</span>
            <h1 class="page-title mb-2">Submit payment proof</h1>
            <p class="page-subtitle mb-0">Upload screenshot and transaction details for admin verification.</p>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$booking): ?>
        <div class="empty-state-card">
            <h3>Booking not found</h3>
            <p>The booking does not exist or does not belong to your account.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <h4>Booking Summary</h4>
                        <p>Check your booking details before payment submission.</p>
                    </div>

                    <div class="summary-list">
                        <div class="summary-row"><span>Booking Code</span><strong><?php echo e($booking['booking_code']); ?></strong></div>
                        <div class="summary-row"><span>Type</span><strong><?php echo e(ucfirst($booking['booking_type'])); ?></strong></div>
                        <div class="summary-row"><span>Total Amount</span><strong><?php echo number_format((float)$booking['total_amount'], 2); ?> MMK</strong></div>
                        <div class="summary-row"><span>Booking Status</span><strong><?php echo e(customer_history_format_status((string)$booking['status'])); ?></strong></div>
                        <div class="summary-row"><span>Payment Status</span><strong><?php echo e(customer_history_format_status((string)$booking['payment_status'])); ?></strong></div>
                    </div>

                    <?php if (($booking['booking_type'] ?? '') === 'bus'): ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Bus Trip</h6>
                            <div class="summary-row"><span>Company</span><strong><?php echo e($booking['bus_company_name'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Route</span><strong><?php echo e(($booking['from_city_name'] ?? '-') . ' → ' . ($booking['to_city_name'] ?? '-')); ?></strong></div>
                            <div class="summary-row"><span>Travel Date</span><strong><?php echo e($booking['trip_date'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Bus Number</span><strong><?php echo e($booking['bus_number'] ?? '-'); ?></strong></div>
                        </div>
                    <?php else: ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Tour Booking</h6>
                            <div class="summary-row"><span>Company</span><strong><?php echo e($booking['tour_company_name'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Package</span><strong><?php echo e($booking['tour_title'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Start Date</span><strong><?php echo e($booking['tour_start_date'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>End Date</span><strong><?php echo e($booking['tour_end_date'] ?? '-'); ?></strong></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($latestPayment): ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Latest Submitted Payment</h6>
                            <div class="summary-row"><span>Method</span><strong><?php echo e($paymentMethods[$latestPayment['payment_method'] ?? ''] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Amount</span><strong><?php echo number_format((float)($latestPayment['amount'] ?? 0), 2); ?> MMK</strong></div>
                            <div class="summary-row"><span>Transaction Ref</span><strong><?php echo e($latestPayment['transaction_ref'] ?? '-'); ?></strong></div>
                            <div class="summary-row">
                                <span>Status</span>
                                <strong><span class="badge bg-<?php echo payment_badge_class((string)($latestPayment['status'] ?? 'submitted')); ?>"><?php echo e(ucfirst($latestPayment['status'] ?? 'submitted')); ?></span></strong>
                            </div>

                            <?php if (!empty($latestPayment['screenshot_path'] ?? '')): ?>
                                <div class="mt-3">
                                    <a href="<?php echo BASE_URL . ltrim((string)$latestPayment['screenshot_path'], '/'); ?>" target="_blank" class="btn btn-nav-soft w-100">
                                        View Uploaded Screenshot
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <h4>Submit Payment</h4>
                        <p>Complete the form with method, reference and screenshot.</p>
                    </div>

                    <?php if ($formBlocked): ?>
                        <div class="alert alert-warning mb-0"><?php echo e($blockMessage); ?></div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>actions/submit_payment.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="booking_id" value="<?php echo e($booking['id']); ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payment Method</label>
                                    <select name="payment_method" class="form-select" required>
                                        <option value="">Select payment method</option>
                                        <?php foreach ($paymentMethods as $value => $label): ?>
                                            <option value="<?php echo e($value); ?>" <?php echo old('payment_method') === $value ? 'selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="<?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK" readonly>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Transaction Reference</label>
                                    <input type="text" name="transaction_ref" class="form-control" value="<?php echo e(old('transaction_ref')); ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Payment Screenshot</label>
                                    <input type="file" name="payment_screenshot" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" rows="4" class="form-control" placeholder="Optional notes"><?php echo e(old('notes')); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-brand w-100">Submit Payment</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>