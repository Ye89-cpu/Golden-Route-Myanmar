<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/admin/refund_reports.php

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/refund_helper.php';
require_once __DIR__ . '/../includes/refund_report_helper.php';

require_role('super_admin');

$page_title = 'Refund Reports';

$startDateInput = $_GET['start_date'] ?? refund_report_default_start_date();
$endDateInput = $_GET['end_date'] ?? refund_report_default_end_date();
$statusFilter = trim($_GET['status'] ?? 'all');

$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'cancelled'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

[$startDate, $endDate] = refund_report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();
$summary = refund_report_summary($conn, $startDate, $endDate);
$rows = refund_report_rows($conn, $startDate, $endDate, $statusFilter, 50);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Refund Reports Dashboard</h2>
            <p class="text-muted mb-0">Refund request analytics and recent refund activity</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>admin/refund_requests.php" class="btn btn-outline-danger">Manage Refund Requests</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if (!$summary['table_exists']): ?>
        <div class="alert alert-warning rounded-4">
            refund_requests table not found yet. Please run Step 21 migration first.
        </div>
    <?php else: ?>
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
                        <label class="form-label">Status Filter</label>
                        <select name="status" class="form-select">
                            <?php foreach ($allowedStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                                    <?php echo e(ucfirst($status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="<?php echo BASE_URL; ?>admin/refund_reports.php" class="btn btn-outline-secondary ms-2">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Total Requests</div>
                        <div class="fs-3 fw-bold"><?php echo e($summary['total_requests']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Pending</div>
                        <div class="fs-3 fw-bold text-warning"><?php echo e($summary['pending_count']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Approved</div>
                        <div class="fs-3 fw-bold text-success"><?php echo e($summary['approved_count']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Rejected</div>
                        <div class="fs-3 fw-bold text-danger"><?php echo e($summary['rejected_count']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Bus Requests</div>
                        <div class="fs-3 fw-bold text-primary"><?php echo e($summary['bus_requests']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Tour Requests</div>
                        <div class="fs-3 fw-bold text-info"><?php echo e($summary['tour_requests']); ?></div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Requested Amount</div>
                        <div class="fs-6 fw-bold"><?php echo e(number_format((float)$summary['requested_amount_total'], 2)); ?> MMK</div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="small text-muted">Approved Amount</div>
                        <div class="fs-6 fw-bold text-success"><?php echo e(number_format((float)$summary['approved_amount_total'], 2)); ?> MMK</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <?php if (empty($rows)): ?>
                    <div class="p-4">
                        <div class="alert alert-info mb-0">No refund report rows found in this filter.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Request</th>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Processed By</th>
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
                                            <div class="small text-muted">
                                                Booking status: <?php echo e(customer_history_format_status((string)$row['booking_status'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div><?php echo e($row['customer_name']); ?></div>
                                            <div class="small text-muted"><?php echo e($row['customer_email']); ?></div>
                                        </td>
                                        <td><?php echo e(strtoupper($row['booking_type'])); ?></td>
                                        <td><?php echo e(number_format((float)$row['requested_amount'], 2)); ?> MMK</td>
                                        <td>
                                            <span class="badge bg-<?php echo e(refund_status_badge_class((string)$row['status'])); ?>">
                                                <?php echo e(refund_format_status((string)$row['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo e($row['processed_by_name'] ?: '-'); ?>
                                            <?php if (!empty($row['processed_at'])): ?>
                                                <div class="small text-muted"><?php echo e(date('Y-m-d H:i', strtotime((string)$row['processed_at']))); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="table-light">
                                        <td></td>
                                        <td colspan="7">
                                            <div><strong>Reason:</strong> <?php echo nl2br(e($row['reason'])); ?></div>
                                            <?php if (!empty($row['admin_note'])): ?>
                                                <div class="mt-2"><strong>Admin Note:</strong> <?php echo nl2br(e($row['admin_note'])); ?></div>
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
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>