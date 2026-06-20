<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';

require_role('tour_admin');

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

$page_title = 'Tour Package Management';
$statusOptions = ['active', 'inactive'];

$editPackage = null;
$editId = (int)($_GET['edit'] ?? 0);

if ($editId > 0) {
    $editSql = "
        SELECT *
        FROM tour_packages
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ";
    $editStmt = $conn->prepare($editSql);
    $editStmt->bind_param('ii', $editId, $company['company_id']);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $editPackage = $editResult->fetch_assoc() ?: null;
    $editStmt->close();

    if (!$editPackage) {
        $conn->close();
        set_flash('error', 'Tour package not found.');
        redirect('tour_admin/packages.php');
    }
}

$packages = [];
$listSql = "
    SELECT *
    FROM tour_packages
    WHERE company_id = ?
    ORDER BY id DESC
";
$listStmt = $conn->prepare($listSql);
$listStmt->bind_param('i', $company['company_id']);
$listStmt->execute();
$listResult = $listStmt->get_result();

while ($row = $listResult->fetch_assoc()) {
    $packages[] = $row;
}
$listStmt->close();

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Tour Package Management</h2>
            <p class="text-muted mb-0">Company: <?php echo e($company['company_name']); ?></p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>tour_admin/dashboard.php" class="btn btn-outline-secondary">
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

    <div class="row g-4">
        <div class="col-lg-5" id="package-form">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <?php echo $editPackage ? 'Edit Tour Package' : 'Create Tour Package'; ?>
                    </h5>

                    <form
                        action="<?php echo BASE_URL . ($editPackage ? 'actions/update_tour_package.php' : 'actions/create_tour_package.php'); ?>"
                        method="POST"
                        enctype="multipart/form-data"
                    >
                        <?php if ($editPackage): ?>
                            <input type="hidden" name="package_id" value="<?php echo e($editPackage['id']); ?>">
                            <input type="hidden" name="existing_cover_image" value="<?php echo e($editPackage['cover_image'] ?? ''); ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input
                                type="text"
                                name="title"
                                class="form-control"
                                value="<?php echo e($editPackage['title'] ?? old('title')); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required><?php echo e($editPackage['description'] ?? old('description')); ?></textarea>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Price</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="price"
                                    class="form-control"
                                    value="<?php echo e($editPackage['price'] ?? old('price')); ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (Days)</label>
                                <input
                                    type="number"
                                    min="1"
                                    name="duration_days"
                                    class="form-control"
                                    value="<?php echo e($editPackage['duration_days'] ?? old('duration_days')); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mt-3 mb-3">
                            <label class="form-label">Hotel Info</label>
                            <textarea name="hotel_info" class="form-control" rows="2"><?php echo e($editPackage['hotel_info'] ?? old('hotel_info')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Transport Info</label>
                            <textarea name="transport_info" class="form-control" rows="2"><?php echo e($editPackage['transport_info'] ?? old('transport_info')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Itinerary</label>
                            <textarea name="itinerary" class="form-control" rows="3"><?php echo e($editPackage['itinerary'] ?? old('itinerary')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Included Services</label>
                            <textarea name="included_services" class="form-control" rows="2"><?php echo e($editPackage['included_services'] ?? old('included_services')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Excluded Services</label>
                            <textarea name="excluded_services" class="form-control" rows="2"><?php echo e($editPackage['excluded_services'] ?? old('excluded_services')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cover Image</label>
                            <input type="file" name="cover_image" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($editPackage['cover_image'])): ?>
                                <div class="mt-2">
                                    <img src="<?php echo BASE_URL . e($editPackage['cover_image']); ?>" alt="Cover Image" style="max-width:160px;" class="img-thumbnail">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <?php
                                $selectedStatus = $editPackage['status'] ?? old('status', 'active');
                                foreach ($statusOptions as $status):
                                ?>
                                    <option value="<?php echo e($status); ?>" <?php echo ($selectedStatus === $status) ? 'selected' : ''; ?>>
                                        <?php echo e(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editPackage ? 'Update Package' : 'Create Package'; ?>
                            </button>

                            <?php if ($editPackage): ?>
                                <a href="<?php echo BASE_URL; ?>tour_admin/packages.php" class="btn btn-outline-secondary">
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
                    <?php if (empty($packages)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No tour packages found yet.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Package</th>
                                        <th>Price</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th style="min-width: 180px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $package): ?>
                                        <tr>
                                            <td><?php echo e($package['id']); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo e($package['title']); ?></div>
                                                <div class="small text-muted">
                                                    <?php echo e(mb_strimwidth((string)$package['description'], 0, 80, '...')); ?>
                                                </div>
                                            </td>
                                            <td><?php echo e(number_format((float)$package['price'], 2)); ?> MMK</td>
                                            <td><?php echo e((int)$package['duration_days']); ?> days</td>
                                            <td>
                                                <span class="badge bg-<?php echo e($package['status'] === 'active' ? 'success' : 'secondary'); ?>">
                                                    <?php echo e(ucfirst($package['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="<?php echo BASE_URL; ?>tour_admin/packages.php?edit=<?php echo e($package['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                        Edit
                                                    </a>

                                                    <a
                                                        href="<?php echo BASE_URL; ?>tour_admin/batches.php?package_id=<?php echo e($package['id']); ?>"
                                                        class="btn btn-sm btn-outline-success"
                                                    >
                                                        Batches
                                                    </a>

                                                    <form action="<?php echo BASE_URL; ?>actions/delete_tour_package.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="package_id" value="<?php echo e($package['id']); ?>">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this package?');"
                                                        >
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