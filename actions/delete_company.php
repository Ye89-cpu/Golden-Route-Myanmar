<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/companies.php');
}

$companyId = (int)($_POST['company_id'] ?? 0);

if ($companyId <= 0) {
    set_flash('error', 'Invalid company selected.');
    redirect('admin/companies.php');
}

$conn = getDBConnection();

try {
    $companySql = "
        SELECT id, name
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

    /*
        Safety check:
        If company already has routes, buses, schedule templates,
        trips, tour packages, or bookings, do not hard delete.
        This prevents database relationship errors.
    */

    $checks = [
        'buses' => "SELECT COUNT(*) AS total FROM buses WHERE company_id = ?",
        'routes' => "SELECT COUNT(*) AS total FROM routes WHERE company_id = ?",
        'schedule_templates' => "SELECT COUNT(*) AS total FROM schedule_templates WHERE company_id = ?",
        'trips' => "SELECT COUNT(*) AS total FROM trips WHERE company_id = ?",
        'tour_packages' => "SELECT COUNT(*) AS total FROM tour_packages WHERE company_id = ?",
    ];

    $hasRelatedData = false;
    $relatedMessages = [];

    foreach ($checks as $label => $sql) {
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('i', $companyId);
            $stmt->execute();

            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $count = (int)($row['total'] ?? 0);

            $stmt->close();

            if ($count > 0) {
                $hasRelatedData = true;
                $relatedMessages[] = $label . ': ' . $count;
            }
        }
    }

    if ($hasRelatedData) {
        $suspendSql = "
            UPDATE companies
            SET status = 'suspended',
                updated_at = NOW()
            WHERE id = ?
            LIMIT 1
        ";

        $suspendStmt = $conn->prepare($suspendSql);

        if ($suspendStmt) {
            $suspendStmt->bind_param('i', $companyId);
            $suspendStmt->execute();
            $suspendStmt->close();
        }

        throw new Exception(
            'This company has related data, so it was suspended instead of deleted. Related data: ' .
            implode(', ', $relatedMessages)
        );
    }

    $deleteSql = "
        DELETE FROM companies
        WHERE id = ?
        LIMIT 1
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new Exception('Failed to prepare company delete.');
    }

    $deleteStmt->bind_param('i', $companyId);

    if (!$deleteStmt->execute()) {
        $deleteStmt->close();
        throw new Exception('Failed to delete company.');
    }

    $deleteStmt->close();

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
        VALUES (?, 'company_deleted', 'company', ?, ?, ?)
    ";

    $auditStmt = $conn->prepare($auditSql);

    if ($auditStmt) {
        $userId = (int)current_user_id();
        $desc = 'Deleted company: ' . ($company['name'] ?? ('Company #' . $companyId));
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $auditStmt->bind_param('iiss', $userId, $companyId, $desc, $ip);
        $auditStmt->execute();
        $auditStmt->close();
    }

    set_flash('success', 'Company deleted successfully.');
} catch (Throwable $e) {
    set_flash('error', $e->getMessage());
}

$conn->close();

redirect('admin/companies.php');