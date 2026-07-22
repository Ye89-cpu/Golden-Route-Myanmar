<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/system_setting_helper.php';

function partner_setting_float(string $key, float $default, float $minimum = 0, float $maximum = 100): float
{
    $raw = system_setting_runtime_get($key, $default);
    $value = is_numeric($raw) ? (float)$raw : $default;
    return max($minimum, min($maximum, $value));
}

function partner_setting_int(string $key, int $default, int $minimum = 0): int
{
    $raw = system_setting_runtime_get($key, $default);
    $value = is_numeric($raw) ? (int)$raw : $default;
    return max($minimum, $value);
}

function partner_program_config(): array
{
    $supportEmail = trim((string)system_setting_runtime_get(
        'partner_support_email',
        system_setting_runtime_get('support_email', 'partners@goldenroute.local')
    ));

    $supportPhone = trim((string)system_setting_runtime_get(
        'partner_support_phone',
        system_setting_runtime_get('support_phone', '+95 9 123 456 789')
    ));

    return [
        'currency' => trim((string)system_setting_runtime_get('default_currency', 'MMK')) ?: 'MMK',
        'bus_commission' => partner_setting_float('partner_bus_commission_percent', 7),
        'tour_commission' => partner_setting_float('partner_tour_commission_percent', 10),
        'both_commission' => partner_setting_float('partner_both_commission_percent', 8),
        'minimum_settlement' => partner_setting_int('partner_min_settlement_amount', 100000),
        'settlement_cycle' => trim((string)system_setting_runtime_get('partner_settlement_cycle', 'Twice monthly')),
        'settlement_method' => trim((string)system_setting_runtime_get(
            'partner_settlement_method',
            'Bank transfer to the verified company account'
        )),
        'period_one' => trim((string)system_setting_runtime_get(
            'partner_settlement_period_one',
            'Paid bookings from the 1st–15th are settled by the 20th.'
        )),
        'period_two' => trim((string)system_setting_runtime_get(
            'partner_settlement_period_two',
            'Paid bookings from the 16th–month end are settled by the 5th of the next month.'
        )),
        'report_delivery' => trim((string)system_setting_runtime_get(
            'partner_report_delivery',
            'Dashboard summary plus downloadable PDF report'
        )),
        'support_email' => $supportEmail,
        'support_phone' => $supportPhone,
        'commercial_note' => trim((string)system_setting_runtime_get(
            'partner_commercial_note',
            'Final rates are confirmed in the signed partner agreement. Refunds, chargebacks, and approved adjustments are deducted before settlement.'
        )),
    ];
}

function partner_commission_for_type(string $companyType, array $config): float
{
    return match ($companyType) {
        'tour_operator' => (float)$config['tour_commission'],
        'both' => (float)$config['both_commission'],
        default => (float)$config['bus_commission'],
    };
}

function partner_company_type_label(string $companyType): string
{
    return match ($companyType) {
        'tour_operator' => 'Tour Operator',
        'both' => 'Bus + Tour Company',
        default => 'Bus Company',
    };
}

function partner_money(float|int $amount, string $currency = 'MMK'): string
{
    $decimals = strtoupper($currency) === 'MMK' ? 0 : 2;
    return number_format((float)$amount, $decimals) . ' ' . strtoupper($currency);
}

function partner_percentage(float|int $value): string
{
    $formatted = number_format((float)$value, 2, '.', '');
    return rtrim(rtrim($formatted, '0'), '.') . '%';
}

function partner_application_table_exists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'partner_applications'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function partner_ensure_application_table(mysqli $conn): void
{
    if (partner_application_table_exists($conn)) {
        return;
    }

    $sql = "
        CREATE TABLE IF NOT EXISTS partner_applications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            application_code VARCHAR(40) NOT NULL,
            company_name VARCHAR(180) NOT NULL,
            company_type ENUM('bus_company','tour_operator','both') NOT NULL DEFAULT 'bus_company',
            license_no VARCHAR(120) DEFAULT NULL,
            contact_name VARCHAR(150) NOT NULL,
            phone VARCHAR(80) NOT NULL,
            email VARCHAR(190) NOT NULL,
            preferred_contact ENUM('phone','email','viber','telegram') NOT NULL DEFAULT 'phone',
            business_address TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            current_routes TEXT DEFAULT NULL,
            monthly_booking_estimate INT UNSIGNED DEFAULT NULL,
            message TEXT DEFAULT NULL,
            status ENUM('new','contacted','reviewing','approved','declined') NOT NULL DEFAULT 'new',
            admin_notes TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_partner_application_code (application_code),
            KEY idx_partner_application_status (status),
            KEY idx_partner_application_created_at (created_at),
            KEY idx_partner_application_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to prepare the partner application table.');
    }
}

function partner_application_code(): string
{
    return 'PRT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function partner_application_status_label(string $status): string
{
    return match ($status) {
        'contacted' => 'Contacted',
        'reviewing' => 'Under Review',
        'approved' => 'Approved',
        'declined' => 'Declined',
        default => 'New',
    };
}

function partner_application_status_class(string $status): string
{
    return match ($status) {
        'contacted' => 'info',
        'reviewing' => 'warning text-dark',
        'approved' => 'success',
        'declined' => 'danger',
        default => 'primary',
    };
}

function partner_csrf_token(): string
{
    if (empty($_SESSION['partner_csrf_token'])) {
        $_SESSION['partner_csrf_token'] = bin2hex(random_bytes(24));
    }

    return (string)$_SESSION['partner_csrf_token'];
}

function partner_verify_csrf(string $token): bool
{
    $sessionToken = (string)($_SESSION['partner_csrf_token'] ?? '');
    return $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);
}
