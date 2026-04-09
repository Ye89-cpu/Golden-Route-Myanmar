<?php
// includes/role_check.php

require_once __DIR__ . '/auth.php';

function require_role($roles)
{
    require_login();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    if (!in_array(current_user_role(), $roles, true)) {
        set_flash('error', 'You are not authorized to access that page.');
        redirect_by_role();
    }
}
?>