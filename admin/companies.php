<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('super_admin');

$page_title = 'Company Management';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

function company_status_badge_class($status)
{
    switch ($status) {
        case 'approved':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'suspended':
            return 'secondary';
        case 'pending':
        default:
            return 'warning text-dark';
    }
}

$summary = [
    'total_companies' => 0,
    'pending_count'   => 0,
    'approved_count'  => 0,
    'rejected_count'  => 0,
    'suspended_count' => 0
];

$summarySql = "
    SELECT
        COUNT(*) AS total_companies,
        COALESCE(SUM(status = 'pending'), 0) AS pending_count,
        COALESCE(SUM(status = 'approved'), 0) AS approved_count,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected_count,
        COALESCE(SUM(status = 'suspended'), 0) AS suspended_count
    FROM companies
";
$summaryStmt = $conn->prepare($summarySql);
if ($summaryStmt) {
    $summaryStmt->execute();
    $summaryResult = $summaryStmt->get_result();
    if ($summaryResult && $summaryResult->num_rows === 1) {
        $summary = $summaryResult->fetch_assoc();
    }
    $summaryStmt->close();
}

$companies = [];
$listSql = "
    SELECT
        id,
        name,
        company_type,
        license,
        phone,
        email,
        address,
        status,
        approved_at,
        created_at
    FROM companies
    ORDER BY FIELD(status, 'pending', 'approved', 'suspended', 'rejected'), id DESC
";
$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($row = $listResult->fetch_assoc()) {
        $row['admin'] = get_company_primary_admin($conn, (int)$row['id']);
        if (!empty($row['admin']['company_user_id'])) {
            $row['admin_permissions'] = get_company_permission_keys($conn, (int)$row['admin']['company_user_id']);
        } else {
            $row['admin_permissions'] = [];
        }
        $companies[] = $row;
    }
    $listStmt->close();
}

$allPermissions = [
    'manage_buses' => 'Manage Buses',
    'manage_bookings' => 'Manage Bookings',
    'approve_bookings' => 'Approve Bookings',
    'manage_routes' => 'Manage Routes',
    'manage_schedules' => 'Manage Schedules',
    'view_ticket' => 'View / Scan Ticket',
];

$conn->close();
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Company Management</h2>
            <p class="text-muted mb-0">
                Create companies, approve them, auto-create admin accounts, and assign permissions.
            </p>
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

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-3">Create New Company</h4>

            <form action="<?php echo BASE_URL; ?>actions/create_company.php" method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Company Type</label>
                        <select name="company_type" class="form-select" required>
                            <option value="bus_company">Bus Company</option>
                            <option value="tour_operator">Tour Operator</option>
                            <option value="both">Both</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">License</label>
                        <input type="text" name="license" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Company Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control"></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_admin_now" id="create_admin_now" value="1">
                            <label class="form-check-label" for="create_admin_now">
                                Auto-create company admin now
                            </label>
                        </div>
                    </div>

<div class="col-md-6">
                        <label class="form-label">Admin Name (optional)</label>
                        <input type="text" name="admin_name" class="form-control" placeholder="Example: Ayar Bus Admin">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Admin Phone (optional)</label>
                        <input type="text" name="admin_phone" class="form-control" placeholder="Example: 09xxxxxxxxx">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Admin Email (optional)</label>
                        <input type="email" name="admin_email" class="form-control">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Admin Password (optional)</label>
                        <input type="text" name="admin_password" class="form-control" placeholder="Leave blank for auto-generated password">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Create Company</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Total</div>
                    <div class="fs-4 fw-bold"><?php echo e($summary['total_companies']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Pending</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo e($summary['pending_count']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Approved</div>
                    <div class="fs-4 fw-bold text-success"><?php echo e($summary['approved_count']); ?></div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="small text-muted">Suspended</div>
                    <div class="fs-4 fw-bold text-secondary"><?php echo e($summary['suspended_count']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($companies as $company): ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1"><?php echo e($company['name']); ?></h5>
                                <div class="text-muted small">
                                    <?php echo e(ucwords(str_replace('_', ' ', $company['company_type']))); ?>
                                    <?php if (!empty($company['license'])): ?>
                                        · License: <?php echo e($company['license']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <span class="badge bg-<?php echo company_status_badge_class($company['status']); ?>">
                                <?php echo e(ucfirst($company['status'])); ?>
                            </span>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <div class="small text-muted">Phone</div>
                                <div><?php echo e($company['phone'] ?: '-'); ?></div>
                            </div>

                            <div class="col-md-4">
                                <div class="small text-muted">Email</div>
                                <div><?php echo e($company['email'] ?: '-'); ?></div>
                            </div>

                            <div class="col-md-4">
                                <div class="small text-muted">Address</div>
                                <div><?php echo e($company['address'] ?: '-'); ?></div>
                            </div>
                        </div>

                        <div class="border rounded-4 p-3 mb-3 bg-light-subtle">
                            <h6 class="fw-bold mb-2">Linked Admin</h6>

                            <?php if (!empty($company['admin'])): ?>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <div class="small text-muted">Name</div>
                                        <div><?php echo e($company['admin']['name'] ?? '-'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Email</div>
                                        <div><?php echo e($company['admin']['email'] ?? '-'); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="small text-muted">Role</div>
                                        <div><?php echo e($company['admin']['role'] ?? '-'); ?></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-muted">No admin linked yet.</div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <?php if ($company['status'] !== 'approved'): ?>
                                <form action="<?php echo BASE_URL; ?>actions/approve_company.php" method="POST" class="d-flex flex-wrap gap-2">
                                    <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">
                                    <input type="hidden" name="auto_create_admin" value="1">
                                    <button type="submit" class="btn btn-success">Approve + Auto Create Admin</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($company['status'] !== 'rejected'): ?>
                                <form action="<?php echo BASE_URL; ?>actions/reject_company.php" method="POST">
                                    <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">
                                    <button type="submit" class="btn btn-danger">Reject</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($company['status'] === 'approved'): ?>
                                <form action="<?php echo BASE_URL; ?>actions/suspend_company.php" method="POST">
                                    <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">
                                    <button type="submit" class="btn btn-outline-secondary">Suspend</button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($company['admin']['company_user_id'])): ?>
                            <div class="border rounded-4 p-3">
                                <h6 class="fw-bold mb-3">Admin Permissions</h6>

                                <form action="<?php echo BASE_URL; ?>actions/update_company_permissions.php" method="POST">
                                    <input type="hidden" name="company_user_id" value="<?php echo e($company['admin']['company_user_id']); ?>">

                                    <div class="row g-3">
                                        <?php foreach ($allPermissions as $permissionKey => $permissionLabel): ?>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="<?php echo e($permissionKey); ?>"
                                                        id="perm_<?php echo e($company['id'] . '_' . $permissionKey); ?>"
                                                        <?php echo in_array($permissionKey, $company['admin_permissions'], true) ? 'checked' : ''; ?>
                                                    >
                                                    <label class="form-check-label" for="perm_<?php echo e($company['id'] . '_' . $permissionKey); ?>">
                                                        <?php echo e($permissionLabel); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">Save Permissions</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>