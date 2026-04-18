<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/profile_helper.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('account/profile.php');
}

$conn = getDBConnection();
$userId = (int)current_user_id();

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($name === '') {
    $conn->close();
    set_flash('error', 'Name is required.');
    redirect('account/profile.php');
}

if ($newPassword !== '' || $confirmPassword !== '') {
    if (strlen($newPassword) < 8) {
        $conn->close();
        set_flash('error', 'New password must be at least 8 characters.');
        redirect('account/profile.php');
    }

    if ($newPassword !== $confirmPassword) {
        $conn->close();
        set_flash('error', 'New password and confirmation do not match.');
        redirect('account/profile.php');
    }
}

$currentSql = "SELECT id, phone, profile_image FROM users WHERE id = ? LIMIT 1";
$currentStmt = $conn->prepare($currentSql);
$currentStmt->bind_param('i', $userId);
$currentStmt->execute();
$currentUser = $currentStmt->get_result()->fetch_assoc();
$currentStmt->close();

if (!$currentUser) {
    $conn->close();
    set_flash('error', 'User account not found.');
    redirect('login.php');
}

if ($phone !== '') {
    $phoneCheckSql = "SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1";
    $phoneCheckStmt = $conn->prepare($phoneCheckSql);
    $phoneCheckStmt->bind_param('si', $phone, $userId);
    $phoneCheckStmt->execute();
    $phoneExists = $phoneCheckStmt->get_result()->fetch_assoc();
    $phoneCheckStmt->close();

    if ($phoneExists) {
        $conn->close();
        set_flash('error', 'This phone number is already used by another account.');
        redirect('account/profile.php');
    }
}

try {
    $profileImagePath = store_profile_image_upload($_FILES['profile_image'] ?? [], $currentUser['profile_image'] ?? '');
} catch (Throwable $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('account/profile.php');
}

$phoneValue = ($phone === '') ? null : $phone;

if ($newPassword !== '') {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateSql = "UPDATE users SET name = ?, phone = ?, profile_image = ?, password = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ssssi', $name, $phoneValue, $profileImagePath, $hashedPassword, $userId);
} else {
    $updateSql = "UPDATE users SET name = ?, phone = ?, profile_image = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('sssi', $name, $phoneValue, $profileImagePath, $userId);
}

if (!$updateStmt->execute()) {
    $updateStmt->close();
    $conn->close();
    set_flash('error', 'Failed to update profile.');
    redirect('account/profile.php');
}
$updateStmt->close();

$_SESSION['user']['name'] = $name;
$_SESSION['user']['phone'] = $phoneValue;
$_SESSION['user']['profile_image'] = $profileImagePath;

$conn->close();

set_flash('success', 'Profile updated successfully.');
redirect('account/profile.php');