<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/profile_helper.php';

if (!isset($page_title) || trim((string) $page_title) === '') {
    $page_title = APP_NAME;
}

$currentRole = current_user_role();
$currentUser = current_user();

$appTitle = (string) system_setting_runtime_get('app_name', APP_NAME);
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);

$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');

$navIs = static function (array $pages) use ($currentPath): string {
    return in_array($currentPath, $pages, true) ? 'active-nav' : '';
};

$logoPath = BASE_URL . 'assets/images/G.png';

$profileLink = BASE_URL . 'account/profile.php';
$dashboardLabel = '';
$dashboardHref = '';

if ($currentRole === 'super_admin') {
    $dashboardLabel = 'Admin';
    $dashboardHref = BASE_URL . 'admin/dashboard.php';
} elseif ($currentRole === 'bus_admin') {
    $dashboardLabel = 'Bus Admin';
    $dashboardHref = BASE_URL . 'bus_admin/dashboard.php';
} elseif ($currentRole === 'tour_admin') {
    $dashboardLabel = 'Tour Admin';
    $dashboardHref = BASE_URL . 'tour_admin/dashboard.php';
}

$displayName = (string) ($currentUser['name'] ?? 'Account');
$trimmedName = trim($displayName);
$initial = $trimmedName !== '' ? strtoupper(substr($trimmedName, 0, 1)) : 'U';
$profileImageUrl = profile_public_path($currentUser['profile_image'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="<?= BASE_URL ?>assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=20260414v4">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/bootstrap-icons/font/bootstrap-icons.css">
</head>

<body>
    <header class="site-header ">
        <div class="floating-nav-wrap">
            <nav class="navbar navbar-expand-lg navbar-light floating-nav border border-2 border-secondary rounded-3">
                <div class="container px-lg-3">
                    <a class="navbar-brand d-flex align-items-center gap-3 fw-bold"
                        href="<?php echo BASE_URL; ?>index.php">
                        <img src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>"
                            alt="Golden Route Myanmar Logo" class="site-logo">
                        <div class="d-flex flex-column">
                            <span class="brand-title">
                                <span
                                    class="brand-gold"><?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                            <small class="brand-subtitle">Bus tickets &amp; tour booking made simple</small>
                        </div>
                    </a>

                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                        aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="mainNavbar">
                        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mb-3 mb-lg-0">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navIs(['index.php', '']); ?>"
                                    href="<?php echo BASE_URL; ?>index.php">Home</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navIs(['about.php', 'about_story.php']); ?>"
                                    href="<?php echo BASE_URL; ?>about.php">About Us</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navIs(['search_bus.php']); ?>"
                                    href="<?php echo BASE_URL; ?>search_bus.php">Find Bus</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $navIs(['tours.php', 'tour_package.php']); ?>"
                                    href="<?php echo BASE_URL; ?>tours.php">Tours</a>
                            </li>

                            <?php if ($currentRole === 'customer'): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['bookings.php', 'ticket.php', 'voucher.php', 'refund_request.php']); ?>"
                                        href="<?php echo BASE_URL; ?>customer/bookings.php">My Bookings</a>
                                </li>
                            <?php endif; ?>

                            <?php if ($dashboardLabel !== ''): ?>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['dashboard.php']); ?>"
                                        href="<?php echo $dashboardHref; ?>">
                                        <?php echo htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <div
                            class="d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center gap-2 ms-lg-3">
                            <?php if (is_logged_in()): ?>
                                <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-nav-soft btn-sm">
                                    🔔 Notifications
                                </a>

                                <a href="<?php echo $profileLink; ?>" class="user-pill"
                                    title="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if ($profileImageUrl !== ''): ?>
                                        <img src="<?php echo e($profileImageUrl); ?>" alt="<?php echo e($displayName); ?>"
                                            class="user-avatar user-avatar-img">
                                    <?php else: ?>
                                        <span
                                            class="user-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <span class="d-none d-md-inline fw-semibold">
                                        <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </a>

                                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-brand btn-sm">Logout</a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-nav-soft btn-sm">Login</a>
                                <?php if ($registrationEnabled): ?>
                                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-brand btn-sm">Create
                                        Account</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>