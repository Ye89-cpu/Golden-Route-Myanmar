<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyId = (int)($_POST['company_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$password = trim((string)($_POST['password'] ?? ''));
$role = trim((string)($_POST['role'] ?? ''));

$allowedRoles = ['bus_admin', 'tour_admin'];

if ($companyId <= 0) {
    set_flash('error', 'Invalid company selected.');
    redirect('admin/companies.php');
}

if ($name === '' || $email === '' || $password === '') {
    set_flash('error', 'Please fill all required fields.');
    redirect('admin/create_company_admin.php?company_id=' . $companyId);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Invalid email address.');
    redirect('admin/create_company_admin.php?company_id=' . $companyId);
}

if (!in_array($role, $allowedRoles, true)) {
    set_flash('error', 'Invalid admin role selected.');
    redirect('admin/create_company_admin.php?company_id=' . $companyId);
}

$conn = getDBConnection();

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

try {
    $companySql = "
        SELECT id, name, company_type
        FROM companies
        WHERE id = ?
        LIMIT 1
    ";

    $companyStmt = $conn->prepare($companySql);

    if (!$companyStmt) {
        throw new Exception('Failed to prepare company lookup.');
    }

    $companyStmt->bind_param('i', $companyId);
    $companyStmt->execute();

    $companyResult = $companyStmt->get_result();
    $company = $companyResult ? $companyResult->fetch_assoc() : null;

    $companyStmt->close();

    if (!$company) {
        throw new Exception('Company not found.');
    }

    $checkEmailSql = "
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ";

    $checkStmt = $conn->prepare($checkEmailSql);

    if (!$checkStmt) {
        throw new Exception('Failed to prepare email check.');
    }

    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();

    $checkResult = $checkStmt->get_result();
    $existingUser = $checkResult ? $checkResult->fetch_assoc() : null;

    $checkStmt->close();

    if ($existingUser) {
        throw new Exception('This email already exists.');
    }

    $conn->begin_transaction();

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $phoneValue = $phone !== '' ? $phone : null;

    $usersHasRole = column_exists($conn, 'users', 'role');
    $usersHasUserType = column_exists($conn, 'users', 'user_type');
    $usersHasStatus = column_exists($conn, 'users', 'status');
    $usersHasCreatedAt = column_exists($conn, 'users', 'created_at');
    $usersHasUpdatedAt = column_exists($conn, 'users', 'updated_at');

    if ($usersHasRole) {
        if ($usersHasStatus && $usersHasCreatedAt && $usersHasUpdatedAt) {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, role, status, created_at, updated_at)
                VALUES
                (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with role.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        } elseif ($usersHasStatus) {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, role, status)
                VALUES
                (?, ?, ?, ?, ?, 'active')
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with role.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        } else {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, role)
                VALUES
                (?, ?, ?, ?, ?)
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with role.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        }
    } elseif ($usersHasUserType) {
        if ($usersHasStatus && $usersHasCreatedAt && $usersHasUpdatedAt) {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, user_type, status, created_at, updated_at)
                VALUES
                (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with user_type.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        } elseif ($usersHasStatus) {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, user_type, status)
                VALUES
                (?, ?, ?, ?, ?, 'active')
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with user_type.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        } else {
            $userSql = "
                INSERT INTO users
                (name, email, phone, password, user_type)
                VALUES
                (?, ?, ?, ?, ?)
            ";

            $userStmt = $conn->prepare($userSql);

            if (!$userStmt) {
                throw new Exception('Failed to prepare user insert with user_type.');
            }

            $userStmt->bind_param(
                'sssss',
                $name,
                $email,
                $phoneValue,
                $hashedPassword,
                $role
            );
        }
    } else {
        throw new Exception('users table must have role or user_type column.');
    }

    if (!$userStmt->execute()) {
        $userStmt->close();
        throw new Exception('Failed to create admin user: ' . $userStmt->error);
    }

    $userId = (int)$userStmt->insert_id;
    $userStmt->close();

    if ($userId <= 0) {
        throw new Exception('User ID could not be generated.');
    }

    $companyUsersHasRole = column_exists($conn, 'company_users', 'role');
    $companyUsersHasStatus = column_exists($conn, 'company_users', 'status');
    $companyUsersHasCreatedAt = column_exists($conn, 'company_users', 'created_at');
    $companyUsersHasUpdatedAt = column_exists($conn, 'company_users', 'updated_at');

    if ($companyUsersHasRole && $companyUsersHasStatus && $companyUsersHasCreatedAt && $companyUsersHasUpdatedAt) {
        $linkSql = "
            INSERT INTO company_users
            (company_id, user_id, role, status, created_at, updated_at)
            VALUES
            (?, ?, 'admin', 'active', NOW(), NOW())
        ";

        $linkStmt = $conn->prepare($linkSql);

        if (!$linkStmt) {
            throw new Exception('Failed to prepare company user link with role.');
        }

        $linkStmt->bind_param('ii', $companyId, $userId);
    } elseif ($companyUsersHasStatus && $companyUsersHasCreatedAt && $companyUsersHasUpdatedAt) {
        $linkSql = "
            INSERT INTO company_users
            (company_id, user_id, status, created_at, updated_at)
            VALUES
            (?, ?, 'active', NOW(), NOW())
        ";

        $linkStmt = $conn->prepare($linkSql);

        if (!$linkStmt) {
            throw new Exception('Failed to prepare company user link.');
        }

        $linkStmt->bind_param('ii', $companyId, $userId);
    } elseif ($companyUsersHasStatus) {
        $linkSql = "
            INSERT INTO company_users
            (company_id, user_id, status)
            VALUES
            (?, ?, 'active')
        ";

        $linkStmt = $conn->prepare($linkSql);

        if (!$linkStmt) {
            throw new Exception('Failed to prepare company user link.');
        }

        $linkStmt->bind_param('ii', $companyId, $userId);
    } else {
        $linkSql = "
            INSERT INTO company_users
            (company_id, user_id)
            VALUES
            (?, ?)
        ";

        $linkStmt = $conn->prepare($linkSql);

        if (!$linkStmt) {
            throw new Exception('Failed to prepare company user link.');
        }

        $linkStmt->bind_param('ii', $companyId, $userId);
    }

    if (!$linkStmt->execute()) {
        $linkStmt->close();
        throw new Exception('Failed to link user with company: ' . $linkStmt->error);
    }

    $linkStmt->close();

    if (column_exists($conn, 'audit_logs', 'user_id')) {
        $auditSql = "
            INSERT INTO audit_logs
            (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES
            (?, 'company_admin_created', 'company', ?, ?, ?)
        ";

        $auditStmt = $conn->prepare($auditSql);

        if ($auditStmt) {
            $superAdminId = (int)current_user_id();
            $description = 'Created ' . $role . ' account for company: ' . ($company['name'] ?? ('Company #' . $companyId));
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $auditStmt->bind_param(
                'iiss',
                $superAdminId,
                $companyId,
                $description,
                $ip
            );

            $auditStmt->execute();
            $auditStmt->close();
        }
    }

    $conn->commit();

    set_flash(
        'success',
        'Company admin account created successfully. Email: ' . $email . ' | Password: ' . $password
    );

    $conn->close();

    redirect('admin/companies.php');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    try {
        $conn->close();
    } catch (Throwable $closeError) {
    }

    set_flash('error', $e->getMessage());
    redirect('admin/create_company_admin.php?company_id=' . $companyId);
}