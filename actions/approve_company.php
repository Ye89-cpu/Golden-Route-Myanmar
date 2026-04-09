<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyId = (int)($_POST['company_id'] ?? 0);
$autoCreateAdmin = isset($_POST['auto_create_admin']) ? 1 : 0;
$adminEmail = trim($_POST['admin_email'] ?? '');
$adminPassword = trim($_POST['admin_password'] ?? '');

if ($companyId <= 0) {
    set_flash('error', 'Invalid company ID.');
    redirect('admin/companies.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $checkSql = "SELECT id, name, company_type, phone, email, status FROM companies WHERE id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception('Failed to prepare company lookup.');
    }

    $checkStmt->bind_param('i', $companyId);
    $checkStmt->execute();
    $companyResult = $checkStmt->get_result();

    if ($companyResult->num_rows !== 1) {
        $checkStmt->close();
        throw new Exception('Company not found.');
    }

    $company = $companyResult->fetch_assoc();
    $checkStmt->close();

    $updateSql = "UPDATE companies SET status = 'approved', approved_at = NOW(), updated_at = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        throw new Exception('Failed to prepare company approval.');
    }

    $updateStmt->bind_param('i', $companyId);

    if (!$updateStmt->execute()) {
        $updateStmt->close();
        throw new Exception('Failed to approve company.');
    }
    $updateStmt->close();

    $message = 'Company approved successfully.';

    $existingAdmin = get_company_primary_admin($conn, $companyId);

    if ($autoCreateAdmin && !$existingAdmin) {
        $adminAccount = create_company_admin_account(
            $conn,
            $company,
            $adminEmail !== '' ? $adminEmail : null,
            $adminPassword !== '' ? $adminPassword : null
        );

        if (!$adminAccount) {
            throw new Exception('Company approved, but admin account auto-creation failed.');
        }

        $message = 'Company approved. Admin account created. Email: ' . $adminAccount['email'] .
            ' | Password: ' . $adminAccount['generated_password'];
    } elseif ($existingAdmin) {
        $message = 'Company approved. Existing admin already linked: ' . ($existingAdmin['email'] ?? 'N/A');
    }

    $action = 'company_approved';
    $entityType = 'company';
    $description = 'Approved company: ' . $company['name'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $userId, $action, $entityType, $companyId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    set_flash('success', $message);
} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', $e->getMessage());
}

$conn->close();
redirect('admin/companies.php');