<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyId = (int)($_POST['company_id'] ?? 0);

if ($companyId <= 0) {
    set_flash('error', 'Invalid company ID.');
    redirect('admin/companies.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    $checkSql = "SELECT id, name, status FROM companies WHERE id = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $companyId);
    $checkStmt->execute();
    $companyResult = $checkStmt->get_result();

    if ($companyResult->num_rows !== 1) {
        $checkStmt->close();
        throw new Exception('Company not found.');
    }

    $company = $companyResult->fetch_assoc();
    $checkStmt->close();

    $updateSql = "UPDATE companies SET status = 'rejected', approved_at = NULL WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('i', $companyId);

    if (!$updateStmt->execute()) {
        $updateStmt->close();
        throw new Exception('Failed to reject company.');
    }
    $updateStmt->close();

    $action = 'company_rejected';
    $entityType = 'company';
    $description = 'Rejected company: ' . $company['name'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userId = current_user_id();

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('ississ', $userId, $action, $entityType, $companyId, $description, $ipAddress);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    set_flash('success', 'Company rejected successfully.');
} catch (Exception $e) {
    $conn->rollback();
    set_flash('error', $e->getMessage());
}

$conn->close();
redirect('admin/companies.php');