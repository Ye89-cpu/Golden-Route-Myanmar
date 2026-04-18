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

function redirect_delete_route_page(array $scope): void
{
    if (($scope['mode'] ?? '') === 'super_admin') {
        $companyId = (int)($scope['company_id'] ?? 0);
        redirect('admin/routes.php' . ($companyId > 0 ? '?company_id=' . $companyId : ''));
    }

    redirect('bus_admin/routes.php');
}

if ($companyId <= 0 || $routeId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid route ID.');
    redirect_delete_route_page($scope);
}

$routeSql = "SELECT id FROM routes WHERE id = ? AND company_id = ? LIMIT 1";
$routeStmt = $conn->prepare($routeSql);
$routeStmt->bind_param('ii', $routeId, $companyId);
$routeStmt->execute();
$routeResult = $routeStmt->get_result();

if ($routeResult->num_rows !== 1) {
    $routeStmt->close();
    $conn->close();
    set_flash('error', 'You are not allowed to delete this route.');
    redirect_delete_route_page($scope);
}
$routeStmt->close();

$usageSql = "
    SELECT
        (SELECT COUNT(*) FROM schedule_templates WHERE route_id = ?) AS schedule_count,
        (SELECT COUNT(*) FROM trips WHERE route_id = ?) AS trip_count
";
$usageStmt = $conn->prepare($usageSql);
$usageStmt->bind_param('ii', $routeId, $routeId);
$usageStmt->execute();
$usageResult = $usageStmt->get_result();
$usage = $usageResult->fetch_assoc();
$usageStmt->close();

if ((int)$usage['schedule_count'] > 0 || (int)$usage['trip_count'] > 0) {
    $conn->close();
    set_flash('error', 'This route is already used in schedules or trips. Set it inactive instead.');
    redirect_delete_route_page($scope);
}

$deleteSql = "DELETE FROM routes WHERE id = ? AND company_id = ?";
$deleteStmt = $conn->prepare($deleteSql);
$deleteStmt->bind_param('ii', $routeId, $companyId);

if ($deleteStmt->execute()) {
    $deleteStmt->close();

    $action = 'route_deleted';
    $entityType = 'route';
    $description = 'Deleted route ID: ' . $routeId;
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
    set_flash('success', 'Route deleted successfully.');
    redirect_delete_route_page($scope);
}

$deleteStmt->close();
$conn->close();

set_flash('error', 'Failed to delete route.');
redirect_delete_route_page($scope);