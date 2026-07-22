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

$logoPath = BASE_URL . 'assets/images/logo.png';

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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=20260721-partner-portal-v1">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/bootstrap-icons/font/bootstrap-icons.css">
</head>

<body>
    <header class="site-header site-header-modern">
        <div class="modern-header-wrap">
            <nav class="navbar navbar-expand-xl modern-header-nav" aria-label="Main navigation">
                <div class="container-fluid modern-header-container">
                    <a class="navbar-brand modern-header-brand" href="<?php echo BASE_URL; ?>index.php" aria-label="<?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?> home">
                        <span class="modern-logo-frame" aria-hidden="true">
                            <span class="modern-logo-glow"></span>
                            <img
                                src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>"
                                alt=""
                                class="modern-site-logo"
                            >
                        </span>
                        <span class="modern-brand-copy">
                            <span class="modern-brand-label">Official travel platform</span>
                            <strong><?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small>Bus tickets <span></span> Tours <span></span> Trusted companies</small>
                        </span>
                    </a>

                    <button
                        class="navbar-toggler modern-menu-toggle"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#mainNavbar"
                        aria-controls="mainNavbar"
                        aria-expanded="false"
                        aria-label="Open navigation menu"
                    >
                        <span class="modern-menu-icon"><i class="bi bi-list"></i></span>
                        <span class="modern-menu-text">Menu</span>
                    </button>

                    <div class="collapse navbar-collapse modern-navbar-collapse" id="mainNavbar">
                        <div class="modern-nav-panel">
                            <ul class="navbar-nav modern-main-nav mb-0">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['index.php', '']); ?>" href="<?php echo BASE_URL; ?>index.php">
                                        <i class="bi bi-house-door"></i><span>Home</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['about.php', 'about_story.php']); ?>" href="<?php echo BASE_URL; ?>about.php">
                                        <i class="bi bi-info-circle"></i><span>About</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['search_bus.php']); ?>" href="<?php echo BASE_URL; ?>search_bus.php">
                                        <i class="bi bi-bus-front"></i><span>Find Bus</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['tours.php', 'tour_package.php']); ?>" href="<?php echo BASE_URL; ?>tours.php">
                                        <i class="bi bi-compass"></i><span>Tours</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo $navIs(['partners.php', 'partner_finance.php', 'partner_reports.php', 'partner_manuals.php', 'partner_contact.php']); ?>" href="<?php echo BASE_URL; ?>partners.php">
                                        <i class="bi bi-handshake"></i><span>Partners</span>
                                    </a>
                                </li>

                                <?php if ($currentRole === 'customer'): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $navIs(['bookings.php', 'ticket.php', 'voucher.php', 'refund_request.php']); ?>" href="<?php echo BASE_URL; ?>customer/bookings.php">
                                            <i class="bi bi-ticket-perforated"></i><span>My Bookings</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($dashboardLabel !== ''): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $navIs(['dashboard.php']); ?>" href="<?php echo $dashboardHref; ?>">
                                            <i class="bi bi-grid"></i><span><?php echo htmlspecialchars($dashboardLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="modern-header-actions">
                            <?php if (is_logged_in()): ?>
                                <a href="<?php echo BASE_URL; ?>notifications.php" class="modern-icon-action" title="Notifications" aria-label="Notifications">
                                    <i class="bi bi-bell"></i>
                                    <span class="modern-action-dot" aria-hidden="true"></span>
                                </a>

                                <a href="<?php echo $profileLink; ?>" class="modern-user-pill" title="Open profile">
                                    <?php if ($profileImageUrl !== ''): ?>
                                        <img src="<?php echo e($profileImageUrl); ?>" alt="<?php echo e($displayName); ?>" class="modern-user-avatar modern-user-avatar-img">
                                    <?php else: ?>
                                        <span class="modern-user-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <span class="modern-user-copy">
                                        <small>Welcome back</small>
                                        <strong><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </span>
                                    <i class="bi bi-chevron-right modern-user-arrow"></i>
                                </a>

                                <a href="<?php echo BASE_URL; ?>logout.php" class="modern-logout-btn">
                                    <i class="bi bi-box-arrow-right"></i><span>Logout</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>login.php" class="modern-login-btn">
                                    <i class="bi bi-person"></i><span>Login</span>
                                </a>
                                <?php if ($registrationEnabled): ?>
                                    <a href="<?php echo BASE_URL; ?>register.php" class="modern-register-btn">
                                        <span class="modern-register-icon"><i class="bi bi-person-plus"></i></span>
                                        <span>Create Account</span>
                                        <i class="bi bi-arrow-up-right"></i>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>
        </div>
    </header>
