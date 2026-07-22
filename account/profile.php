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
$roleLabel = ucwords(str_replace('_', ' ', (string)$user['role']));
$profileFields = [trim((string)$user['name']), trim((string)$user['email']), trim((string)$user['phone']), trim((string)$user['profile_image'])];
$completedFields = count(array_filter($profileFields, static fn($value) => $value !== ''));
$profileCompletion = (int)round(($completedFields / count($profileFields)) * 100);

$dashboardLink = BASE_URL . 'customer/bookings.php';
$dashboardLabel = 'My Bookings';
if ($role === 'super_admin') {
    $dashboardLink = BASE_URL . 'admin/dashboard.php';
    $dashboardLabel = 'Admin Dashboard';
} elseif ($role === 'bus_admin') {
    $dashboardLink = BASE_URL . 'bus_admin/dashboard.php';
    $dashboardLabel = 'Bus Dashboard';
} elseif ($role === 'tour_admin') {
    $dashboardLink = BASE_URL . 'tour_admin/dashboard.php';
    $dashboardLabel = 'Tour Dashboard';
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.profile-page { --profile-ink:#172033; --profile-muted:#667085; --profile-brand:#e5a100; --profile-soft:#fff7df; }
.profile-hero { background:linear-gradient(135deg,#171f31 0%,#26344f 70%,#3a4a69 100%); border-radius:28px; color:#fff; padding:32px; position:relative; overflow:hidden; }
.profile-hero::after { content:""; position:absolute; width:230px; height:230px; border-radius:50%; background:rgba(255,193,7,.18); right:-70px; top:-95px; }
.profile-avatar { width:112px; height:112px; border-radius:28px; object-fit:cover; border:4px solid rgba(255,255,255,.86); box-shadow:0 14px 35px rgba(0,0,0,.24); }
.profile-avatar-fallback { display:grid; place-items:center; background:linear-gradient(135deg,#ffc107,#ff9f1a); color:#1b2333; font-size:42px; font-weight:800; }
.profile-role-pill { display:inline-flex; align-items:center; gap:7px; padding:7px 12px; border-radius:999px; background:rgba(255,255,255,.13); font-size:.84rem; }
.profile-shell { border:1px solid #edf0f5; border-radius:24px; background:#fff; box-shadow:0 14px 38px rgba(23,32,51,.07); }
.profile-card-title { color:var(--profile-ink); font-weight:800; }
.profile-info-row { display:flex; justify-content:space-between; gap:16px; padding:13px 0; border-bottom:1px solid #eef1f5; }
.profile-info-row:last-child { border-bottom:0; }
.profile-info-row span { color:var(--profile-muted); }
.profile-progress { height:9px; border-radius:99px; background:#edf0f5; overflow:hidden; }
.profile-progress > span { display:block; height:100%; background:linear-gradient(90deg,#f4b400,#ff8b22); border-radius:99px; }
.profile-stat { border:1px solid #edf0f5; border-radius:20px; padding:18px; background:linear-gradient(180deg,#fff,#fbfcff); height:100%; }
.profile-stat i { width:42px; height:42px; display:grid; place-items:center; border-radius:14px; background:var(--profile-soft); color:#b97900; font-size:1.15rem; }
.profile-stat strong { display:block; margin-top:14px; font-size:1.7rem; color:var(--profile-ink); }
.profile-upload-preview { width:78px; height:78px; border-radius:20px; object-fit:cover; border:1px solid #e5e9f0; background:#f8f9fb; }
.profile-page .form-control { min-height:48px; border-radius:13px; border-color:#dfe4ec; }
.profile-page .form-control:focus { border-color:#e5a100; box-shadow:0 0 0 .2rem rgba(229,161,0,.13); }
.profile-page .btn { border-radius:12px; font-weight:700; }
.profile-activity-item { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:16px; border:1px solid #edf0f5; border-radius:18px; }
.profile-activity-icon { width:42px; height:42px; border-radius:14px; background:#eef4ff; color:#2d64c8; display:grid; place-items:center; flex:0 0 auto; }
@media (max-width:767.98px) { .profile-hero { padding:24px; } .profile-avatar { width:92px; height:92px; border-radius:24px; } }
</style>

<div class="container py-5 profile-page">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm"><?php echo e($error); ?></div>
    <?php endif; ?>

    <section class="profile-hero mb-4">
        <div class="row align-items-center g-4 position-relative" style="z-index:1;">
            <div class="col-auto">
                <?php if ($profileImageUrl !== ''): ?>
                    <img src="<?php echo e($profileImageUrl); ?>" alt="<?php echo e($user['name']); ?>" class="profile-avatar" id="profileHeroPreview">
                <?php else: ?>
                    <div class="profile-avatar profile-avatar-fallback" id="profileAvatarFallback">
                        <?php echo e(strtoupper(substr(trim((string)$user['name']), 0, 1) ?: 'U')); ?>
                    </div>
                    <img src="" alt="Profile preview" class="profile-avatar d-none" id="profileHeroPreview">
                <?php endif; ?>
            </div>
            <div class="col">
                <div class="profile-role-pill mb-3"><i class="bi bi-patch-check-fill"></i> <?php echo e($roleLabel); ?></div>
                <h1 class="fw-bold mb-1"><?php echo e($user['name']); ?></h1>
                <p class="mb-3 text-white-50"><?php echo e($user['email']); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo e($dashboardLink); ?>" class="btn btn-warning"><i class="bi bi-grid me-1"></i><?php echo e($dashboardLabel); ?></a>
                    <a href="<?php echo BASE_URL; ?>notifications.php" class="btn btn-outline-light"><i class="bi bi-bell me-1"></i>Notifications</a>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="p-3 rounded-4" style="background:rgba(255,255,255,.1);">
                    <div class="d-flex justify-content-between small mb-2"><span>Profile completion</span><strong><?php echo $profileCompletion; ?>%</strong></div>
                    <div class="profile-progress"><span style="width:<?php echo $profileCompletion; ?>%"></span></div>
                    <small class="d-block mt-2 text-white-50">Add your phone and profile photo to complete your account.</small>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="profile-shell p-4 mb-4">
                <h5 class="profile-card-title mb-3">Account overview</h5>
                <div class="profile-info-row"><span>Phone</span><strong><?php echo e($user['phone'] ?: 'Not added'); ?></strong></div>
                <div class="profile-info-row"><span>Status</span><strong class="text-<?php echo ($user['status'] ?? '') === 'active' ? 'success' : 'secondary'; ?>"><?php echo e(ucfirst((string)$user['status'])); ?></strong></div>
                <div class="profile-info-row"><span>Joined</span><strong><?php echo e(date('M d, Y', strtotime((string)$user['created_at']))); ?></strong></div>
                <div class="profile-info-row"><span>Last login</span><strong><?php echo !empty($user['last_login_at']) ? e(date('M d, Y H:i', strtotime((string)$user['last_login_at']))) : '—'; ?></strong></div>
            </div>

            <?php if ($companyInfo): ?>
                <div class="profile-shell p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="profile-activity-icon"><i class="bi bi-buildings"></i></div>
                        <div><small class="text-muted">Assigned company</small><h5 class="profile-card-title mb-0"><?php echo e($companyInfo['company_name'] ?? '-'); ?></h5></div>
                    </div>
                    <div class="profile-info-row"><span>Company type</span><strong><?php echo e(ucwords(str_replace('_', ' ', (string)($companyInfo['company_type'] ?? '-')))); ?></strong></div>
                    <div class="profile-info-row"><span>Company role</span><strong><?php echo e(ucfirst((string)($companyInfo['role_in_company'] ?? '-'))); ?></strong></div>
                    <div class="profile-info-row"><span>Company status</span><strong><?php echo e(ucfirst((string)($companyInfo['company_status'] ?? '-'))); ?></strong></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <?php if ($role === 'customer' && is_array($summary)): ?>
                <div class="row g-3 mb-4">
                    <?php
                    $stats = [
                        ['Total bookings', $summary['total_bookings'], 'bi-ticket-perforated'],
                        ['Bus trips', $summary['bus_bookings'], 'bi-bus-front'],
                        ['Tour bookings', $summary['tour_bookings'], 'bi-map'],
                        ['Paid bookings', $summary['paid_bookings'], 'bi-check-circle'],
                    ];
                    foreach ($stats as $stat):
                    ?>
                        <div class="col-6 col-xl-3"><div class="profile-stat"><i class="bi <?php echo e($stat[2]); ?>"></i><strong><?php echo e($stat[1]); ?></strong><span class="text-muted small"><?php echo e($stat[0]); ?></span></div></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="profile-shell p-4 p-lg-5 mb-4">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div><span class="text-uppercase small fw-bold text-warning">Account settings</span><h3 class="profile-card-title mb-1">Update your profile</h3><p class="text-muted mb-0">Changes are saved through the existing secure profile action.</p></div>
                    <i class="bi bi-person-gear fs-2 text-warning"></i>
                </div>

                <form action="<?php echo BASE_URL; ?>actions/update_profile.php" method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo e($user['name']); ?>" maxlength="120" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo e($user['phone'] ?? ''); ?>" placeholder="09xxxxxxxxx">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Email address</label>
                            <div class="input-group"><span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope"></i></span><input type="email" class="form-control border-start-0" value="<?php echo e($user['email']); ?>" disabled></div>
                            <div class="form-text">Email remains locked to protect account roles and company permissions.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Profile photo</label>
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-3 p-3 rounded-4 bg-light">
                                <img src="<?php echo $profileImageUrl !== '' ? e($profileImageUrl) : e(BASE_URL . 'assets/images/logo.png'); ?>" alt="Preview" class="profile-upload-preview" id="profileFilePreview" onerror="this.style.visibility='hidden'">
                                <div class="flex-grow-1"><input type="file" name="profile_image" id="profileImageInput" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif"><div class="form-text">JPG, PNG, WEBP or GIF, maximum 2MB.</div></div>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold">New password</label><input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Leave blank to keep current password" autocomplete="new-password"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Confirm new password</label><input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Re-enter new password" autocomplete="new-password"><div class="invalid-feedback">Passwords do not match.</div></div>
                        <div class="col-12 d-flex flex-wrap gap-2 pt-2"><button type="submit" class="btn btn-warning px-4"><i class="bi bi-check2-circle me-1"></i>Save profile</button><button type="reset" class="btn btn-outline-secondary px-4">Reset</button></div>
                    </div>
                </form>
            </div>

            <?php if ($role === 'customer' && is_array($summary)): ?>
                <div class="profile-shell p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="profile-card-title mb-1">Recent activity</h5><p class="text-muted small mb-0">Your five latest bookings.</p></div><a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-sm btn-outline-dark">View all</a></div>
                    <?php if (empty($historyRows)): ?>
                        <div class="text-center py-4"><i class="bi bi-ticket-perforated fs-1 text-muted"></i><p class="text-muted mt-2 mb-3">You do not have any bookings yet.</p><a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-warning">Book your first trip</a></div>
                    <?php else: ?>
                        <div class="d-grid gap-2">
                            <?php foreach (array_slice($historyRows, 0, 5) as $row): ?>
                                <div class="profile-activity-item">
                                    <div class="d-flex align-items-center gap-3 min-w-0"><div class="profile-activity-icon"><i class="bi <?php echo $row['booking_type'] === 'bus' ? 'bi-bus-front' : 'bi-map'; ?>"></i></div><div><strong class="d-block"><?php echo e($row['booking_code']); ?></strong><span class="text-muted small"><?php echo $row['booking_type'] === 'bus' ? e(($row['from_city_name'] ?? '-') . ' → ' . ($row['to_city_name'] ?? '-')) : e($row['package_title'] ?? 'Tour booking'); ?></span></div></div>
                                    <div class="text-end"><span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['payment_status'])); ?>"><?php echo e(customer_history_format_status((string)$row['payment_status'])); ?></span><small class="d-block text-muted mt-1"><?php echo e(date('M d, Y', strtotime((string)$row['booked_at']))); ?></small></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="profile-shell p-4"><h5 class="profile-card-title">Account security notes</h5><p class="text-muted mb-0">Your email is intentionally locked here. You can safely update your name, phone, password and profile image without affecting your assigned permissions.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('profileImageInput');
    const preview = document.getElementById('profileFilePreview');
    const heroPreview = document.getElementById('profileHeroPreview');
    const fallback = document.getElementById('profileAvatarFallback');
    const form = document.getElementById('profileForm');
    const password = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');

    if (input) {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            if (file.size > 2 * 1024 * 1024) {
                alert('Profile image must be 2MB or smaller.');
                this.value = '';
                return;
            }
            const url = URL.createObjectURL(file);
            if (preview) { preview.src = url; preview.style.visibility = 'visible'; }
            if (heroPreview) { heroPreview.src = url; heroPreview.classList.remove('d-none'); }
            if (fallback) fallback.classList.add('d-none');
        });
    }

    function validatePasswords() {
        if (!confirmPassword) return true;
        const matched = !password.value || password.value === confirmPassword.value;
        confirmPassword.classList.toggle('is-invalid', !matched);
        return matched;
    }
    if (password && confirmPassword) {
        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
    if (form) form.addEventListener('submit', function (event) { if (!validatePasswords()) event.preventDefault(); });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
