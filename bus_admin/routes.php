<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';


$conn = getDBConnection();
require_company_permission($conn, 'manage_routes');

require_role('bus_admin');

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

function route_status_badge_class($status)
{
    return $status === 'active' ? 'success' : 'secondary';
}

$page_title = 'Manage Routes';

$allowedStatuses = ['active', 'inactive'];

/*
|--------------------------------------------------------------------------
| Load active cities
|--------------------------------------------------------------------------
*/
$cities = [];
$citySql = "
    SELECT id, name, state_region
    FROM cities
    WHERE is_active = 1
    ORDER BY name ASC
";
$cityStmt = $conn->prepare($citySql);
$cityStmt->execute();
$cityResult = $cityStmt->get_result();

while ($row = $cityResult->fetch_assoc()) {
    $cities[] = $row;
}
$cityStmt->close();

/*
|--------------------------------------------------------------------------
| Load route for edit
|--------------------------------------------------------------------------
*/
$editRoute = null;
$editRouteId = (int)($_GET['edit'] ?? 0);

if ($editRouteId > 0) {
    $editSql = "
        SELECT id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status
        FROM routes
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ";
    $editStmt = $conn->prepare($editSql);
    $editStmt->bind_param('ii', $editRouteId, $company['company_id']);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $editRoute = $editResult->fetch_assoc() ?: null;
    $editStmt->close();

    if (!$editRoute) {
        $conn->close();
        set_flash('error', 'Route not found for editing.');
        redirect('bus_admin/routes.php');
    }
}

/*
|--------------------------------------------------------------------------
| Load route list
|--------------------------------------------------------------------------
*/
$routes = [];
$listSql = "
    SELECT
        r.id,
        r.from_city_id,
        r.to_city_id,
        r.distance_km,
        r.duration_minutes,
        r.base_price,
        r.status,
        r.created_at,
        fc.name AS from_city_name,
        tc.name AS to_city_name
    FROM routes r
    INNER JOIN cities fc ON fc.id = r.from_city_id
    INNER JOIN cities tc ON tc.id = r.to_city_id
    WHERE r.company_id = ?
    ORDER BY fc.name ASC, tc.name ASC, r.id DESC
";
$listStmt = $conn->prepare($listSql);
$listStmt->bind_param('i', $company['company_id']);
$listStmt->execute();
$listResult = $listStmt->get_result();

while ($row = $listResult->fetch_assoc()) {
    $routes[] = $row;
}
$listStmt->close();

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Route Management</h2>
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
                        <?php echo $editRoute ? 'Edit Route' : 'Create New Route'; ?>
                    </h5>

                    <?php if (empty($cities)): ?>
                        <div class="alert alert-warning mb-0">
                            No active cities found. Please add cities first.
                        </div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL . ($editRoute ? 'actions/update_route.php' : 'actions/create_route.php'); ?>" method="POST">
                            <?php if ($editRoute): ?>
                                <input type="hidden" name="route_id" value="<?php echo e($editRoute['id']); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">From City</label>
                                <select name="from_city_id" class="form-select" required>
                                    <option value="">Select departure city</option>
                                    <?php
                                    $selectedFrom = $editRoute['from_city_id'] ?? old('from_city_id');
                                    foreach ($cities as $city):
                                    ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo ((string)$selectedFrom === (string)$city['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">To City</label>
                                <select name="to_city_id" class="form-select" required>
                                    <option value="">Select arrival city</option>
                                    <?php
                                    $selectedTo = $editRoute['to_city_id'] ?? old('to_city_id');
                                    foreach ($cities as $city):
                                    ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo ((string)$selectedTo === (string)$city['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Distance (KM)</label>
                                <input
                                    type="number"
                                    name="distance_km"
                                    class="form-control"
                                    step="0.01"
                                    min="1"
                                    value="<?php echo e($editRoute['distance_km'] ?? old('distance_km')); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Duration (Minutes)</label>
                                <input
                                    type="number"
                                    name="duration_minutes"
                                    class="form-control"
                                    min="1"
                                    value="<?php echo e($editRoute['duration_minutes'] ?? old('duration_minutes')); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Base Price</label>
                                <input
                                    type="number"
                                    name="base_price"
                                    class="form-control"
                                    step="0.01"
                                    min="0"
                                    value="<?php echo e($editRoute['base_price'] ?? old('base_price')); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <?php
                                    $selectedStatus = $editRoute['status'] ?? old('status', 'active');
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
                                    <?php echo $editRoute ? 'Update Route' : 'Create Route'; ?>
                                </button>

                                <?php if ($editRoute): ?>
                                    <a href="<?php echo BASE_URL; ?>bus_admin/routes.php" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <?php if (empty($routes)): ?>
                        <div class="p-4">
                            <div class="alert alert-info mb-0">No routes found yet.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Route</th>
                                        <th>Distance</th>
                                        <th>Duration</th>
                                        <th>Base Price</th>
                                        <th>Status</th>
                                        <th style="min-width: 170px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routes as $route): ?>
                                        <tr>
                                            <td><?php echo e($route['id']); ?></td>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?php echo e($route['from_city_name']); ?> → <?php echo e($route['to_city_name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo e(number_format((float)$route['distance_km'], 2)); ?> km</td>
                                            <td><?php echo e((int)$route['duration_minutes']); ?> min</td>
                                            <td><?php echo e(number_format((float)$route['base_price'], 2)); ?> MMK</td>
                                            <td>
                                                <span class="badge bg-<?php echo route_status_badge_class($route['status']); ?>">
                                                    <?php echo e(ucfirst($route['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a
                                                        href="<?php echo BASE_URL; ?>bus_admin/routes.php?edit=<?php echo e($route['id']); ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form action="<?php echo BASE_URL; ?>actions/delete_route.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="route_id" value="<?php echo e($route['id']); ?>">
                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this route?');"
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