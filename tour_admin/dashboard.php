<?php
require_once __DIR__ . '/../includes/role_check.php';
require_role('tour_admin');

$page_title = 'Tour Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';

$user = current_user();
?>

<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h2 class="fw-bold mb-3">Tour Operator Admin Dashboard</h2>
            <p><strong>Name:</strong> <?php echo e($user['name']); ?></p>
            <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
            <p><strong>Role:</strong> <?php echo e($user['role']); ?></p>

            <div class="mt-4">
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-danger">Logout</a>
                <a href="<?php echo BASE_URL; ?>tour_admin/bookings.php" class="btn btn-outline-primary">
    Manage Bookings
</a>

<a href="<?php echo BASE_URL; ?>tour_admin/voucher_checkin.php" class="btn btn-outline-dark">
    Voucher Check-in
</a>
<a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-dark">Notifications</a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>