<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_helper.php';

require_role('super_admin');

$page_title = 'Business Reports';

$startDateInput = $_GET['start_date'] ?? report_default_start_date();
$endDateInput = $_GET['end_date'] ?? report_default_end_date();
[$startDate, $endDate] = report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();
$summary = report_fetch_summary($conn, $startDate, $endDate);

$companyRows = [];
$companySql = "
    SELECT
        c.id,
        c.name AS company_name,
        c.company_type,
        COUNT(DISTINCT x.booking_id) AS total_bookings,
        COALESCE(SUM(x.booking_type = 'bus'), 0) AS bus_bookings,
        COALESCE(SUM(x.booking_type = 'tour'), 0) AS tour_bookings,
        COALESCE(SUM(x.payment_status = 'paid'), 0) AS paid_bookings,
        COALESCE(SUM(x.payment_status = 'pending_review'), 0) AS pending_review,
        COALESCE(SUM(CASE WHEN x.payment_status = 'paid' THEN x.total_amount ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(p.status = 'submitted'), 0) AS submitted_payments,
        COALESCE(SUM(p.status = 'verified'), 0) AS verified_payments,
        COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END), 0) AS verified_payment_amount
    FROM companies c
    LEFT JOIN (
        SELECT
            t.company_id,
            b.id AS booking_id,
            b.booking_type,
            b.payment_status,
            b.total_amount
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        WHERE b.booking_type = 'bus'
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
        UNION ALL
        SELECT
            tp.company_id,
            b.id AS booking_id,
            b.booking_type,
            b.payment_status,
            b.total_amount
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE b.booking_type = 'tour'
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
    ) x ON x.company_id = c.id
    LEFT JOIN payments p ON p.booking_id = x.booking_id
    WHERE c.status = 'approved'
    GROUP BY c.id, c.name, c.company_type
    ORDER BY revenue DESC, total_bookings DESC, c.name ASC
";
$stmt = $conn->prepare($companySql);
$stmt->bind_param('ssss', $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $companyRows[] = $row;
}
$stmt->close();

$bookingRows = [];
$bookingSql = "
    SELECT
        b.id,
        b.booking_code,
        b.booking_type,
        b.passenger_count,
        b.total_amount,
        b.status AS booking_status,
        b.payment_status,
        b.booked_at,
        u.name AS customer_name,
        COALESCE(bus_company.name, tour_company.name, '-') AS company_name
    FROM bookings b
    INNER JOIN users u ON u.id = b.user_id
    LEFT JOIN trips t ON t.id = b.trip_id
    LEFT JOIN companies bus_company ON bus_company.id = t.company_id
    LEFT JOIN tour_batches tb ON tb.id = b.tour_batch_id
    LEFT JOIN tour_packages tp ON tp.id = tb.tour_package_id
    LEFT JOIN companies tour_company ON tour_company.id = tp.company_id
    WHERE DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
    ORDER BY b.id DESC
    LIMIT 100
";
$stmt = $conn->prepare($bookingSql);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $bookingRows[] = $row;
}
$stmt->close();

$paymentRows = report_fetch_recent_payments($conn, $startDate, $endDate, 50);
$tourRows = report_fetch_tour_package_breakdown($conn, $startDate, $endDate, 50);

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Business Reports</h2>
            <p class="text-muted mb-0">Bookings, payments, tour package payments and company business summary.</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex gap-2 flex-wrap">
            <a href="<?php echo BASE_URL; ?>admin/business_reports_pdf.php?start_date=<?php echo e($startDate); ?>&end_date=<?php echo e($endDate); ?>" class="btn btn-danger" target="_blank">Export PDF</a>
            <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-primary">Old Reports</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

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
                    <a href="<?php echo BASE_URL; ?>admin/business_reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Total Bookings</span><strong><?php echo e($summary['total_bookings']); ?></strong><small>Bus: <?php echo e($summary['bus_bookings']); ?> | Tour: <?php echo e($summary['tour_bookings']); ?></small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Gross Revenue</span><strong><?php echo e(number_format((float)$summary['gross_revenue'], 2)); ?></strong><small>MMK paid bookings</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Verified Payments</span><strong><?php echo e($summary['verified_payments']); ?></strong><small><?php echo e(number_format((float)$summary['verified_payment_amount'], 2)); ?> MMK</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="metric-card"><span>Pending Review</span><strong><?php echo e($summary['pending_review_bookings']); ?></strong><small>Submitted payment proofs</small></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Company Business Table</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Company</th><th>Type</th><th>Bookings</th><th>Bus</th><th>Tour</th><th>Paid</th><th>Pending</th><th>Revenue</th><th>Verified Payment</th></tr></thead>
                    <tbody>
                    <?php foreach ($companyRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($row['company_name']); ?></td>
                            <td><?php echo e(ucwords(str_replace('_', ' ', $row['company_type']))); ?></td>
                            <td><?php echo e($row['total_bookings']); ?></td>
                            <td><?php echo e($row['bus_bookings']); ?></td>
                            <td><?php echo e($row['tour_bookings']); ?></td>
                            <td><?php echo e($row['paid_bookings']); ?></td>
                            <td><?php echo e($row['pending_review']); ?></td>
                            <td><?php echo e(number_format((float)$row['revenue'], 2)); ?> MMK</td>
                            <td><?php echo e(number_format((float)$row['verified_payment_amount'], 2)); ?> MMK</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Recent Bookings</h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Code</th><th>Type</th><th>Customer</th><th>Company</th><th>Passengers</th><th>Amount</th><th>Booking</th><th>Payment</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($bookingRows as $row): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($row['booking_code']); ?></td>
                            <td><?php echo e(strtoupper($row['booking_type'])); ?></td>
                            <td><?php echo e($row['customer_name']); ?></td>
                            <td><?php echo e($row['company_name']); ?></td>
                            <td><?php echo e($row['passenger_count']); ?></td>
                            <td><?php echo e(number_format((float)$row['total_amount'], 2)); ?> MMK</td>
                            <td><?php echo e(ucfirst($row['booking_status'])); ?></td>
                            <td><?php echo e(ucwords(str_replace('_', ' ', $row['payment_status']))); ?></td>
                            <td><?php echo e(date('Y-m-d', strtotime((string)$row['booked_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Tour Package Payments</h5></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Package</th><th>Company</th><th>Bookings</th><th>Passengers</th><th>Revenue</th></tr></thead>
                            <tbody>
                            <?php foreach ($tourRows as $row): ?>
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
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-0">
                    <div class="p-4 border-bottom"><h5 class="fw-bold mb-0">Payment Table</h5></div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Booking</th><th>Type</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($paymentRows as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($row['booking_code']); ?></td>
                                    <td><?php echo e(strtoupper($row['booking_type'])); ?></td>
                                    <td><?php echo e($row['customer_name']); ?></td>
                                    <td><?php echo e(ucwords(str_replace('_', ' ', $row['payment_method']))); ?></td>
                                    <td><?php echo e(number_format((float)$row['amount'], 2)); ?> MMK</td>
                                    <td><span class="badge bg-<?php echo e(report_payment_badge_class((string)$row['status'])); ?>"><?php echo e(ucfirst($row['status'])); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
