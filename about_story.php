<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/about_content.php';

$slug = trim((string)($_GET['story'] ?? ''));
$story = grm_about_story_by_slug($slug);

$page_title = $story ? ($story['title'] . ' - Travel Story') : 'Travel Story';

require_once __DIR__ . '/includes/header.php';
?>

<main class="about-page-wrap py-5">
    <div class="container">
        <?php if (!$story): ?>
            <div class="about-section-card text-center py-5">
                <span class="about-mini-kicker">Story Not Found</span>
                <h1 class="about-section-title mt-2">This travel story is not available</h1>
                <p class="about-section-subtitle mb-4">Please go back and choose another travel memory from the About Us page.</p>
                <a href="<?php echo BASE_URL; ?>about.php" class="btn btn-brand">Back to About Us</a>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <a href="<?php echo BASE_URL; ?>about.php" class="about-back-link">
                    <i class="bi bi-arrow-left"></i> Back to About Us
                </a>
            </div>

            <section class="about-section-card overflow-hidden mb-4">
                <div class="row g-0 align-items-stretch">
                    <div class="col-lg-6">
                        <img src="<?php echo e($story['cover_image']); ?>" alt="<?php echo e($story['title']); ?>" class="w-100 h-100 object-fit-cover about-detail-cover">
                    </div>
                    <div class="col-lg-6">
                        <div class="p-4 p-lg-5">
                            <span class="about-kicker">Travel History</span>
                            <h1 class="about-hero-title mt-3 mb-3"><?php echo e($story['title']); ?></h1>

                            <div class="about-story-meta mb-3">
                                <span><i class="bi bi-calendar-event"></i> <?php echo e($story['year']); ?></span>
                                <span><i class="bi bi-geo-alt"></i> <?php echo e($story['location']); ?></span>
                                <span><i class="bi bi-clock"></i> <?php echo e($story['duration']); ?></span>
                            </div>

                            <p class="about-section-text mb-4"><?php echo e($story['summary']); ?></p>

                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($story['highlights'] as $highlight): ?>
                                    <span class="about-highlight-chip"><?php echo e($highlight); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="about-section-card h-100">
                        <span class="about-mini-kicker">Story Details</span>
                        <h2 class="about-section-title mt-2">A closer look at this journey</h2>

                        <?php foreach ($story['story_paragraphs'] as $paragraph): ?>
                            <p class="about-section-text"><?php echo e($paragraph); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="about-section-card h-100">
                        <span class="about-mini-kicker">Memorable Points</span>
                        <h2 class="about-section-title mt-2">What made it special</h2>

                        <ul class="about-memory-list">
                            <?php foreach ($story['memories'] as $memory): ?>
                                <li><?php echo e($memory); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="mb-4">
                <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
                    <div>
                        <span class="about-mini-kicker">Gallery</span>
                        <h2 class="about-section-title mt-2 mb-0">Moments from the journey</h2>
                    </div>
                </div>

                <div class="row g-4">
                    <?php foreach ($story['gallery'] as $item): ?>
                        <div class="col-md-6">
                            <div class="about-gallery-card h-100">
                                <img src="<?php echo e($item['image']); ?>" alt="<?php echo e($item['caption']); ?>" class="about-gallery-image">
                                <div class="about-gallery-caption"><?php echo e($item['caption']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="about-cta-box text-center">
                <span class="about-mini-kicker">Continue Exploring</span>
                <h2 class="about-section-title mt-2">Discover more tours and destinations</h2>
                <p class="about-section-subtitle mx-auto mb-4" style="max-width: 700px;">
                    Past travel stories inspire the trips we design today. Explore current routes and tours to continue the journey.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-brand">Explore Tours</a>
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-nav-soft">Find Bus</a>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>