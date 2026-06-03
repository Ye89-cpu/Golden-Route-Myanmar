<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

$page_title = 'Create Company Admin - Golden Route Myanmar';

$conn = getDBConnection();

$companyId = (int)($_GET['company_id'] ?? 0);
$company = null;

if ($companyId > 0) {
    $sql = "
        SELECT id, name, company_type, email, phone, status
        FROM companies
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param('i', $companyId);
        $stmt->execute();

        $result = $stmt->get_result();
        $company = $result ? $result->fetch_assoc() : null;

        $stmt->close();
    }
}

$conn->close();

if (!$company) {
    set_flash('error', 'Company not found.');
    redirect('admin/companies.php');
}

$defaultRole = 'bus_admin';

if (($company['company_type'] ?? '') === 'tour_operator') {
    $defaultRole = 'tour_admin';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <span class="section-kicker">Super Admin</span>
            <h1 class="page-title mb-2">Create Company Admin Account</h1>
            <p class="page-subtitle mb-0">
                Create a login account for this company admin dashboard.
            </p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>admin/companies.php" class="btn btn-outline-secondary">
                Back to Companies
            </a>
        </div>
    </div>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Company</h4>
                    <p>Admin account will be linked to this company.</p>
                </div>

                <div class="summary-list">
                    <div class="summary-row">
                        <span>Company</span>
                        <strong><?php echo e($company['name']); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Type</span>
                        <strong><?php echo e(ucwords(str_replace('_', ' ', $company['company_type']))); ?></strong>
                    </div>

                    <div class="summary-row">
                        <span>Status</span>
                        <strong><?php echo e(ucfirst($company['status'])); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Admin Login Details</h4>
                    <p>Create email and password for company admin.</p>
                </div>

                <form action="<?php echo BASE_URL; ?>actions/create_company_admin.php" method="POST">
                    <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">

                    <div class="mb-3">
                        <label class="form-label">Admin Name</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?php echo e($company['name']); ?> Admin"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Admin Email</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="admin@example.com"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input
                            type="text"
                            name="phone"
                            class="form-control"
                            value="<?php echo e($company['phone'] ?? ''); ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input
                            type="text"
                            name="password"
                            class="form-control"
                            value="password123"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Admin Role</label>
                        <select name="role" class="form-select" required>
                            <option value="bus_admin" <?php echo $defaultRole === 'bus_admin' ? 'selected' : ''; ?>>
                                Bus Company Admin
                            </option>

                            <option value="tour_admin" <?php echo $defaultRole === 'tour_admin' ? 'selected' : ''; ?>>
                                Tour Company Admin
                            </option>
                        </select>

                        <?php if (($company['company_type'] ?? '') === 'both'): ?>
                            <div class="form-text">
                                This company is Bus + Tour type. You may create one bus_admin and one tour_admin account if needed.
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">
                        Create Admin Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>