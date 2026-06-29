<?php
require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/db.php';

function get_bus_admin_company(mysqli $conn, int $userId): ?array
{
    $sql = "
        SELECT
            cu.id AS company_user_id,
            cu.company_id,
            cu.role_in_company,
            c.name AS company_name,
            c.company_type,
            c.status AS company_status
        FROM company_users cu
        INNER JOIN companies c ON c.id = cu.company_id
        WHERE cu.user_id = ?
          AND cu.status = 'active'
          AND c.status = 'approved'
          AND c.company_type IN ('bus_company', 'both')
        ORDER BY cu.id ASC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $company ?: null;
}

function get_tour_admin_company(mysqli $conn, int $userId): ?array
{
    $sql = "
        SELECT
            cu.id AS company_user_id,
            cu.company_id,
            cu.role_in_company,
            c.name AS company_name,
            c.company_type,
            c.status AS company_status
        FROM company_users cu
        INNER JOIN companies c ON c.id = cu.company_id
        WHERE cu.user_id = ?
          AND cu.status = 'active'
          AND c.status = 'approved'
          AND c.company_type IN ('tour_operator', 'both')
        ORDER BY cu.id ASC
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $company ?: null;
}

function require_bus_admin_company(mysqli $conn): array
{
    require_role('bus_admin');

    $company = get_bus_admin_company($conn, (int) current_user_id());

    if (!$company) {
        set_flash('error', 'No approved bus company is assigned to your account.');
        redirect('bus_admin/dashboard.php');
    }

    return $company;
}

function require_tour_admin_company(mysqli $conn): array
{
    require_role('tour_admin');

    $company = get_tour_admin_company($conn, (int) current_user_id());

    if (!$company) {
        set_flash('error', 'No approved tour company is assigned to your account.');
        redirect('tour_admin/dashboard.php');
    }

    return $company;
}


function get_related_bus_company_ids(mysqli $conn, int $companyId): array
{
    if ($companyId <= 0) {
        return [];
    }

    $companySql = "
        SELECT id, name
        FROM companies
        WHERE id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($companySql);
    if (!$stmt) {
        return [$companyId];
    }

    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$company) {
        return [$companyId];
    }

    $companyName = trim((string)($company['name'] ?? ''));
    if ($companyName === '') {
        return [$companyId];
    }

    /*
        Some demo data contains duplicate approved bus companies with the same
        display name (for example, Shwe Mandalar Express). The bus admin may be
        linked to the newer company record while trips/bookings still belong to
        the older company record. Include same-name approved bus companies so
        bookings, payment proofs, and approvals are visible to the assigned admin.
    */
    $relatedSql = "
        SELECT id
        FROM companies
        WHERE status = 'approved'
          AND company_type IN ('bus_company', 'both')
          AND (
              id = ?
              OR LOWER(TRIM(name)) = LOWER(TRIM(?))
          )
        ORDER BY id ASC
    ";

    $stmt = $conn->prepare($relatedSql);
    if (!$stmt) {
        return [$companyId];
    }

    $stmt->bind_param('is', $companyId, $companyName);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    $stmt->close();

    $ids[] = $companyId;
    $ids = array_values(array_unique(array_filter($ids, static fn($id) => (int)$id > 0)));
    sort($ids);

    return $ids;
}

function get_bus_admin_company_scope_ids(mysqli $conn, ?array $company = null): array
{
    if ($company === null) {
        $company = get_bus_admin_company($conn, (int) current_user_id());
    }

    $companyId = (int)($company['company_id'] ?? 0);
    if ($companyId <= 0) {
        return [];
    }

    return get_related_bus_company_ids($conn, $companyId);
}

function fetch_manageable_bus_companies(mysqli $conn): array
{
    $companies = [];

    $sql = "
        SELECT
            id AS company_id,
            name AS company_name,
            company_type,
            status AS company_status
        FROM companies
        WHERE status = 'approved'
          AND company_type IN ('bus_company', 'both')
        ORDER BY name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $companies;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }

    $stmt->close();
    return $companies;
}

function fetch_manageable_tour_companies(mysqli $conn): array
{
    $companies = [];

    $sql = "
        SELECT
            id AS company_id,
            name AS company_name,
            company_type,
            status AS company_status
        FROM companies
        WHERE status = 'approved'
          AND company_type IN ('tour_operator', 'both')
        ORDER BY name ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $companies;
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $companies[] = $row;
    }

    $stmt->close();
    return $companies;
}

function resolve_route_schedule_company_scope(mysqli $conn): array
{
    $role = current_user_role();

    if ($role === 'super_admin') {
        $selectedCompanyId = (int)($_GET['company_id'] ?? $_POST['company_id'] ?? 0);

        if ($selectedCompanyId <= 0) {
            return [
                'mode' => 'super_admin',
                'company' => null,
                'company_id' => 0,
                'companies' => fetch_manageable_bus_companies($conn),
            ];
        }

        $sql = "
            SELECT
                id AS company_id,
                name AS company_name,
                company_type,
                status AS company_status
            FROM companies
            WHERE id = ?
              AND status = 'approved'
              AND company_type IN ('bus_company', 'both')
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [
                'mode' => 'super_admin',
                'company' => null,
                'company_id' => 0,
                'companies' => fetch_manageable_bus_companies($conn),
            ];
        }

        $stmt->bind_param('i', $selectedCompanyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return [
            'mode' => 'super_admin',
            'company' => $company ?: null,
            'company_id' => (int)($company['company_id'] ?? 0),
            'companies' => fetch_manageable_bus_companies($conn),
        ];
    }

    if ($role === 'bus_admin') {
        $company = get_bus_admin_company($conn, (int) current_user_id());

        return [
            'mode' => 'bus_admin',
            'company' => $company ?: null,
            'company_id' => (int)($company['company_id'] ?? 0),
            'companies' => [],
        ];
    }

    return [
        'mode' => 'unknown',
        'company' => null,
        'company_id' => 0,
        'companies' => [],
    ];
}

function resolve_tour_company_scope(mysqli $conn): array
{
    $role = current_user_role();

    if ($role === 'super_admin') {
        $selectedCompanyId = (int)($_GET['company_id'] ?? $_POST['company_id'] ?? 0);

        if ($selectedCompanyId <= 0) {
            return [
                'mode' => 'super_admin',
                'company' => null,
                'company_id' => 0,
                'companies' => fetch_manageable_tour_companies($conn),
            ];
        }

        $sql = "
            SELECT
                id AS company_id,
                name AS company_name,
                company_type,
                status AS company_status
            FROM companies
            WHERE id = ?
              AND status = 'approved'
              AND company_type IN ('tour_operator', 'both')
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [
                'mode' => 'super_admin',
                'company' => null,
                'company_id' => 0,
                'companies' => fetch_manageable_tour_companies($conn),
            ];
        }

        $stmt->bind_param('i', $selectedCompanyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $company = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return [
            'mode' => 'super_admin',
            'company' => $company ?: null,
            'company_id' => (int)($company['company_id'] ?? 0),
            'companies' => fetch_manageable_tour_companies($conn),
        ];
    }

    if ($role === 'tour_admin') {
        $company = get_tour_admin_company($conn, (int) current_user_id());

        return [
            'mode' => 'tour_admin',
            'company' => $company ?: null,
            'company_id' => (int)($company['company_id'] ?? 0),
            'companies' => [],
        ];
    }

    return [
        'mode' => 'unknown',
        'company' => null,
        'company_id' => 0,
        'companies' => [],
    ];
}