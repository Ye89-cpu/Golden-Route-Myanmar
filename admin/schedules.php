<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/schedule_helper.php';

require_role('super_admin');

$conn = getDBConnection();
$scope = resolve_route_schedule_company_scope($conn);
$company = $scope['company'];
$companies = $scope['companies'];
$companyId = (int)($scope['company_id'] ?? 0);

$page_title = 'Admin Schedule Management';

$weekdayOptions = get_weekday_options();

$routes = [];
$buses = [];
$templates = [];

if ($companyId > 0) {
    $routeSql = "
        SELECT r.id, fc.name AS from_city_name, tc.name AS to_city_name, r.base_price
        FROM routes r
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE r.company_id = ? AND r.status = 'active'
        ORDER BY fc.name ASC, tc.name ASC
    ";
    $stmt = $conn->prepare($routeSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }
    $stmt->close();

    $busSql = "
        SELECT id, bus_number, bus_type
        FROM buses
        WHERE company_id = ? AND status = 'active'
        ORDER BY bus_number ASC
    ";
    $stmt = $conn->prepare($busSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
    }
    $stmt->close();

    $tplSql = "
        SELECT st.*, fc.name AS from_city_name, tc.name AS to_city_name, b.bus_number
        FROM schedule_templates st
        INNER JOIN routes r ON r.id = st.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        INNER JOIN buses b ON b.id = st.bus_id
        WHERE st.company_id = ?
        ORDER BY st.id DESC
    ";
    $stmt = $conn->prepare($tplSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    $stmt->close();
}

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Admin Schedule Management</h2>
            <p class="text-muted mb-0">Super Admin can create schedule templates and generate trips for any bus company.</p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
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
            <form method="GET" action="<?php echo BASE_URL; ?>admin/schedules.php" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label">Choose Company</label>
                    <select name="company_id" class="form-select" required>
                        <option value="">Select bus company</option>
                        <?php foreach ($companies as $item): ?>
                            <option value="<?php echo e($item['id']); ?>" <?php echo $companyId === (int)$item['id'] ? 'selected' : ''; ?>>
                                <?php echo e($item['name']); ?> (<?php echo e($item['company_type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-primary w-100">Load Company Schedule Area</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($companyId <= 0 || !$company): ?>
        <div class="alert alert-info rounded-4">Choose a company first to manage schedules.</div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Create Schedule Template</h5>
                        <p class="text-muted">Company: <?php echo e($company['company_name']); ?></p>

                        <?php if (empty($routes) || empty($buses)): ?>
                            <div class="alert alert-warning mb-0">
                                This company needs at least one active route and one active bus before schedules can be created.
                            </div>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>actions/generate_schedule_action.php" method="POST">
                                <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Route</label>
                                    <select name="route_id" class="form-select" required>
                                        <option value="">Select route</option>
                                        <?php foreach ($routes as $route): ?>
                                            <option value="<?php echo e($route['id']); ?>">
                                                <?php echo e($route['from_city_name']); ?> → <?php echo e($route['to_city_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Bus</label>
                                    <select name="bus_id" class="form-select" required>
                                        <option value="">Select bus</option>
                                        <?php foreach ($buses as $bus): ?>
                                            <option value="<?php echo e($bus['id']); ?>">
                                                <?php echo e($bus['bus_number']); ?> (<?php echo e($bus['bus_type']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Departure Time</label>
                                        <input type="time" name="departure_time" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Arrival Time</label>
                                        <input type="time" name="arrival_time" class="form-control" required>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" min="0" name="price" class="form-control" required>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Frequency</label>
                                    <select name="frequency" class="form-select" required>
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label d-block">Weekdays</label>
                                    <div class="row g-2">
                                        <?php foreach ($weekdayOptions as $value => $label): ?>
                                            <div class="col-6">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="weekdays[]" value="<?php echo e($value); ?>" id="wd_<?php echo e($value); ?>">
                                                    <label class="form-check-label" for="wd_<?php echo e($value); ?>"><?php echo e($label); ?></label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label">Active From</label>
                                        <input type="date" name="active_from" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Active To</label>
                                        <input type="date" name="active_to" class="form-control" required>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>

                                <div class="d-grid gap-2 mt-4">
                                    <button type="submit" name="action_type" value="create_template" class="btn btn-outline-primary">Create Template Only</button>
                                    <button type="submit" name="action_type" value="create_and_generate" class="btn btn-primary">Create Template + Generate Trips</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Schedule Templates</h5>
                        <p class="text-muted">Company: <?php echo e($company['company_name']); ?></p>

                        <?php if (empty($templates)): ?>
                            <div class="alert alert-info mb-0">No schedule templates found for this company yet.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Route</th>
                                            <th>Bus</th>
                                            <th>Time</th>
                                            <th>Frequency</th>
                                            <th>Date Range</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($templates as $tpl): ?>
                                            <tr>
                                                <td><?php echo e($tpl['from_city_name']); ?> → <?php echo e($tpl['to_city_name']); ?></td>
                                                <td><?php echo e($tpl['bus_number']); ?></td>
                                                <td><?php echo e($tpl['departure_time']); ?> - <?php echo e($tpl['arrival_time']); ?></td>
                                                <td><?php echo e(ucfirst($tpl['frequency'])); ?></td>
                                                <td><?php echo e($tpl['active_from']); ?> to <?php echo e($tpl['active_to']); ?></td>
                                                <td><span class="badge bg-<?php echo $tpl['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo e(ucfirst($tpl['status'])); ?></span></td>
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>