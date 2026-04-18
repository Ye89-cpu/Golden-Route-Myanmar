<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

if (!in_array((string)current_user_role(), ['super_admin', 'bus_admin'], true)) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user_role() === 'super_admin' ? 'admin/routes.php' : 'bus_admin/routes.php');
}

$conn = getDBConnection();
$scope = resolve_route_schedule_company_scope($conn);

if ($scope['mode'] === 'bus_admin') {
    require_company_permission($conn, 'manage_routes');
}

$companyId = (int)($scope['company_id'] ?? 0);
$routeId = (int)($_POST['route_id'] ?? 0);

function redirect_update_route_page(array $scope, int $routeId = 0): void
{
    if (($scope['mode'] ?? '') === 'super_admin') {
        $companyId = (int)($scope['company_id'] ?? 0);
        $url = 'admin/routes.php';
        $params = [];
        if ($companyId > 0) {
            $params[] = 'company_id=' . $companyId;
        }
        if ($routeId > 0) {
            $params[] = 'edit=' . $routeId;
        }
        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }
        redirect($url);
    }

    redirect('bus_admin/routes.php' . ($routeId > 0 ? '?edit=' . $routeId : ''));
}

if ($companyId <= 0 || $routeId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid company or route.');
    redirect_update_route_page($scope, $routeId);
}

$fromCityId = (int)($_POST['from_city_id'] ?? 0);
$toCityId = (int)($_POST['to_city_id'] ?? 0);
$distanceKm = trim($_POST['distance_km'] ?? '');
$durationMinutes = (int)($_POST['duration_minutes'] ?? 0);
$basePrice = trim($_POST['base_price'] ?? '');
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['active', 'inactive'];

$ownerSql = "SELECT id FROM routes WHERE id = ? AND company_id = ? LIMIT 1";
$ownerStmt = $conn->prepare($ownerSql);
$ownerStmt->bind_param('ii', $routeId, $companyId);
$ownerStmt->execute();
$ownerResult = $ownerStmt->get_result();

if ($ownerResult->num_rows !== 1) {
    $ownerStmt->close();
    $conn->close();
    set_flash('error', 'You are not allowed to update this route.');
    redirect_update_route_page($scope);
}
$ownerStmt->close();

if ($fromCityId <= 0 || $toCityId <= 0) {
    $conn->close();
    set_flash('error', 'Please select both cities.');
    redirect_update_route_page($scope, $routeId);
}

if ($fromCityId === $toCityId) {
    $conn->close();
    set_flash('error', 'From city and To city cannot be the same.');
    redirect_update_route_page($scope, $routeId);
}

if ($distanceKm === '' || !is_numeric($distanceKm) || (float)$distanceKm <= 0) {
    $conn->close();
    set_flash('error', 'Distance must be a valid positive number.');
    redirect_update_route_page($scope, $routeId);
}

if ($durationMinutes <= 0) {
    $conn->close();
    set_flash('error', 'Duration must be greater than 0.');
    redirect_update_route_page($scope, $routeId);
}

if ($basePrice === '' || !is_numeric($basePrice) || (float)$basePrice < 0) {
    $conn->close();
    set_flash('error', 'Base price must be a valid number.');
    redirect_update_route_page($scope, $routeId);
}

if (!in_array($status, $allowedStatuses, true)) {
    $conn->close();
    set_flash('error', 'Invalid route status.');
    redirect_update_route_page($scope, $routeId);
}

$citySql = "SELECT id FROM cities WHERE id = ? AND is_active = 1 LIMIT 1";
$cityStmt = $conn->prepare($citySql);

$cityStmt->bind_param('i', $fromCityId);
$cityStmt->execute();
$fromResult = $cityStmt->get_result();
if ($fromResult->num_rows !== 1) {
    $cityStmt->close();
    $conn->close();
    set_flash('error', 'Invalid departure city.');
    redirect_update_route_page($scope, $routeId);
}

$cityStmt->bind_param('i', $toCityId);
$cityStmt->execute();
$toResult = $cityStmt->get_result();
if ($toResult->num_rows !== 1) {
    $cityStmt->close();
    $conn->close();
    set_flash('error', 'Invalid arrival city.');
    redirect_update_route_page($scope, $routeId);
}
$cityStmt->close();

$dupSql = "
    SELECT id
    FROM routes
    WHERE company_id = ? AND from_city_id = ? AND to_city_id = ? AND id <> ?
    LIMIT 1
";
$dupStmt = $conn->prepare($dupSql);
$dupStmt->bind_param('iiii', $companyId, $fromCityId, $toCityId, $routeId);
$dupStmt->execute();
$dupResult = $dupStmt->get_result();

if ($dupResult->num_rows > 0) {
    $dupStmt->close();
    $conn->close();
    set_flash('error', 'This route already exists for the selected company.');
    redirect_update_route_page($scope, $routeId);
}
$dupStmt->close();

$distanceValue = (float)$distanceKm;
$priceValue = (float)$basePrice;

$updateSql = "
    UPDATE routes
    SET from_city_id = ?, to_city_id = ?, distance_km = ?, duration_minutes = ?, base_price = ?, status = ?
    WHERE id = ? AND company_id = ?
";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param(
    'iididsii',
    $fromCityId,
    $toCityId,
    $distanceValue,
    $durationMinutes,
    $priceValue,
    $status,
    $routeId,
    $companyId
);

if ($updateStmt->execute()) {
    $updateStmt->close();

    $action = 'route_updated';
    $entityType = 'route';
    $description = 'Updated route ID: ' . $routeId;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $userId, $action, $entityType, $routeId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->close();
    set_flash('success', 'Route updated successfully.');
    redirect_update_route_page($scope);
}

$updateStmt->close();
$conn->close();

set_flash('error', 'Failed to update route.');
redirect_update_route_page($scope, $routeId);