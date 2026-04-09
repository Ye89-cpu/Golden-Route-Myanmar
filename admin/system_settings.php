<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/system_setting_helper.php';

require_role('super_admin');

$page_title = 'System Settings';

$conn = getDBConnection();
$settings = fetch_system_settings_map($conn);
$checks = system_production_readiness_checks($conn, dirname(__DIR__));
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">System Settings</h2>
            <p class="text-muted mb-0">Runtime settings + production readiness checks</p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="btn btn-outline-dark">Audit Logs</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Application Settings</h5>

                    <form action="<?php echo BASE_URL; ?>actions/save_system_settings.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">App Name</label>
                            <input type="text" name="app_name" class="form-control" value="<?php echo e($settings['app_name'] ?? 'Myanmar Bus & Tour Booking'); ?>" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Support Email</label>
                                <input type="email" name="support_email" class="form-control" value="<?php echo e($settings['support_email'] ?? 'support@example.com'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Support Phone</label>
                                <input type="text" name="support_phone" class="form-control" value="<?php echo e($settings['support_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Default Currency</label>
                                <input type="text" name="default_currency" class="form-control" value="<?php echo e($settings['default_currency'] ?? 'MMK'); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Default Timezone</label>
                                <input type="text" name="default_timezone" class="form-control" value="<?php echo e($settings['default_timezone'] ?? 'Asia/Yangon'); ?>">
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="maintenance_mode" <?php echo !empty($settings['maintenance_mode']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="maintenance_mode">
                                Enable Maintenance Mode
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maintenance Message</label>
                            <textarea name="maintenance_message" rows="4" class="form-control"><?php echo e($settings['maintenance_message'] ?? 'System is under maintenance. Please try again later.'); ?></textarea>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="registration_enabled" value="1" id="registration_enabled" <?php echo !empty($settings['registration_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="registration_enabled">
                                Allow Customer Registration
                            </label>
                        </div>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="email_enabled" value="1" id="email_enabled" <?php echo !empty($settings['email_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_enabled">
                                Enable Email Sending
                            </label>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="ticket_qr_required" value="1" id="ticket_qr_required" <?php echo !empty($settings['ticket_qr_required']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ticket_qr_required">
                                Require QR / Token validation in boarding and check-in flows
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Production Readiness</h5>

                    <div class="list-group list-group-flush">
                        <?php foreach ($checks as $check): ?>
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div>
                                        <div class="fw-semibold"><?php echo e($check['label']); ?></div>
                                        <div class="small text-muted"><?php echo e($check['detail']); ?></div>
                                    </div>
                                    <span class="badge bg-<?php echo $check['status'] ? 'success' : 'danger'; ?>">
                                        <?php echo $check['status'] ? 'OK' : 'FAIL'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <hr>
                    <div class="small text-muted">
                        Tip: maintenance mode ကို ON လုပ်ရင် super admin ကပဲ site ထဲဝင်နိုင်မယ်။
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>