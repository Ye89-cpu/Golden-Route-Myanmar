<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Register - Golden Route Myanmar';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);
?>

<div class="auth-page auth-page-register">
    <div class="container">
        <div class="auth-page-grid">
            <section class="auth-visual-card auth-visual-register">
                <div class="auth-visual-overlay"></div>
                <div class="auth-visual-content">
                    <span class="auth-mini-badge">
                        <i class="bi bi-stars"></i>
                        New Customer Registration
                    </span>

                    <h1 class="auth-visual-title">Create your travel account in minutes</h1>
                    <p class="auth-visual-text">
                        Register once to book buses and tours, upload payment proof, download tickets and
                        monitor your trips from a single account.
                    </p>

                    <div class="auth-highlight-grid">
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-lightning-charge"></i></span>
                            <div>
                                <strong>Quick account setup</strong>
                                <small>Simple registration flow for customers</small>
                            </div>
                        </div>

                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-credit-card"></i></span>
                            <div>
                                <strong>Payment tracking</strong>
                                <small>Monitor proof review and confirmation</small>
                            </div>
                        </div>

                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-map"></i></span>
                            <div>
                                <strong>Bus and tour access</strong>
                                <small>Manage routes, batches and bookings easily</small>
                            </div>
                        </div>
                    </div>

                    <div class="auth-visual-footer">
                        <div class="auth-visual-stat">
                            <strong>1 Account</strong>
                            <span>All bookings</span>
                        </div>
                        <div class="auth-visual-stat">
                            <strong>QR Ready</strong>
                            <span>Ticket access</span>
                        </div>
                        <div class="auth-visual-stat">
                            <strong>Easy</strong>
                            <span>Travel management</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="auth-form-card-wrap">
                <div class="auth-form-card-modern auth-form-card-register">
                    <div class="auth-form-topbar">
                        <span class="auth-form-badge">Register</span>
                        <span class="auth-form-note">Customer account</span>
                    </div>

                    <div class="auth-form-header-modern">
                        <h2>Create your account</h2>
                        <p>Fill in your details to start booking buses and tours with one secure profile.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4 mb-4"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4 mb-4"><?php echo e($success); ?></div>
                    <?php endif; ?>

                    <?php if (!$registrationEnabled): ?>
                        <div class="alert alert-warning rounded-4 mb-0">
                            Customer registration is currently disabled by the administrator.
                        </div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>actions/register_action.php" method="POST" class="auth-modern-form" novalidate>
                            <div class="auth-modern-field">
                                <label class="form-label">Full Name</label>
                                <div class="auth-input-wrap">
                                    <span class="auth-input-icon"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        name="name"
                                        class="form-control auth-control"
                                        value="<?php echo e(old('name')); ?>"
                                        placeholder="Enter your full name"
                                        required
                                    >
                                </div>
                            </div>

                            <div class="auth-modern-field auth-grid-2">
                                <div>
                                    <label class="form-label">Email Address</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-envelope"></i></span>
                                        <input
                                            type="email"
                                            name="email"
                                            class="form-control auth-control"
                                            value="<?php echo e(old('email')); ?>"
                                            placeholder="Enter your email"
                                            required
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label">Phone Number</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-telephone"></i></span>
                                        <input
                                            type="text"
                                            name="phone"
                                            class="form-control auth-control"
                                            value="<?php echo e(old('phone')); ?>"
                                            placeholder="09xxxxxxxxx"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="auth-modern-field auth-grid-2">
                                <div>
                                    <label class="form-label">Password</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-lock"></i></span>
                                        <input
                                            type="password"
                                            name="password"
                                            class="form-control auth-control"
                                            placeholder="Create password"
                                            required
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label">Confirm Password</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-shield-check"></i></span>
                                        <input
                                            type="password"
                                            name="password_confirmation"
                                            class="form-control auth-control"
                                            placeholder="Confirm password"
                                            required
                                        >
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn auth-submit-btn w-100">
                                <i class="bi bi-person-plus"></i>
                                Create Account
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="auth-helper-row">
                        <span>Already have an account?</span>
                        <a href="<?php echo BASE_URL; ?>login.php">Login here</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<?php
clear_old_input();
require_once __DIR__ . '/includes/footer.php';