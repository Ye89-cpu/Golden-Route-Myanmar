<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/about_content.php';

$page_title = 'About Us';
$about = grm_about_page_data();
$stories = grm_about_histories();

require_once __DIR__ . '/includes/header.php';
?>

<main class="about-page-wrap">
    <section class="about-hero-section py-5">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-6">
                    <span class="about-kicker"><?php echo e($about['hero_badge']); ?></span>
                    <h1 class="about-hero-title mt-3"><?php echo e($about['hero_title']); ?></h1>
                    <p class="about-hero-text mt-3"><?php echo e($about['hero_text']); ?></p>

                    <div class="row g-3 mt-4">
                        <?php foreach ($about['stats'] as $stat): ?>
                            <div class="col-sm-4">
                                <div class="about-stat-card h-100">
                                    <small><?php echo e($stat['label']); ?></small>
                                    <strong><?php echo e($stat['value']); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="about-hero-image-card">
                        <img src="<?php echo e($about['hero_image']); ?>" alt="About Golden Route Myanmar" class="img-fluid rounded-4 w-100 about-hero-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="about-section-card">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-7">
                        <span class="about-mini-kicker">Company Background</span>
                        <h2 class="about-section-title mt-2"><?php echo e($about['intro_title']); ?></h2>
                        <p class="about-section-text mb-0"><?php echo e($about['intro_text']); ?></p>
                    </div>
                    <div class="col-lg-5">
                        <div class="about-quote-box">
                            <div class="about-quote-icon"><i class="bi bi-stars"></i></div>
                            <p class="mb-0">
                                We started by serving travelers directly. Today, we are turning that same trust into a better digital experience.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-4">
                <span class="about-mini-kicker">Milestones</span>
                <h2 class="about-section-title mt-2">Our journey through the years</h2>
                <p class="about-section-subtitle">A simple timeline showing how the business grew from offline service to digital planning.</p>
            </div>

            <div class="about-timeline-wrap">
                <?php foreach ($about['timeline'] as $item): ?>
                    <div class="about-timeline-item">
                        <div class="about-timeline-year"><?php echo e($item['year']); ?></div>
                        <div class="about-timeline-content">
                            <h5><?php echo e($item['title']); ?></h5>
                            <p class="mb-0"><?php echo e($item['text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5 about-soft-section">
        <div class="container">
            <div class="text-center mb-4">
                <span class="about-mini-kicker">Digital Direction</span>
                <h2 class="about-section-title mt-2"><?php echo e($about['web_shift_title']); ?></h2>
                <p class="about-section-subtitle">The move to web was not random. It came from real business changes and customer needs.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($about['web_shift_reasons'] as $reason): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="about-reason-card h-100">
                            <div class="about-reason-icon">
                                <i class="bi <?php echo e($reason['icon']); ?>"></i>
                            </div>
                            <h5><?php echo e($reason['title']); ?></h5>
                            <p class="mb-0"><?php echo e($reason['text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                <div>
                    <span class="about-mini-kicker">Travel Memories</span>
                    <h2 class="about-section-title mt-2 mb-1">Some past tour stories we still remember</h2>
                    <p class="about-section-subtitle mb-0">Each story helps explain how our travel experience influenced the platform we are building today.</p>
                </div>
                <div class="about-inline-note">Beautiful memories, practical lessons, and the beginning of better tour design.</div>
            </div>

            <div class="row g-4">
                <?php foreach ($stories as $story): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="about-story-card h-100">
                            <div class="about-story-image-wrap">
                                <img src="<?php echo e($story['cover_image']); ?>" alt="<?php echo e($story['title']); ?>" class="about-story-image">
                                <span class="about-story-year"><?php echo e($story['year']); ?></span>
                            </div>
                            <div class="about-story-body">
                                <div class="about-story-meta mb-2">
                                    <span><i class="bi bi-geo-alt"></i> <?php echo e($story['location']); ?></span>
                                    <span><i class="bi bi-clock"></i> <?php echo e($story['duration']); ?></span>
                                </div>
                                <h4><?php echo e($story['title']); ?></h4>
                                <p><?php echo e($story['excerpt']); ?></p>
                                <a href="<?php echo BASE_URL; ?>about_story.php?story=<?php echo urlencode($story['slug']); ?>" class="btn btn-brand btn-sm">
                                    Read Story
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5 about-soft-section">
        <div class="container">
            <div class="text-center mb-4">
                <span class="about-mini-kicker">What we believe</span>
                <h2 class="about-section-title mt-2">Values behind the platform</h2>
            </div>

            <div class="row g-4">
                <?php foreach ($about['values'] as $value): ?>
                    <div class="col-lg-3 col-md-6">
                        <div class="about-value-card h-100">
                            <h5><?php echo e($value['title']); ?></h5>
                            <p class="mb-0"><?php echo e($value['text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="about-cta-box text-center">
                <span class="about-mini-kicker">Explore More</span>
                <h2 class="about-section-title mt-2">See where our story is going next</h2>
                <p class="about-section-subtitle mx-auto mb-4" style="max-width: 760px;">
                    Golden Route Myanmar is growing from a trusted local service into a more complete digital booking and travel experience.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand">Find Bus</a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft">Explore Tours</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>