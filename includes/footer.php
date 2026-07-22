<?php
require_once __DIR__ . '/../config.php';

$appTitle = (string) system_setting_runtime_get('app_name', APP_NAME);
$supportEmail = (string) system_setting_runtime_get('support_email', 'support@example.com');
$supportPhone = (string) system_setting_runtime_get('support_phone', '+95 9 123 456 789');
$logoPath = BASE_URL . 'assets/images/logo.png';
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);
$currentYear = date('Y');
$footerTravelDate = date('Y-m-d');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$isUserLoggedIn = !empty($_SESSION['user']);

$footerRoutes = [
    ['from' => 'Yangon', 'to' => 'Mandalay'],
    ['from' => 'Yangon', 'to' => 'Bagan'],
    ['from' => 'Mandalay', 'to' => 'Taunggyi'],
];
?>

<footer class="site-footer-premium">
    <div class="footer-glow footer-glow-1"></div>
    <div class="footer-glow footer-glow-2"></div>
    <div class="footer-grid-pattern" aria-hidden="true"></div>

    <div class="container position-relative" style="z-index:2;">
        <?php if (empty($hide_footer_cta)): ?>
            <div class="footer-cta-bar footer-cta-v3">
                <div class="footer-cta-icon"><i class="bi bi-compass"></i></div>
                <div class="footer-cta-copy">
                    <span class="footer-cta-kicker">Your next route starts here</span>
                    <h3 class="footer-cta-title">Search trusted trips and keep every booking in one place.</h3>
                    <p class="footer-cta-text">
                        Compare approved companies, choose a seat, submit payment proof, and access your ticket from one account.
                    </p>
                </div>
                <div class="footer-cta-actions">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand footer-cta-btn">
                        <i class="bi bi-search"></i> Find a bus
                    </a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn footer-outline-btn footer-cta-btn">
                        <i class="bi bi-map"></i> Explore tours
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer-trust-strip">
            <div class="footer-trust-item">
                <span><i class="bi bi-patch-check-fill"></i></span>
                <div><strong>Approved companies</strong><small>Travel with verified operators</small></div>
            </div>
            <div class="footer-trust-item">
                <span><i class="bi bi-shield-lock-fill"></i></span>
                <div><strong>Secure booking records</strong><small>Account and payment tracking</small></div>
            </div>
            <div class="footer-trust-item">
                <span><i class="bi bi-qr-code"></i></span>
                <div><strong>Easy ticket access</strong><small>Open tickets and vouchers anytime</small></div>
            </div>
        </div>

        <div class="footer-main-wrap">
            <div class="row g-4">
                <div class="col-xl-4 col-lg-4">
                    <div class="footer-brand-card h-100">
                        <div class="footer-brand-head">
                            <img
                                src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?> Logo"
                                class="footer-logo"
                                onerror="this.style.display='none'">

                            <div>
                                <div class="footer-brand-title">
                                    <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="footer-brand-subtitle">Travel smarter across Myanmar</div>
                            </div>
                        </div>

                        <p class="footer-text">
                            A modern platform for bus tickets, tour packages, payment verification,
                            QR boarding, refunds, and complete booking history.
                        </p>

                        <div class="footer-route-box">
                            <span class="footer-route-label"><i class="bi bi-signpost-split"></i> Popular routes</span>
                            <div class="footer-route-links">
                                <?php foreach ($footerRoutes as $route): ?>
                                    <?php
                                        $routeUrl = BASE_URL . 'search_bus.php?' . http_build_query([
                                            'from' => $route['from'],
                                            'to' => $route['to'],
                                            'travel_date' => $footerTravelDate,
                                            'service_type' => 'all',
                                            'search' => '1',
                                        ]);
                                    ?>
                                    <a href="<?php echo htmlspecialchars($routeUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($route['from'], ENT_QUOTES, 'UTF-8'); ?>
                                        <i class="bi bi-arrow-right"></i>
                                        <?php echo htmlspecialchars($route['to'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="footer-social-wrap">
                            <a href="https://facebook.com/" target="_blank" rel="noopener" class="footer-social-icon" aria-label="Facebook">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="https://t.me/" target="_blank" rel="noopener" class="footer-social-icon" aria-label="Telegram">
                                <i class="bi bi-telegram"></i>
                            </a>
                            <a href="https://www.tiktok.com/" target="_blank" rel="noopener" class="footer-social-icon" aria-label="TikTok">
                                <i class="bi bi-tiktok"></i>
                            </a>
                            <a href="https://youtube.com/" target="_blank" rel="noopener" class="footer-social-icon" aria-label="YouTube">
                                <i class="bi bi-youtube"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2 col-xl-2">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Explore</h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>index.php"><i class="bi bi-house"></i> Home</a></li>
                            <li><a href="<?php echo BASE_URL; ?>search_bus.php"><i class="bi bi-bus-front"></i> Find Bus</a></li>
                            <li><a href="<?php echo BASE_URL; ?>tours.php"><i class="bi bi-map"></i> Tours</a></li>
                            <li><a href="<?php echo BASE_URL; ?>about.php"><i class="bi bi-info-circle"></i> About Us</a></li>
                            <li><a href="<?php echo BASE_URL; ?>events.php"><i class="bi bi-calendar-event"></i> Events</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2 col-xl-2">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">My Travel</h6>
                        <ul class="footer-links">
                            <?php if ($isUserLoggedIn): ?>
                                <li><a href="<?php echo BASE_URL; ?>customer/bookings.php"><i class="bi bi-ticket-perforated"></i> My Bookings</a></li>
                                <li><a href="<?php echo BASE_URL; ?>notifications.php"><i class="bi bi-bell"></i> Notifications</a></li>
                                <li><a href="<?php echo BASE_URL; ?>account/profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                            <?php else: ?>
                                <li><a href="<?php echo BASE_URL; ?>login.php"><i class="bi bi-box-arrow-in-right"></i> Customer Login</a></li>
                                <?php if ($registrationEnabled): ?>
                                    <li><a href="<?php echo BASE_URL; ?>register.php"><i class="bi bi-person-plus"></i> Create Account</a></li>
                                <?php endif; ?>
                            <?php endif; ?>
                            <li><a href="<?php echo BASE_URL; ?>payment.php"><i class="bi bi-credit-card"></i> Payment Guide</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2 col-xl-2">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Help & Legal</h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>partners.php"><i class="bi bi-handshake"></i> Partner Program</a></li>
                            <li><a href="<?php echo BASE_URL; ?>partner_finance.php"><i class="bi bi-wallet2"></i> Commission & Payments</a></li>
                            <li><a href="<?php echo BASE_URL; ?>partner_manuals.php"><i class="bi bi-journal-check"></i> Admin Manuals</a></li>
                            <li><a href="<?php echo BASE_URL; ?>privacy_policy.php"><i class="bi bi-shield-check"></i> Privacy Policy</a></li>
                            <li><a href="<?php echo BASE_URL; ?>cookie_policy.php"><i class="bi bi-cookie"></i> Cookie Policy</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2 col-xl-2">
                    <div class="footer-link-card footer-contact-card h-100">
                        <h6 class="footer-title">Contact</h6>

                        <a class="footer-contact-row" href="mailto:<?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?>">
                            <span><i class="bi bi-envelope"></i></span>
                            <div><small>Email support</small><strong><?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        </a>

                        <a class="footer-contact-row" href="tel:<?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?>">
                            <span><i class="bi bi-telephone"></i></span>
                            <div><small>Call us</small><strong><?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        </a>

                        <div class="footer-contact-row">
                            <span><i class="bi bi-geo-alt"></i></span>
                            <div><small>Office location</small><strong>Yangon, Myanmar</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="footer-divider">

            <div class="footer-bottom">
                <div class="footer-bottom-left">
                    <small>
                        &copy; <?php echo $currentYear; ?> <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?>.
                        All rights reserved.
                    </small>
                </div>

                <div class="footer-bottom-center">
                    <span><i class="bi bi-shield-check"></i> Secure</span>
                    <span><i class="bi bi-lightning-charge"></i> Simple</span>
                    <span><i class="bi bi-heart"></i> Made for Myanmar travel</span>
                </div>

                <div class="footer-bottom-right">
                    <a href="#" class="footer-back-top" aria-label="Back to top">
                        Back to top <i class="bi bi-arrow-up"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app-ui.js?v=20260721-partner-portal"></script>
</body>
</html>
