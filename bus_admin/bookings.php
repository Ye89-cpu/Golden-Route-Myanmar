<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/bus_booking_helper.php';

require_role('bus_admin');

$page_title = 'Bus Booking Management';

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$tripDate = trim($_GET['trip_date'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? 'all');
$bookingStatus = trim($_GET['booking_status'] ?? 'all');

$allowedPaymentStatuses = ['all', 'unpaid', 'pending_review', 'paid', 'failed', 'refunded'];
$allowedBookingStatuses = ['all', 'pending', 'confirmed', 'paid', 'cancelled', 'completed'];

if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) {
    $paymentStatus = 'all';
}

if (!in_array($bookingStatus, $allowedBookingStatuses, true)) {
    $bookingStatus = 'all';
}

try {
    $summary = fetch_bus_admin_booking_summary($conn, (int)$company['company_id']);
    $rows = fetch_bus_admin_bookings($conn, (int)$company['company_id'], [
        'trip_date' => $tripDate,
        'payment_status' => $paymentStatus,
        'booking_status' => $bookingStatus,
    ]);
} catch (Throwable $e) {
    $conn->close();
    die('Bus booking management error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Bus Booking Management</h2>
            <p class="text-muted mb-0">Company: <?php echo e($company['company_name']); ?></p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>bus_admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
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
                    <div class="small text-muted">Total Bookings</div>
                    <div class="fs-3 fw-bold"><?php echo e($summary['total_bookings']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Paid</div>
                    <div class="fs-3 fw-bold text-success"><?php echo e($summary['paid_bookings']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Pending Review</div>
                    <div class="fs-3 fw-bold text-warning"><?php echo e($summary['pending_review_bookings']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Paid Amount</div>
                    <div class="fs-6 fw-bold"><?php echo e(number_format((float)$summary['paid_amount'], 2)); ?> MMK</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Trip Date</label>
                    <input type="date" name="trip_date" class="form-control" value="<?php echo e($tripDate); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        <?php foreach ($allowedPaymentStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $paymentStatus === $status ? 'selected' : ''; ?>>
                                <?php echo e(bus_booking_format_status($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Booking Status</label>
                    <select name="booking_status" class="form-select">
                        <?php foreach ($allowedBookingStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $bookingStatus === $status ? 'selected' : ''; ?>>
                                <?php echo e(bus_booking_format_status($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="<?php echo BASE_URL; ?>bus_admin/bookings.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($rows)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No bus bookings found.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Route / Trip</th>
                                <th>Passengers</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="min-width: 260px;">Actions</th>
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
                                        <div class="small text-muted"><?php echo e($row['customer_email']); ?></div>
                                        <div class="small text-muted"><?php echo e($row['customer_phone'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['from_city_name']); ?> → <?php echo e($row['to_city_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo e($row['trip_date']); ?> |
                                            <?php echo e(date('H:i', strtotime((string)$row['departure_datetime']))); ?> |
                                            Bus <?php echo e($row['bus_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['passenger_count']); ?> pax</div>
                                        <div class="small text-muted">Passengers rows: <?php echo e($row['passenger_rows']); ?></div>
                                        <div class="small text-muted">Seats: <?php echo e($row['seat_rows']); ?></div>
                                    </td>
                                    <td><?php echo e(number_format((float)$row['total_amount'], 2)); ?> MMK</td>
                                    <td>
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$row['booking_status'])); ?>">
                                                <?php echo e(bus_booking_format_status((string)$row['booking_status'])); ?>
                                            </span>
                                        </div>
                                        <div class="mb-1">
                                            <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$row['payment_status'])); ?>">
                                                <?php echo e(bus_booking_format_status((string)$row['payment_status'])); ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($row['refund_request_status'])): ?>
                                            <div>
                                                <span class="badge bg-<?php echo e(bus_booking_badge_class((string)$row['refund_request_status'])); ?>">
                                                    Refund: <?php echo e(bus_booking_format_status((string)$row['refund_request_status'])); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="<?php echo BASE_URL; ?>bus_admin/booking_detail.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                View Detail
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>bus_admin/manifest.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-sm btn-outline-dark">
                                                Manifest
                                            </a>
                                            <?php if (!empty($row['ticket_pdf_file'])): ?>
                                                <a href="<?php echo BASE_URL . e($row['ticket_pdf_file']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                                    Ticket PDF
                                                </a>
                                            <?php endif; ?>
                                        </div>
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