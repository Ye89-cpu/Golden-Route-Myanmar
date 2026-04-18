<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/schedule_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';


$conn = getDBConnection();
require_company_permission($conn, 'manage_schedules');

require_role('bus_admin');

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$page_title = 'Schedule Template & Trip Generation';

$weekdayOptions = get_weekday_options();
$frequencyOptions = ['daily', 'weekly', 'custom'];
$statusOptions = ['active', 'inactive'];

/*
|--------------------------------------------------------------------------
| Load active routes
|--------------------------------------------------------------------------
*/
$routes = [];
$routeSql = "
    SELECT
        r.id,
        fc.name AS from_city_name,
        tc.name AS to_city_name,
        r.base_price
    FROM routes r
    INNER JOIN cities fc ON fc.id = r.from_city_id
    INNER JOIN cities tc ON tc.id = r.to_city_id
    WHERE r.company_id = ?
      AND r.status = 'active'
    ORDER BY fc.name ASC, tc.name ASC
";
$routeStmt = $conn->prepare($routeSql);
$routeStmt->bind_param('i', $company['company_id']);
$routeStmt->execute();
$routeResult = $routeStmt->get_result();

while ($row = $routeResult->fetch_assoc()) {
    $routes[] = $row;
}
$routeStmt->close();

/*
|--------------------------------------------------------------------------
| Load active buses
|--------------------------------------------------------------------------
*/
$buses = [];
$busSql = "
    SELECT id, bus_number, bus_type, total_seats, layout_type
    FROM buses
    WHERE company_id = ?
      AND status = 'active'
    ORDER BY bus_number ASC
";
$busStmt = $conn->prepare($busSql);
$busStmt->bind_param('i', $company['company_id']);
$busStmt->execute();
$busResult = $busStmt->get_result();

while ($row = $busResult->fetch_assoc()) {
    $buses[] = $row;
}
$busStmt->close();

/*
|--------------------------------------------------------------------------
| Load existing templates
|--------------------------------------------------------------------------
*/
$templates = [];
$templateSql = "
    SELECT
        st.*,
        b.bus_number,
        b.total_seats,
        fc.name AS from_city_name,
        tc.name AS to_city_name,
        (
            SELECT COUNT(*)
            FROM trips t
            WHERE t.schedule_template_id = st.id
        ) AS generated_trip_count
    FROM schedule_templates st
    INNER JOIN buses b ON b.id = st.bus_id
    INNER JOIN routes r ON r.id = st.route_id
    INNER JOIN cities fc ON fc.id = r.from_city_id
    INNER JOIN cities tc ON tc.id = r.to_city_id
    WHERE st.company_id = ?
    ORDER BY st.id DESC
";
$templateStmt = $conn->prepare($templateSql);
$templateStmt->bind_param('i', $company['company_id']);
$templateStmt->execute();
$templateResult = $templateStmt->get_result();

while ($row = $templateResult->fetch_assoc()) {
    $templates[] = $row;
}
$templateStmt->close();

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Schedule Template & Trip Generation</h2>
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

    <?php if (empty($routes) || empty($buses)): ?>
        <div class="alert alert-warning rounded-4">
            Please make sure your company has at least one <strong>active route</strong> and one
            <strong>active bus</strong> before creating schedule templates.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Create Schedule Template</h5>

                    <form action="<?php echo BASE_URL; ?>actions/generate_schedule_action.php" method="POST">
                        <input type="hidden" name="action_type" value="create_template">

                        <div class="mb-3">
                            <label class="form-label">Route</label>
                            <select name="route_id" class="form-select" required>
                                <option value="">Select route</option>
                                <?php foreach ($routes as $route): ?>
                                    <option
                                        value="<?php echo e($route['id']); ?>"
                                        data-price="<?php echo e($route['base_price']); ?>"
                                        <?php echo ((string)old('route_id') === (string)$route['id']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($route['from_city_name'] . ' → ' . $route['to_city_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Bus</label>
                            <select name="bus_id" class="form-select" required>
                                <option value="">Select bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <option
                                        value="<?php echo e($bus['id']); ?>"
                                        <?php echo ((string)old('bus_id') === (string)$bus['id']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo e($bus['bus_number'] . ' (' . strtoupper($bus['layout_type']) . ', ' . $bus['total_seats'] . ' seats)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Departure Time</label>
                                <input
                                    type="time"
                                    name="departure_time"
                                    class="form-control"
                                    value="<?php echo e(old('departure_time')); ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Arrival Time</label>
                                <input
                                    type="time"
                                    name="arrival_time"
                                    class="form-control"
                                    value="<?php echo e(old('arrival_time')); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mt-3 mb-3">
                            <label class="form-label">Price</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="price"
                                class="form-control"
                                value="<?php echo e(old('price')); ?>"
                                placeholder="Example: 25000"
                                required
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select name="frequency" id="frequency" class="form-select" required>
                                <?php
                                $selectedFrequency = old('frequency', 'daily');
                                foreach ($frequencyOptions as $frequency):
                                ?>
                                    <option value="<?php echo e($frequency); ?>" <?php echo ($selectedFrequency === $frequency) ? 'selected' : ''; ?>>
                                        <?php echo e(ucfirst($frequency)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="weekdaySection" class="mb-3" style="<?php echo ($selectedFrequency === 'daily') ? 'display:none;' : ''; ?>">
                            <label class="form-label">Weekdays</label>
                            <div class="row g-2">
                                <?php
                                $selectedWeekdays = old('weekdays', []);
                                if (!is_array($selectedWeekdays)) {
                                    $selectedWeekdays = [];
                                }
                                foreach ($weekdayOptions as $day):
                                ?>
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                name="weekdays[]"
                                                value="<?php echo e($day); ?>"
                                                id="day_<?php echo e($day); ?>"
                                                <?php echo in_array($day, $selectedWeekdays, true) ? 'checked' : ''; ?>
                                            >
                                            <label class="form-check-label" for="day_<?php echo e($day); ?>">
                                                <?php echo e($day); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">
                                For weekly/custom schedules, select the days to generate trips.
                            </small>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Active From</label>
                                <input
                                    type="date"
                                    name="active_from"
                                    class="form-control"
                                    value="<?php echo e(old('active_from')); ?>"
                                    required
                                >
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Active To</label>
                                <input
                                    type="date"
                                    name="active_to"
                                    class="form-control"
                                    value="<?php echo e(old('active_to')); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="mt-3 mb-4">
                            <label class="form-label">Template Status</label>
                            <select name="status" class="form-select" required>
                                <?php
                                $selectedStatus = old('status', 'active');
                                foreach ($statusOptions as $status):
                                ?>
                                    <option value="<?php echo e($status); ?>" <?php echo ($selectedStatus === $status) ? 'selected' : ''; ?>>
                                        <?php echo e(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                Save Template
                            </button>

                            <button
                                type="submit"
                                class="btn btn-success"
                                onclick="this.form.action_type.value='create_and_generate';"
                            >
                                Save + Generate Trips
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($templates)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">
                                No schedule templates found yet.
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Route / Bus</th>
                                        <th>Time</th>
                                        <th>Frequency</th>
                                        <th>Date Range</th>
                                        <th>Price</th>
                                        <th>Trips</th>
                                        <th>Status</th>
                                        <th style="min-width: 170px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($templates as $template): ?>
                                        <tr>
                                            <td><?php echo e($template['id']); ?></td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php echo e($template['from_city_name']); ?> → <?php echo e($template['to_city_name']); ?>
                                                </div>
                                                <div class="small text-muted">
                                                    Bus: <?php echo e($template['bus_number']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div><?php echo e(substr($template['departure_time'], 0, 5)); ?> → <?php echo e(substr($template['arrival_time'], 0, 5)); ?></div>
                                                <div class="small text-muted">
                                                    Days: <?php echo e(format_weekdays_display($template['weekdays'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo schedule_frequency_badge($template['frequency']); ?>">
                                                    <?php echo e(ucfirst($template['frequency'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?php echo e($template['active_from']); ?></div>
                                                <div class="small text-muted">to <?php echo e($template['active_to']); ?></div>
                                            </td>
                                            <td><?php echo e(number_format((float)$template['price'], 2)); ?> MMK</td>
                                            <td><?php echo e((int)$template['generated_trip_count']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo schedule_status_badge($template['status']); ?>">
                                                    <?php echo e(ucfirst($template['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form action="<?php echo BASE_URL; ?>actions/generate_schedule_action.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action_type" value="generate_existing">
                                                    <input type="hidden" name="schedule_template_id" value="<?php echo e($template['id']); ?>">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Generate trips for this schedule template?');"
                                                    >
                                                        Generate Trips
                                                    </button>
                                                </form>
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

<script>
(function () {
    const frequency = document.getElementById('frequency');
    const weekdaySection = document.getElementById('weekdaySection');

    if (frequency && weekdaySection) {
        function toggleWeekdays() {
            weekdaySection.style.display = frequency.value === 'daily' ? 'none' : 'block';
        }

        frequency.addEventListener('change', toggleWeekdays);
        toggleWeekdays();
    }
})();
</script>

<?php
clear_old_input();
require_once __DIR__ . '/../includes/footer.php';
?>