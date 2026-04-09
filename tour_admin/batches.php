<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/tour_booking_helper.php';

require_role('tour_admin');

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

$packageId = (int)($_GET['package_id'] ?? 0);
if ($packageId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid package ID.');
    redirect('tour_admin/packages.php');
}

$package = fetch_tour_package_for_company($conn, $packageId, (int)$company['company_id']);
if (!$package) {
    $conn->close();
    set_flash('error', 'Tour package not found or not allowed.');
    redirect('tour_admin/packages.php');
}

$page_title = 'Manage Tour Batches';
$statusOptions = ['open', 'full', 'closed', 'cancelled'];
$editBatch = null;
$editId = (int)($_GET['edit'] ?? 0);

if ($editId > 0) {
    $editBatch = fetch_tour_batch_for_package($conn, $editId, $packageId);

    if (!$editBatch) {
        $conn->close();
        set_flash('error', 'Batch not found.');
        redirect('tour_admin/batches.php?package_id=' . $packageId);
    }
}

$batches = fetch_tour_batches_for_package($conn, $packageId);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Tour Batch Management</h2>
            <p class="text-muted mb-0">Package: <?php echo e($package['title']); ?></p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>tour_admin/packages.php" class="btn btn-outline-secondary">
                Back to Packages
            </a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?php echo $editBatch ? 'Edit Batch' : 'Create Batch'; ?></h5>

                    <form action="<?php echo BASE_URL . ($editBatch ? 'actions/update_tour_batch.php' : 'actions/create_tour_batch.php'); ?>" method="POST">
                        <input type="hidden" name="package_id" value="<?php echo e($packageId); ?>">
                        <?php if ($editBatch): ?>
                            <input type="hidden" name="batch_id" value="<?php echo e($editBatch['id']); ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input
                                    type="date"
                                    name="start_date"
                                    class="form-control"
                                    value="<?php echo e($editBatch['start_date'] ?? old('start_date')); ?>"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">End Date</label>
                                <input
                                    type="date"
                                    name="end_date"
                                    class="form-control"
                                    value="<?php echo e($editBatch['end_date'] ?? old('end_date')); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mt-3 row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Capacity</label>
                                <input
                                    type="number"
                                    name="capacity"
                                    class="form-control"
                                    min="1"
                                    value="<?php echo e($editBatch['capacity'] ?? old('capacity')); ?>"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Booked Count</label>
                                <input
                                    type="number"
                                    class="form-control"
                                    value="<?php echo e($editBatch['booked_count'] ?? 0); ?>"
                                    readonly
                                >
                            </div>
                        </div>

                        <div class="mt-3 mb-3">
                            <label class="form-label">Price</label>
                            <input
                                type="number"
                                name="price"
                                class="form-control"
                                min="0"
                                step="0.01"
                                value="<?php echo e($editBatch['price'] ?? old('price', $package['price'])); ?>"
                                required
                            >
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <?php
                                $selectedStatus = $editBatch['status'] ?? old('status', 'open');
                                foreach ($statusOptions as $status):
                                ?>
                                    <option value="<?php echo e($status); ?>" <?php echo $selectedStatus === $status ? 'selected' : ''; ?>>
                                        <?php echo e(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editBatch ? 'Update Batch' : 'Create Batch'; ?>
                            </button>
                            <?php if ($editBatch): ?>
                                <a href="<?php echo BASE_URL; ?>tour_admin/batches.php?package_id=<?php echo e($packageId); ?>" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($batches)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No batches found yet.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Date Range</th>
                                        <th>Capacity</th>
                                        <th>Booked</th>
                                        <th>Remaining</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th style="min-width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($batches as $batch): ?>
                                        <tr>
                                            <td><?php echo e($batch['id']); ?></td>
                                            <td>
                                                <?php echo e($batch['start_date']); ?><br>
                                                <span class="small text-muted">to <?php echo e($batch['end_date']); ?></span>
                                            </td>
                                            <td><?php echo e((int)$batch['capacity']); ?></td>
                                            <td><?php echo e((int)$batch['booked_count']); ?></td>
                                            <td><?php echo e(tour_batch_remaining_slots($batch)); ?></td>
                                            <td><?php echo e(number_format((float)$batch['price'], 2)); ?> MMK</td>
                                            <td>
                                                <span class="badge bg-<?php echo e(tour_batch_badge_class($batch['status'])); ?>">
                                                    <?php echo e(ucfirst($batch['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="<?php echo BASE_URL; ?>tour_admin/batches.php?package_id=<?php echo e($packageId); ?>&edit=<?php echo e($batch['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                        Edit
                                                    </a>

                                                    <form action="<?php echo BASE_URL; ?>actions/delete_tour_batch.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="package_id" value="<?php echo e($packageId); ?>">
                                                        <input type="hidden" name="batch_id" value="<?php echo e($batch['id']); ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this batch?');">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
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
    </div>
</div>
<?php
clear_old_input();
require_once __DIR__ . '/../includes/footer.php';
?>
