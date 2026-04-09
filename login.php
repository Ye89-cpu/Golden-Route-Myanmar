<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Login - Myanmar Bus & Tour Booking';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
?>

<div class="auth-shell">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="auth-side-panel">
                    <span class="auth-kicker">Secure Login</span>
                    <h1 class="auth-side-title">Welcome back</h1>
                    <p class="auth-side-text">
                        Login to manage tickets, vouchers, notifications, payments and booking history
                        from one clean dashboard.
                    </p>

                    <div class="auth-feature-list">
                        <div class="auth-feature-item">Role-based dashboard redirection</div>
                        <div class="auth-feature-item">Notification center and booking tracking</div>
                        <div class="auth-feature-item">Cleaner UI for admin and customer users</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="auth-card">
                    <div class="auth-card-header">
                        <h2 class="mb-2">Login</h2>
                        <p class="text-muted mb-0">Use your account credentials to continue.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4"><?php echo e($success); ?></div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>actions/login_action.php" method="POST" class="auth-form-grid" novalidate>
                        <div>
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg" value="<?php echo e(old('email')); ?>" required>
                        </div>

                        <div>
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
                    </form>

                    <div class="text-center mt-4">
                        Don’t have an account?
                        <a href="<?php echo BASE_URL; ?>register.php">Register here</a>
                    </div>

                    <div class="demo-login-note mt-4">
                        Demo super admin: <strong>admin@mbtb.local</strong> / <strong>Admin@123</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
clear_old_input();
require_once __DIR__ . '/includes/footer.php';
