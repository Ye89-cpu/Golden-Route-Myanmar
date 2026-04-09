<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notification_helper.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('notifications.php');
}

$notificationId = (int)($_POST['notification_id'] ?? 0);
$backUrl = trim($_POST['back_url'] ?? 'notifications.php');

$conn = getDBConnection();
mark_notification_as_read($conn, $notificationId, (int)$user['id']);
$conn->close();

redirect($backUrl ?: 'notifications.php');