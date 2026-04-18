<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_setting_helper.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/system_settings.php');
}

$appName = trim($_POST['app_name'] ?? '');
$supportEmail = trim($_POST['support_email'] ?? '');
$supportPhone = trim($_POST['support_phone'] ?? '');
$defaultCurrency = trim($_POST['default_currency'] ?? 'MMK');
$defaultTimezone = trim($_POST['default_timezone'] ?? 'Asia/Yangon');
$maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';
$maintenanceMessage = trim($_POST['maintenance_message'] ?? '');
$registrationEnabled = isset($_POST['registration_enabled']) ? '1' : '0';
$emailEnabled = isset($_POST['email_enabled']) ? '1' : '0';
$ticketQrRequired = isset($_POST['ticket_qr_required']) ? '1' : '0';

if ($appName === '') {
    set_flash('error', 'App name is required.');
    redirect('admin/system_settings.php');
}

$conn = getDBConnection();

try {
    $conn->begin_transaction();

    system_setting_upsert($conn, 'app_name', $appName, 'string', 'Application display name', 1);
    system_setting_upsert($conn, 'support_email', $supportEmail, 'string', 'Support email address', 1);
    system_setting_upsert($conn, 'support_phone', $supportPhone, 'string', 'Support phone number', 1);
    system_setting_upsert($conn, 'default_currency', $defaultCurrency, 'string', 'Default currency label', 1);
    system_setting_upsert($conn, 'default_timezone', $defaultTimezone, 'string', 'System timezone', 1);
    system_setting_upsert($conn, 'maintenance_mode', $maintenanceMode, 'bool', 'Enable maintenance mode', 0);
    system_setting_upsert($conn, 'maintenance_message', $maintenanceMessage, 'text', 'Maintenance page message', 1);
    system_setting_upsert($conn, 'registration_enabled', $registrationEnabled, 'bool', 'Allow customer registration', 0);
    system_setting_upsert($conn, 'email_enabled', $emailEnabled, 'bool', 'Enable email sending logic', 0);
    system_setting_upsert($conn, 'ticket_qr_required', $ticketQrRequired, 'bool', 'Require QR/token validation', 0);

    if (system_audit_logs_table_exists($conn)) {
        $action = 'system_settings_updated';
        $entityType = 'system_settings';
        $entityId = null;
        $description = 'Updated system settings';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userId = (int)current_user_id();

        $sql = "
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ississ', $userId, $action, $entityType, $entityId, $description, $ipAddress);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    $conn->close();

    set_flash('success', 'System settings saved successfully.');
    redirect('admin/system_settings.php');
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();

    set_flash('error', 'Failed to save settings: ' . $e->getMessage());
    redirect('admin/system_settings.php');
}