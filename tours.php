<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tour_booking_helper.php';

$page_title = 'Tour Packages';

if (!function_exists('mbtb_excerpt')) {
    function mbtb_excerpt(string $text, int $limit = 120): string
    {
        $text = trim(strip_tags($text));

        if ($text === '') {
            return 'No description available.';
        }

        if (function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $limit, '...');
        }

        return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
    }
}

$conn = getDBConnection();
$packages = [];

try {
    $packages = fetch_public_tour_packages($conn);
} catch (Throwable $e) {
    $packages = [];
}
$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-hero-sm">
    <div class="container">
        <div class="page-hero-content">
            <span class="section-kicker">Discover Myanmar</span>
            <h1 class="page-title">Tour Packages</h1>
            <p class="page-subtitle">
                Browse available tour packages, compare batches and choose the best trip for your schedule.
            </p>
        </div>
    </div>
</section>

<div class="container py-5">
    <?php if (empty($packages)): ?>
        <div class="empty-state-card">
            <h3>No active tour packages yet</h3>
            <p>Please check again later after tour packages are published.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($packages as $package): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="tour-card h-100">
                        <div class="tour-card-media">
                            <?php if (!empty($package['cover_image'])): ?>
                                <img
                                    src="<?php echo BASE_URL . ltrim((string)$package['cover_image'], '/'); ?>"
                                    alt="<?php echo e($package['title']); ?>"
                                    class="tour-card-image"
                                >
                            <?php else: ?>
                                <div class="tour-card-image tour-card-placeholder">
                                    <span><?php echo e($package['title']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="tour-card-body">
                            <div class="tour-card-top">
                                <span class="soft-badge"><?php echo e($package['company_name'] ?? 'Tour Company'); ?></span>
                                <h3 class="tour-card-title"><?php echo e($package['title'] ?? 'Untitled Package'); ?></h3>
                                <p class="tour-card-text">
                                    <?php echo e(mbtb_excerpt((string)($package['description'] ?? ''), 120)); ?>
                                </p>
                            </div>

                            <div class="tour-meta-grid">
                                <div class="tour-meta-item">
                                    <span class="meta-label">Duration</span>
                                    <strong><?php echo (int)($package['duration_days'] ?? 0); ?> days</strong>
                                </div>

                                <div class="tour-meta-item">
                                    <span class="meta-label">Starting From</span>
                                    <strong>
                                        <?php
                                        $displayPrice = isset($package['min_batch_price']) && $package['min_batch_price'] !== null
                                            ? (float)$package['min_batch_price']
                                            : (float)($package['price'] ?? 0);
                                        echo number_format($displayPrice, 0);
                                        ?> MMK
                                    </strong>
                                </div>

                                <div class="tour-meta-item">
                                    <span class="meta-label">Batches</span>
                                    <strong><?php echo (int)($package['total_batches'] ?? 0); ?></strong>
                                </div>

                                <div class="tour-meta-item">
                                    <span class="meta-label">Status</span>
                                    <strong>Available</strong>
                                </div>
                            </div>

                            <a href="<?php echo BASE_URL; ?>tour_package.php?package_id=<?php echo (int)($package['id'] ?? 0); ?>" class="btn btn-brand w-100 mt-3">
                                View Details / Book
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>