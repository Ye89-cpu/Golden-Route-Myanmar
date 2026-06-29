<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';

require_role('tour_admin');

$page_title = 'Tour Package Payments';

function tour_payment_status_badge_class(string $status): string
{
    switch ($status) {
        case 'verified':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'submitted':
        default:
            return 'warning text-dark';
    }
}

function tour_booking_payment_badge_class(string $status): string
{
    switch ($status) {
        case 'paid':
            return 'success';
        case 'pending_review':
            return 'warning text-dark';
        case 'failed':
        case 'rejected':
        case 'refunded':
            return 'danger';
        case 'unpaid':
        default:
            return 'secondary';
    }
}

function tour_payment_method_label(string $method): string
{
    $map = [
        'wave_money' => 'Wave Money',
        'kbzpay' => 'KBZPay',
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
    ];

    return $map[$method] ?? ucwords(str_replace('_', ' ', $method));
}

$conn = getDBConnection();
require_company_permission($conn, 'manage_tour_payments');
$company = require_tour_admin_company($conn);
$companyId = (int)$company['company_id'];

$summary = [
    'total_payments' => 0,
    'submitted_count' => 0,
    'verified_count' => 0,
    'rejected_count' => 0,
];

$summarySql = "
    SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(p.status = 'submitted'), 0) AS submitted_count,
        COALESCE(SUM(p.status = 'verified'), 0) AS verified_count,
        COALESCE(SUM(p.status = 'rejected'), 0) AS rejected_count
    FROM payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
    WHERE b.booking_type = 'tour'
      AND tb.company_id = ?
";
$stmt = $conn->prepare($summarySql);
$stmt->bind_param('i', $companyId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $summary = array_merge($summary, $row);
}
$stmt->close();

$payments = [];
$listSql = "
    SELECT
        p.id,
        p.booking_id,
        p.amount,
        p.payment_method,
        p.transaction_ref,
        p.screenshot_path,
        p.status AS payment_status,
        p.created_at,
        b.booking_code,
        b.status AS booking_status,
        b.payment_status AS booking_payment_status,
        u.name AS customer_name,
        u.email AS customer_email,
        tp.title AS package_title,
        tb.start_date
    FROM payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN users u ON u.id = b.user_id
    INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
    INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
    WHERE b.booking_type = 'tour'
      AND tb.company_id = ?
    ORDER BY FIELD(p.status, 'submitted', 'rejected', 'verified'), p.id DESC
";
$stmt = $conn->prepare($listSql);
$stmt->bind_param('i', $companyId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <span class="section-kicker">Tour Admin</span>
            <h2 class="fw-bold mb-1">Tour Package Payments</h2>
            <p class="text-muted mb-0">Verify or reject payment proofs for <?php echo e($company['company_name']); ?> tour bookings.</p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>tour_admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Total Payments</div><div class="fs-4 fw-bold"><?php echo e($summary['total_payments']); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Submitted</div><div class="fs-4 fw-bold text-warning"><?php echo e($summary['submitted_count']); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Verified</div><div class="fs-4 fw-bold text-success"><?php echo e($summary['verified_count']); ?></div></div></div></div>
        <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><div class="small text-muted">Rejected</div><div class="fs-4 fw-bold text-danger"><?php echo e($summary['rejected_count']); ?></div></div></div></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <div class="p-4"><div class="alert alert-info mb-0">No tour payment records found.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Booking</th>
                                <th>Package</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction Ref</th>
                                <th>Screenshot</th>
                                <th>Payment Status</th>
                                <th>Booking Payment</th>
                                <th>Submitted At</th>
                                <th style="min-width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo e($payment['id']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($payment['booking_code']); ?></div>
                                        <div class="small text-muted">Booking ID: <?php echo e($payment['booking_id']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($payment['package_title']); ?></div>
                                        <div class="small text-muted">Start: <?php echo e($payment['start_date']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($payment['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($payment['customer_email']); ?></div>
                                    </td>
                                    <td><?php echo e(number_format((float)$payment['amount'], 2)); ?> MMK</td>
                                    <td><?php echo e(tour_payment_method_label((string)$payment['payment_method'])); ?></td>
                                    <td><?php echo e($payment['transaction_ref'] ?: '-'); ?></td>
                                    <td>
                                        <?php if (!empty($payment['screenshot_path'])): ?>
                                            <a href="<?php echo BASE_URL . e($payment['screenshot_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-<?php echo tour_payment_status_badge_class((string)$payment['payment_status']); ?>"><?php echo e(ucfirst((string)$payment['payment_status'])); ?></span></td>
                                    <td><span class="badge bg-<?php echo tour_booking_payment_badge_class((string)$payment['booking_payment_status']); ?>"><?php echo e(ucwords(str_replace('_', ' ', (string)$payment['booking_payment_status']))); ?></span></td>
                                    <td><?php echo e(date('Y-m-d H:i', strtotime((string)$payment['created_at']))); ?></td>
                                    <td>
                                        <?php if ($payment['payment_status'] === 'submitted'): ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <form action="<?php echo BASE_URL; ?>actions/verify_payment.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="payment_id" value="<?php echo e($payment['id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Verify this tour package payment?');">Verify</button>
                                                </form>
                                                <form action="<?php echo BASE_URL; ?>actions/reject_payment.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="payment_id" value="<?php echo e($payment['id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this tour package payment?');">Reject</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">No actions available</span>
                                        <?php endif; ?>
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
