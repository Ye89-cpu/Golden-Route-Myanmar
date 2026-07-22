<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/partner_program_helper.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/partner_applications.php');
}

$applicationId = (int)($_POST['application_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'new'));
$adminNotes = trim((string)($_POST['admin_notes'] ?? ''));
$allowedStatuses = ['new', 'contacted', 'reviewing', 'approved', 'declined'];

if ($applicationId <= 0 || !in_array($status, $allowedStatuses, true)) {
    set_flash('error', 'Invalid partner application update.');
    redirect('admin/partner_applications.php');
}

$conn = getDBConnection();

try {
    partner_ensure_application_table($conn);
    $stmt = $conn->prepare("UPDATE partner_applications SET status = ?, admin_notes = NULLIF(?, ''), updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the application update.');
    }
    $stmt->bind_param('ssi', $status, $adminNotes, $applicationId);
    $stmt->execute();
    $stmt->close();

    if (system_audit_logs_table_exists($conn)) {
        $userId = (int)current_user_id();
        $action = 'partner_application_updated';
        $entityType = 'partner_application';
        $description = 'Updated partner application status to ' . $status;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $auditStmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        if ($auditStmt) {
            $auditStmt->bind_param('ississ', $userId, $action, $entityType, $applicationId, $description, $ip);
            $auditStmt->execute();
            $auditStmt->close();
        }
    }

    $conn->close();
    set_flash('success', 'Partner application review updated.');
} catch (Throwable $e) {
    $conn->close();
    set_flash('error', 'Unable to update the partner application.');
}

redirect('admin/partner_applications.php');
