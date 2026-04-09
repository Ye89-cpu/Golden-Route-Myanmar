<?php
// includes/auth.php

require_once __DIR__ . '/../bootstrap/init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    $path = trim((string)$path);

    if ($path === '') {
        $path = 'index.php';
    }

    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function set_flash($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

function get_flash($type)
{
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function save_old_input($data = [])
{
    $_SESSION['old_input'] = $data;
}

function old($key, $default = '')
{
    return $_SESSION['old_input'][$key] ?? $default;
}

function clear_old_input()
{
    unset($_SESSION['old_input']);
}

function login_user($user)
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role'  => $user['role'],
        'status'=> $user['status']
    ];
}

function logout_user()
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function is_logged_in()
{
    return isset($_SESSION['user']['id']);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function current_user_id()
{
    return $_SESSION['user']['id'] ?? null;
}

function current_user_role()
{
    return $_SESSION['user']['role'] ?? null;
}

function require_guest()
{
    if (is_logged_in()) {
        redirect_by_role();
    }
}

function require_login()
{
    if (!is_logged_in()) {
        set_flash('error', 'Please login first.');
        redirect('login.php');
    }
}

function redirect_by_role($role = null)
{
    $role = $role ?? current_user_role();

    switch ($role) {
        case 'super_admin':
            redirect('admin/dashboard.php');
            break;
        case 'bus_admin':
            redirect('bus_admin/dashboard.php');
            break;
        case 'tour_admin':
            redirect('tour_admin/dashboard.php');
            break;
        case 'customer':
            redirect('customer/profile.php');
            break;
        default:
            redirect('index.php');
    }
}
