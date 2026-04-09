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

$backUrl = trim($_POST['back_url'] ?? 'notifications.php');

$conn = getDBConnection();
mark_all_notifications_as_read($conn, (int)$user['id']);
$conn->close();

redirect($backUrl ?: 'notifications.php');