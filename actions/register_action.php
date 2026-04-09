<?php
// actions/register_action.php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_setting_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

$conn = getDBConnection();

if (!system_setting_bool($conn, 'registration_enabled', true)) {
    $conn->close();
    set_flash('error', 'Customer registration is currently disabled.');
    redirect('login.php');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirmation = $_POST['password_confirmation'] ?? '';

save_old_input([
    'name'  => $name,
    'email' => $email,
    'phone' => $phone
]);

if ($name === '' || $email === '' || $password === '' || $passwordConfirmation === '') {
    $conn->close();
    set_flash('error', 'Please fill in all required fields.');
    redirect('register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $conn->close();
    set_flash('error', 'Please enter a valid email address.');
    redirect('register.php');
}

if (strlen($password) < 8) {
    $conn->close();
    set_flash('error', 'Password must be at least 8 characters.');
    redirect('register.php');
}

if ($password !== $passwordConfirmation) {
    $conn->close();
    set_flash('error', 'Password and confirm password do not match.');
    redirect('register.php');
}

$checkEmailSql = "SELECT id FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($checkEmailSql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();

    set_flash('error', 'This email is already registered.');
    redirect('register.php');
}
$stmt->close();

if ($phone !== '') {
    $checkPhoneSql = "SELECT id FROM users WHERE phone = ? LIMIT 1";
    $stmt = $conn->prepare($checkPhoneSql);
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();

        set_flash('error', 'This phone number is already registered.');
        redirect('register.php');
    }
    $stmt->close();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = 'customer';
$status = 'active';
$phoneValue = ($phone === '') ? null : $phone;

$insertSql = "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
$stmt->bind_param('ssssss', $name, $email, $phoneValue, $hashedPassword, $role, $status);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();

    clear_old_input();
    set_flash('success', 'Registration successful. Please login.');
    redirect('login.php');
}

$stmt->close();
$conn->close();

set_flash('error', 'Registration failed. Please try again.');
redirect('register.php');