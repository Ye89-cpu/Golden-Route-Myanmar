<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/customer/bookings.php

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_history_helper.php';

require_role('customer');

$page_title = 'My Bookings - Golden Route Myanmar';

$conn = getDBConnection();
$currentUserId = (int)current_user_id();

try {
    $allRows = fetch_customer_booking_history($conn, $currentUserId);
} catch (Throwable $e) {
    $conn->close();
    die('Booking history error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

$typeFilter = trim($_GET['type'] ?? 'all');
$paymentFilter = trim($_GET['payment'] ?? 'all');

$allowedTypeFilters = ['all', 'bus', 'tour'];
$allowedPaymentFilters = ['all', 'unpaid', 'pending_review', 'paid', 'failed', 'refunded'];

if (!in_array($typeFilter, $allowedTypeFilters, true)) {
    $typeFilter = 'all';
}

if (!in_array($paymentFilter, $allowedPaymentFilters, true)) {
    $paymentFilter = 'all';
}

$summary = summarize_customer_booking_history($allRows);
$rows = filter_customer_booking_history($allRows, $typeFilter, $paymentFilter);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">My Booking History</h2>
            <p class="text-muted mb-0">Bus booking, tour booking, payment, refund status အားလုံးကို တစ်နေရာတည်းမှာ ကြည့်နိုင်ပါတယ်။</p>
        </div>

        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>customer/profile.php" class="btn btn-outline-secondary">Back to Profile</a>
            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-outline-primary">Book Bus</a>
            <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-primary">Browse Tours</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Total</div>
                    <div class="fs-4 fw-bold"><?php echo e($summary['total_bookings']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Bus</div>
                    <div class="fs-4 fw-bold text-primary"><?php echo e($summary['bus_bookings']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Tour</div>
                    <div class="fs-4 fw-bold text-info"><?php echo e($summary['tour_bookings']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Refund Pending</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo e($summary['refund_pending']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Refund Approved</div>
                    <div class="fs-4 fw-bold text-success"><?php echo e($summary['refund_approved']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-2">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Total Paid</div>
                    <div class="fs-6 fw-bold"><?php echo e(number_format((float)$summary['total_spent'], 2)); ?> MMK</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="small text-muted mb-2">Filter by booking type</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($allowedTypeFilters as $filter): ?>
                            <a
                                href="<?php echo BASE_URL; ?>customer/bookings.php?type=<?php echo e($filter); ?>&payment=<?php echo e($paymentFilter); ?>"
                                class="btn btn-sm <?php echo $typeFilter === $filter ? 'btn-primary' : 'btn-outline-primary'; ?>"
                            >
                                <?php echo e(ucfirst($filter)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="small text-muted mb-2">Filter by payment status</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($allowedPaymentFilters as $filter): ?>
                            <a
                                href="<?php echo BASE_URL; ?>customer/bookings.php?type=<?php echo e($typeFilter); ?>&payment=<?php echo e($filter); ?>"
                                class="btn btn-sm <?php echo $paymentFilter === $filter ? 'btn-dark' : 'btn-outline-dark'; ?>"
                            >
                                <?php echo e(customer_history_format_status($filter)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info rounded-4">
            No booking history found for the selected filters.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($rows as $row): ?>
                <?php
                $canRequestRefund = (
                    ($row['payment_status'] ?? '') === 'paid'
                    && ($row['booking_status'] ?? '') !== 'cancelled'
                    && !in_array((string)($row['refund_request_status'] ?? ''), ['pending', 'approved'], true)
                );
                ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <span class="badge <?php echo ($row['booking_type'] === 'bus') ? 'bg-primary' : 'bg-info text-dark'; ?>">
                                            <?php echo e(strtoupper($row['booking_type'])); ?>
                                        </span>

                                        <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['booking_status'])); ?>">
                                            Booking: <?php echo e(customer_history_format_status((string)$row['booking_status'])); ?>
                                        </span>

                                        <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['payment_status'])); ?>">
                                            Payment: <?php echo e(customer_history_format_status((string)$row['payment_status'])); ?>
                                        </span>

                                        <?php if (!empty($row['refund_request_status'])): ?>
                                            <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['refund_request_status'])); ?>">
                                                Refund: <?php echo e(customer_history_format_status((string)$row['refund_request_status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <h5 class="fw-bold mb-2"><?php echo e($row['booking_code']); ?></h5>

                                    <div class="text-muted small mb-3">
                                        Booked at: <?php echo e(date('Y-m-d H:i', strtotime((string)$row['booked_at']))); ?>
                                    </div>

                                    <?php if ($row['booking_type'] === 'bus'): ?>
                                        <div class="row g-2">
                                            <div class="col-md-4"><strong>Company:</strong> <?php echo e($row['company_name']); ?></div>
                                            <div class="col-md-4"><strong>Route:</strong> <?php echo e($row['from_city_name']); ?> → <?php echo e($row['to_city_name']); ?></div>
                                            <div class="col-md-4"><strong>Bus:</strong> <?php echo e($row['bus_number']); ?></div>
                                            <div class="col-md-4"><strong>Trip Date:</strong> <?php echo e($row['trip_date']); ?></div>
                                            <div class="col-md-4"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$row['departure_datetime']))); ?></div>
                                            <div class="col-md-4"><strong>Arrival:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$row['arrival_datetime']))); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="row g-2">
                                            <div class="col-md-4"><strong>Company:</strong> <?php echo e($row['company_name']); ?></div>
                                            <div class="col-md-4"><strong>Package:</strong> <?php echo e($row['package_title']); ?></div>
                                            <div class="col-md-4"><strong>Duration:</strong> <?php echo e((int)($row['duration_days'] ?? 0)); ?> day(s)</div>
                                            <div class="col-md-6"><strong>Batch:</strong> <?php echo e($row['start_date']); ?> to <?php echo e($row['end_date']); ?></div>
                                            <div class="col-md-6"><strong>Batch Status:</strong> <?php echo e(customer_history_format_status((string)($row['batch_status'] ?? ''))); ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="row g-2 mt-2">
                                        <div class="col-md-3"><strong>Passengers:</strong> <?php echo e((int)$row['passenger_count']); ?></div>
                                        <div class="col-md-3"><strong>Total:</strong> <?php echo e(number_format((float)$row['total_amount'], 2)); ?> MMK</div>

                                        <?php if ($row['booking_type'] === 'bus' && !empty($row['ticket_no'])): ?>
                                            <div class="col-md-6"><strong>Ticket No:</strong> <?php echo e($row['ticket_no']); ?></div>
                                        <?php elseif ($row['booking_type'] === 'tour' && !empty($row['voucher_code'])): ?>
                                            <div class="col-md-6"><strong>Voucher Code:</strong> <?php echo e($row['voucher_code']); ?></div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($row['refund_request_code'])): ?>
                                        <div class="mt-3 small text-muted">
                                            <strong>Refund Request Code:</strong> <?php echo e($row['refund_request_code']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex flex-column gap-2" style="min-width: 230px;">
                                    <?php if (in_array($row['payment_status'], ['unpaid', 'failed', 'rejected'], true)): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-warning">
                                            Pay Now
                                        </a>
                                    <?php elseif ($row['payment_status'] === 'pending_review'): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-warning">
                                            View Payment Submission
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($row['booking_type'] === 'bus'): ?>
                                        <?php if (!empty($row['ticket_id'])): ?>
                                            <a href="<?php echo BASE_URL; ?>customer/ticket.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-primary">
                                                View Ticket
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($row['voucher_id'])): ?>
                                            <a href="<?php echo BASE_URL; ?>customer/voucher.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-info text-dark">
                                                View Voucher
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($canRequestRefund): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/refund_request.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-danger">
                                            Request Refund
                                        </a>
                                    <?php elseif (!empty($row['refund_request_status'])): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/refund_request.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-secondary">
                                            View Refund Status
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>