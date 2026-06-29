<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/report_helper.php';

require_role('tour_admin');

$page_title = 'Tour Business Reports';

$startDateInput = $_GET['start_date'] ?? report_default_start_date();
$endDateInput = $_GET['end_date'] ?? report_default_end_date();
[$startDate, $endDate] = report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();
require_company_permission($conn, 'view_business_reports');
$company = require_tour_admin_company($conn);
$companyId = (int)$company['company_id'];

$summary = report_fetch_tour_company_summary($conn, $companyId, $startDate, $endDate);
$packageRows = report_fetch_tour_company_package_breakdown($conn, $companyId, $startDate, $endDate, 50);
$bookingRows = report_fetch_tour_company_recent_bookings($conn, $companyId, $startDate, $endDate, 100);
$paymentRows = report_fetch_tour_company_recent_payments($conn, $companyId, $startDate, $endDate, 50);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <span class="section-kicker">Tour Admin</span>
            <h2 class="fw-bold mb-1">Business Reports</h2>
            <p class="text-muted mb-0">Tour package bookings, payments, refunds and revenue for <?php echo e($company['company_name']); ?>.</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex gap-2 flex-wrap">
            <a href="<?php echo BASE_URL; ?>tour_admin/business_reports_pdf.php?start_date=<?php echo e($startDate); ?>&end_date=<?php echo e($endDate); ?>" class="btn btn-danger" target="_blank">Export PDF</a>
            <button type="button" class="btn btn-outline-primary" onclick="window.print();">Print</button>
            <a href="<?php echo BASE_URL; ?>tour_admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

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
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <a href="<?php echo BASE_URL; ?>tour_admin/business_reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Total Tour Bookings</span><strong><?php echo e($summary['total_bookings']); ?></strong><small>Paid: <?php echo e($summary['paid_bookings']); ?> | Pending: <?php echo e($summary['pending_review_bookings']); ?></small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Gross Revenue</span><strong><?php echo e(number_format((float)$summary['gross_revenue'], 2)); ?></strong><small>MMK paid bookings</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Verified Payments</span><strong><?php echo e($summary['verified_payments']); ?></strong><small><?php echo e(number_format((float)$summary['verified_payment_amount'], 2)); ?> MMK</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Tour Utilization</span><strong><?php echo e($summary['tour_utilization_percent']); ?>%</strong><small><?php echo e($summary['tour_sold_slots']); ?> / <?php echo e($summary['tour_capacity']); ?> slots</small></div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Refunded Bookings</div><div class="fs-4 fw-bold text-danger"><?php echo e($summary['refunded_bookings']); ?></div><div class="small text-muted"><?php echo e(number_format((float)$summary['refunded_amount'], 2)); ?> MMK</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Submitted Payments</div><div class="fs-4 fw-bold text-warning"><?php echo e($summary['submitted_payments']); ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Rejected Payments</div><div class="fs-4 fw-bold text-danger"><?php echo e($summary['rejected_payments']); ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Tour Batches</div><div class="fs-4 fw-bold"><?php echo e($summary['tour_batch_count']); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Package Business Table</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Package</th><th>Bookings</th><th>Passengers</th><th>Pending Payments</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($packageRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($row['package_title']); ?></td>
                            <td><?php echo e($row['booking_count']); ?></td>
                            <td><?php echo e($row['passengers']); ?></td>
                            <td><?php echo e($row['pending_review']); ?></td>
                            <td><?php echo e(number_format((float)$row['revenue'], 2)); ?> MMK</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($packageRows)): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">No package records found for this date range.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Recent Tour Bookings</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Package</th><th>Customer</th><th>Travel Date</th><th>Passengers</th><th>Amount</th><th>Booking</th><th>Payment</th><th>Booked At</th></tr></thead>
                    <tbody>
                    <?php foreach ($bookingRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($row['booking_code']); ?></td>
                            <td><?php echo e($row['package_title']); ?></td>
                            <td><?php echo e($row['customer_name']); ?></td>
                            <td><?php echo e($row['start_date']); ?></td>
                            <td><?php echo e($row['passenger_count']); ?></td>
                            <td><?php echo e(number_format((float)$row['total_amount'], 2)); ?> MMK</td>
                            <td><?php echo e(ucfirst((string)$row['booking_status'])); ?></td>
                            <td><?php echo e(ucwords(str_replace('_', ' ', (string)$row['payment_status']))); ?></td>
                            <td><?php echo e(date('Y-m-d', strtotime((string)$row['booked_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($bookingRows)): ?>
                        <tr><td colspan="9" class="text-muted text-center py-4">No bookings found for this date range.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Payment Table</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Booking</th><th>Package</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    <?php foreach ($paymentRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($row['booking_code']); ?></td>
                            <td><?php echo e($row['package_title']); ?></td>
                            <td><?php echo e($row['customer_name']); ?></td>
                            <td><?php echo e(ucwords(str_replace('_', ' ', (string)$row['payment_method']))); ?></td>
                            <td><?php echo e(number_format((float)$row['amount'], 2)); ?> MMK</td>
                            <td><span class="badge bg-<?php echo e(report_payment_badge_class((string)$row['status'])); ?>"><?php echo e(ucfirst((string)$row['status'])); ?></span></td>
                            <td><?php echo e(date('Y-m-d', strtotime((string)$row['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($paymentRows)): ?>
                        <tr><td colspan="7" class="text-muted text-center py-4">No payment records found for this date range.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
