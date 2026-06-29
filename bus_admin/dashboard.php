<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/bus_booking_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('bus_admin');

$page_title = 'Bus Admin Dashboard';

$user = current_user();
$conn = getDBConnection();

$canManageBuses = false;
$canManageBookings = false;
$canApproveBookings = false;
$canViewTicket = false;
$canManageRoutes = false;
$canManageSchedules = false;

try {
    if (function_exists('user_has_company_permission')) {
        $canManageBuses = user_has_company_permission($conn, 'manage_buses');
        $canManageBookings = user_has_company_permission($conn, 'manage_bookings');
        $canApproveBookings = user_has_company_permission($conn, 'approve_bookings');
        $canViewTicket = user_has_company_permission($conn, 'view_ticket');
        $canManageRoutes = user_has_company_permission($conn, 'manage_routes');
        $canManageSchedules = user_has_company_permission($conn, 'manage_schedules');
    }
} catch (Throwable $e) {
    $canManageBuses = false;
    $canManageBookings = false;
    $canApproveBookings = false;
    $canViewTicket = false;
    $canManageRoutes = false;
    $canManageSchedules = false;
}

$company = null;
if (function_exists('get_bus_admin_company')) {
    $company = get_bus_admin_company($conn, (int) current_user_id());
}

$summary = [
    'total_buses' => 0,
    'active_buses' => 0,
    'maintenance_buses' => 0,
    'inactive_buses' => 0,
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'paid_bookings' => 0,
];

if ($company) {
    $companyId = (int)($company['company_id'] ?? 0);

    $busSql = "
        SELECT
            COUNT(*) AS total_buses,
            COALESCE(SUM(status = 'active'), 0) AS active_buses,
            COALESCE(SUM(status = 'maintenance'), 0) AS maintenance_buses,
            COALESCE(SUM(status = 'inactive'), 0) AS inactive_buses
        FROM buses
        WHERE company_id = ?
    ";
    $stmt = $conn->prepare($busSql);
    if ($stmt) {
        $stmt->bind_param('i', $companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($row) {
            $summary['total_buses'] = (int)($row['total_buses'] ?? 0);
            $summary['active_buses'] = (int)($row['active_buses'] ?? 0);
            $summary['maintenance_buses'] = (int)($row['maintenance_buses'] ?? 0);
            $summary['inactive_buses'] = (int)($row['inactive_buses'] ?? 0);
        }
        $stmt->close();
    }

    try {
        $bookingSummary = fetch_bus_admin_booking_summary($conn, $companyId);
        $summary['total_bookings'] = (int)($bookingSummary['total_bookings'] ?? 0);
        $summary['pending_bookings'] = (int)($bookingSummary['pending_review_bookings'] ?? 0);
        $summary['paid_bookings'] = (int)($bookingSummary['paid_bookings'] ?? 0);
    } catch (Throwable $e) {
        // Keep dashboard accessible even if booking summary fails.
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="admin-hero-panel mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <span class="section-kicker">Bus Admin</span>
                <h1 class="page-title mb-2">Company Dashboard</h1>
                <p class="page-subtitle mb-0">
                    Welcome, <?php echo e($user['name'] ?? 'Bus Admin'); ?>.
                    Manage only your assigned company’s buses, routes, schedules, bookings, and tickets.
                </p>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-2">
                    <?php if ($canManageBuses): ?>
                        <a href="<?php echo BASE_URL; ?>bus_admin/buses.php" class="btn btn-brand">Manage Buses</a>
                    <?php endif; ?>
                    <?php if ($canManageBookings): ?>
                        <a href="<?php echo BASE_URL; ?>bus_admin/bookings.php" class="btn btn-nav-soft">Manage Bookings</a>
                    <?php endif; ?>
                    <?php if (!$canManageBuses && !$canManageBookings): ?>
                        <div class="alert alert-warning mb-0">No bus admin actions are enabled for this account.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$company): ?>
        <div class="alert alert-warning rounded-4">
            Your account is not linked to any approved bus company yet.
            Super Admin needs to create/approve a company and assign your user in <code>company_users</code>.
        </div>
    <?php else: ?>
        <div class="panel-card mb-4">
            <div class="panel-card-header">
                <h4>Assigned Company</h4>
                <p>This dashboard is filtered by your company only.</p>
            </div>

            <div class="summary-list">
                <div class="summary-row">
                    <span>Company</span>
                    <strong><?php echo e($company['company_name'] ?? '-'); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Company Type</span>
                    <strong><?php echo e($company['company_type'] ?? '-'); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Role in Company</span>
                    <strong><?php echo e($company['role_in_company'] ?? '-'); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Status</span>
                    <strong><?php echo e($company['company_status'] ?? '-'); ?></strong>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="metric-card">
                    <span>Total Buses</span>
                    <strong><?php echo e($summary['total_buses']); ?></strong>
                    <small>All buses in this company</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="metric-card">
                    <span>Active Buses</span>
                    <strong><?php echo e($summary['active_buses']); ?></strong>
                    <small>Currently running</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="metric-card">
                    <span>Maintenance</span>
                    <strong><?php echo e($summary['maintenance_buses']); ?></strong>
                    <small>Under maintenance</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="metric-card">
                    <span>Inactive</span>
                    <strong><?php echo e($summary['inactive_buses']); ?></strong>
                    <small>Not active now</small>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <span>Total Bookings</span>
                    <strong><?php echo e($summary['total_bookings']); ?></strong>
                    <small>Bookings for this company only</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <span>Pending Bookings</span>
                    <strong><?php echo e($summary['pending_bookings']); ?></strong>
                    <small>Need action / follow-up</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <span>Paid Bookings</span>
                    <strong><?php echo e($summary['paid_bookings']); ?></strong>
                    <small>Confirmed payments</small>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <div class="panel-card-header">
                <h4>Quick Actions</h4>
                <p>Permissions and company type can control which actions appear here.</p>
            </div>

            <div class="quick-link-grid">
                <?php if ($canManageBuses): ?>
                    <a href="<?php echo BASE_URL; ?>bus_admin/buses.php" class="quick-link-card">Edit Buses</a>
                <?php endif; ?>

                <?php if ($canManageBookings): ?>
                    <a href="<?php echo BASE_URL; ?>bus_admin/bookings.php" class="quick-link-card">
                        <?php echo $canApproveBookings ? 'Edit / Approve Bookings' : 'View Bookings'; ?>
                    </a>
                <?php endif; ?>

                <?php if ($canManageRoutes): ?>
                    <a href="<?php echo BASE_URL; ?>bus_admin/routes.php" class="quick-link-card">Add / Edit Routes</a>
                <?php endif; ?>

                <?php if ($canManageSchedules): ?>
                    <a href="<?php echo BASE_URL; ?>bus_admin/generate_schedule.php" class="quick-link-card">Add / Edit Schedules</a>
                <?php endif; ?>

                <?php if ($canViewTicket): ?>
                    <a href="<?php echo BASE_URL; ?>bus_admin/scan_ticket.php" class="quick-link-card">View / Scan Tickets</a>
                <?php endif; ?>

                <a href="<?php echo BASE_URL; ?>bus_admin/trip_boarding.php" class="quick-link-card">Trip Boarding</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
require_once __DIR__ . '/../includes/footer.php';
?>