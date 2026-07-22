<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/partner_program_helper.php';

require_role('super_admin');

$page_title = 'Partner Applications - Golden Route Myanmar';
$conn = getDBConnection();
partner_ensure_application_table($conn);

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatuses = ['all', 'new', 'contacted', 'reviewing', 'approved', 'declined'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$counts = ['all' => 0, 'new' => 0, 'contacted' => 0, 'reviewing' => 0, 'approved' => 0, 'declined' => 0];
$countResult = $conn->query("SELECT status, COUNT(*) AS total FROM partner_applications GROUP BY status");
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $status = (string)($row['status'] ?? '');
        $total = (int)($row['total'] ?? 0);
        if (isset($counts[$status])) {
            $counts[$status] = $total;
            $counts['all'] += $total;
        }
    }
}

$applications = [];
if ($statusFilter === 'all') {
    $sql = "SELECT * FROM partner_applications ORDER BY FIELD(status, 'new','contacted','reviewing','approved','declined'), created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
    }
} else {
    $stmt = $conn->prepare("SELECT * FROM partner_applications WHERE status = ? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param('s', $statusFilter);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        $stmt->close();
    }
}

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5 partner-admin-applications">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
            <span class="section-kicker">Super Admin</span>
            <h1 class="page-title mb-2">Partner Applications</h1>
            <p class="page-subtitle mb-0">Review onboarding requests from bus companies and tour operators.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>partners.php" class="btn btn-outline-primary" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Public partner portal</a>
            <a href="<?php echo BASE_URL; ?>admin/system_settings.php#partner-settings" class="btn btn-outline-dark"><i class="bi bi-sliders"></i> Partner settings</a>
            <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
    <?php if ($error = get_flash('error')): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

    <div class="partner-admin-filter-grid mb-4">
        <?php foreach ($counts as $status => $total): ?>
            <a href="?status=<?php echo e($status); ?>" class="partner-admin-filter-card <?php echo $statusFilter === $status ? 'is-active' : ''; ?>">
                <small><?php echo e($status === 'all' ? 'All applications' : partner_application_status_label($status)); ?></small>
                <strong><?php echo number_format($total); ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($applications)): ?>
        <div class="empty-state-card"><div class="empty-state-icon"><i class="bi bi-inbox"></i></div><h4>No partner applications found</h4><p>Applications submitted from the public partner contact page will appear here.</p></div>
    <?php else: ?>
        <div class="partner-admin-application-list">
            <?php foreach ($applications as $application): ?>
                <article class="partner-admin-application-card">
                    <div class="partner-admin-application-top">
                        <div class="partner-admin-company-title">
                            <span class="partner-admin-company-icon"><i class="bi <?php echo $application['company_type'] === 'tour_operator' ? 'bi-map' : ($application['company_type'] === 'both' ? 'bi-stars' : 'bi-bus-front'); ?>"></i></span>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="mb-0"><?php echo e($application['company_name']); ?></h3>
                                    <span class="badge bg-<?php echo e(partner_application_status_class($application['status'])); ?>"><?php echo e(partner_application_status_label($application['status'])); ?></span>
                                </div>
                                <small><?php echo e($application['application_code']); ?> · <?php echo e(partner_company_type_label($application['company_type'])); ?> · Submitted <?php echo e(date('d M Y, h:i A', strtotime($application['created_at']))); ?></small>
                            </div>
                        </div>
                        <div class="partner-admin-contact-actions">
                            <a href="mailto:<?php echo e($application['email']); ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-envelope"></i> Email</a>
                            <a href="tel:<?php echo e($application['phone']); ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-telephone"></i> Call</a>
                        </div>
                    </div>

                    <div class="partner-admin-application-details">
                        <div><small>Contact person</small><strong><?php echo e($application['contact_name']); ?></strong></div>
                        <div><small>Phone</small><strong><?php echo e($application['phone']); ?></strong></div>
                        <div><small>Email</small><strong><?php echo e($application['email']); ?></strong></div>
                        <div><small>Preferred contact</small><strong><?php echo e(ucfirst($application['preferred_contact'])); ?></strong></div>
                        <div><small>License</small><strong><?php echo e($application['license_no'] ?: 'Not provided'); ?></strong></div>
                        <div><small>Monthly estimate</small><strong><?php echo $application['monthly_booking_estimate'] !== null ? number_format((int)$application['monthly_booking_estimate']) . ' bookings' : 'Not provided'; ?></strong></div>
                    </div>

                    <?php if (!empty($application['current_routes']) || !empty($application['business_address']) || !empty($application['message'])): ?>
                        <div class="partner-admin-notes-grid">
                            <?php if (!empty($application['current_routes'])): ?><div><small>Routes / destinations</small><p><?php echo nl2br(e($application['current_routes'])); ?></p></div><?php endif; ?>
                            <?php if (!empty($application['business_address'])): ?><div><small>Business address</small><p><?php echo nl2br(e($application['business_address'])); ?></p></div><?php endif; ?>
                            <?php if (!empty($application['message'])): ?><div class="partner-admin-message"><small>Message / requirements</small><p><?php echo nl2br(e($application['message'])); ?></p></div><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo BASE_URL; ?>actions/update_partner_application.php" method="POST" class="partner-admin-review-form">
                        <input type="hidden" name="application_id" value="<?php echo (int)$application['id']; ?>">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Application status</label>
                                <select name="status" class="form-select">
                                    <?php foreach (['new', 'contacted', 'reviewing', 'approved', 'declined'] as $status): ?>
                                        <option value="<?php echo e($status); ?>" <?php echo $application['status'] === $status ? 'selected' : ''; ?>><?php echo e(partner_application_status_label($status)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Internal review notes</label>
                                <textarea name="admin_notes" class="form-control" rows="2" maxlength="3000" placeholder="Document checks, call notes, agreed next action..."><?php echo e($application['admin_notes']); ?></textarea>
                            </div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Save Review</button></div>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
