<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_setting_helper.php';

require_role('super_admin');

$page_title = 'Audit Logs';

$conn = getDBConnection();

$action = trim($_GET['action'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$userId = trim($_GET['user_id'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$actions = fetch_distinct_audit_actions($conn);
$entityTypes = fetch_distinct_audit_entity_types($conn);
$logs = fetch_audit_logs_with_filters($conn, [
    'action' => $action,
    'entity_type' => $entityType,
    'user_id' => $userId !== '' ? (int)$userId : null,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Audit Logs</h2>
            <p class="text-muted mb-0">System activity history</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>admin/system_settings.php" class="btn btn-outline-primary">System Settings</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="action" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($actions as $item): ?>
                            <option value="<?php echo e($item); ?>" <?php echo $action === $item ? 'selected' : ''; ?>>
                                <?php echo e($item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Entity Type</label>
                    <select name="entity_type" class="form-select">
                        <option value="">All</option>
                        <?php foreach ($entityTypes as $item): ?>
                            <option value="<?php echo e($item); ?>" <?php echo $entityType === $item ? 'selected' : ''; ?>>
                                <?php echo e($item); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">User ID</label>
                    <input type="number" name="user_id" class="form-control" value="<?php echo e($userId); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No audit logs found.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Description</th>
                                <th>IP</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo e($log['id']); ?></td>
                                    <td>
                                        <?php if (!empty($log['user_id'])): ?>
                                            <div><?php echo e($log['user_name'] ?: 'User #' . $log['user_id']); ?></div>
                                            <div class="small text-muted"><?php echo e($log['user_email'] ?: ''); ?></div>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-dark"><?php echo e($log['action']); ?></span></td>
                                    <td>
                                        <div><?php echo e($log['entity_type']); ?></div>
                                        <div class="small text-muted">ID: <?php echo e($log['entity_id'] ?? '-'); ?></div>
                                    </td>
                                    <td><?php echo e($log['description'] ?: '-'); ?></td>
                                    <td><?php echo e($log['ip_address'] ?: '-'); ?></td>
                                    <td><?php echo e(date('Y-m-d H:i:s', strtotime((string)$log['created_at']))); ?></td>
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