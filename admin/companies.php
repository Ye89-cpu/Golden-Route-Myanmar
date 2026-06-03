<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

$page_title = 'Company Management - Golden Route Myanmar';

$conn = getDBConnection();

function admin_company_status_badge(string $status): string
{
    switch ($status) {
        case 'approved':
            return 'success';
        case 'pending':
            return 'warning text-dark';
        case 'rejected':
            return 'danger';
        case 'suspended':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function admin_company_type_label(string $type): string
{
    switch ($type) {
        case 'bus_company':
            return 'Bus Company';
        case 'tour_operator':
            return 'Tour Operator';
        case 'both':
            return 'Bus + Tour Company';
        default:
            return ucwords(str_replace('_', ' ', $type));
    }
}

function admin_company_logo_url(?string $logo): string
{
    $logo = trim((string)$logo);

    if ($logo === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $logo)) {
        return $logo;
    }

    return BASE_URL . ltrim($logo, '/');
}

function admin_company_initials(string $name): string
{
    $words = preg_split('/\s+/', trim($name));
    $initials = '';

    foreach ($words as $word) {
        if ($word !== '') {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'GR';
}

$editCompany = null;
$editId = (int)($_GET['edit'] ?? 0);

if ($editId > 0) {
    $editSql = "
        SELECT *
        FROM companies
        WHERE id = ?
        LIMIT 1
    ";

    $editStmt = $conn->prepare($editSql);

    if ($editStmt) {
        $editStmt->bind_param('i', $editId);
        $editStmt->execute();

        $editResult = $editStmt->get_result();
        $editCompany = $editResult ? $editResult->fetch_assoc() : null;

        $editStmt->close();
    }
}

$companies = [];

$companySql = "
    SELECT
        id,
        name,
        company_type,
        license,
        phone,
        email,
        address,
        description,
        logo,
        status,
        approved_at,
        created_at,
        updated_at
    FROM companies
    ORDER BY id DESC
";

$companyStmt = $conn->prepare($companySql);

if ($companyStmt) {
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();

    while ($row = $companyResult->fetch_assoc()) {
        $companies[] = $row;
    }

    $companyStmt->close();
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <span class="section-kicker">Super Admin</span>
            <h1 class="page-title mb-2">Company Management</h1>
            <p class="page-subtitle mb-0">
                Add, edit, delete, and create admin accounts for bus companies and tour operators.
            </p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">
                Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success">
            <?php echo e($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4><?php echo $editCompany ? 'Edit Company' : 'Add New Company'; ?></h4>
                    <p>
                        <?php echo $editCompany ? 'Update selected company information.' : 'Create new bus company, tour operator, or combined company.'; ?>
                    </p>
                </div>

                <form action="<?php echo BASE_URL . ($editCompany ? 'actions/update_company.php' : 'actions/create_company.php'); ?>" method="POST">
                    <?php if ($editCompany): ?>
                        <input type="hidden" name="company_id" value="<?php echo e($editCompany['id']); ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?php echo e($editCompany['name'] ?? ''); ?>"
                            placeholder="Example: JJ Myanmar Express"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Company Type <span class="text-danger">*</span></label>
                        <?php $selectedType = $editCompany['company_type'] ?? 'bus_company'; ?>

                        <select name="company_type" class="form-select" required>
                            <option value="bus_company" <?php echo $selectedType === 'bus_company' ? 'selected' : ''; ?>>
                                Bus Company
                            </option>

                            <option value="tour_operator" <?php echo $selectedType === 'tour_operator' ? 'selected' : ''; ?>>
                                Tour Operator
                            </option>

                            <option value="both" <?php echo $selectedType === 'both' ? 'selected' : ''; ?>>
                                Bus + Tour Company
                            </option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">License</label>
                        <input
                            type="text"
                            name="license"
                            class="form-control"
                            value="<?php echo e($editCompany['license'] ?? ''); ?>"
                            placeholder="Example: LIC-BUS-2026"
                        >
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input
                                type="text"
                                name="phone"
                                class="form-control"
                                value="<?php echo e($editCompany['phone'] ?? ''); ?>"
                                placeholder="09450000000"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?php echo e($editCompany['email'] ?? ''); ?>"
                                placeholder="company@example.com"
                            >
                        </div>
                    </div>

                    <div class="mt-3 mb-3">
                        <label class="form-label">Address</label>
                        <input
                            type="text"
                            name="address"
                            class="form-control"
                            value="<?php echo e($editCompany['address'] ?? ''); ?>"
                            placeholder="Yangon, Mandalay, Naypyidaw..."
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Logo Path</label>
                        <input
                            type="text"
                            name="logo"
                            class="form-control"
                            value="<?php echo e($editCompany['logo'] ?? ''); ?>"
                            placeholder="uploads/company_logos/company-name-1.svg"
                        >
                        <div class="form-text">
                            Example: uploads/company_logos/jj-myanmar-express-14.svg
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea
                            name="description"
                            rows="4"
                            class="form-control"
                            placeholder="Short company description"
                        ><?php echo e($editCompany['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <?php $selectedStatus = $editCompany['status'] ?? 'approved'; ?>

                        <select name="status" class="form-select" required>
                            <option value="approved" <?php echo $selectedStatus === 'approved' ? 'selected' : ''; ?>>
                                Approved
                            </option>

                            <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>
                                Pending
                            </option>

                            <option value="rejected" <?php echo $selectedStatus === 'rejected' ? 'selected' : ''; ?>>
                                Rejected
                            </option>

                            <option value="suspended" <?php echo $selectedStatus === 'suspended' ? 'selected' : ''; ?>>
                                Suspended
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">
                        <?php echo $editCompany ? 'Update Company' : '+ Add Company'; ?>
                    </button>

                    <?php if ($editCompany): ?>
                        <a href="<?php echo BASE_URL; ?>admin/companies.php" class="btn btn-outline-secondary w-100 mt-2">
                            Cancel Edit
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>Companies</h4>
                    <p>All registered bus and tour companies.</p>
                </div>

                <?php if (empty($companies)): ?>
                    <div class="empty-inline-box">
                        No companies found. Add your first company from the form.
                    </div>
                <?php else: ?>
                    <div class="admin-list-stack">
                        <?php foreach ($companies as $company): ?>
                            <?php $logoUrl = admin_company_logo_url($company['logo'] ?? ''); ?>

                            <div class="admin-list-item align-items-start">
                                <div class="d-flex gap-3 align-items-start">
                                    <div style="width: 72px; height: 72px; border-radius: 18px; overflow: hidden; border: 1px solid #e5e7eb; background: #f8fafc; flex: 0 0 auto; display: flex; align-items: center; justify-content: center;">
                                        <?php if ($logoUrl !== ''): ?>
                                            <img
                                                src="<?php echo e($logoUrl); ?>"
                                                alt="<?php echo e($company['name']); ?> logo"
                                                style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                            >
                                        <?php else: ?>
                                            <strong>
                                                <?php echo e(admin_company_initials((string)$company['name'])); ?>
                                            </strong>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <strong><?php echo e($company['name']); ?></strong>

                                        <div class="text-muted small mt-1">
                                            <?php echo e(admin_company_type_label((string)$company['company_type'])); ?>
                                            <?php if (!empty($company['license'])): ?>
                                                · License: <?php echo e($company['license']); ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-muted small mt-1">
                                            <?php echo e($company['phone'] ?: '-'); ?>
                                            <?php if (!empty($company['email'])): ?>
                                                · <?php echo e($company['email']); ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-muted small mt-1">
                                            <?php echo e($company['address'] ?: 'No address'); ?>
                                        </div>

                                        <?php if (!empty($company['description'])): ?>
                                            <div class="small mt-2">
                                                <?php echo e(mb_strimwidth((string)$company['description'], 0, 120, '...')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <span class="badge bg-<?php echo admin_company_status_badge((string)$company['status']); ?>">
                                        <?php echo e(ucfirst((string)$company['status'])); ?>
                                    </span>

                                    <div class="mt-3 d-flex flex-wrap gap-2 justify-content-end">
                                        <a
                                            href="<?php echo BASE_URL; ?>admin/companies.php?edit=<?php echo e($company['id']); ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="<?php echo BASE_URL; ?>admin/create_company_admin.php?company_id=<?php echo e($company['id']); ?>"
                                            class="btn btn-sm btn-outline-success"
                                        >
                                            Add Admin
                                        </a>

                                        <form
                                            action="<?php echo BASE_URL; ?>actions/delete_company.php"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this company? If related data exists, it will be suspended instead of deleted.');"
                                        >
                                            <input type="hidden" name="company_id" value="<?php echo e($company['id']); ?>">

                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </div>

                                    <div class="text-muted small mt-2">
                                        Created: <?php echo e(date('Y-m-d', strtotime((string)$company['created_at']))); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>