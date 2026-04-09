<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

if (!isset($page_title) || trim((string)$page_title) === '') {
    $page_title = APP_NAME;
}

$currentRole = current_user_role();
$currentUser = current_user();

$appTitle = (string) system_setting_runtime_get('app_name', APP_NAME);
$supportEmail = (string) system_setting_runtime_get('support_email', 'support@example.com');
$supportPhone = (string) system_setting_runtime_get('support_phone', '+95 9 123 456 789');
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

$navIs = static function (array $pages) use ($currentPath): string {
    return in_array($currentPath, $pages, true) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <nav class="navbar navbar-expand-lg custom-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand brand-logo" href="<?php echo BASE_URL; ?>index.php">
                <span class="brand-mark">MB</span>
                <span class="brand-text">
                    <span class="brand-title"><?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                    <small class="brand-subtitle">
                        <?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?> ·
                        <?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                </span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon custom-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0 nav-pill-group">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $navIs(['index.php', '']); ?>" href="<?php echo BASE_URL; ?>index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $navIs(['search_bus.php']); ?>" href="<?php echo BASE_URL; ?>search_bus.php">Bus Search</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $navIs(['tours.php', 'tour_package.php']); ?>" href="<?php echo BASE_URL; ?>tours.php">Tours</a>
                    </li>

                    <?php if ($currentRole === 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $navIs(['bookings.php', 'ticket.php', 'voucher.php', 'refund_request.php']); ?>" href="<?php echo BASE_URL; ?>customer/bookings.php">My Bookings</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($currentRole === 'super_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin Panel</a>
                        </li>
                    <?php elseif ($currentRole === 'bus_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>bus_admin/dashboard.php">Bus Admin</a>
                        </li>
                    <?php elseif ($currentRole === 'tour_admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>tour_admin/dashboard.php">Tour Admin</a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2 nav-auth-buttons">
                    <?php if (is_logged_in()): ?>
                        <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-sm btn-nav-soft">Notifications</a>

                        <?php
                        $profileLink = BASE_URL . 'customer/profile.php';
                        if ($currentRole === 'super_admin') {
                            $profileLink = BASE_URL . 'admin/dashboard.php';
                        } elseif ($currentRole === 'bus_admin') {
                            $profileLink = BASE_URL . 'bus_admin/dashboard.php';
                        } elseif ($currentRole === 'tour_admin') {
                            $profileLink = BASE_URL . 'tour_admin/dashboard.php';
                        }
                        ?>

                        <a href="<?php echo $profileLink; ?>" class="btn btn-sm btn-nav-soft">
                            <?php echo htmlspecialchars($currentUser['name'] ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-nav-primary">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-sm btn-nav-soft">Login</a>
                        <?php if ($registrationEnabled): ?>
                            <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-sm btn-nav-primary">Register</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>