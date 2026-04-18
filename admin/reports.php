<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/admin/reports.php

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_helper.php';

require_role('super_admin');

$page_title = 'Reports Dashboard';

$startDateInput = $_GET['start_date'] ?? report_default_start_date();
$endDateInput = $_GET['end_date'] ?? report_default_end_date();

[$startDate, $endDate] = report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();

$summary = report_fetch_summary($conn, $startDate, $endDate);
$recentPayments = report_fetch_recent_payments($conn, $startDate, $endDate, 15);
$busRouteRows = report_fetch_bus_route_breakdown($conn, $startDate, $endDate, 10);
$tourPackageRows = report_fetch_tour_package_breakdown($conn, $startDate, $endDate, 10);

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Admin Reports Dashboard</h2>
            <p class="text-muted mb-0">
                Bus + Tour sales, bookings, payments, occupancy summary
            </p>
        </div>

        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">
                Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Total Bookings</div>
                    <div class="fs-3 fw-bold"><?php echo e($summary['total_bookings']); ?></div>
                    <div class="small text-muted mt-2">
                        Bus: <?php echo e($summary['bus_bookings']); ?> |
                        Tour: <?php echo e($summary['tour_bookings']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Gross Revenue</div>
                    <div class="fs-4 fw-bold text-success">
                        <?php echo e(number_format((float)$summary['gross_revenue'], 2)); ?> MMK
                    </div>
                    <div class="small text-muted mt-2">
                        Paid bookings: <?php echo e($summary['paid_bookings']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Pending Review</div>
                    <div class="fs-3 fw-bold text-warning">
                        <?php echo e($summary['pending_review_bookings']); ?>
                    </div>
                    <div class="small text-muted mt-2">
                        Submitted payments: <?php echo e($summary['submitted_payments']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Refunded Amount</div>
                    <div class="fs-4 fw-bold text-danger">
                        <?php echo e(number_format((float)$summary['refunded_amount'], 2)); ?> MMK
                    </div>
                    <div class="small text-muted mt-2">
                        Refunded bookings: <?php echo e($summary['refunded_bookings']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Verified Payments</div>
                    <div class="fs-3 fw-bold text-success"><?php echo e($summary['verified_payments']); ?></div>
                    <div class="small text-muted mt-2">
                        <?php echo e(number_format((float)$summary['verified_payment_amount'], 2)); ?> MMK
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Rejected Payments</div>
                    <div class="fs-3 fw-bold text-danger"><?php echo e($summary['rejected_payments']); ?></div>
                    <div class="small text-muted mt-2">
                        Total payments: <?php echo e($summary['total_payments']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Tickets Generated</div>
                    <div class="fs-3 fw-bold text-primary"><?php echo e($summary['tickets_generated']); ?></div>
                    <div class="small text-muted mt-2">
                        Vouchers: <?php echo e($summary['vouchers_generated']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Bus Occupancy</div>
                    <div class="fs-3 fw-bold">
                        <?php echo e(number_format((float)$summary['bus_occupancy_percent'], 2)); ?>%
                    </div>
                    <div class="small text-muted mt-2">
                        Sold: <?php echo e($summary['bus_sold_seats']); ?> / Capacity: <?php echo e($summary['bus_capacity']); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Bus Capacity & Sales</h5>
                    <div class="mb-2"><strong>Total Trips:</strong> <?php echo e($summary['bus_trip_count']); ?></div>
                    <div class="mb-2"><strong>Total Capacity:</strong> <?php echo e($summary['bus_capacity']); ?></div>
                    <div class="mb-2"><strong>Sold Seats:</strong> <?php echo e($summary['bus_sold_seats']); ?></div>

                    <div class="progress mt-3" style="height: 22px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo e(min(100, (float)$summary['bus_occupancy_percent'])); ?>%;">
                            <?php echo e(number_format((float)$summary['bus_occupancy_percent'], 2)); ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Tour Capacity & Sales</h5>
                    <div class="mb-2"><strong>Total Batches:</strong> <?php echo e($summary['tour_batch_count']); ?></div>
                    <div class="mb-2"><strong>Total Capacity:</strong> <?php echo e($summary['tour_capacity']); ?></div>
                    <div class="mb-2"><strong>Sold Slots:</strong> <?php echo e($summary['tour_sold_slots']); ?></div>

                    <div class="progress mt-3" style="height: 22px;">
                        <div class="progress-bar bg-info text-dark" role="progressbar" style="width: <?php echo e(min(100, (float)$summary['tour_utilization_percent'])); ?>%;">
                            <?php echo e(number_format((float)$summary['tour_utilization_percent'], 2)); ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Top Bus Routes</h5>
                    </div>

                    <?php if (empty($busRouteRows)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No bus report data found in this date range.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Route</th>
                                        <th>Company</th>
                                        <th>Bookings</th>
                                        <th>Seats</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($busRouteRows as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($row['route_name']); ?></td>
                                            <td><?php echo e($row['company_name']); ?></td>
                                            <td><?php echo e($row['booking_count']); ?></td>
                                            <td><?php echo e($row['seats_sold']); ?></td>
                                            <td><?php echo e(number_format((float)$row['revenue'], 2)); ?> MMK</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom">
                        <h5 class="fw-bold mb-0">Top Tour Packages</h5>
                    </div>

                    <?php if (empty($tourPackageRows)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No tour report data found in this date range.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Package</th>
                                        <th>Company</th>
                                        <th>Bookings</th>
                                        <th>Passengers</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tourPackageRows as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($row['package_title']); ?></td>
                                            <td><?php echo e($row['company_name']); ?></td>
                                            <td><?php echo e($row['booking_count']); ?></td>
                                            <td><?php echo e($row['passengers']); ?></td>
                                            <td><?php echo e(number_format((float)$row['revenue'], 2)); ?> MMK</td>
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

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0">Recent Payments</h5>
            </div>

            <?php if (empty($recentPayments)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No payments found in this date range.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Booking</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td><?php echo e($payment['id']); ?></td>
                                    <td class="fw-semibold"><?php echo e($payment['booking_code']); ?></td>
                                    <td><?php echo e(strtoupper($payment['booking_type'])); ?></td>
                                    <td>
                                        <div><?php echo e($payment['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($payment['customer_email']); ?></div>
                                    </td>
                                    <td><?php echo e(ucwords(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                    <td><?php echo e(number_format((float)$payment['amount'], 2)); ?> MMK</td>
                                    <td>
                                        <span class="badge bg-<?php echo e(report_payment_badge_class((string)$payment['status'])); ?>">
                                            <?php echo e(ucfirst($payment['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e(date('Y-m-d H:i', strtotime((string)$payment['created_at']))); ?></td>
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