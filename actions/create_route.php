<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

if (!in_array((string)current_user_role(), ['super_admin', 'bus_admin'], true)) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $role = current_user_role();
    redirect($role === 'super_admin' ? 'admin/routes.php' : 'bus_admin/routes.php');
}

$conn = getDBConnection();
$scope = resolve_route_schedule_company_scope($conn);

if ($scope['mode'] === 'bus_admin') {
    require_company_permission($conn, 'manage_routes');
}

$companyId = (int)($scope['company_id'] ?? 0);

function redirect_route_page(array $scope): void
{
    if (($scope['mode'] ?? '') === 'super_admin') {
        $companyId = (int)($scope['company_id'] ?? 0);
        redirect('admin/routes.php' . ($companyId > 0 ? '?company_id=' . $companyId : ''));
    }

    redirect('bus_admin/routes.php');
}

$fromCityId = (int)($_POST['from_city_id'] ?? 0);
$toCityId = (int)($_POST['to_city_id'] ?? 0);
$distanceKm = trim($_POST['distance_km'] ?? '');
$durationMinutes = (int)($_POST['duration_minutes'] ?? 0);
$basePrice = trim($_POST['base_price'] ?? '');
$status = trim($_POST['status'] ?? '');

$allowedStatuses = ['active', 'inactive'];

if ($companyId <= 0) {
    $conn->close();
    set_flash('error', 'Please choose a company first.');
    redirect_route_page($scope);
}

if ($fromCityId <= 0 || $toCityId <= 0) {
    $conn->close();
    set_flash('error', 'Please select both cities.');
    redirect_route_page($scope);
}

if ($fromCityId === $toCityId) {
    $conn->close();
    set_flash('error', 'From city and To city cannot be the same.');
    redirect_route_page($scope);
}

if ($distanceKm === '' || !is_numeric($distanceKm) || (float)$distanceKm <= 0) {
    $conn->close();
    set_flash('error', 'Distance must be a valid positive number.');
    redirect_route_page($scope);
}

if ($durationMinutes <= 0) {
    $conn->close();
    set_flash('error', 'Duration must be greater than 0.');
    redirect_route_page($scope);
}

if ($basePrice === '' || !is_numeric($basePrice) || (float)$basePrice < 0) {
    $conn->close();
    set_flash('error', 'Base price must be a valid number.');
    redirect_route_page($scope);
}

if (!in_array($status, $allowedStatuses, true)) {
    $conn->close();
    set_flash('error', 'Invalid route status.');
    redirect_route_page($scope);
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
    redirect_route_page($scope);
}

$cityStmt->bind_param('i', $toCityId);
$cityStmt->execute();
$toResult = $cityStmt->get_result();
if ($toResult->num_rows !== 1) {
    $cityStmt->close();
    $conn->close();
    set_flash('error', 'Invalid arrival city.');
    redirect_route_page($scope);
}
$cityStmt->close();

$dupSql = "
    SELECT id
    FROM routes
    WHERE company_id = ? AND from_city_id = ? AND to_city_id = ?
    LIMIT 1
";
$dupStmt = $conn->prepare($dupSql);
$dupStmt->bind_param('iii', $companyId, $fromCityId, $toCityId);
$dupStmt->execute();
$dupResult = $dupStmt->get_result();

if ($dupResult->num_rows > 0) {
    $dupStmt->close();
    $conn->close();
    set_flash('error', 'This route already exists for the selected company.');
    redirect_route_page($scope);
}
$dupStmt->close();

$distanceValue = (float)$distanceKm;
$priceValue = (float)$basePrice;

$sql = "
    INSERT INTO routes
    (company_id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'iiidids',
    $companyId,
    $fromCityId,
    $toCityId,
    $distanceValue,
    $durationMinutes,
    $priceValue,
    $status
);

if ($stmt->execute()) {
    $routeId = (int)$stmt->insert_id;
    $stmt->close();

    $action = 'route_created';
    $entityType = 'route';
    $description = 'Created route ID: ' . $routeId;
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
    set_flash('success', 'Route created successfully.');
    redirect_route_page($scope);
}

$stmt->close();
$conn->close();

set_flash('error', 'Failed to create route.');
redirect_route_page($scope);