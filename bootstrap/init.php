<?php
// bootstrap/init.php

require_once __DIR__ . '/../includes/system_setting_helper.php';

if (function_exists('system_setting_bootstrap')) {
    system_setting_bootstrap();
}