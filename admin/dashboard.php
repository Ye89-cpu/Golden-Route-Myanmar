<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/event_helper.php';

require_role('super_admin');

$page_title = 'Super Admin Dashboard';

$conn = getDBConnection();
ensure_events_table_exists($conn);

$user = current_user();

if (empty($_SESSION['promotion_admin_csrf'])) {
    $_SESSION['promotion_admin_csrf'] = bin2hex(random_bytes(24));
}
$promotionAdminCsrf = (string)$_SESSION['promotion_admin_csrf'];

try {
    $conn->query("CREATE TABLE IF NOT EXISTS promotions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(150) NOT NULL,
        promo_code VARCHAR(50) NULL,
        description TEXT NULL,
        discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
        discount_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        starts_at DATETIME NULL,
        ends_at DATETIME NULL,
        status ENUM('active','inactive','expired') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_promotions_promo_code (promo_code),
        KEY idx_promotions_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // Dashboard remains available when the optional promotion module cannot initialize.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promotion_action'])) {
    try {
        $postedToken = (string)($_POST['promotion_csrf'] ?? '');
        if ($postedToken === '' || !hash_equals($promotionAdminCsrf, $postedToken)) {
            throw new Exception('Promotion form session expired. Please refresh and try again.');
        }

        $promotionAction = trim((string)$_POST['promotion_action']);
        if ($promotionAction === 'create') {
            $promotionTitle = trim((string)($_POST['promotion_title'] ?? ''));
            $promotionCode = strtoupper(trim((string)($_POST['promotion_code'] ?? '')));
            $promotionDescription = trim((string)($_POST['promotion_description'] ?? ''));
            $discountType = trim((string)($_POST['discount_type'] ?? 'percentage'));
            $discountValue = trim((string)($_POST['discount_value'] ?? ''));
            $startsAtInput = trim((string)($_POST['starts_at'] ?? ''));
            $endsAtInput = trim((string)($_POST['ends_at'] ?? ''));
            $promotionStatus = trim((string)($_POST['promotion_status'] ?? 'active'));

            if ($promotionTitle === '' || strlen($promotionTitle) > 150) {
                throw new Exception('Promotion title is required and must be 150 characters or fewer.');
            }
            if ($promotionCode !== '' && (strlen($promotionCode) > 50 || preg_match('/^[A-Z0-9_-]+$/', $promotionCode) !== 1)) {
                throw new Exception('Promotion code may contain only letters, numbers, hyphens and underscores.');
            }
            if (!in_array($discountType, ['percentage', 'fixed'], true)) {
                throw new Exception('Invalid discount type.');
            }
            if ($discountValue === '' || !is_numeric($discountValue) || (float)$discountValue <= 0) {
                throw new Exception('Discount value must be greater than zero.');
            }
            if ($discountType === 'percentage' && (float)$discountValue > 100) {
                throw new Exception('Percentage discount cannot be greater than 100%.');
            }
            if (!in_array($promotionStatus, ['active', 'inactive'], true)) {
                throw new Exception('Invalid promotion status.');
            }

            $normalizeDateTime = static function (string $value): ?string {
                if ($value === '') return null;
                $value = str_replace('T', ' ', $value);
                $date = DateTime::createFromFormat('Y-m-d H:i', $value);
                if (!$date || $date->format('Y-m-d H:i') !== $value) {
                    throw new Exception('Promotion start/end date format is invalid.');
                }
                return $date->format('Y-m-d H:i:s');
            };
            $startsAt = $normalizeDateTime($startsAtInput);
            $endsAt = $normalizeDateTime($endsAtInput);
            if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
                throw new Exception('Promotion end time must be later than its start time.');
            }

            $stmt = $conn->prepare("INSERT INTO promotions (title, promo_code, description, discount_type, discount_value, starts_at, ends_at, status) VALUES (?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Could not prepare promotion insert.');
            $discountValueFloat = (float)$discountValue;
            $stmt->bind_param('ssssdsss', $promotionTitle, $promotionCode, $promotionDescription, $discountType, $discountValueFloat, $startsAt, $endsAt, $promotionStatus);
            if (!$stmt->execute()) {
                $message = $stmt->errno === 1062 ? 'That promotion code already exists.' : 'Could not create the promotion.';
                $stmt->close();
                throw new Exception($message);
            }
            $stmt->close();
            set_flash('success', 'Promotion created and published to the public Events & Promotions page.');
        } elseif ($promotionAction === 'toggle') {
            $promotionId = (int)($_POST['promotion_id'] ?? 0);
            if ($promotionId <= 0) throw new Exception('Invalid promotion selected.');
            $stmt = $conn->prepare("UPDATE promotions SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
            if (!$stmt) throw new Exception('Could not prepare promotion status update.');
            $stmt->bind_param('i', $promotionId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Promotion status updated.');
        } elseif ($promotionAction === 'delete') {
            $promotionId = (int)($_POST['promotion_id'] ?? 0);
            if ($promotionId <= 0) throw new Exception('Invalid promotion selected.');
            $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
            if (!$stmt) throw new Exception('Could not prepare promotion deletion.');
            $stmt->bind_param('i', $promotionId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Promotion deleted.');
        } else {
            throw new Exception('Invalid promotion action.');
        }
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
    }

    redirect('admin/dashboard.php#promotion-manager');
}

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

$promotionSummary = [
    'total_promotions' => 0,
    'active_now' => 0,
    'upcoming' => 0,
    'inactive_or_expired' => 0,
];
$recentPromotions = [];

try {
    $promotionTableResult = $conn->query("SHOW TABLES LIKE 'promotions'");
    if ($promotionTableResult instanceof mysqli_result && $promotionTableResult->num_rows > 0) {
        $promotionSummaryResult = $conn->query("
            SELECT
                COUNT(*) AS total_promotions,
                COALESCE(SUM(status = 'active' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())), 0) AS active_now,
                COALESCE(SUM(status = 'active' AND starts_at IS NOT NULL AND starts_at > NOW()), 0) AS upcoming,
                COALESCE(SUM(status <> 'active' OR (ends_at IS NOT NULL AND ends_at < NOW())), 0) AS inactive_or_expired
            FROM promotions
        " );
        if ($promotionSummaryResult) {
            $promotionSummary = array_merge($promotionSummary, $promotionSummaryResult->fetch_assoc() ?: []);
            $promotionSummaryResult->free();
        }

        $promotionResult = $conn->query("
            SELECT id, title, promo_code, discount_type, discount_value, starts_at, ends_at, status
            FROM promotions
            ORDER BY
                CASE WHEN status = 'active' AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) THEN 1 ELSE 2 END,
                id DESC
            LIMIT 4
        " );
        if ($promotionResult) {
            while ($promotion = $promotionResult->fetch_assoc()) {
                $recentPromotions[] = $promotion;
            }
            $promotionResult->free();
        }
    }
} catch (Throwable $e) {
    // Promotion widgets remain at zero when the optional table is unavailable.
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

<style>
.admin-command-strip{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:1.5rem}.admin-command-card{display:flex;align-items:center;gap:13px;padding:17px 18px;border:1px solid #e8ecf2;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(27,38,59,.055);text-decoration:none;color:#263247;transition:.2s ease}.admin-command-card:hover{transform:translateY(-3px);color:#151f33;box-shadow:0 16px 35px rgba(27,38,59,.1)}.admin-command-icon{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;background:#fff5d6;color:#b87800;font-size:1.2rem}.admin-command-card strong{display:block}.admin-command-card small{color:#7b8495}.dashboard-promo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.dashboard-promo-card{border:1px solid #e9edf3;border-radius:20px;padding:18px;background:linear-gradient(180deg,#fff,#fbfcff);height:100%}.dashboard-promo-code{display:inline-flex;padding:6px 10px;border-radius:9px;background:#fff4cf;color:#785000;font-weight:900;letter-spacing:.08em}.dashboard-promo-value{font-size:1.55rem;font-weight:900;color:#b87800}.admin-dashboard-note{border-radius:18px;background:linear-gradient(135deg,#172033,#31415e);color:#fff;padding:18px 20px}.admin-dashboard-note a{color:#ffd269;font-weight:800}.promotion-manager-form{border:1px solid #e8ecf2;border-radius:20px;padding:20px;background:#fbfcfe;margin-bottom:20px}.promotion-manager-form .form-control,.promotion-manager-form .form-select{min-height:45px;border-radius:12px}.promotion-card-actions{display:flex;gap:7px;margin-top:14px}.promotion-card-actions form{flex:1}.promotion-card-actions .btn{width:100%;border-radius:10px}@media(max-width:991.98px){.admin-command-strip,.dashboard-promo-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:575.98px){.admin-command-strip,.dashboard-promo-grid{grid-template-columns:1fr}}
</style>

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

    <div class="admin-command-strip">
        <a href="<?php echo BASE_URL; ?>admin/companies.php" class="admin-command-card"><span class="admin-command-icon"><i class="bi bi-buildings"></i></span><span><strong>Companies</strong><small>Review and approve partners</small></span></a>
        <a href="<?php echo BASE_URL; ?>admin/partner_applications.php" class="admin-command-card"><span class="admin-command-icon"><i class="bi bi-inboxes"></i></span><span><strong>Partner Requests</strong><small>Review public applications</small></span></a>
        <a href="<?php echo BASE_URL; ?>admin/schedules.php" class="admin-command-card"><span class="admin-command-icon"><i class="bi bi-calendar2-week"></i></span><span><strong>Schedules</strong><small>Create templates and trips</small></span></a>
        <a href="<?php echo BASE_URL; ?>admin/payments.php" class="admin-command-card"><span class="admin-command-icon"><i class="bi bi-wallet2"></i></span><span><strong>Payments</strong><small><?php echo e($summary['pending_payment_reviews']); ?> waiting for review</small></span></a>
        <a href="<?php echo BASE_URL; ?>events.php" class="admin-command-card"><span class="admin-command-icon"><i class="bi bi-percent"></i></span><span><strong>Public promotions</strong><small><?php echo e($promotionSummary['active_now']); ?> active now</small></span></a>
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
                <h4>Live Promotion Codes</h4>
                <p>These figures come from the promotions table and respect start and expiry times.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>events.php#promo-checker" class="btn btn-brand btn-sm">Open Public Promotions</a>
        </div>

        <div class="promotion-manager-form" id="promotion-manager">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div><h5 class="mb-1">Create a real promotion</h5><p class="text-muted small mb-0">Saved records immediately appear on the public promotions page when active and within the valid date range.</p></div>
                <i class="bi bi-ticket-perforated fs-2 text-warning"></i>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>admin/dashboard.php#promotion-manager" class="row g-3">
                <input type="hidden" name="promotion_action" value="create">
                <input type="hidden" name="promotion_csrf" value="<?php echo e($promotionAdminCsrf); ?>">
                <div class="col-md-6"><label class="form-label small fw-semibold">Promotion title</label><input type="text" name="promotion_title" class="form-control" maxlength="150" placeholder="Summer Travel Deal" required></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Promo code</label><input type="text" name="promotion_code" class="form-control text-uppercase" maxlength="50" placeholder="SUMMER20"></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Status</label><select name="promotion_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Discount type</label><select name="discount_type" class="form-select"><option value="percentage">Percentage</option><option value="fixed">Fixed MMK</option></select></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Discount value</label><input type="number" name="discount_value" class="form-control" min="0.01" step="0.01" required></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Starts at</label><input type="datetime-local" name="starts_at" class="form-control"></div>
                <div class="col-md-3"><label class="form-label small fw-semibold">Ends at</label><input type="datetime-local" name="ends_at" class="form-control"></div>
                <div class="col-md-9"><label class="form-label small fw-semibold">Description</label><input type="text" name="promotion_description" class="form-control" maxlength="500" placeholder="Explain where or when customers can use this offer."></div>
                <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-brand w-100">Create Promotion</button></div>
            </form>
        </div>

        <div class="event-stat-grid mb-4">
            <div class="event-stat-card"><span>Total Promotions</span><strong><?php echo e($promotionSummary['total_promotions']); ?></strong></div>
            <div class="event-stat-card"><span>Active Now</span><strong><?php echo e($promotionSummary['active_now']); ?></strong></div>
            <div class="event-stat-card"><span>Upcoming</span><strong><?php echo e($promotionSummary['upcoming']); ?></strong></div>
            <div class="event-stat-card"><span>Inactive / Expired</span><strong><?php echo e($promotionSummary['inactive_or_expired']); ?></strong></div>
        </div>

        <?php if (empty($recentPromotions)): ?>
            <div class="admin-dashboard-note"><strong>No promotion records found.</strong> The public checker will stay empty until active records are added to the <code class="text-warning">promotions</code> table.</div>
        <?php else: ?>
            <div class="dashboard-promo-grid">
                <?php foreach ($recentPromotions as $promotion): ?>
                    <?php
                    $promotionIsActive = ($promotion['status'] ?? '') === 'active'
                        && (empty($promotion['starts_at']) || strtotime((string)$promotion['starts_at']) <= time())
                        && (empty($promotion['ends_at']) || strtotime((string)$promotion['ends_at']) >= time());
                    $discountText = ($promotion['discount_type'] ?? '') === 'fixed'
                        ? number_format((float)$promotion['discount_value'], 0) . ' MMK OFF'
                        : rtrim(rtrim(number_format((float)$promotion['discount_value'], 2), '0'), '.') . '% OFF';
                    ?>
                    <div class="dashboard-promo-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3"><span class="badge bg-<?php echo $promotionIsActive ? 'success' : 'secondary'; ?>"><?php echo $promotionIsActive ? 'Active' : e(ucfirst((string)$promotion['status'])); ?></span><span class="dashboard-promo-value"><?php echo e($discountText); ?></span></div>
                        <h5 class="mb-2"><?php echo e($promotion['title']); ?></h5>
                        <?php if (!empty($promotion['promo_code'])): ?><span class="dashboard-promo-code"><?php echo e($promotion['promo_code']); ?></span><?php else: ?><span class="text-muted small">Automatic offer</span><?php endif; ?>
                        <div class="text-muted small mt-3">Ends: <?php echo !empty($promotion['ends_at']) ? e(date('M d, Y', strtotime((string)$promotion['ends_at']))) : 'No expiry'; ?></div>
                        <div class="promotion-card-actions">
                            <form method="POST" action="<?php echo BASE_URL; ?>admin/dashboard.php#promotion-manager"><input type="hidden" name="promotion_csrf" value="<?php echo e($promotionAdminCsrf); ?>"><input type="hidden" name="promotion_action" value="toggle"><input type="hidden" name="promotion_id" value="<?php echo (int)$promotion['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-dark"><?php echo ($promotion['status'] ?? '') === 'active' ? 'Deactivate' : 'Activate'; ?></button></form>
                            <form method="POST" action="<?php echo BASE_URL; ?>admin/dashboard.php#promotion-manager" onsubmit="return confirm('Delete this promotion?');"><input type="hidden" name="promotion_csrf" value="<?php echo e($promotionAdminCsrf); ?>"><input type="hidden" name="promotion_action" value="delete"><input type="hidden" name="promotion_id" value="<?php echo (int)$promotion['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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