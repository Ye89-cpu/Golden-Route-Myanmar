<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Register - Myanmar Bus & Tour Booking';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);
?>

<div class="auth-shell">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="auth-side-panel">
                    <span class="auth-kicker">Customer Access</span>
                    <h1 class="auth-side-title">Create your booking account</h1>
                    <p class="auth-side-text">
                        Register once to manage bus bookings, tour packages, payment tracking,
                        QR tickets and downloadable PDFs in one place.
                    </p>

                    <div class="auth-feature-list">
                        <div class="auth-feature-item">Fast bus and tour booking flow</div>
                        <div class="auth-feature-item">Payment status and refund tracking</div>
                        <div class="auth-feature-item">QR ticket and voucher access</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="auth-card">
                    <div class="auth-card-header">
                        <h2 class="mb-2">Register</h2>
                        <p class="text-muted mb-0">Fill your details to create a customer account.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4"><?php echo e($success); ?></div>
                    <?php endif; ?>

                    <?php if (!$registrationEnabled): ?>
                        <div class="alert alert-warning rounded-4 mb-0">
                            Customer registration is currently disabled by the administrator.
                        </div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>actions/register_action.php" method="POST" class="auth-form-grid" novalidate>
                            <div>
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control form-control-lg" value="<?php echo e(old('name')); ?>" required>
                            </div>

                            <div>
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control form-control-lg" value="<?php echo e(old('email')); ?>" required>
                            </div>

                            <div>
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control form-control-lg" value="<?php echo e(old('phone')); ?>" placeholder="09xxxxxxxxx">
                            </div>

                            <div>
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control form-control-lg" required>
                            </div>

                            <div>
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="form-control form-control-lg" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">Create Account</button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        Already have an account?
                        <a href="<?php echo BASE_URL; ?>login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
clear_old_input();
require_once __DIR__ . '/includes/footer.php';