<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/refund_helper.php';

require_role('tour_admin');

$page_title = 'Tour Refund Requests';

$statusFilter = trim($_GET['status'] ?? 'all');
$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'cancelled'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$conn = getDBConnection();
require_company_permission($conn, 'manage_tour_refunds');
$company = require_tour_admin_company($conn);
$companyId = (int)$company['company_id'];

$rows = [];
$sql = "
    SELECT
        rr.*,
        b.booking_code,
        b.booking_type,
        b.passenger_count,
        b.total_amount,
        b.status AS booking_status,
        b.payment_status,
        u.name AS customer_name,
        u.email AS customer_email,
        tp.title AS package_title,
        tb.start_date,
        tb.end_date
    FROM refund_requests rr
    INNER JOIN bookings b ON b.id = rr.booking_id
    INNER JOIN users u ON u.id = rr.user_id
    INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
    INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
    WHERE b.booking_type = 'tour'
      AND tb.company_id = ?
";
$params = [$companyId];
$types = 'i';

if ($statusFilter !== 'all') {
    $sql .= " AND rr.status = ? ";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql .= " ORDER BY rr.id DESC ";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <span class="section-kicker">Tour Admin</span>
            <h2 class="fw-bold mb-1">Tour Refund Requests</h2>
            <p class="text-muted mb-0">Approve or reject refund requests for <?php echo e($company['company_name']); ?> tour bookings.</p>
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

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($allowedStatuses as $status): ?>
                    <a href="<?php echo BASE_URL; ?>tour_admin/refund_requests.php?status=<?php echo e($status); ?>" class="btn btn-sm <?php echo $statusFilter === $status ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <?php echo e(ucfirst($status)); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($rows)): ?>
                <div class="p-4"><div class="alert alert-info mb-0">No tour refund requests found.</div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Request</th>
                                <th>Booking</th>
                                <th>Package</th>
                                <th>Customer</th>
                                <th>Reason</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="min-width: 280px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?php echo e($row['id']); ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['request_code']); ?></div>
                                        <div class="small text-muted"><?php echo e(date('Y-m-d H:i', strtotime((string)$row['created_at']))); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['booking_code']); ?></div>
                                        <div class="small text-muted">Passengers: <?php echo e($row['passenger_count']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['package_title']); ?></div>
                                        <div class="small text-muted"><?php echo e($row['start_date']); ?> → <?php echo e($row['end_date']); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['customer_name']); ?></div>
                                        <div class="small text-muted"><?php echo e($row['customer_email']); ?></div>
                                    </td>
                                    <td style="max-width: 240px;">
                                        <div class="small"><?php echo nl2br(e($row['reason'])); ?></div>
                                        <?php if (!empty($row['admin_note'])): ?>
                                            <div class="small text-muted mt-2"><strong>Admin:</strong> <?php echo nl2br(e($row['admin_note'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e(number_format((float)$row['requested_amount'], 2)); ?> MMK</td>
                                    <td><span class="badge bg-<?php echo refund_status_badge_class((string)$row['status']); ?>"><?php echo e(refund_format_status((string)$row['status'])); ?></span></td>
                                    <td>
                                        <?php if ($row['status'] === 'pending'): ?>
                                            <form action="<?php echo BASE_URL; ?>actions/approve_refund_request.php" method="POST" class="mb-2">
                                                <input type="hidden" name="refund_request_id" value="<?php echo e($row['id']); ?>">
                                                <input type="text" name="admin_note" class="form-control form-control-sm mb-2" placeholder="Approval note (optional)">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this tour refund request?');">Approve Refund</button>
                                            </form>
                                            <form action="<?php echo BASE_URL; ?>actions/reject_refund_request.php" method="POST">
                                                <input type="hidden" name="refund_request_id" value="<?php echo e($row['id']); ?>">
                                                <input type="text" name="admin_note" class="form-control form-control-sm mb-2" placeholder="Rejection reason (optional)">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this tour refund request?');">Reject Request</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">No further actions</span>
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
