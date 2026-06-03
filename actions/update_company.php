<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyId = (int)($_POST['company_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$companyType = trim((string)($_POST['company_type'] ?? 'bus_company'));
$license = trim((string)($_POST['license'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$logo = trim((string)($_POST['logo'] ?? ''));
$status = trim((string)($_POST['status'] ?? 'approved'));

$allowedTypes = ['bus_company', 'tour_operator', 'both'];
$allowedStatuses = ['pending', 'approved', 'rejected', 'suspended'];

if ($companyId <= 0) {
    set_flash('error', 'Invalid company selected.');
    redirect('admin/companies.php');
}

if ($name === '') {
    set_flash('error', 'Company name is required.');
    redirect('admin/companies.php?edit=' . $companyId);
}

if (!in_array($companyType, $allowedTypes, true)) {
    set_flash('error', 'Invalid company type.');
    redirect('admin/companies.php?edit=' . $companyId);
}

if (!in_array($status, $allowedStatuses, true)) {
    set_flash('error', 'Invalid company status.');
    redirect('admin/companies.php?edit=' . $companyId);
}

$conn = getDBConnection();

try {
    $oldSql = "
        SELECT id, approved_at
        FROM companies
        WHERE id = ?
        LIMIT 1
    ";

    $oldStmt = $conn->prepare($oldSql);

    if (!$oldStmt) {
        throw new Exception('Failed to prepare company lookup.');
    }

    $oldStmt->bind_param('i', $companyId);
    $oldStmt->execute();

    $oldResult = $oldStmt->get_result();
    $oldCompany = $oldResult ? $oldResult->fetch_assoc() : null;

    $oldStmt->close();

    if (!$oldCompany) {
        throw new Exception('Company not found.');
    }

    $approvedAt = $oldCompany['approved_at'] ?? null;

    if ($status === 'approved' && empty($approvedAt)) {
        $approvedAt = date('Y-m-d H:i:s');
    }

    if ($status !== 'approved') {
        $approvedAt = null;
    }

    $sql = "
        UPDATE companies
        SET
            name = ?,
            company_type = ?,
            license = NULLIF(?, ''),
            phone = NULLIF(?, ''),
            email = NULLIF(?, ''),
            address = NULLIF(?, ''),
            description = NULLIF(?, ''),
            logo = NULLIF(?, ''),
            status = ?,
            approved_at = ?,
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Failed to prepare company update.');
    }

    $stmt->bind_param(
        'ssssssssssi',
        $name,
        $companyType,
        $license,
        $phone,
        $email,
        $address,
        $description,
        $logo,
        $status,
        $approvedAt,
        $companyId
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update company.');
    }

    $stmt->close();

    $auditSql = "
        INSERT INTO audit_logs
        (
            user_id,
            action,
            entity_type,
            entity_id,
            description,
            ip_address
        )
        VALUES (?, 'company_updated', 'company', ?, ?, ?)
    ";

    $auditStmt = $conn->prepare($auditSql);

    if ($auditStmt) {
        $userId = (int)current_user_id();
        $desc = 'Updated company: ' . $name;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $auditStmt->bind_param('iiss', $userId, $companyId, $desc, $ip);
        $auditStmt->execute();
        $auditStmt->close();
    }

    set_flash('success', 'Company updated successfully.');
} catch (Throwable $e) {
    set_flash('error', $e->getMessage());
    $conn->close();
    redirect('admin/companies.php?edit=' . $companyId);
}

$conn->close();

redirect('admin/companies.php');