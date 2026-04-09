<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

$page_title = 'Super Admin Dashboard';

$conn = getDBConnection();
$user = current_user();

$summary = [
    'total_companies' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'suspended_count' => 0,
    'total_bookings' => 0,
    'pending_payments' => 0,
    'refund_requests' => 0,
    'notifications' => 0,
];

$cardsSql = [
    'company_summary' => "
        SELECT
            COUNT(*) AS total_companies,
            COALESCE(SUM(status = 'pending'), 0) AS pending_count,
            COALESCE(SUM(status = 'approved'), 0) AS approved_count,
            COALESCE(SUM(status = 'rejected'), 0) AS rejected_count,
            COALESCE(SUM(status = 'suspended'), 0) AS suspended_count
        FROM companies
    ",
    'bookings' => "SELECT COUNT(*) AS total FROM bookings",
    'pending_payments' => "SELECT COUNT(*) AS total FROM payments WHERE status = 'submitted'",
    'refund_requests' => "SELECT COUNT(*) AS total FROM refund_requests",
    'notifications' => "SELECT COUNT(*) AS total FROM notifications",
];

try {
    $stmt = $conn->prepare($cardsSql['company_summary']);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : [];
        if ($row) {
            $summary = array_merge($summary, $row);
        }
        $stmt->close();
    }

    foreach (['bookings', 'pending_payments', 'refund_requests', 'notifications'] as $key) {
        $stmt = $conn->prepare($cardsSql[$key]);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            if ($key === 'bookings') {
                $summary['total_bookings'] = (int)($row['total'] ?? 0);
            } else {
                $summary[$key] = (int)($row['total'] ?? 0);
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // keep dashboard alive
}

$recentCompanies = [];
$recentPayments = [];

try {
    $companySql = "
        SELECT id, name, company_type, status, created_at
        FROM companies
        ORDER BY id DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($companySql);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recentCompanies[] = $row;
        }
        $stmt->close();
    }

    $paymentSql = "
        SELECT p.id, p.amount, p.status, p.created_at, b.booking_code
        FROM payments p
        INNER JOIN bookings b ON b.id = p.booking_id
        ORDER BY p.id DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($paymentSql);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recentPayments[] = $row;
        }
        $stmt->close();
    }
} catch (Throwable $e) {
    // ignore list errors
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';

function adminStatusBadgeClass(string $status): string
{
    switch ($status) {
        case 'approved':
        case 'verified':
            return 'success';
        case 'pending':
        case 'submitted':
            return 'warning text-dark';
        case 'rejected':
        case 'suspended':
            return 'danger';
        default:
            return 'secondary';
    }
}
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
                <span class="section-kicker">Super Admin</span>
                <h1 class="page-title mb-2">Dashboard overview</h1>
                <p class="page-subtitle mb-0">
                    Welcome back, <?php echo e($user['name'] ?? 'Admin'); ?>.
                    Review companies, payments, refunds, templates, system settings and audit activity from here.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>admin/companies.php" class="btn btn-brand">Manage Companies</a>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-nav-soft">Open Reports</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Total Companies</span>
                <strong><?php echo e($summary['total_companies']); ?></strong>
                <small>All registered companies</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Pending Approval</span>
                <strong><?php echo e($summary['pending_count']); ?></strong>
                <small>Waiting for review</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Total Bookings</span>
                <strong><?php echo e($summary['total_bookings']); ?></strong>
                <small>Bus + tour bookings</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Pending Payments</span>
                <strong><?php echo e($summary['pending_payments']); ?></strong>
                <small>Proofs waiting review</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Quick Actions</h4>
                    <p>Open common admin modules quickly.</p>
                </div>

                <div class="quick-link-grid">
                    <a href="<?php echo BASE_URL; ?>admin/companies.php" class="quick-link-card">Companies</a>
                    <a href="<?php echo BASE_URL; ?>admin/payments.php" class="quick-link-card">Payments</a>
                    <a href="<?php echo BASE_URL; ?>admin/refund_requests.php" class="quick-link-card">Refund Requests</a>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="quick-link-card">Reports</a>
                    <a href="<?php echo BASE_URL; ?>admin/email_templates.php" class="quick-link-card">Email Templates</a>
                    <a href="<?php echo BASE_URL; ?>admin/system_settings.php" class="quick-link-card">System Settings</a>
                    <a href="<?php echo BASE_URL; ?>notifications.php" class="quick-link-card">Notifications</a>
                    <a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="quick-link-card">Audit Logs</a>
                    <a href="<?php echo BASE_URL; ?>admin/routes.php" class="quick-link-card">Manage Routes</a>
                    <a href="<?php echo BASE_URL; ?>admin/schedules.php" class="quick-link-card">Manage Schedules</a>
                    <a href="<?php echo BASE_URL; ?>admin/routes.php" class="quick-link-card">Manage All Routes</a>
                    <a href="<?php echo BASE_URL; ?>admin/schedules.php" class="quick-link-card">Manage All Schedules</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Status Summary</h4>
                    <p>Company and request health at a glance.</p>
                </div>

                <div class="summary-list">
                    <div class="summary-row"><span>Approved Companies</span><strong><?php echo e($summary['approved_count']); ?></strong></div>
                    <div class="summary-row"><span>Rejected Companies</span><strong><?php echo e($summary['rejected_count']); ?></strong></div>
                    <div class="summary-row"><span>Suspended Companies</span><strong><?php echo e($summary['suspended_count']); ?></strong></div>
                    <div class="summary-row"><span>Refund Requests</span><strong><?php echo e($summary['refund_requests']); ?></strong></div>
                    <div class="summary-row"><span>Notifications</span><strong><?php echo e($summary['notifications']); ?></strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Recent Companies</h4>
                    <p>Newest company records in the system.</p>
                </div>

                <?php if (empty($recentCompanies)): ?>
                    <div class="empty-inline-box">No company records found.</div>
                <?php else: ?>
                    <div class="admin-list-stack">
                        <?php foreach ($recentCompanies as $company): ?>
                            <div class="admin-list-item">
                                <div>
                                    <strong><?php echo e($company['name']); ?></strong>
                                    <div class="text-muted small"><?php echo e(ucwords(str_replace('_', ' ', $company['company_type']))); ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo adminStatusBadgeClass((string)$company['status']); ?>">
                                        <?php echo e(ucfirst($company['status'])); ?>
                                    </span>
                                    <div class="text-muted small mt-1"><?php echo e(date('Y-m-d', strtotime($company['created_at']))); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Recent Payments</h4>
                    <p>Latest payment submissions and reviews.</p>
                </div>

                <?php if (empty($recentPayments)): ?>
                    <div class="empty-inline-box">No payment records found.</div>
                <?php else: ?>
                    <div class="admin-list-stack">
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="admin-list-item">
                                <div>
                                    <strong><?php echo e($payment['booking_code']); ?></strong>
                                    <div class="text-muted small"><?php echo number_format((float)$payment['amount'], 2); ?> MMK</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo adminStatusBadgeClass((string)$payment['status']); ?>">
                                        <?php echo e(ucfirst($payment['status'])); ?>
                                    </span>
                                    <div class="text-muted small mt-1"><?php echo e(date('Y-m-d', strtotime($payment['created_at']))); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>