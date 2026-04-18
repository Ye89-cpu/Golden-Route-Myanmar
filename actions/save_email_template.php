<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';

require_role('super_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/email_templates.php');
}

$id = (int)($_POST['id'] ?? 0);
$code = trim($_POST['code'] ?? '');
$name = trim($_POST['name'] ?? '');
$channel = trim($_POST['channel'] ?? 'both');
$status = trim($_POST['status'] ?? 'active');
$variablesHint = trim($_POST['variables_hint'] ?? '');
$subjectTemplate = trim($_POST['subject_template'] ?? '');
$bodyTemplate = trim($_POST['body_template'] ?? '');

save_old_input($_POST);

$allowedChannels = ['email', 'notification', 'both'];
$allowedStatuses = ['active', 'inactive'];

if ($code === '' || $name === '' || $subjectTemplate === '' || $bodyTemplate === '') {
    set_flash('error', 'All required fields must be filled.');
    redirect('admin/email_templates.php' . ($id > 0 ? '?edit_id=' . $id : ''));
}

if (!in_array($channel, $allowedChannels, true) || !in_array($status, $allowedStatuses, true)) {
    set_flash('error', 'Invalid channel or status.');
    redirect('admin/email_templates.php' . ($id > 0 ? '?edit_id=' . $id : ''));
}

$conn = getDBConnection();

try {
    if ($id > 0) {
        $sql = "
            UPDATE email_templates
            SET code = ?, name = ?, subject_template = ?, body_template = ?, channel = ?, variables_hint = ?, status = ?
            WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Update prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('sssssssi', $code, $name, $subjectTemplate, $bodyTemplate, $channel, $variablesHint, $status, $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to update email template.');
        }
        $stmt->close();

        set_flash('success', 'Email template updated successfully.');
    } else {
        $sql = "
            INSERT INTO email_templates
            (code, name, subject_template, body_template, channel, variables_hint, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Insert prepare failed: ' . $conn->error);
        }

        $stmt->bind_param('sssssss', $code, $name, $subjectTemplate, $bodyTemplate, $channel, $variablesHint, $status);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to create email template.');
        }
        $stmt->close();

        set_flash('success', 'Email template created successfully.');
    }

    clear_old_input();
    $conn->close();
    redirect('admin/email_templates.php');
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('admin/email_templates.php' . ($id > 0 ? '?edit_id=' . $id : ''));
}