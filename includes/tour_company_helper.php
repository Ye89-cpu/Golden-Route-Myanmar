<?php
require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/db.php';

function get_tour_admin_company(mysqli $conn, int $userId): ?array
{
    $sql = "
        SELECT
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
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $company = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $company;
}

function require_tour_admin_company(mysqli $conn): array
{
    require_role('tour_admin');

    $userId = current_user_id();
    $company = get_tour_admin_company($conn, (int)$userId);

    if (!$company) {
        set_flash('error', 'No approved tour company is assigned to your account.');
        redirect('tour_admin/dashboard.php');
    }

    return $company;
}