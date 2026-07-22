<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Login - Golden Route Myanmar';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
?>

<main class="auth-page auth-page-login auth-v3">
    <div class="auth-v3-orb auth-v3-orb-one"></div>
    <div class="auth-v3-orb auth-v3-orb-two"></div>

    <div class="container position-relative">
        <div class="auth-page-grid">
            <section class="auth-visual-card auth-visual-login">
                <div class="auth-visual-overlay"></div>
                <div class="auth-v3-pattern" aria-hidden="true"></div>

                <div class="auth-visual-content">
                    <div>
                        <a href="<?php echo BASE_URL; ?>index.php" class="auth-back-link">
                            <i class="bi bi-arrow-left"></i>
                            Back to home
                        </a>

                        <span class="auth-mini-badge">
                            <i class="bi bi-shield-check"></i>
                            Secure customer access
                        </span>

                        <h1 class="auth-visual-title">Your complete journey, ready when you are.</h1>
                        <p class="auth-visual-text">
                            Sign in to continue your booking, review payment status, open QR tickets,
                            and manage every trip from one simple dashboard.
                        </p>
                    </div>

                    <div class="auth-route-preview">
                        <div class="auth-route-preview-head">
                            <span><i class="bi bi-bus-front"></i> Upcoming journey</span>
                            <small>Simple booking flow</small>
                        </div>
                        <div class="auth-route-line">
                            <div>
                                <small>From</small>
                                <strong>Yangon</strong>
                            </div>
                            <span class="auth-route-track"><i class="bi bi-arrow-right"></i></span>
                            <div class="text-end">
                                <small>To</small>
                                <strong>Mandalay</strong>
                            </div>
                        </div>
                        <div class="auth-route-meta">
                            <span><i class="bi bi-patch-check-fill"></i> Approved companies</span>
                            <span><i class="bi bi-qr-code"></i> QR ticket access</span>
                        </div>
                    </div>

                    <div class="auth-highlight-grid auth-highlight-grid-inline">
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-ticket-perforated"></i></span>
                            <div>
                                <strong>Manage bookings</strong>
                                <small>Bus and tour history together</small>
                            </div>
                        </div>
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-bell"></i></span>
                            <div>
                                <strong>Track updates</strong>
                                <small>Payment and booking notifications</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="auth-form-card-wrap">
                <div class="auth-form-card-modern">
                    <div class="auth-brand-row">
                        <div class="auth-brand-mark">
                            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Golden Route Myanmar">
                        </div>
                        <div>
                            <strong>Golden Route Myanmar</strong>
                            <span>Travel smarter across Myanmar</span>
                        </div>
                    </div>

                    <div class="auth-form-topbar">
                        <span class="auth-form-badge"><i class="bi bi-box-arrow-in-right"></i> Login</span>
                        <span class="auth-form-note"><i class="bi bi-lock-fill"></i> Protected access</span>
                    </div>

                    <div class="auth-form-header-modern">
                        <h2>Welcome back</h2>
                        <p>Enter your account details to continue to bookings and travel updates.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger auth-alert" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <span><?php echo e($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success auth-alert" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <span><?php echo e($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>actions/login_action.php" method="POST" class="auth-modern-form" data-login-validation novalidate>
                        <div class="auth-modern-field">
                            <label class="form-label" for="loginEmail">Email address</label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon"><i class="bi bi-envelope"></i></span>
                                <input
                                    type="email"
                                    id="loginEmail"
                                    name="email"
                                    class="form-control auth-control"
                                    value="<?php echo e(old('email')); ?>"
                                    placeholder="name@example.com"
                                    autocomplete="username"
                                    inputmode="email"
                                    aria-describedby="loginEmailFormat loginEmailMessage"
                                    data-login-email
                                    required
                                >
                            </div>
                            <div class="auth-format-hint" id="loginEmailFormat">
                                <i class="bi bi-info-circle"></i>
                                Use the email registered with your account.
                            </div>
                            <div class="auth-field-message" id="loginEmailMessage" data-email-message></div>
                        </div>

                        <div class="auth-modern-field">
                            <label class="form-label" for="loginPassword">Password</label>
                            <div class="auth-input-wrap auth-password-wrap">
                                <span class="auth-input-icon"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    id="loginPassword"
                                    name="password"
                                    class="form-control auth-control auth-control-has-toggle"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    minlength="8"
                                    aria-describedby="loginPasswordMessage"
                                    data-login-password
                                    required
                                >
                                <button
                                    type="button"
                                    class="auth-toggle-password"
                                    data-password-toggle
                                    data-target="#loginPassword"
                                    aria-label="Show password"
                                    aria-pressed="false"
                                >
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="auth-field-message" id="loginPasswordMessage" data-password-message></div>
                        </div>

                        <button type="submit" class="btn auth-submit-btn w-100">
                            <span>Sign in securely</span>
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </form>

                    <div class="auth-security-row">
                        <span><i class="bi bi-shield-lock"></i> Secure sign-in</span>
                        <span><i class="bi bi-person-check"></i> Customer account</span>
                        <span><i class="bi bi-clock-history"></i> Booking history</span>
                    </div>

                    <div class="auth-helper-row">
                        <span>New to Golden Route?</span>
                        <a href="<?php echo BASE_URL; ?>register.php">Create an account <i class="bi bi-arrow-up-right"></i></a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<?php
clear_old_input();
$hide_footer_cta = true;
require_once __DIR__ . '/includes/footer.php';
