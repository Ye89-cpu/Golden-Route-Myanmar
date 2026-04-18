<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/tour_admin_booking_helper.php';

require_role('tour_admin');

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

$batchId = (int)($_GET['batch_id'] ?? 0);
$startDate = trim($_GET['start_date'] ?? '');
$batchStatus = trim($_GET['batch_status'] ?? 'all');
$searchValue = trim($_GET['search_value'] ?? '');

$allowedBatchStatuses = ['all', 'open', 'full', 'closed', 'cancelled'];
if (!in_array($batchStatus, $allowedBatchStatuses, true)) {
    $batchStatus = 'all';
}

$page_title = 'Voucher Check-in';

try {
    $batchRows = fetch_tour_admin_batch_checkin_list($conn, (int)$company['company_id'], [
        'start_date' => $startDate,
        'batch_status' => $batchStatus,
    ]);

    $currentBatch = null;
    $manifestRows = [];
    $searchVoucher = null;
    $searchState = null;
    $searchMessage = null;

    if ($batchId > 0) {
        $currentBatch = fetch_tour_admin_batch_detail($conn, (int)$company['company_id'], $batchId);

        if (!$currentBatch) {
            $conn->close();
            set_flash('error', 'Batch not found or not allowed.');
            redirect('tour_admin/voucher_checkin.php');
        }

        $manifestRows = fetch_tour_admin_batch_manifest_rows($conn, (int)$company['company_id'], $batchId);

        if ($searchValue !== '') {
            $searchVoucher = find_batch_voucher_for_company($conn, (int)$company['company_id'], $batchId, $searchValue, false);

            if (!$searchVoucher) {
                $searchState = 'invalid';
                $searchMessage = 'No matching voucher was found for this batch.';
            } elseif (($searchVoucher['voucher_status'] ?? '') === 'cancelled') {
                $searchState = 'invalid';
                $searchMessage = 'This voucher has been cancelled.';
            } elseif (($searchVoucher['voucher_status'] ?? '') === 'used' || !empty($searchVoucher['used_at'])) {
                $searchState = 'used';
                $searchMessage = 'This voucher has already been checked in.';
            } elseif (($searchVoucher['booking_status'] ?? '') !== 'paid' || ($searchVoucher['payment_status'] ?? '') !== 'paid') {
                $searchState = 'invalid';
                $searchMessage = 'This voucher is not eligible for check-in because payment is not verified.';
            } elseif (($searchVoucher['batch_status'] ?? '') === 'cancelled') {
                $searchState = 'invalid';
                $searchMessage = 'This batch has been cancelled.';
            } else {
                $searchState = 'valid';
                $searchMessage = 'Valid voucher found. Ready for check-in.';
            }
        }
    }
} catch (Throwable $e) {
    $conn->close();
    die('Voucher check-in error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Voucher Check-in</h2>
            <p class="text-muted mb-0">Company: <?php echo e($company['company_name']); ?></p>
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
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Batch Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Batch Status</label>
                    <select name="batch_status" class="form-select">
                        <?php foreach ($allowedBatchStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $batchStatus === $status ? 'selected' : ''; ?>>
                                <?php echo e(tour_admin_format_status($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="<?php echo BASE_URL; ?>tour_admin/voucher_checkin.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-0">
            <?php if (empty($batchRows)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No batches found for check-in.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Package / Batch</th>
                                <th>Capacity</th>
                                <th>Paid Pax</th>
                                <th>Vouchers</th>
                                <th>Status</th>
                                <th style="min-width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batchRows as $row): ?>
                                <?php $waitingCount = max(0, (int)$row['issued_vouchers'] - (int)$row['checked_in_vouchers']); ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['package_title']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo e($row['start_date']); ?> to <?php echo e($row['end_date']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div>Capacity: <?php echo e($row['capacity']); ?></div>
                                        <div class="small text-muted">Booked count: <?php echo e($row['booked_count']); ?></div>
                                    </td>
                                    <td>
                                        <div>Paid bookings: <?php echo e($row['paid_bookings']); ?></div>
                                        <div class="small text-muted">Paid passengers: <?php echo e($row['paid_passengers']); ?></div>
                                    </td>
                                    <td>
                                        <div>Issued: <?php echo e($row['issued_vouchers']); ?></div>
                                        <div class="small text-muted">Checked-in: <?php echo e($row['checked_in_vouchers']); ?></div>
                                        <div class="small text-muted">Waiting: <?php echo e($waitingCount); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo e(tour_admin_badge_class((string)$row['batch_status'])); ?>">
                                            <?php echo e(tour_admin_format_status((string)$row['batch_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>tour_admin/voucher_checkin.php?batch_id=<?php echo e($row['batch_id']); ?>" class="btn btn-sm btn-primary">
                                            Open Check-in
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($currentBatch): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Current Batch</h5>
                <div class="row g-3">
                    <div class="col-md-4"><strong>Package:</strong> <?php echo e($currentBatch['package_title']); ?></div>
                    <div class="col-md-4"><strong>Date:</strong> <?php echo e($currentBatch['start_date']); ?> to <?php echo e($currentBatch['end_date']); ?></div>
                    <div class="col-md-4"><strong>Status:</strong> <?php echo e(tour_admin_format_status((string)$currentBatch['batch_status'])); ?></div>
                    <div class="col-md-4"><strong>Paid Bookings:</strong> <?php echo e($currentBatch['paid_bookings']); ?></div>
                    <div class="col-md-4"><strong>Paid Passengers:</strong> <?php echo e($currentBatch['paid_passengers']); ?></div>
                    <div class="col-md-4"><strong>Issued Vouchers:</strong> <?php echo e($currentBatch['issued_vouchers']); ?></div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3">Search Voucher for This Batch</h5>

                <form method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="batch_id" value="<?php echo e($batchId); ?>">
                    <div class="col-lg-9">
                        <label class="form-label">Voucher Code or QR Token</label>
                        <input
                            type="text"
                            name="search_value"
                            class="form-control"
                            value="<?php echo e($searchValue); ?>"
                            placeholder="Enter voucher_code or qr_token"
                        >
                    </div>
                    <div class="col-lg-3">
                        <button type="submit" class="btn btn-primary w-100">Check Voucher</button>
                    </div>
                </form>

                <?php if ($searchValue !== ''): ?>
                    <hr>
                    <?php if ($searchState === 'valid'): ?>
                        <div class="alert alert-success"><?php echo e($searchMessage); ?></div>
                    <?php elseif ($searchState === 'used'): ?>
                        <div class="alert alert-warning"><?php echo e($searchMessage); ?></div>
                    <?php else: ?>
                        <div class="alert alert-danger"><?php echo e($searchMessage); ?></div>
                    <?php endif; ?>

                    <?php if ($searchVoucher): ?>
                        <div class="row g-3">
                            <div class="col-md-4"><strong>Voucher Code:</strong> <?php echo e($searchVoucher['voucher_code']); ?></div>
                            <div class="col-md-4"><strong>Customer:</strong> <?php echo e($searchVoucher['customer_name']); ?></div>
                            <div class="col-md-4"><strong>Phone:</strong> <?php echo e($searchVoucher['customer_phone'] ?: '-'); ?></div>
                            <div class="col-md-4"><strong>Booking Code:</strong> <?php echo e($searchVoucher['booking_code']); ?></div>
                            <div class="col-md-4"><strong>Package:</strong> <?php echo e($searchVoucher['package_title']); ?></div>
                            <div class="col-md-4">
                                <strong>Voucher Status:</strong>
                                <span class="badge bg-<?php echo e(tour_admin_badge_class((string)$searchVoucher['voucher_status'])); ?>">
                                    <?php echo e(tour_admin_format_status((string)$searchVoucher['voucher_status'])); ?>
                                </span>
                            </div>
                            <div class="col-md-4"><strong>Batch Date:</strong> <?php echo e($searchVoucher['start_date']); ?> to <?php echo e($searchVoucher['end_date']); ?></div>
                            <div class="col-md-4"><strong>Used At:</strong> <?php echo e($searchVoucher['used_at'] ? date('Y-m-d H:i:s', strtotime((string)$searchVoucher['used_at'])) : '-'); ?></div>
                            <div class="col-md-4"><strong>Passengers:</strong> <?php echo e($searchVoucher['passenger_count']); ?></div>
                        </div>

                        <?php if (tour_admin_can_mark_voucher_used($searchVoucher)): ?>
                            <form method="POST" action="<?php echo BASE_URL; ?>actions/mark_voucher_checked_in.php" class="mt-3">
                                <input type="hidden" name="batch_id" value="<?php echo e($batchId); ?>">
                                <input type="hidden" name="voucher_id" value="<?php echo e($searchVoucher['voucher_id']); ?>">
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    onclick="return confirm('Confirm check-in and mark this voucher as used?');"
                                >
                                    Mark as Checked-in
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <div class="p-4 border-bottom">
                    <h5 class="fw-bold mb-0">Batch Manifest</h5>
                </div>

                <?php if (empty($manifestRows)): ?>
                    <div class="p-4">
                        <div class="alert alert-info mb-0">No tour bookings found for this batch.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Travelers</th>
                                    <th>Voucher</th>
                                    <th>Status</th>
                                    <th style="min-width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($manifestRows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($row['booking_code']); ?></div>
                                            <div class="small text-muted"><?php echo e(date('Y-m-d H:i', strtotime((string)$row['booked_at']))); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo e($row['customer_name']); ?></div>
                                            <div class="small text-muted"><?php echo e($row['customer_phone'] ?: '-'); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo e($row['passenger_count']); ?> pax</div>
                                            <div class="small text-muted"><?php echo e($row['passenger_names'] ?: '-'); ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($row['voucher_code'] ?: '-'); ?></div>
                                            <?php if (!empty($row['voucher_pdf_file'])): ?>
                                                <div class="small">
                                                    <a href="<?php echo BASE_URL . e($row['voucher_pdf_file']); ?>" target="_blank">PDF</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="mb-1">
                                                <span class="badge bg-<?php echo e(tour_admin_badge_class((string)$row['payment_status'])); ?>">
                                                    <?php echo e(tour_admin_format_status((string)$row['payment_status'])); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($row['voucher_status'])): ?>
                                                <div class="mb-1">
                                                    <span class="badge bg-<?php echo e(tour_admin_badge_class((string)$row['voucher_status'])); ?>">
                                                        Voucher: <?php echo e(tour_admin_format_status((string)$row['voucher_status'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($row['refund_request_status'])): ?>
                                                <div>
                                                    <span class="badge bg-<?php echo e(tour_admin_badge_class((string)$row['refund_request_status'])); ?>">
                                                        Refund: <?php echo e(tour_admin_format_status((string)$row['refund_request_status'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="<?php echo BASE_URL; ?>tour_admin/booking_detail.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-sm btn-outline-primary">
                                                    Detail
                                                </a>

                                                <?php if (tour_admin_can_mark_voucher_used($row)): ?>
                                                    <form method="POST" action="<?php echo BASE_URL; ?>actions/mark_voucher_checked_in.php" class="d-inline">
                                                        <input type="hidden" name="batch_id" value="<?php echo e($batchId); ?>">
                                                        <input type="hidden" name="voucher_id" value="<?php echo e($row['voucher_id']); ?>">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-success"
                                                            onclick="return confirm('Mark this voucher as checked-in?');"
                                                        >
                                                            Check-in
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                        No Action
                                                    </button>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($row['used_at'])): ?>
                                                <div class="small text-muted mt-2">
                                                    Used at: <?php echo e(date('Y-m-d H:i:s', strtotime((string)$row['used_at']))); ?>
                                                </div>
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