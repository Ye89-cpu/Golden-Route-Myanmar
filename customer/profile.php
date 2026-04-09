<?php
// /opt/lampp/htdocs/myanmar_bus_tour_booking/customer/profile.php

require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_history_helper.php';

require_role('customer');

$page_title = 'Customer Profile';
$user = current_user();

$conn = getDBConnection();
$historyRows = fetch_customer_booking_history($conn, (int)current_user_id());
$conn->close();

$summary = summarize_customer_booking_history($historyRows);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Customer Profile</h2>
            <p class="text-muted mb-0">Welcome back, <?php echo e($user['name']); ?>.</p>
        </div>

        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-primary">My Booking History</a>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-danger">Logout</a>
            <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-dark">Notifications</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Account Information</h5>
                    <p><strong>Name:</strong> <?php echo e($user['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo e($user['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo e($user['phone'] ?: '-'); ?></p>
                    <p class="mb-0"><strong>Role:</strong> <?php echo e($user['role']); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="small text-muted">Total</div>
                            <div class="fs-4 fw-bold"><?php echo e($summary['total_bookings']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="small text-muted">Bus</div>
                            <div class="fs-4 fw-bold text-primary"><?php echo e($summary['bus_bookings']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="small text-muted">Tour</div>
                            <div class="fs-4 fw-bold text-info"><?php echo e($summary['tour_bookings']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body">
                            <div class="small text-muted">Paid</div>
                            <div class="fs-4 fw-bold text-success"><?php echo e($summary['paid_bookings']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3">Quick Actions</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-primary">View All Bookings</a>
                                <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-outline-primary">Book a Bus</a>
                                <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-outline-info">Browse Tours</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3">Recent Activity</h5>

            <?php if (empty($historyRows)): ?>
                <div class="alert alert-info mb-0">You do not have any bookings yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking Code</th>
                                <th>Type</th>
                                <th>Service</th>
                                <th>Booked At</th>
                                <th>Payment</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($historyRows, 0, 5) as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e($row['booking_code']); ?></td>
                                    <td><?php echo e(strtoupper($row['booking_type'])); ?></td>
                                    <td>
                                        <?php if ($row['booking_type'] === 'bus'): ?>
                                            <?php echo e($row['from_city_name']); ?> → <?php echo e($row['to_city_name']); ?>
                                        <?php else: ?>
                                            <?php echo e($row['package_title']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e(date('Y-m-d H:i', strtotime((string)$row['booked_at']))); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['payment_status'])); ?>">
                                            <?php echo e(customer_history_format_status((string)$row['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-sm btn-outline-primary">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>