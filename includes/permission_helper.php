<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

function default_permissions_for_company_type(string $companyType): array
{
    switch ($companyType) {
        case 'bus_company':
            return [
                'manage_buses',
                'manage_bookings',
                'approve_bookings',
                'manage_routes',
                'manage_schedules',
                'view_ticket',
            ];

        case 'tour_operator':
            return [
                'manage_bookings',
            ];

        case 'both':
            return [
                'manage_buses',
                'manage_bookings',
                'approve_bookings',
                'manage_routes',
                'manage_schedules',
                'view_ticket',
            ];

        default:
            return [];
    }
}

function ensure_unique_user_email(mysqli $conn, string $baseEmail): string
{
    $email = trim($baseEmail);
    if ($email === '') {
        $email = 'companyadmin@mbtb.local';
    }

    $candidate = $email;
    $counter = 1;

    while (true) {
        $sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return $candidate;
        }

        $parts = explode('@', $email, 2);
        $local = $parts[0] ?? 'companyadmin';
        $domain = $parts[1] ?? 'mbtb.local';

        $candidate = $local . $counter . '@' . $domain;
        $counter++;
    }
}

function create_company_admin_account(mysqli $conn, array $company, ?string $customEmail = null, ?string $customPassword = null): ?array
{
    $companyId = (int)($company['id'] ?? 0);
    $companyType = trim((string)($company['company_type'] ?? 'bus_company'));
    $companyName = trim((string)($company['name'] ?? 'Company'));
    $companyPhone = trim((string)($company['phone'] ?? ''));
    $companyEmail = trim((string)($company['email'] ?? ''));

    if ($companyId <= 0) {
        return null;
    }

    $role = 'bus_admin';
    if ($companyType === 'tour_operator') {
        $role = 'tour_admin';
    }

    $baseEmailName = preg_replace('/[^a-z0-9]+/i', '', strtolower($companyName));
    if ($baseEmailName === '') {
        $baseEmailName = 'companyadmin' . $companyId;
    }

    $emailGuess = $customEmail ?: ($companyEmail !== '' ? $companyEmail : ($baseEmailName . '@mbtb.local'));
    $email = ensure_unique_user_email($conn, $emailGuess);

    $plainPassword = $customPassword ?: ('Admin@' . rand(1000, 9999));
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $userName = $companyName . ' Admin';

    $userSql = "
        INSERT INTO users (name, email, phone, password, role, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ";
    $userStmt = $conn->prepare($userSql);
    if (!$userStmt) {
        return null;
    }

    $userStmt->bind_param('sssss', $userName, $email, $companyPhone, $hashedPassword, $role);

    if (!$userStmt->execute()) {
        $userStmt->close();
        return null;
    }

    $userId = (int)$userStmt->insert_id;
    $userStmt->close();

    if ($userId <= 0) {
        return null;
    }

    $companyUserSql = "
        INSERT INTO company_users (company_id, user_id, role_in_company, status, created_at, updated_at)
        VALUES (?, ?, 'admin', 'active', NOW(), NOW())
    ";
    $companyUserStmt = $conn->prepare($companyUserSql);
    if (!$companyUserStmt) {
        return null;
    }

    $companyUserStmt->bind_param('ii', $companyId, $userId);

    if (!$companyUserStmt->execute()) {
        $companyUserStmt->close();
        return null;
    }

    $companyUserId = (int)$companyUserStmt->insert_id;
    $companyUserStmt->close();

    if ($companyUserId <= 0) {
        return null;
    }

    $defaultPermissions = default_permissions_for_company_type($companyType);
    sync_company_permissions($conn, $companyUserId, $defaultPermissions);

    return [
        'user_id' => $userId,
        'company_user_id' => $companyUserId,
        'role' => $role,
        'email' => $email,
        'generated_password' => $plainPassword,
    ];
}

function get_company_primary_admin(mysqli $conn, int $companyId): ?array
{
    $sql = "
        SELECT
            cu.id AS company_user_id,
            cu.company_id,
            cu.user_id,
            cu.role_in_company,
            cu.status AS company_user_status,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.status AS user_status
        FROM company_users cu
        INNER JOIN users u ON u.id = cu.user_id
        WHERE cu.company_id = ?
          AND cu.role_in_company = 'admin'
        ORDER BY cu.id ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function get_company_permission_keys(mysqli $conn, int $companyUserId): array
{
    $permissions = [];

    $sql = "
        SELECT permission_key
        FROM company_user_permissions
        WHERE company_user_id = ?
        ORDER BY permission_key ASC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $permissions;
    }

    $stmt->bind_param('i', $companyUserId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $permissions[] = (string)$row['permission_key'];
    }

    $stmt->close();

    return $permissions;
}

function sync_company_permissions(mysqli $conn, int $companyUserId, array $permissions): void
{
    $clean = [];
    foreach ($permissions as $permission) {
        $permission = trim((string)$permission);
        if ($permission !== '') {
            $clean[] = $permission;
        }
    }

    $clean = array_values(array_unique($clean));

    $deleteSql = "DELETE FROM company_user_permissions WHERE company_user_id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $companyUserId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    if (empty($clean)) {
        return;
    }

    $insertSql = "
        INSERT INTO company_user_permissions (company_user_id, permission_key, created_at)
        VALUES (?, ?, NOW())
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        return;
    }

    foreach ($clean as $permission) {
        $insertStmt->bind_param('is', $companyUserId, $permission);
        $insertStmt->execute();
    }

    $insertStmt->close();
}

function current_company_user_record(mysqli $conn): ?array
{
    $userId = (int)current_user_id();
    if ($userId <= 0) {
        return null;
    }

    $sql = "
        SELECT *
        FROM company_users
        WHERE user_id = ?
          AND status = 'active'
        ORDER BY id ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function user_has_company_permission(mysqli $conn, string $permission): bool
{
    $companyUser = current_company_user_record($conn);
    if (!$companyUser) {
        return false;
    }

    $companyUserId = (int)($companyUser['id'] ?? 0);
    if ($companyUserId <= 0) {
        return false;
    }

    $sql = "
        SELECT id
        FROM company_user_permissions
        WHERE company_user_id = ?
          AND permission_key = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('is', $companyUserId, $permission);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool)$row;
}

function require_company_permission(mysqli $conn, string $permission): void
{
    if (!user_has_company_permission($conn, $permission)) {
        set_flash('error', 'You do not have permission for this action.');
        redirect('index.php');
    }
}