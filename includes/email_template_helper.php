<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/email_template_helper.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_setting_helper.php';

function email_template_table_exists(mysqli $conn): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'email_templates'
        LIMIT 1
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return false;
    }

    $row = $result->fetch_assoc();
    return ((int)($row['total'] ?? 0)) > 0;
}

function fetch_all_email_templates(mysqli $conn): array
{
    if (!email_template_table_exists($conn)) {
        return [];
    }

    $sql = "SELECT * FROM email_templates ORDER BY id ASC";
    $result = $conn->query($sql);

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

function fetch_email_template_by_id(mysqli $conn, int $id): ?array
{
    if (!email_template_table_exists($conn)) {
        return null;
    }

    $sql = "SELECT * FROM email_templates WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Email template by ID prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_email_template_by_code(mysqli $conn, string $code): ?array
{
    if (!email_template_table_exists($conn)) {
        return null;
    }

    $sql = "SELECT * FROM email_templates WHERE code = ? AND status = 'active' LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Email template by code prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function render_email_template_placeholders(string $text, array $data = []): string
{
    $replacements = [];
    foreach ($data as $key => $value) {
        $replacements['{{' . $key . '}}'] = (string)$value;
    }

    return strtr($text, $replacements);
}

function notification_email_log(string $message): void
{
    $logDir = __DIR__ . '/../storage/logs';
    $logFile = $logDir . '/outgoing_email.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
}

function notification_mailer_require_libraries(): bool
{
    $vendorAutoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
        return class_exists('\PHPMailer\PHPMailer\PHPMailer');
    }

    $phpMailerBase = __DIR__ . '/../libs/PHPMailer/src/';
    $needed = [
        $phpMailerBase . 'Exception.php',
        $phpMailerBase . 'PHPMailer.php',
        $phpMailerBase . 'SMTP.php',
    ];

    foreach ($needed as $file) {
        if (!file_exists($file)) {
            return false;
        }
    }

    require_once $phpMailerBase . 'Exception.php';
    require_once $phpMailerBase . 'PHPMailer.php';
    require_once $phpMailerBase . 'SMTP.php';

    return class_exists('\PHPMailer\PHPMailer\PHPMailer');
}

function send_raw_notification_email(string $toEmail, string $toName, string $subject, string $htmlBody, array $attachments = []): bool
{
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        notification_email_log('MAIL_DISABLED_CONFIG | TO=' . $toEmail . ' | SUBJECT=' . $subject);
        return false;
    }

    if (!notification_mailer_require_libraries()) {
        notification_email_log('MAILER_LIBRARY_MISSING | TO=' . $toEmail . ' | SUBJECT=' . $subject);
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = defined('MAIL_HOST') ? MAIL_HOST : '';
        $mail->Port = defined('MAIL_PORT') ? MAIL_PORT : 587;
        $mail->SMTPAuth = true;
        $mail->Username = defined('MAIL_USERNAME') ? MAIL_USERNAME : '';
        $mail->Password = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
        $mail->SMTPSecure = defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls';
        $mail->CharSet = 'UTF-8';

        $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'Notification');

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        foreach ($attachments as $attachment) {
            if (is_string($attachment) && file_exists($attachment)) {
                $mail->addAttachment($attachment);
            } elseif (is_array($attachment) && !empty($attachment['path']) && file_exists($attachment['path'])) {
                $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $htmlBody)));

        $sent = $mail->send();
        notification_email_log('MAIL_SENT=' . ($sent ? '1' : '0') . ' | TO=' . $toEmail . ' | SUBJECT=' . $subject);

        return $sent;
    } catch (Throwable $e) {
        notification_email_log('MAIL_ERROR | TO=' . $toEmail . ' | SUBJECT=' . $subject . ' | ERROR=' . $e->getMessage());
        return false;
    }
}

function dispatch_templated_email(
    mysqli $conn,
    string $templateCode,
    string $toEmail,
    string $toName,
    array $data = [],
    array $attachments = [],
    string $fallbackSubject = '',
    string $fallbackBody = ''
): bool {
    if ($toEmail === '') {
        return false;
    }

    if (!system_setting_bool($conn, 'email_enabled', defined('MAIL_ENABLED') ? MAIL_ENABLED : false)) {
        notification_email_log('MAIL_DISABLED_SETTING | TO=' . $toEmail . ' | CODE=' . $templateCode);
        return false;
    }

    $template = fetch_email_template_by_code($conn, $templateCode);

    $subject = $fallbackSubject;
    $body = $fallbackBody;

    if ($template) {
        $subject = render_email_template_placeholders((string)$template['subject_template'], $data);
        $body = render_email_template_placeholders((string)$template['body_template'], $data);
    } else {
        $subject = render_email_template_placeholders($fallbackSubject, $data);
        $body = render_email_template_placeholders($fallbackBody, $data);
    }

    if ($subject === '' || $body === '') {
        notification_email_log('MAIL_SKIPPED_EMPTY_TEMPLATE | CODE=' . $templateCode . ' | TO=' . $toEmail);
        return false;
    }

    return send_raw_notification_email($toEmail, $toName, $subject, $body, $attachments);
}