<?php
require_once __DIR__ . '/../config.php';

$appTitle = (string) system_setting_runtime_get('app_name', APP_NAME);
$supportEmail = (string) system_setting_runtime_get('support_email', 'support@example.com');
$supportPhone = (string) system_setting_runtime_get('support_phone', '+95 9 123 456 789');
$logoPath = BASE_URL . 'assets/images/G.png';
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);
$currentYear = date('Y');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$isUserLoggedIn = !empty($_SESSION['user']);
?>

<footer class="site-footer-premium p-2">


    <div class="container-fluid position-relative" style="z-index:2;">
        <div class="footer-cta-bar">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <span class="footer-cta-kicker">Plan Your Next Journey</span>
                    <h3 class="footer-cta-title mb-2">Book bus tickets, explore tours, and manage trips in one place</h3>
                    <p class="footer-cta-text mb-0">
                        Faster search, secure payment proof upload, booking history, and smoother customer support.
                    </p>
                </div>
                <div class="col-lg-4">
                    <div class="footer-cta-actions">
                        <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand footer-cta-btn">
                            Find Bus
                        </a>
                        <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft footer-cta-btn">
                            Explore Tours
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-main-wrap">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-brand-card h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
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
                            Trusted bus tickets, tour packages, QR boarding, secure payment checking,
                            and booking history in one modern platform for customers and admins.
                        </p>

                        <div class="footer-chip-wrap">
                            <span class="footer-chip">Secure Payment</span>
                            <span class="footer-chip">QR Ticket</span>
                            <span class="footer-chip">Easy Refund</span>
                            <span class="footer-chip">Tour Booking</span>
                        </div>

                        <div class="footer-brand-mini-stats">
                            <div class="footer-mini-stat">
                                <strong>Fast</strong>
                                <span>Search & booking flow</span>
                            </div>
                            <div class="footer-mini-stat">
                                <strong>Safe</strong>
                                <span>Payment & booking records</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Quick Links</h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>index.php">Home</a></li>
                            <li><a href="<?php echo BASE_URL; ?>about.php">About Us</a></li>
                            <li><a href="<?php echo BASE_URL; ?>search_bus.php">Find Bus</a></li>
                            <li><a href="<?php echo BASE_URL; ?>tours.php">Tours</a></li>
                            <?php if ($isUserLoggedIn): ?>
                                <li><a href="<?php echo BASE_URL; ?>customer/bookings.php">My Bookings</a></li>
                            <?php else: ?>
                                <li><a href="<?php echo BASE_URL; ?>login.php">Login</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-sm-6 col-lg-2">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Legal</h6>
                        <ul class="footer-links">
                            <li><a href="<?php echo BASE_URL; ?>privacy_policy.php">Privacy Policy</a></li>
                            <li><a href="<?php echo BASE_URL; ?>cookie_policy.php">Cookie Policy</a></li>
                            <li><a href="<?php echo BASE_URL; ?>about.php">Company Information</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-sm-6">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Customer Services</h6>
                        <ul class="footer-links">
                            <?php if ($isUserLoggedIn): ?>
                                <li><a href="<?php echo BASE_URL; ?>customer/bookings.php">Booking History</a></li>
                                <li><a href="<?php echo BASE_URL; ?>notifications.php">Notifications</a></li>
                            <?php else: ?>
                                <?php if ($registrationEnabled): ?>
                                    <li><a href="<?php echo BASE_URL; ?>register.php">Create Account</a></li>
                                <?php endif; ?>
                                <li><a href="<?php echo BASE_URL; ?>login.php">Customer Login</a></li>
                            <?php endif; ?>
                            <li><a href="<?php echo BASE_URL; ?>payment.php">Payment Guide</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-sm-6">
                    <div class="footer-link-card h-100">
                        <h6 class="footer-title">Contact & Support</h6>

                        <div class="footer-contact-item">
                            <span class="footer-contact-label">Email</span>
                            <a href="mailto:<?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>

                        <div class="footer-contact-item">
                            <span class="footer-contact-label">Phone</span>
                            <a href="tel:<?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>

                        <div class="footer-contact-item">
                            <span class="footer-contact-label">Location</span>
                            <span>Yangon, Myanmar</span>
                        </div>

                        <div class="footer-social-wrap">
                            <a href="https://facebook.com/" target="_blank" class="footer-social-icon" aria-label="Facebook">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="https://t.me/" target="_blank" class="footer-social-icon" aria-label="Telegram">
                                <i class="bi bi-telegram"></i>
                            </a>
                            <a href="https://www.tiktok.com/" target="_blank" class="footer-social-icon" aria-label="TikTok">
                                <i class="bi bi-tiktok"></i>
                            </a>
                            <a href="https://youtube.com/" target="_blank" class="footer-social-icon" aria-label="YouTube">
                                <i class="bi bi-youtube"></i>
                            </a>
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
                    <a href="<?php echo BASE_URL; ?>privacy_policy.php">Privacy Policy</a>
                    <a href="<?php echo BASE_URL; ?>cookie_policy.php">Cookie Policy</a>
                </div>

                <div class="footer-bottom-right">
                    <small>Built for smarter travel and easier booking management.</small>
                </div>
            </div>
        </div>
    </div>
</footer>

<script src="<?= BASE_URL ?>assets/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/app-ui.js"></script>
</body>
</html>