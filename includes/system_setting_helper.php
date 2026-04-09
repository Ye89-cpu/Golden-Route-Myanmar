<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/system_setting_helper.php

require_once __DIR__ . '/db.php';

function system_settings_table_exists(mysqli $conn): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'system_settings'
        LIMIT 1
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();
    return ((int)($row['total'] ?? 0)) > 0;
}

function system_audit_logs_table_exists(mysqli $conn): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'audit_logs'
        LIMIT 1
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();
    return ((int)($row['total'] ?? 0)) > 0;
}

function system_setting_get(mysqli $conn, string $key, $default = null)
{
    if (!system_settings_table_exists($conn)) {
        return $default;
    }

    $sql = "SELECT setting_value, value_type FROM system_settings WHERE setting_key = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return $default;
    }

    $value = $row['setting_value'];
    $type = $row['value_type'];

    switch ($type) {
        case 'bool':
            return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
        case 'int':
            return (int)$value;
        case 'json':
            $decoded = json_decode((string)$value, true);
            return $decoded !== null ? $decoded : $default;
        default:
            return $value;
    }
}

function system_setting_bool(mysqli $conn, string $key, bool $default = false): bool
{
    return (bool)system_setting_get($conn, $key, $default);
}

function fetch_all_system_settings(mysqli $conn): array
{
    if (!system_settings_table_exists($conn)) {
        return [];
    }

    $sql = "SELECT * FROM system_settings ORDER BY setting_key ASC";
    $result = $conn->query($sql);

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetch_system_settings_map(mysqli $conn): array
{
    $rows = fetch_all_system_settings($conn);
    $map = [];

    foreach ($rows as $row) {
        $key = $row['setting_key'];
        $type = $row['value_type'];
        $value = $row['setting_value'];

        switch ($type) {
            case 'bool':
                $map[$key] = in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
                break;
            case 'int':
                $map[$key] = (int)$value;
                break;
            case 'json':
                $decoded = json_decode((string)$value, true);
                $map[$key] = $decoded !== null ? $decoded : $value;
                break;
            default:
                $map[$key] = $value;
                break;
        }
    }

    return $map;
}

function system_setting_runtime_map(bool $forceRefresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$forceRefresh) {
        return $cache;
    }

    try {
        $conn = getDBConnection();
        $cache = fetch_system_settings_map($conn);
        $conn->close();
    } catch (Throwable $e) {
        $cache = [];
    }

    return is_array($cache) ? $cache : [];
}

function system_setting_runtime_get(string $key, $default = null)
{
    $settings = system_setting_runtime_map();
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function system_setting_runtime_bool(string $key, bool $default = false): bool
{
    $value = system_setting_runtime_get($key, $default);
    return is_bool($value) ? $value : (bool)$value;
}


function system_setting_upsert(mysqli $conn, string $key, string $value, string $valueType = 'string', string $description = '', int $isPublic = 0): bool
{
    if (!system_settings_table_exists($conn)) {
        return false;
    }

    $sql = "
        INSERT INTO system_settings (setting_key, setting_value, value_type, description, is_public)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            description = VALUES(description),
            is_public = VALUES(is_public)
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ssssi', $key, $value, $valueType, $description, $isPublic);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function system_setting_current_role_from_session(): string
{
    if (!empty($_SESSION['user']['role'])) {
        return (string)$_SESSION['user']['role'];
    }

    if (!empty($_SESSION['auth']['role'])) {
        return (string)$_SESSION['auth']['role'];
    }

    return '';
}

function system_setting_is_super_admin_session(): bool
{
    return system_setting_current_role_from_session() === 'super_admin';
}

function system_setting_render_maintenance_page(string $message): void
{
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Maintenance</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            body {
                margin: 0;
                font-family: Arial, sans-serif;
                background: #f8f9fa;
                color: #212529;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            .box {
                background: white;
                padding: 32px;
                border-radius: 18px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
                max-width: 640px;
                width: calc(100% - 32px);
                text-align: center;
            }
            h1 { margin-top: 0; }
            p { line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>System Maintenance</h1>
            <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function system_setting_bootstrap(): void
{
    if (!function_exists('getDBConnection')) {
        return;
    }

    try {
        $conn = getDBConnection();

        if (!system_settings_table_exists($conn)) {
            $conn->close();
            return;
        }

        $timezone = (string)system_setting_get($conn, 'default_timezone', 'Asia/Yangon');
        if ($timezone !== '') {
            @date_default_timezone_set($timezone);
        }

        $maintenanceMode = system_setting_bool($conn, 'maintenance_mode', false);
        $maintenanceMessage = (string)system_setting_get(
            $conn,
            'maintenance_message',
            'System is under maintenance. Please try again later.'
        );

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        $allowedDuringMaintenance = [
            'login.php',
            'logout.php',
        ];

        $isAllowed = false;
        foreach ($allowedDuringMaintenance as $allowed) {
            if (str_ends_with($scriptName, $allowed)) {
                $isAllowed = true;
                break;
            }
        }

        if (
            $maintenanceMode
            && !system_setting_is_super_admin_session()
            && !$isAllowed
            && strpos($requestUri, '/assets/') === false
        ) {
            $conn->close();
            system_setting_render_maintenance_page($maintenanceMessage);
        }

        $conn->close();
    } catch (Throwable $e) {
        // fail open
    }
}

function system_production_readiness_checks(mysqli $conn, string $projectRoot): array
{
    $checks = [];

    $storagePath = rtrim($projectRoot, '/') . '/storage';
    $logsPath = $storagePath . '/logs';
    $uploadsPath = rtrim($projectRoot, '/') . '/uploads';
    $outgoingEmailLog = $logsPath . '/outgoing_email.log';

    $checks[] = [
        'label' => 'system_settings table',
        'status' => system_settings_table_exists($conn),
        'detail' => 'Required for runtime settings and maintenance mode'
    ];

    $checks[] = [
        'label' => 'audit_logs table',
        'status' => system_audit_logs_table_exists($conn),
        'detail' => 'Required for audit viewer'
    ];

    $checks[] = [
        'label' => 'notifications table',
        'status' => (bool)$conn->query("SHOW TABLES LIKE 'notifications'")->num_rows,
        'detail' => 'Required for notification center'
    ];

    $checks[] = [
        'label' => 'email_templates table',
        'status' => (bool)$conn->query("SHOW TABLES LIKE 'email_templates'")->num_rows,
        'detail' => 'Required for templated emails'
    ];

    $checks[] = [
        'label' => 'refund_requests table',
        'status' => (bool)$conn->query("SHOW TABLES LIKE 'refund_requests'")->num_rows,
        'detail' => 'Required for refund workflow'
    ];

    $checks[] = [
        'label' => 'uploads directory writable',
        'status' => is_dir($uploadsPath) && is_writable($uploadsPath),
        'detail' => $uploadsPath
    ];

    $checks[] = [
        'label' => 'storage/logs writable',
        'status' => is_dir($logsPath) && is_writable($logsPath),
        'detail' => $logsPath
    ];

    $checks[] = [
        'label' => 'outgoing_email.log writable',
        'status' => (file_exists($outgoingEmailLog) && is_writable($outgoingEmailLog)) || (!file_exists($outgoingEmailLog) && is_writable($logsPath)),
        'detail' => $outgoingEmailLog
    ];

    $checks[] = [
        'label' => 'FPDF library',
        'status' => file_exists(rtrim($projectRoot, '/') . '/libs/fpdf/fpdf.php'),
        'detail' => 'libs/fpdf/fpdf.php'
    ];

    $checks[] = [
        'label' => 'PHP QR Code library',
        'status' => file_exists(rtrim($projectRoot, '/') . '/libs/phpqrcode/qrlib.php'),
        'detail' => 'libs/phpqrcode/qrlib.php'
    ];

    $phpMailerVendor = file_exists(rtrim($projectRoot, '/') . '/vendor/autoload.php');
    $phpMailerLib = file_exists(rtrim($projectRoot, '/') . '/libs/PHPMailer/src/PHPMailer.php');

    $checks[] = [
        'label' => 'PHPMailer library',
        'status' => $phpMailerVendor || $phpMailerLib,
        'detail' => $phpMailerVendor ? 'vendor/autoload.php' : 'libs/PHPMailer/src/PHPMailer.php'
    ];

    $checks[] = [
        'label' => 'mysqli extension',
        'status' => extension_loaded('mysqli'),
        'detail' => 'PHP extension check'
    ];

    $checks[] = [
        'label' => 'gd extension',
        'status' => extension_loaded('gd'),
        'detail' => 'Useful for image handling'
    ];

    $checks[] = [
        'label' => 'PHP version >= 8.1',
        'status' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'detail' => 'Current: ' . PHP_VERSION
    ];

    return $checks;
}

function fetch_audit_logs_with_filters(mysqli $conn, array $filters = []): array
{
    if (!system_audit_logs_table_exists($conn)) {
        return [];
    }

    $sql = "
        SELECT
            al.*,
            u.name AS user_name,
            u.email AS user_email
        FROM audit_logs al
        LEFT JOIN users u ON u.id = al.user_id
        WHERE 1 = 1
    ";

    $params = [];
    $types = '';

    if (!empty($filters['action'])) {
        $sql .= " AND al.action = ? ";
        $params[] = $filters['action'];
        $types .= 's';
    }

    if (!empty($filters['entity_type'])) {
        $sql .= " AND al.entity_type = ? ";
        $params[] = $filters['entity_type'];
        $types .= 's';
    }

    if (!empty($filters['user_id'])) {
        $sql .= " AND al.user_id = ? ";
        $params[] = (int)$filters['user_id'];
        $types .= 'i';
    }

    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(al.created_at) >= ? ";
        $params[] = $filters['date_from'];
        $types .= 's';
    }

    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(al.created_at) <= ? ";
        $params[] = $filters['date_to'];
        $types .= 's';
    }

    $sql .= " ORDER BY al.id DESC LIMIT 300 ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Audit log query prepare failed: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_distinct_audit_actions(mysqli $conn): array
{
    if (!system_audit_logs_table_exists($conn)) {
        return [];
    }

    $sql = "SELECT DISTINCT action FROM audit_logs ORDER BY action ASC";
    $result = $conn->query($sql);

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row['action'];
        }
    }

    return $rows;
}

function fetch_distinct_audit_entity_types(mysqli $conn): array
{
    if (!system_audit_logs_table_exists($conn)) {
        return [];
    }

    $sql = "SELECT DISTINCT entity_type FROM audit_logs ORDER BY entity_type ASC";
    $result = $conn->query($sql);

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row['entity_type'];
        }
    }

    return $rows;
}