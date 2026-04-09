<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/includes/footer.php
require_once __DIR__ . '/../config.php';

$appTitle = (string) system_setting_runtime_get('app_name', APP_NAME);
$supportEmail = (string) system_setting_runtime_get('support_email', 'support@example.com');
$supportPhone = (string) system_setting_runtime_get('support_phone', '+95 9 123 456 789');
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-top">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="footer-brand">
                        <div class="footer-brand-badge">MB</div>
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></h5>
                            <p class="mb-0 footer-muted">
                                Bus tickets, tour packages, QR boarding, payment tracking and admin control in one place.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="footer-title">Quick Links</h6>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>index.php">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>search_bus.php">Bus Search</a></li>
                        <li><a href="<?php echo BASE_URL; ?>tours.php">Tours</a></li>
                        <li><a href="<?php echo BASE_URL; ?>login.php">Login</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-title">Customer</h6>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>customer/bookings.php">My Bookings</a></li>
                        <li><a href="<?php echo BASE_URL; ?>payment.php">Payment</a></li>
                        <li><a href="<?php echo BASE_URL; ?>notifications.php">Notifications</a></li>
                        <li><a href="<?php echo BASE_URL; ?>register.php">Create Account</a></li>
                    </ul>
                </div>

                <div class="col-lg-3 col-md-6">
                    <h6 class="footer-title">Contact</h6>
                    <ul class="footer-contact">
                        <li>Email: <?php echo htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'); ?></li>
                        <li>Phone: <?php echo htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8'); ?></li>
                        <li>Location: Yangon, Myanmar</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <small>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appTitle, ENT_QUOTES, 'UTF-8'); ?></small>
                <small class="footer-muted">PHP + MySQL + Bootstrap</small>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>