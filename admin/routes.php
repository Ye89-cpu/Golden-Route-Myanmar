<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';

require_role('super_admin');

$conn = getDBConnection();

$scope = resolve_route_schedule_company_scope($conn);
$company = $scope['company'];
$companies = $scope['companies'];
$companyId = (int)($scope['company_id'] ?? 0);

$page_title = 'Admin Route Management';

function route_status_badge_class($status)
{
    return $status === 'active' ? 'success' : 'secondary';
}

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

$editRoute = null;
$editRouteId = (int)($_GET['edit'] ?? 0);

if ($companyId > 0 && $editRouteId > 0) {
    $editSql = "
        SELECT id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status
        FROM routes
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ";

    $editStmt = $conn->prepare($editSql);
    $editStmt->bind_param('ii', $editRouteId, $companyId);
    $editStmt->execute();
    $editResult = $editStmt->get_result();

    $editRoute = $editResult->fetch_assoc() ?: null;

    $editStmt->close();
}

$routes = [];

if ($companyId > 0) {
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
    $listStmt->bind_param('i', $companyId);
    $listStmt->execute();
    $listResult = $listStmt->get_result();

    while ($row = $listResult->fetch_assoc()) {
        $routes[] = $row;
    }

    $listStmt->close();
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Admin Route Management</h2>
            <p class="text-muted mb-0">Super Admin can manage routes for any approved bus company.</p>
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

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" action="<?php echo BASE_URL; ?>admin/routes.php" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label">Choose Company</label>

                    <select name="company_id" class="form-select" required>
                        <option value="">Select bus company</option>

                        <?php foreach ($companies as $item): ?>
                            <?php
                            /*
                                FIX:
                                Some helper functions return:
                                company_id, company_name

                                But old code used:
                                id, name

                                This safe code supports both formats.
                            */
                            $itemCompanyId = (int)($item['company_id'] ?? $item['id'] ?? 0);
                            $itemCompanyName = $item['company_name'] ?? $item['name'] ?? 'Unknown Company';
                            $itemCompanyType = $item['company_type'] ?? 'bus_company';
                            ?>

                            <?php if ($itemCompanyId > 0): ?>
                                <option value="<?php echo e($itemCompanyId); ?>" <?php echo $companyId === $itemCompanyId ? 'selected' : ''; ?>>
                                    <?php echo e($itemCompanyName); ?> (<?php echo e($itemCompanyType); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-4">
                    <button type="submit" class="btn btn-primary w-100">
                        Load Company Routes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($companyId <= 0 || !$company): ?>
        <div class="alert alert-info rounded-4">
            Choose a company first to manage routes.
        </div>
    <?php else: ?>
        <?php
        $currentCompanyName = $company['company_name'] ?? $company['name'] ?? 'Selected Company';
        ?>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">
                            <?php echo $editRoute ? 'Edit Route' : 'Create New Route'; ?>
                        </h5>

                        <p class="text-muted">
                            Company: <?php echo e($currentCompanyName); ?>
                        </p>

                        <form action="<?php echo BASE_URL . ($editRoute ? 'actions/update_route.php' : 'actions/create_route.php'); ?>" method="POST">
                            <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">

                            <?php if ($editRoute): ?>
                                <input type="hidden" name="route_id" value="<?php echo e($editRoute['id']); ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">From City</label>

                                <select name="from_city_id" class="form-select" required>
                                    <option value="">Select departure city</option>

                                    <?php $selectedFrom = $editRoute['from_city_id'] ?? ''; ?>

                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo (string)$selectedFrom === (string)$city['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">To City</label>

                                <select name="to_city_id" class="form-select" required>
                                    <option value="">Select arrival city</option>

                                    <?php $selectedTo = $editRoute['to_city_id'] ?? ''; ?>

                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo (string)$selectedTo === (string)$city['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Distance (KM)</label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    name="distance_km"
                                    class="form-control"
                                    value="<?php echo e($editRoute['distance_km'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Duration (Minutes)</label>

                                <input
                                    type="number"
                                    min="1"
                                    name="duration_minutes"
                                    class="form-control"
                                    value="<?php echo e($editRoute['duration_minutes'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Base Price</label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    name="base_price"
                                    class="form-control"
                                    value="<?php echo e($editRoute['base_price'] ?? ''); ?>"
                                    required
                                >
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Status</label>

                                <select name="status" class="form-select" required>
                                    <option value="active" <?php echo (($editRoute['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>
                                        Active
                                    </option>

                                    <option value="inactive" <?php echo (($editRoute['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>
                                        Inactive
                                    </option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <?php echo $editRoute ? 'Update Route' : 'Create Route'; ?>
                            </button>

                            <?php if ($editRoute): ?>
                                <a href="<?php echo BASE_URL; ?>admin/routes.php?company_id=<?php echo e($companyId); ?>" class="btn btn-outline-secondary w-100 mt-2">
                                    Cancel Edit
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Route List</h5>

                        <p class="text-muted">
                            Company: <?php echo e($currentCompanyName); ?>
                        </p>

                        <?php if (empty($routes)): ?>
                            <div class="alert alert-info mb-0">
                                No routes found for this company yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Route</th>
                                            <th>Distance</th>
                                            <th>Duration</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($routes as $route): ?>
                                            <tr>
                                                <td>
                                                    <?php echo e($route['from_city_name']); ?>
                                                    →
                                                    <?php echo e($route['to_city_name']); ?>
                                                </td>

                                                <td>
                                                    <?php echo e($route['distance_km']); ?> km
                                                </td>

                                                <td>
                                                    <?php echo e($route['duration_minutes']); ?> min
                                                </td>

                                                <td>
                                                    <?php echo number_format((float)$route['base_price'], 2); ?>
                                                </td>

                                                <td>
                                                    <span class="badge bg-<?php echo route_status_badge_class($route['status']); ?>">
                                                        <?php echo e(ucfirst($route['status'])); ?>
                                                    </span>
                                                </td>

                                                <td class="text-end">
                                                    <a
                                                        href="<?php echo BASE_URL; ?>admin/routes.php?company_id=<?php echo e($companyId); ?>&edit=<?php echo e($route['id']); ?>"
                                                        class="btn btn-sm btn-outline-primary"
                                                    >
                                                        Edit
                                                    </a>

                                                    <form action="<?php echo BASE_URL; ?>actions/delete_route.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">
                                                        <input type="hidden" name="route_id" value="<?php echo e($route['id']); ?>">

                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Delete this route?')"
                                                        >
                                                            Delete
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
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>