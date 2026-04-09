<?php
require_once __DIR__ . '/includes/auth.php';

logout_user();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_flash('success', 'You have been logged out successfully.');
redirect('login.php');
?>