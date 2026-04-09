<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';

require_role('bus_admin');

$page_title = 'Manage Buses';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

function bus_status_badge_class($status)
{
    switch ($status) {
        case 'active':
            return 'success';
        case 'maintenance':
            return 'warning text-dark';
        case 'inactive':
        default:
            return 'secondary';
    }
}

$allowedBusTypes = ['normal', 'vip', 'sleeper', 'mini_bus'];
$allowedLayoutTypes = ['2x2', '2x1', 'sleeper', 'vip', 'custom'];
$allowedStatuses = ['active', 'maintenance', 'inactive'];

$editBus = null;
$editBusId = (int)($_GET['edit'] ?? 0);

if ($editBusId > 0) {
    $editSql = "
        SELECT id, bus_number, plate_number, bus_type, total_seats, layout_type, status
        FROM buses
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ";
    $editStmt = $conn->prepare($editSql);
    $editStmt->bind_param('ii', $editBusId, $company['company_id']);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $editBus = $editResult->fetch_assoc() ?: null;
    $editStmt->close();

    if (!$editBus) {
        set_flash('error', 'Bus not found for editing.');
        redirect('bus_admin/buses.php');
    }
}

$listSql = "
    SELECT id, bus_number, plate_number, bus_type, total_seats, layout_type, status, created_at
    FROM buses
    WHERE company_id = ?
    ORDER BY id DESC
";
$listStmt = $conn->prepare($listSql);
$listStmt->bind_param('i', $company['company_id']);
$listStmt->execute();
$listResult = $listStmt->get_result();

$buses = [];
while ($row = $listResult->fetch_assoc()) {
    $buses[] = $row;
}
$listStmt->close();
$conn->close();
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Bus Management</h2>
            <p class="text-muted mb-0">
                Company: <?php echo e($company['company_name']); ?>
            </p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>bus_admin/dashboard.php" class="btn btn-outline-secondary">
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
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <?php echo $editBus ? 'Edit Bus' : 'Create New Bus'; ?>
                    </h5>

                    <form
                        action="<?php echo BASE_URL . ($editBus ? 'actions/update_bus.php' : 'actions/create_bus.php'); ?>"
                        method="POST"
                    >
                        <?php if ($editBus): ?>
                            <input type="hidden" name="bus_id" value="<?php echo e($editBus['id']); ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Bus Number</label>
                            <input
                                type="text"
                                name="bus_number"
                                class="form-control"
                                value="<?php echo e($editBus['bus_number'] ?? old('bus_number')); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Plate Number</label>
                            <input
                                type="text"
                                name="plate_number"
                                class="form-control"
                                value="<?php echo e($editBus['plate_number'] ?? old('plate_number')); ?>"
                                placeholder="Optional"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bus Type</label>
                            <select name="bus_type" class="form-select" required>
                                <?php
                                $selectedBusType = $editBus['bus_type'] ?? old('bus_type', 'normal');
                                foreach ($allowedBusTypes as $type):
                                ?>
                                    <option value="<?php echo e($type); ?>" <?php echo ($selectedBusType === $type) ? 'selected' : ''; ?>>
                                        <?php echo e(ucwords(str_replace('_', ' ', $type))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Total Seats</label>
                            <input
                                type="number"
                                name="total_seats"
                                class="form-control"
                                min="1"
                                max="100"
                                value="<?php echo e($editBus['total_seats'] ?? old('total_seats')); ?>"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Layout Type</label>
                            <select name="layout_type" class="form-select" required>
                                <?php
                                $selectedLayoutType = $editBus['layout_type'] ?? old('layout_type', '2x2');
                                foreach ($allowedLayoutTypes as $layout):
                                ?>
                                    <option value="<?php echo e($layout); ?>" <?php echo ($selectedLayoutType === $layout) ? 'selected' : ''; ?>>
                                        <?php echo e(strtoupper($layout)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <?php
                                $selectedStatus = $editBus['status'] ?? old('status', 'active');
                                foreach ($allowedStatuses as $status):
                                ?>
                                    <option value="<?php echo e($status); ?>" <?php echo ($selectedStatus === $status) ? 'selected' : ''; ?>>
                                        <?php echo e(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $editBus ? 'Update Bus' : 'Create Bus'; ?>
                            </button>

                            <?php if ($editBus): ?>
                                <a href="<?php echo BASE_URL; ?>bus_admin/buses.php" class="btn btn-outline-secondary">
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
                    <?php if (empty($buses)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No buses found yet.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Bus Number</th>
                                        <th>Plate</th>
                                        <th>Type</th>
                                        <th>Seats</th>
                                        <th>Layout</th>
                                        <th>Status</th>
                                        <th style="min-width: 170px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($buses as $bus): ?>
                                        <tr>
                                            <td><?php echo e($bus['id']); ?></td>
                                            <td class="fw-semibold"><?php echo e($bus['bus_number']); ?></td>
                                            <td><?php echo e($bus['plate_number'] ?: '-'); ?></td>
                                            <td><?php echo e(ucwords(str_replace('_', ' ', $bus['bus_type']))); ?></td>
                                            <td><?php echo e($bus['total_seats']); ?></td>
                                            <td><?php echo e(strtoupper($bus['layout_type'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo bus_status_badge_class($bus['status']); ?>">
                                                    <?php echo e(ucfirst($bus['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a
                                                        href="<?php echo BASE_URL; ?>bus_admin/buses.php?edit=<?php echo e($bus['id']); ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form action="<?php echo BASE_URL; ?>actions/delete_bus.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="bus_id" value="<?php echo e($bus['id']); ?>">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this bus?');"
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