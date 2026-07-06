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
$errors = [];

save_old_input([
    'name'  => $name,
    'email' => $email,
    'phone' => $phone
]);

if ($name === '') {
    $errors['name'] = 'Full Name field is required.';
} elseif (!preg_match("/^[\p{L}\s.'-]+$/u", $name)) {
    $errors['name'] = 'Full Name should contain letters only.';
}

if ($email === '') {
    $errors['email'] = 'Email field is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if ($phone === '') {
    $errors['phone'] = 'Phone Number field is required.';
} elseif (!preg_match('/^[0-9]+$/', $phone)) {
    $errors['phone'] = 'Phone Number should contain numbers only.';
} elseif (!preg_match('/^09[0-9]{7,9}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if ($password === '') {
    $errors['password'] = 'Password field is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
} elseif (!preg_match('/[A-Za-z]/', $password)) {
    $errors['password'] = 'Password must contain at least one letter.';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'Password must contain at least one number.';
}

if ($passwordConfirmation === '') {
    $errors['password_confirmation'] = 'Confirm Password field is required.';
} elseif ($password !== '' && $password !== $passwordConfirmation) {
    $errors['password_confirmation'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    $conn->close();
    $_SESSION['register_errors'] = $errors;
    set_flash('error', 'Please correct the highlighted fields.');
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

    $_SESSION['register_errors'] = ['email' => 'This email is already registered.'];
    set_flash('error', 'This email is already registered.');
    redirect('register.php');
}
$stmt->close();

$checkPhoneSql = "SELECT id FROM users WHERE phone = ? LIMIT 1";
$stmt = $conn->prepare($checkPhoneSql);
$stmt->bind_param('s', $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();

    $_SESSION['register_errors'] = ['phone' => 'This phone number is already registered.'];
    set_flash('error', 'This phone number is already registered.');
    redirect('register.php');
}
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$role = 'customer';
$status = 'active';

$insertSql = "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insertSql);
$stmt->bind_param('ssssss', $name, $email, $phone, $hashedPassword, $role, $status);

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
