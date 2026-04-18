<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/profile_helper.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/customer_history_helper.php';

require_login();

$page_title = 'My Profile';
$conn = getDBConnection();
$userId = (int)current_user_id();
$role = (string)current_user_role();

$userSql = "SELECT id, name, email, phone, role, status, profile_image, created_at, last_login_at FROM users WHERE id = ? LIMIT 1";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    $conn->close();
    logout_user();
    set_flash('error', 'Your account could not be found. Please login again.');
    redirect('login.php');
}

$_SESSION['user']['name'] = $user['name'];
$_SESSION['user']['phone'] = $user['phone'];
$_SESSION['user']['profile_image'] = $user['profile_image'];

$companyInfo = null;
$historyRows = [];
$summary = null;

if ($role === 'customer') {
    $historyRows = fetch_customer_booking_history($conn, $userId);
    $summary = summarize_customer_booking_history($historyRows);
} elseif ($role === 'bus_admin') {
    $companyInfo = get_bus_admin_company($conn, $userId);
} elseif ($role === 'tour_admin') {
    $companyInfo = get_tour_admin_company($conn, $userId);
}

$conn->close();

$profileImageUrl = profile_public_path($user['profile_image'] ?? '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success rounded-4"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger rounded-4"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">My Profile</h2>
            <p class="text-muted mb-0">Manage your account information and profile image.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($role === 'super_admin'): ?>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-dark">Dashboard</a>
            <?php elseif ($role === 'bus_admin'): ?>
                <a href="<?php echo BASE_URL; ?>bus_admin/dashboard.php" class="btn btn-outline-dark">Dashboard</a>
            <?php elseif ($role === 'tour_admin'): ?>
                <a href="<?php echo BASE_URL; ?>tour_admin/dashboard.php" class="btn btn-outline-dark">Dashboard</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-outline-dark">My Bookings</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-primary">Notifications</a>
            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-outline-danger">Logout</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 profile-side-card mb-4">
                <div class="card-body p-4 text-center">
                    <?php if ($profileImageUrl !== ''): ?>
                        <img src="<?php echo e($profileImageUrl); ?>" alt="<?php echo e($user['name']); ?>" class="profile-photo-lg mb-3">
                    <?php else: ?>
                        <div class="profile-photo-lg profile-photo-fallback mb-3 mx-auto">
                            <?php echo e(strtoupper(substr(trim((string)$user['name']), 0, 1) ?: 'U')); ?>
                        </div>
                    <?php endif; ?>

                    <h4 class="fw-bold mb-1"><?php echo e($user['name']); ?></h4>
                    <div class="text-muted mb-1"><?php echo e($user['email']); ?></div>
                    <div class="small text-muted mb-3">
                        Role: <?php echo e(ucwords(str_replace('_', ' ', $user['role']))); ?>
                    </div>

                    <div class="profile-meta-list text-start">
                        <div><strong>Phone:</strong> <?php echo e($user['phone'] ?: '-'); ?></div>
                        <div><strong>Status:</strong> <?php echo e(ucfirst((string)$user['status'])); ?></div>
                        <div><strong>Joined:</strong> <?php echo e(date('Y-m-d', strtotime((string)$user['created_at']))); ?></div>
                        <div><strong>Last Login:</strong> <?php echo !empty($user['last_login_at']) ? e(date('Y-m-d H:i', strtotime((string)$user['last_login_at']))) : '-'; ?></div>
                    </div>
                </div>
            </div>

            <?php if ($companyInfo): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Assigned Company</h5>
                        <div class="profile-meta-list">
                            <div><strong>Name:</strong> <?php echo e($companyInfo['company_name'] ?? '-'); ?></div>
                            <div><strong>Type:</strong> <?php echo e(ucwords(str_replace('_', ' ', (string)($companyInfo['company_type'] ?? '-')))); ?></div>
                            <div><strong>Role in Company:</strong> <?php echo e(ucfirst((string)($companyInfo['role_in_company'] ?? '-'))); ?></div>
                            <div><strong>Status:</strong> <?php echo e(ucfirst((string)($companyInfo['company_status'] ?? '-'))); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Update Account</h5>
                    <form action="<?php echo BASE_URL; ?>actions/update_profile.php" method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo e($user['name']); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo e($user['phone'] ?? ''); ?>">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo e($user['email']); ?>" disabled>
                                <div class="form-text">Login email is kept locked here to avoid role/account conflicts.</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Profile Image</label>
                                <input type="file" name="profile_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
                                <div class="form-text">Allowed: JPG, PNG, WEBP, GIF. Max size: 2MB.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Save Profile</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($role === 'customer' && is_array($summary)): ?>
                <div class="row g-3 mb-4">
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
            <?php else: ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Account Notes</h5>
                        <ul class="mb-0">
                            <li>Your profile image will appear in the top navigation after saving.</li>
                            <li>Company admins created from Super Admin → Companies can update their own name, phone, password, and image here.</li>
                            <li>Email is locked here so company-linked accounts stay stable in the permission system.</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>