<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';

require_role('tour_admin');

$page_title = 'Tour Admin Dashboard';

$user = current_user();
$company = null;
$canViewReports = false;
$canManageTourPayments = false;
$canManageTourRefunds = false;
$stats = [
    'packages_total' => 0,
    'packages_active' => 0,
    'packages_inactive' => 0,
    'batches_total' => 0,
    'batches_open' => 0,
];

$conn = getDBConnection();
$company = get_tour_admin_company($conn, (int)current_user_id());

if ($company) {
    $canViewReports = user_has_company_permission($conn, 'view_business_reports');
    $canManageTourPayments = user_has_company_permission($conn, 'manage_tour_payments');
    $canManageTourRefunds = user_has_company_permission($conn, 'manage_tour_refunds');

    $packageStatsSql = "
        SELECT
            COUNT(*) AS total_packages,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_packages,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive_packages
        FROM tour_packages
        WHERE company_id = ?
    ";
    $packageStatsStmt = $conn->prepare($packageStatsSql);
    $packageStatsStmt->bind_param('i', $company['company_id']);
    $packageStatsStmt->execute();
    $packageStats = $packageStatsStmt->get_result()->fetch_assoc() ?: [];
    $packageStatsStmt->close();

    $batchStatsSql = "
        SELECT
            COUNT(tb.id) AS total_batches,
            SUM(CASE WHEN tb.status = 'open' THEN 1 ELSE 0 END) AS open_batches
        FROM tour_batches tb
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE tp.company_id = ?
    ";
    $batchStatsStmt = $conn->prepare($batchStatsSql);
    $batchStatsStmt->bind_param('i', $company['company_id']);
    $batchStatsStmt->execute();
    $batchStats = $batchStatsStmt->get_result()->fetch_assoc() ?: [];
    $batchStatsStmt->close();

    $stats = [
        'packages_total' => (int)($packageStats['total_packages'] ?? 0),
        'packages_active' => (int)($packageStats['active_packages'] ?? 0),
        'packages_inactive' => (int)($packageStats['inactive_packages'] ?? 0),
        'batches_total' => (int)($batchStats['total_batches'] ?? 0),
        'batches_open' => (int)($batchStats['open_batches'] ?? 0),
    ];
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h2 class="fw-bold mb-3">Tour Operator Admin Dashboard</h2>
            <p><strong>Name:</strong> <?php echo e($user['name']); ?></p>
            <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
            <p><strong>Role:</strong> <?php echo e($user['role']); ?></p>
            <?php if ($company): ?>
                <p><strong>Company:</strong> <?php echo e($company['company_name']); ?></p>
            <?php endif; ?>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-danger">Logout</a>
                <a href="<?php echo BASE_URL; ?>tour_admin/packages.php" class="btn btn-outline-primary">
                    Manage Tour Packages
                </a>
                <a href="<?php echo BASE_URL; ?>tour_admin/packages.php#package-form" class="btn btn-outline-success">
                    Add Tour Package
                </a>
                <a href="<?php echo BASE_URL; ?>tour_admin/bookings.php" class="btn btn-outline-primary">
                    Manage Bookings
                </a>
                <a href="<?php echo BASE_URL; ?>tour_admin/voucher_checkin.php" class="btn btn-outline-dark">
                    Voucher Check-in
                </a>
                <?php if ($canViewReports): ?>
                    <a href="<?php echo BASE_URL; ?>tour_admin/business_reports.php" class="btn btn-outline-danger">
                        Business Reports
                    </a>
                <?php endif; ?>
                <?php if ($canManageTourPayments): ?>
                    <a href="<?php echo BASE_URL; ?>tour_admin/payments.php" class="btn btn-outline-success">
                        Package Payments
                    </a>
                <?php endif; ?>
                <?php if ($canManageTourRefunds): ?>
                    <a href="<?php echo BASE_URL; ?>tour_admin/refund_requests.php" class="btn btn-outline-warning">
                        Refund Requests
                    </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-dark">Notifications</a>
            </div>
        </div>
    </div>

    <?php if (!$company): ?>
        <div class="alert alert-warning rounded-4 shadow-sm">
            No approved tour company is assigned to your account yet. Please contact the system administrator.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small text-uppercase fw-semibold">Total Packages</div>
                        <div class="display-6 fw-bold mb-0"><?php echo e($stats['packages_total']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small text-uppercase fw-semibold">Active Packages</div>
                        <div class="display-6 fw-bold mb-0"><?php echo e($stats['packages_active']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small text-uppercase fw-semibold">Open Batches</div>
                        <div class="display-6 fw-bold mb-0"><?php echo e($stats['batches_open']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="text-muted small text-uppercase fw-semibold">Total Batches</div>
                        <div class="display-6 fw-bold mb-0"><?php echo e($stats['batches_total']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
                    <div>
                        <h4 class="fw-bold mb-2">Tour Package CRUD</h4>
                        <p class="text-muted mb-0">
                            Create new tour packages, edit existing package information, manage batches, or delete packages that have no bookings.
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo BASE_URL; ?>tour_admin/packages.php#package-form" class="btn btn-primary">
                            Add Package
                        </a>
                        <a href="<?php echo BASE_URL; ?>tour_admin/packages.php" class="btn btn-outline-primary">
                            Edit / Delete Packages
                        </a>
                    </div>
                </div>
            </div>
        </div>


        <div class="row g-4 mt-1">
            <?php if ($canViewReports): ?>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold">Business Reports</h5>
                            <p class="text-muted">Generate tour package revenue, payment and booking reports for your company.</p>
                            <a href="<?php echo BASE_URL; ?>tour_admin/business_reports.php" class="btn btn-danger">Open Reports</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canManageTourPayments): ?>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold">Package Payments</h5>
                            <p class="text-muted">Verify or reject customer payment proofs for your own tour package bookings.</p>
                            <a href="<?php echo BASE_URL; ?>tour_admin/payments.php" class="btn btn-success">Review Payments</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canManageTourRefunds): ?>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold">Refund Requests</h5>
                            <p class="text-muted">Approve or reject refund requests for your own tour package bookings.</p>
                            <a href="<?php echo BASE_URL; ?>tour_admin/refund_requests.php" class="btn btn-warning">Review Refunds</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
