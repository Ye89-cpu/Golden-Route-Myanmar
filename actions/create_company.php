<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$name = trim($_POST['name'] ?? '');
$companyType = trim($_POST['company_type'] ?? 'bus_company');
$license = trim($_POST['license'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$description = trim($_POST['description'] ?? '');
$status = trim($_POST['status'] ?? 'pending');
$createAdminNow = isset($_POST['create_admin_now']) ? 1 : 0;
$adminName = trim($_POST['admin_name'] ?? '');
$adminPhone = trim($_POST['admin_phone'] ?? '');
$adminEmail = trim($_POST['admin_email'] ?? '');
$adminPassword = trim($_POST['admin_password'] ?? '');

$allowedTypes = ['bus_company', 'tour_operator', 'both'];
$allowedStatuses = ['pending', 'approved'];

if ($name === '' || !in_array($companyType, $allowedTypes, true) || !in_array($status, $allowedStatuses, true)) {
    set_flash('error', 'Please fill the required company fields correctly.');
    redirect('admin/companies.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $companySql = "
        INSERT INTO companies
        (name, company_type, license, phone, email, address, description, status, approved_at, created_at, updated_at)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $approvedAt = ($status === 'approved') ? date('Y-m-d H:i:s') : null;

    $stmt = $conn->prepare($companySql);
    if (!$stmt) {
        throw new Exception('Failed to prepare company insert.');
    }

    $stmt->bind_param(
        'sssssssss',
        $name,
        $companyType,
        $license,
        $phone,
        $email,
        $address,
        $description,
        $status,
        $approvedAt
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to create company.');
    }

    $companyId = (int)$stmt->insert_id;
    $stmt->close();

    if ($companyId <= 0) {
        throw new Exception('Company ID not generated.');
    }

    $company = [
        'id' => $companyId,
        'name' => $name,
        'company_type' => $companyType,
        'phone' => $phone,
        'email' => $email,
    ];

    $flashMessage = 'Company created successfully.';

    if ($status === 'approved' && $createAdminNow) {
        $adminAccount = create_company_admin_account(
            $conn,
            $company,
            $adminEmail !== '' ? $adminEmail : null,
            $adminPassword !== '' ? $adminPassword : null,
            $adminName !== '' ? $adminName : null,
            $adminPhone !== '' ? $adminPhone : null
        );

        if (!$adminAccount) {
            throw new Exception('Company was created, but admin account creation failed.');
        }

        $flashMessage = 'Company created and admin auto-created. Email: ' . $adminAccount['email'] .
            ' | Password: ' . $adminAccount['generated_password'];
    }

    $action = 'company_created';
    $entityType = 'company';
    $entityId = $companyId;
    $descriptionLog = 'Created company: ' . $name;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $descriptionLog, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    set_flash('success', $flashMessage);
} catch (Throwable $e) {
    $conn->rollback();
    set_flash('error', $e->getMessage());
}

$conn->close();
redirect('admin/companies.php');