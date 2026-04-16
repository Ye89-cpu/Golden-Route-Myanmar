<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/event_helper.php';

$page_title = 'Events & Promotions - Golden Route Myanmar';

$conn = getDBConnection();
$events = [];

try {
    ensure_events_table_exists($conn);
    $events = get_public_events($conn, 100);
} catch (Throwable $e) {
    $events = [];
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center mb-5">
        <span class="section-kicker">Golden Route Myanmar</span>
        <h1 class="page-title mb-2">Events & Promotions</h1>
        <p class="page-subtitle mx-auto" style="max-width: 760px;">
            Browse active travel campaigns, seasonal promotions, public announcements and featured events published by the super admin team.
        </p>
    </div>

    <?php if (empty($events)): ?>
        <div class="alert alert-info text-center">There are no active events or promotions right now.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $event): ?>
                <?php
                    $eventTitle = $event['title'] ?? 'Travel Event';
                    $eventType = $event['event_type'] ?? 'Promotion';
                    $eventDescription = trim((string)($event['description'] ?? ''));
                    $eventDate = trim((string)($event['event_date'] ?? ''));
                    $eventLocation = trim((string)($event['location'] ?? ''));
                ?>
                <div class="col-md-6 col-xl-4" id="event-<?php echo (int)$event['id']; ?>">
                    <div class="card h-100 border-0 shadow-sm overflow-hidden">
                        <img
                            src="<?php echo e(event_public_image($event['image_path'] ?? null, $eventType)); ?>"
                            alt="<?php echo e($eventTitle); ?>"
                            class="card-img-top"
                            style="height: 220px; object-fit: cover;"
                        >
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge bg-warning text-dark"><?php echo e($eventType); ?></span>
                                <?php if ($eventDate !== ''): ?>
                                    <span class="badge bg-light text-dark border"><i class="bi bi-calendar-event"></i> <?php echo e($eventDate); ?></span>
                                <?php endif; ?>
                            </div>

                            <h4 class="card-title mb-2"><?php echo e($eventTitle); ?></h4>

                            <?php if ($eventLocation !== ''): ?>
                                <p class="text-muted small mb-2"><i class="bi bi-geo-alt"></i> <?php echo e($eventLocation); ?></p>
                            <?php endif; ?>

                            <p class="card-text mb-0">
                                <?php echo e($eventDescription !== '' ? $eventDescription : 'More details will be announced soon.'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>