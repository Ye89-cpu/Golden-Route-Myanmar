<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Login - Golden Route Myanmar';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
?>

<div class="auth-page auth-page-login">
    <div class="container">
        <div class="auth-page-grid">
            <section class="auth-visual-card auth-visual-login">
                <div class="auth-visual-overlay"></div>
                <div class="auth-visual-content">
                    <span class="auth-mini-badge">
                        <i class="bi bi-shield-lock"></i>
                        Secure Customer Access
                    </span>

                    <h1 class="auth-visual-title">Welcome back to your travel dashboard</h1>
                    <p class="auth-visual-text">
                        Login to check bookings, payment status, QR tickets, notifications and refund updates
                        from one clean account.
                    </p>

                    <div class="auth-highlight-grid">
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-bus-front"></i></span>
                            <div>
                                <strong>Fast booking access</strong>
                                <small>Bus and tour history in one place</small>
                            </div>
                        </div>

                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-qr-code"></i></span>
                            <div>
                                <strong>QR ticket ready</strong>
                                <small>Open tickets and vouchers anytime</small>
                            </div>
                        </div>

                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-bell"></i></span>
                            <div>
                                <strong>Real-time updates</strong>
                                <small>Track payment and booking notifications</small>
                            </div>
                        </div>
                    </div>

                    <div class="auth-visual-footer">
                        <div class="auth-visual-stat">
                            <strong>24/7</strong>
                            <span>Support flow</span>
                        </div>
                        <div class="auth-visual-stat">
                            <strong>Safe</strong>
                            <span>Account login</span>
                        </div>
                        <div class="auth-visual-stat">
                            <strong>Quick</strong>
                            <span>Dashboard access</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="auth-form-card-wrap">
                <div class="auth-form-card-modern">
                    <div class="auth-form-topbar">
                        <span class="auth-form-badge">Login</span>
                        <span class="auth-form-note">Golden Route Myanmar</span>
                    </div>

                    <div class="auth-form-header-modern">
                        <h2>Sign in to continue</h2>
                        <p>Enter your account details below to access bookings and travel updates.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4 mb-4"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4 mb-4"><?php echo e($success); ?></div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>actions/login_action.php" method="POST" class="auth-modern-form" novalidate>
                        <div class="auth-modern-field">
                            <label class="form-label">Email Address</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><i class="bi bi-envelope"></i></span>
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control auth-control"
                                    value="<?php echo e(old('email')); ?>"
                                    placeholder="Enter your email address"
                                    required
                                >
                            </div>
                        </div>

                        <div class="auth-modern-field">
                            <label class="form-label">Password</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    name="password"
                                    class="form-control auth-control"
                                    placeholder="Enter your password"
                                    required
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn auth-submit-btn w-100">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Login Now
                        </button>
                    </form>

                    <div class="auth-helper-row">
                        <span>Don’t have an account?</span>
                        <a href="<?php echo BASE_URL; ?>register.php">Create account</a>
                    </div>


                </div>
            </section>
        </div>
    </div>
</div>

<?php
clear_old_input();
require_once __DIR__ . '/includes/footer.php';