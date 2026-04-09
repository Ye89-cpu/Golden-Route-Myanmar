<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyUserId = (int)($_POST['company_user_id'] ?? 0);
$permissions = $_POST['permissions'] ?? [];

if ($companyUserId <= 0) {
    set_flash('error', 'Invalid company user ID.');
    redirect('admin/companies.php');
}

$allowedPermissions = [
    'manage_buses',
    'manage_bookings',
    'approve_bookings',
    'manage_routes',
    'manage_schedules',
    'view_ticket',
];

$cleanPermissions = [];
foreach ((array)$permissions as $permission) {
    $permission = trim((string)$permission);
    if (in_array($permission, $allowedPermissions, true)) {
        $cleanPermissions[] = $permission;
    }
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    sync_company_permissions($conn, $companyUserId, $cleanPermissions);

    $companyUserSql = "
        SELECT cu.company_id, u.name, u.email
        FROM company_users cu
        INNER JOIN users u ON u.id = cu.user_id
        WHERE cu.id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($companyUserSql);
    if ($stmt) {
        $stmt->bind_param('i', $companyUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $action = 'company_permissions_updated';
            $entityType = 'company';
            $entityId = (int)$row['company_id'];
            $description = 'Updated permissions for admin: ' . ($row['email'] ?? $row['name'] ?? 'Unknown');
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userId = current_user_id();

            $auditSql = "
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $auditStmt = $conn->prepare($auditSql);
            if ($auditStmt) {
                $auditStmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $description, $ipAddress);
                $auditStmt->execute();
                $auditStmt->close();
            }
        }
    }

    $conn->commit();
    set_flash('success', 'Company admin permissions updated successfully.');
} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', $e->getMessage());
}

$conn->close();
redirect('admin/companies.php');