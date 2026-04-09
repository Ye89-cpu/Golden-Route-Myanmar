<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_template_helper.php';

require_role('super_admin');

$page_title = 'Email Templates';

$conn = getDBConnection();
$templates = fetch_all_email_templates($conn);

$editId = (int)($_GET['edit_id'] ?? 0);
$editingTemplate = $editId > 0 ? fetch_email_template_by_id($conn, $editId) : null;
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Email Templates</h2>
            <p class="text-muted mb-0">Manage system email subjects and bodies</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-dark">Notification Center</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($templates)): ?>
                        <div class="p-4">
                            <div class="alert alert-warning mb-0">No email_templates table data found. Run Step 26 migration first.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Channel</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e($template['code']); ?></td>
                                            <td><?php echo e($template['name']); ?></td>
                                            <td><?php echo e(strtoupper($template['channel'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $template['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo e(ucfirst($template['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>admin/email_templates.php?edit_id=<?php echo e($template['id']); ?>" class="btn btn-sm btn-outline-primary">
                                                    Edit
                                                </a>
                                            </td>
                                        </tr>
                                        <tr class="table-light">
                                            <td colspan="5">
                                                <div class="small"><strong>Variables:</strong> <?php echo e($template['variables_hint'] ?: '-'); ?></div>
                                                <div class="small mt-1"><strong>Subject:</strong> <?php echo e($template['subject_template']); ?></div>
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

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3"><?php echo $editingTemplate ? 'Edit Template' : 'Create Template'; ?></h5>

                    <form action="<?php echo BASE_URL; ?>actions/save_email_template.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo e($editingTemplate['id'] ?? 0); ?>">

                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control" required value="<?php echo e($editingTemplate['code'] ?? old('code')); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo e($editingTemplate['name'] ?? old('name')); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Channel</label>
                            <select name="channel" class="form-select" required>
                                <?php $currentChannel = $editingTemplate['channel'] ?? old('channel') ?? 'both'; ?>
                                <option value="email" <?php echo $currentChannel === 'email' ? 'selected' : ''; ?>>EMAIL</option>
                                <option value="notification" <?php echo $currentChannel === 'notification' ? 'selected' : ''; ?>>NOTIFICATION</option>
                                <option value="both" <?php echo $currentChannel === 'both' ? 'selected' : ''; ?>>BOTH</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <?php $currentStatus = $editingTemplate['status'] ?? old('status') ?? 'active'; ?>
                                <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Variables Hint</label>
                            <textarea name="variables_hint" rows="3" class="form-control"><?php echo e($editingTemplate['variables_hint'] ?? old('variables_hint')); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Subject Template</label>
                            <input type="text" name="subject_template" class="form-control" required value="<?php echo e($editingTemplate['subject_template'] ?? old('subject_template')); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Body Template (HTML allowed)</label>
                            <textarea name="body_template" rows="12" class="form-control" required><?php echo e($editingTemplate['body_template'] ?? old('body_template')); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary"><?php echo $editingTemplate ? 'Update Template' : 'Create Template'; ?></button>
                        <a href="<?php echo BASE_URL; ?>admin/email_templates.php" class="btn btn-outline-secondary ms-2">Clear</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>