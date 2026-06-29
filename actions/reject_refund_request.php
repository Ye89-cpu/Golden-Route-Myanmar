<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/refund_helper.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role(['super_admin', 'tour_admin']);

$isTourAdmin = current_user_role() === 'tour_admin';
$redirectPath = $isTourAdmin ? 'tour_admin/refund_requests.php' : 'admin/refund_requests.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($redirectPath);
}

$refundRequestId = (int)($_POST['refund_request_id'] ?? 0);
$adminNote = trim($_POST['admin_note'] ?? '');
$actorUserId = (int)current_user_id();

if ($refundRequestId <= 0) {
    set_flash('error', 'Invalid refund request ID.');
    redirect($redirectPath);
}

$conn = getDBConnection();

try {
    $tourCompanyId = 0;
    if ($isTourAdmin) {
        require_company_permission($conn, 'manage_tour_refunds');
        $tourCompany = require_tour_admin_company($conn);
        $tourCompanyId = (int)($tourCompany['company_id'] ?? 0);
        if ($tourCompanyId <= 0) {
            throw new Exception('No approved tour company is assigned to your account.');
        }
    }

    $conn->begin_transaction();

    $request = $isTourAdmin
        ? fetch_refund_request_for_tour_admin_update($conn, $refundRequestId, $tourCompanyId)
        : fetch_refund_request_for_admin_update($conn, $refundRequestId);
    if (!$request) {
        throw new Exception('Refund request not found.');
    }

    if ($request['status'] !== 'pending') {
        throw new Exception('Only pending refund requests can be rejected.');
    }

    $sql = "
        UPDATE refund_requests
        SET status = 'rejected',
            admin_note = ?,
            processed_by = ?,
            processed_at = NOW()
        WHERE id = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare refund rejection statement.');
    }

    $stmt->bind_param('sii', $adminNote, $actorUserId, $refundRequestId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to reject refund request.');
    }
    $stmt->close();

    $action = 'refund_rejected';
    $entityType = 'refund_request';
    $description = 'Rejected refund request ' . $request['request_code'] . ' for booking ' . $request['booking_code'];
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($auditSql);
    if (!$stmt) {
        throw new Exception('Failed to prepare audit log statement.');
    }

    $stmt->bind_param('ississ', $actorUserId, $action, $entityType, $refundRequestId, $description, $ipAddress);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to write audit log.');
    }
    $stmt->close();

    // Refund rejected notification
    notify_event_refund_rejected_by_request_id($conn, $refundRequestId, $actorUserId);

    $conn->commit();
    $conn->close();

    set_flash('success', 'Refund request rejected successfully.');
    redirect($redirectPath);
} catch (Exception $e) {
    if ($conn instanceof mysqli) {
        $conn->rollback();
        $conn->close();
    }

    set_flash('error', $e->getMessage());
    redirect($redirectPath);
}