<?php
// actions/login_action.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

$conn = getDBConnection();

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

save_old_input([
    'email' => $email
]);

if ($email === '' || $password === '') {
    set_flash('error', 'Please enter your email and password.');
    redirect('login.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please enter a valid email address. Format: name@example.com');
    redirect('login.php');
}

if (strlen($password) < 8) {
    set_flash('error', 'Password must be at least 8 characters.');
    redirect('login.php');
}

$sql = "SELECT id, name, email, phone, password, role, status, profile_image FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    $conn->close();

    set_flash('error', 'Invalid email or password.');
    redirect('login.php');
}

$user = $result->fetch_assoc();
$stmt->close();

if (!password_verify($password, $user['password'])) {
    $conn->close();
    set_flash('error', 'Invalid email or password.');
    redirect('login.php');
}

if ($user['status'] !== 'active') {
    $conn->close();
    set_flash('error', 'Your account is not active.');
    redirect('login.php');
}

$updateSql = "UPDATE users SET last_login_at = NOW() WHERE id = ?";
$updateStmt = $conn->prepare($updateSql);
$updateStmt->bind_param('i', $user['id']);
$updateStmt->execute();
$updateStmt->close();

$conn->close();

login_user($user);
clear_old_input();
set_flash('success', 'Login successful.');
redirect_by_role($user['role']);
?>