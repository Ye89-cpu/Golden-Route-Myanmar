<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/event_helper.php';

require_role('super_admin');

$page_title = 'Super Admin Dashboard';

$conn = getDBConnection();
ensure_events_table_exists($conn);

$user = current_user();

$summary = [
    'total_companies' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'suspended_count' => 0,
    'total_bookings' => 0,
    'pending_payments' => 0,
    'unpaid_bookings' => 0,
    'pending_payment_reviews' => 0,
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
    'pending_payments' => "
        SELECT COUNT(*) AS total
        FROM bookings b
        WHERE b.payment_status IN ('unpaid', 'pending_review')
           OR EXISTS (
                SELECT 1
                FROM payments p
                WHERE p.booking_id = b.id
                  AND p.status = 'submitted'
           )
    ",
    'unpaid_bookings' => "SELECT COUNT(*) AS total FROM bookings WHERE payment_status = 'unpaid'",
    'pending_payment_reviews' => "SELECT COUNT(*) AS total FROM payments WHERE status = 'submitted'",
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

    foreach (['bookings', 'pending_payments', 'unpaid_bookings', 'pending_payment_reviews', 'refund_requests', 'notifications'] as $key) {
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
    // Keep dashboard alive.
}

$eventSummary = get_event_dashboard_summary($conn);
$recentEvents = get_recent_events($conn, 4);

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
    // Ignore list errors.
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
                    Review companies, payments, refunds, events, routes, schedules, reports, and system activity from here.
                </p>
            </div>

            <div class="col-lg-4">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>admin/events.php#event-form-card" class="btn btn-brand">
                        + Add Event
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/business_reports.php" class="btn btn-nav-soft">
                        Open Business Reports
                    </a>
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
                <small>Unpaid / pending review bookings</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="panel-card h-100">
                <div class="panel-card-header quick-links-header">
                    <div>
                        <h4>Quick Actions</h4>
                        <p>Open common admin modules quickly.</p>
                    </div>

                    <a href="<?php echo BASE_URL; ?>admin/events.php#event-form-card" class="btn btn-sm btn-brand">
                        + Add Event
                    </a>
                </div>

                <div class="quick-link-grid">
                    <a href="<?php echo BASE_URL; ?>admin/companies.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Companies</h4>
                            <p>Manage registered companies</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/companies.php" class="quick-link-card featured-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Add Company / Admin</h4>
                            <p>Add company and create admin account</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/payments.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-credit-card-2-front"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Payments</h4>
                            <p>Review payment submissions</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/refund_requests.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Refund Requests</h4>
                            <p>Handle refund approvals</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/business_reports.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Reports</h4>
                            <p>View business analytics</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/email_templates.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-envelope-paper"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Email Templates</h4>
                            <p>Edit email layouts</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/system_settings.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-gear"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>System Settings</h4>
                            <p>Control platform settings</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>notifications.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-bell"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Notifications</h4>
                            <p>Manage admin alerts</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Audit Logs</h4>
                            <p>Track system activities</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/routes.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-sign-turn-right"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Manage Routes</h4>
                            <p>Update all travel routes</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/schedules.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-calendar3"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Manage Schedules</h4>
                            <p>Edit trip schedules</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/events.php#event-form-card" class="quick-link-card featured-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Add Event</h4>
                            <p>Create promotions and travel events</p>
                        </div>
                    </a>

                    <a href="<?php echo BASE_URL; ?>admin/business_reports.php" class="quick-link-card">
                        <div class="quick-link-icon">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <div class="quick-link-content">
                            <h4>Open Reports</h4>
                            <p>Check business performance</p>
                        </div>
                    </a>
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
                    <div class="summary-row">
                        <span>Total Bookings</span>
                        <strong><?php echo e($summary['total_bookings']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Pending Payments</span>
                        <strong><?php echo e($summary['pending_payments']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Unpaid Bookings</span>
                        <strong><?php echo e($summary['unpaid_bookings']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Payment Proofs for Review</span>
                        <strong><?php echo e($summary['pending_payment_reviews']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Total Events</span>
                        <strong><?php echo e($eventSummary['total_events']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Active Events</span>
                        <strong><?php echo e($eventSummary['active_events']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Approved Companies</span>
                        <strong><?php echo e($summary['approved_count']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Rejected Companies</span>
                        <strong><?php echo e($summary['rejected_count']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Suspended Companies</span>
                        <strong><?php echo e($summary['suspended_count']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Refund Requests</span>
                        <strong><?php echo e($summary['refund_requests']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Notifications</span>
                        <strong><?php echo e($summary['notifications']); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel-card mb-4">
        <div class="panel-card-header quick-links-header">
            <div>
                <h4>Events & Promotions</h4>
                <p>Track homepage campaigns, announcements and seasonal content.</p>
            </div>

            <a href="<?php echo BASE_URL; ?>admin/events.php" class="btn btn-brand btn-sm">
                Manage Events
            </a>
        </div>

        <div class="event-stat-grid mb-4">
            <div class="event-stat-card">
                <span>Total Events</span>
                <strong><?php echo e($eventSummary['total_events']); ?></strong>
            </div>

            <div class="event-stat-card">
                <span>Active</span>
                <strong><?php echo e($eventSummary['active_events']); ?></strong>
            </div>

            <div class="event-stat-card">
                <span>Draft</span>
                <strong><?php echo e($eventSummary['draft_events']); ?></strong>
            </div>

            <div class="event-stat-card">
                <span>Expired</span>
                <strong><?php echo e($eventSummary['expired_events']); ?></strong>
            </div>
        </div>

        <?php if (empty($recentEvents)): ?>
            <div class="empty-inline-box">
                No events found yet. Add your first event from the events page.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($recentEvents as $event): ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="dashboard-event-card">
                            <div class="dashboard-event-top">
                                <span class="badge bg-<?php echo e(event_status_badge_class((string)$event['status'])); ?>">
                                    <?php echo e(ucfirst((string)$event['status'])); ?>
                                </span>

                                <span class="dashboard-event-type">
                                    <?php echo e($event['event_type']); ?>
                                </span>
                            </div>

                            <h5><?php echo e($event['title']); ?></h5>
                            <p><?php echo e($event['location'] ?: 'No location'); ?></p>

                            <div class="dashboard-event-date">
                                <?php echo !empty($event['event_date']) ? e($event['event_date']) : 'No event date'; ?>
                            </div>

                            <a href="<?php echo BASE_URL; ?>admin/events.php?edit=<?php echo (int)$event['id']; ?>#event-form-card" class="dashboard-event-link">
                                Edit Event
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Recent Companies</h4>
                    <p>Newest company records in the system.</p>
                </div>

                <?php if (empty($recentCompanies)): ?>
                    <div class="empty-inline-box">
                        No company records found.
                    </div>
                <?php else: ?>
                    <div class="admin-list-stack">
                        <?php foreach ($recentCompanies as $company): ?>
                            <div class="admin-list-item">
                                <div>
                                    <strong><?php echo e($company['name']); ?></strong>

                                    <div class="text-muted small">
                                        <?php echo e(ucwords(str_replace('_', ' ', $company['company_type']))); ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <span class="badge bg-<?php echo adminStatusBadgeClass((string)$company['status']); ?>">
                                        <?php echo e(ucfirst($company['status'])); ?>
                                    </span>

                                    <div class="text-muted small mt-1">
                                        <?php echo e(date('Y-m-d', strtotime($company['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="<?php echo BASE_URL; ?>admin/companies.php" class="btn btn-nav-soft w-100">
                        View All Companies
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Recent Payments</h4>
                    <p>Latest payment submissions and reviews.</p>
                </div>

                <?php if (empty($recentPayments)): ?>
                    <div class="empty-inline-box">
                        No payment records found.
                    </div>
                <?php else: ?>
                    <div class="admin-list-stack">
                        <?php foreach ($recentPayments as $payment): ?>
                            <div class="admin-list-item">
                                <div>
                                    <strong><?php echo e($payment['booking_code']); ?></strong>

                                    <div class="text-muted small">
                                        <?php echo number_format((float)$payment['amount'], 2); ?> MMK
                                    </div>
                                </div>

                                <div class="text-end">
                                    <span class="badge bg-<?php echo adminStatusBadgeClass((string)$payment['status']); ?>">
                                        <?php echo e(ucfirst($payment['status'])); ?>
                                    </span>

                                    <div class="text-muted small mt-1">
                                        <?php echo e(date('Y-m-d', strtotime($payment['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="<?php echo BASE_URL; ?>admin/payments.php" class="btn btn-nav-soft w-100">
                        View All Payments
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>