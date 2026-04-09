<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/config.php

if (!defined('MBTB_CONFIG_LOADED')) {
    define('MBTB_CONFIG_LOADED', true);
}

if (!function_exists('mbtb_env')) {
    function mbtb_env(string $key, $default = '')
    {
        $value = getenv($key);
        return ($value === false || $value === null || $value === '') ? $default : $value;
    }
}

if (!function_exists('mbtb_env_bool')) {
    function mbtb_env_bool(string $key, bool $default = false): bool
    {
        $raw = getenv($key);
        if ($raw === false || $raw === null || $raw === '') {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $value === null ? $default : $value;
    }
}

if (!function_exists('mbtb_detect_scheme')) {
    function mbtb_detect_scheme(): string
    {
        $https = $_SERVER['HTTPS'] ?? '';
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $port = (string)($_SERVER['SERVER_PORT'] ?? '');

        if ($forwardedProto === 'https' || $https === 'on' || $https === '1' || $port === '443') {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('mbtb_detect_base_url')) {
    function mbtb_detect_base_url(): string
    {
        $configured = rtrim((string) mbtb_env('MBTB_BASE_URL', ''), '/');
        if ($configured !== '') {
            return $configured . '/';
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = mbtb_detect_scheme();

        $projectDir = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
        $documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''));

        $basePath = '/';

        if ($documentRoot !== '' && str_starts_with($projectDir, $documentRoot)) {
            $relative = trim(substr($projectDir, strlen($documentRoot)), '/');
            $basePath = $relative !== '' ? '/' . $relative . '/' : '/';
        } else {
            // fallback for odd server setups
            $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
            $basePath = preg_replace('#/actions/.*$#', '/', $scriptName);
            $basePath = preg_replace('#/admin/.*$#', '/', $basePath);
            $basePath = preg_replace('#/bus_admin/.*$#', '/', $basePath);
            $basePath = preg_replace('#/tour_admin/.*$#', '/', $basePath);
            $basePath = preg_replace('#/customer/.*$#', '/', $basePath);
            $basePath = rtrim(dirname($basePath), '/.') . '/';
            if ($basePath === '//') {
                $basePath = '/';
            }
        }

        return $scheme . '://' . $host . $basePath;
    }
}

define('APP_NAME', (string) mbtb_env('MBTB_APP_NAME', 'Myanmar Bus & Tour Booking'));
define('BASE_URL', mbtb_detect_base_url());

define('DB_HOST', (string) mbtb_env('DB_HOST', 'localhost'));
define('DB_NAME', (string) mbtb_env('DB_NAME', 'myanmar_bus_tour_booking'));
define('DB_USER', (string) mbtb_env('DB_USER', 'root'));
define('DB_PASS', (string) mbtb_env('DB_PASS', ''));

define('MAIL_ENABLED', mbtb_env_bool('MAIL_ENABLED', true));
define('MAIL_HOST', (string) mbtb_env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) mbtb_env('MAIL_PORT', '587'));
define('MAIL_USERNAME', (string) mbtb_env('MAIL_USERNAME', 'yemyatkyaw6227@gmail.com'));
define('MAIL_PASSWORD', (string) mbtb_env('MAIL_PASSWORD', 'zqhd nmgn hpci jwil'));
define('MAIL_ENCRYPTION', (string) mbtb_env('MAIL_ENCRYPTION', 'tls'));
define('MAIL_FROM_ADDRESS', (string) mbtb_env('MAIL_FROM_ADDRESS', 'yourrealgmail@gmail.com'));
define('MAIL_FROM_NAME', (string) mbtb_env('MAIL_FROM_NAME', APP_NAME));

date_default_timezone_set((string) mbtb_env('APP_TIMEZONE', 'Asia/Yangon'));