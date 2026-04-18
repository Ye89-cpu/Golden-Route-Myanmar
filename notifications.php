<?php

require_once __DIR__ . '/includes/role_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notification_helper.php';

$user = current_user();
if (!$user) {
    redirect('login.php');
}

$page_title = 'Notification Center';

$conn = getDBConnection();
$notifications = fetch_user_notifications($conn, (int)$user['id'], 100);
$unreadCount = count_user_unread_notifications($conn, (int)$user['id']);
$conn->close();

require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Notification Center</h2>
            <p class="text-muted mb-0">Unread: <?php echo e($unreadCount); ?></p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <form action="<?php echo BASE_URL; ?>actions/mark_all_notifications_read.php" method="POST" class="d-inline">
                <input type="hidden" name="back_url" value="notifications.php">
                <button type="submit" class="btn btn-outline-primary">Mark All Read</button>
            </form>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-secondary">Home</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No notifications found.</div>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item p-4 <?php echo (int)$notification['is_read'] === 0 ? 'bg-light' : ''; ?>">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        <span class="badge bg-<?php echo e($notification['type']); ?>">
                                            <?php echo e(ucfirst($notification['type'])); ?>
                                        </span>
                                        <?php if ((int)$notification['is_read'] === 0): ?>
                                            <span class="badge bg-dark">Unread</span>
                                        <?php endif; ?>
                                    </div>

                                    <h5 class="fw-bold mb-2"><?php echo e($notification['title']); ?></h5>
                                    <div class="mb-2"><?php echo nl2br(e($notification['message'])); ?></div>
                                    <div class="small text-muted">
                                        <?php echo e(date('Y-m-d H:i:s', strtotime((string)$notification['created_at']))); ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-2" style="min-width: 220px;">
                                    <?php if (!empty($notification['link_url'])): ?>
                                        <a href="<?php echo e($notification['link_url']); ?>" class="btn btn-sm btn-outline-primary">
                                            Open Link
                                        </a>
                                    <?php endif; ?>

                                    <?php if ((int)$notification['is_read'] === 0): ?>
                                        <form action="<?php echo BASE_URL; ?>actions/mark_notification_read.php" method="POST">
                                            <input type="hidden" name="notification_id" value="<?php echo e($notification['id']); ?>">
                                            <input type="hidden" name="back_url" value="<?php echo e(BASE_URL . 'notifications.php'); ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">Mark Read</button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>Read</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>