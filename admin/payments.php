<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

$page_title = 'Payment Verification';

$conn = getDBConnection();

function payment_status_badge_class($status)
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

function booking_payment_badge_class($status)
{
    switch ($status) {
        case 'paid':
            return 'success';
        case 'pending_review':
            return 'warning text-dark';
        case 'rejected':
            return 'danger';
        case 'unpaid':
        default:
            return 'secondary';
    }
}

function payment_method_label($method)
{
    $map = [
        'wave_money'    => 'Wave Money',
        'kbzpay'        => 'KBZPay',
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank Transfer'
    ];

    return $map[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

$summary = [
    'total_payments'    => 0,
    'submitted_count'   => 0,
    'verified_count'    => 0,
    'rejected_count'    => 0
];

$summarySql = "
    SELECT
        COUNT(*) AS total_payments,
        COALESCE(SUM(status = 'submitted'), 0) AS submitted_count,
        COALESCE(SUM(status = 'verified'), 0) AS verified_count,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_count
    FROM payments
";
$summaryStmt = $conn->prepare($summarySql);
$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();

if ($summaryResult && $summaryResult->num_rows === 1) {
    $summary = $summaryResult->fetch_assoc();
}
$summaryStmt->close();

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

        c.name AS company_name
    FROM payments p
    INNER JOIN bookings b ON b.id = p.booking_id
    INNER JOIN users u ON u.id = b.user_id
    LEFT JOIN trips t ON t.id = b.trip_id
    LEFT JOIN companies c ON c.id = t.company_id
    ORDER BY FIELD(p.status, 'submitted', 'rejected', 'verified'), p.id DESC
";
$listStmt = $conn->prepare($listSql);
$listStmt->execute();
$listResult = $listStmt->get_result();

while ($row = $listResult->fetch_assoc()) {
    $payments[] = $row;
}
$listStmt->close();

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Payment Verification</h2>
            <p class="text-muted mb-0">Review submitted customer payments.</p>
        </div>

        <div class="mt-3 mt-lg-0">
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

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Total Payments</div>
                    <div class="fs-4 fw-bold"><?php echo e($summary['total_payments']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Submitted</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo e($summary['submitted_count']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Verified</div>
                    <div class="fs-4 fw-bold text-success"><?php echo e($summary['verified_count']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Rejected</div>
                    <div class="fs-4 fw-bold text-danger"><?php echo e($summary['rejected_count']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($payments)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No payment records found.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Company</th>
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
                                        <div class="small text-muted">
                                            Booking ID: <?php echo e($payment['booking_id']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo e($payment['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($payment['customer_email']); ?></div>
                                    </td>
                                    <td><?php echo e($payment['company_name'] ?: '-'); ?></td>
                                    <td><?php echo e(number_format((float)$payment['amount'], 2)); ?> MMK</td>
                                    <td><?php echo e(payment_method_label($payment['payment_method'])); ?></td>
                                    <td><?php echo e($payment['transaction_ref'] ?: '-'); ?></td>
                                    <td>
                                        <?php if (!empty($payment['screenshot_path'])): ?>
                                            <a
                                                href="<?php echo BASE_URL . e($payment['screenshot_path']); ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-primary"
                                            >
                                                View
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo payment_status_badge_class($payment['payment_status']); ?>">
                                            <?php echo e(ucfirst($payment['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo booking_payment_badge_class($payment['booking_payment_status']); ?>">
                                            <?php echo e(ucfirst(str_replace('_', ' ', $payment['booking_payment_status']))); ?>
                                        </span>
                                    </td>
                                    <td><?php echo e(date('Y-m-d H:i', strtotime($payment['created_at']))); ?></td>
                                    <td>
                                        <?php if ($payment['payment_status'] === 'submitted'): ?>
                                            <div class="d-flex flex-wrap gap-2">
                                                <form action="<?php echo BASE_URL; ?>actions/verify_payment.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="payment_id" value="<?php echo e($payment['id']); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Verify this payment?');"
                                                    >
                                                        Verify
                                                    </button>
                                                </form>

                                                <form action="<?php echo BASE_URL; ?>actions/reject_payment.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="payment_id" value="<?php echo e($payment['id']); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Reject this payment?');"
                                                    >
                                                        Reject
                                                    </button>
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