<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/about_content.php';

$page_title = 'About Us';
$about = grm_about_page_data();
$stories = grm_about_histories();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.about-modern-page {
    --about-ink: #162033;
    --about-muted: #667085;
    --about-gold: #c9983f;
    --about-gold-dark: #9c6c18;
    --about-navy: #13233f;
    --about-surface: rgba(255, 255, 255, .92);
    background:
        radial-gradient(circle at 8% 4%, rgba(201, 152, 63, .15), transparent 25%),
        radial-gradient(circle at 94% 34%, rgba(19, 35, 63, .09), transparent 22%),
        #f7f3ed;
    color: var(--about-ink);
}

.about-modern-page .about-shell {
    border-radius: 34px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(22, 32, 51, .14);
}

.about-modern-page .about-hero-modern {
    position: relative;
    min-height: 620px;
    display: flex;
    align-items: end;
    background:
        linear-gradient(90deg, rgba(10, 20, 38, .90) 0%, rgba(10, 20, 38, .68) 48%, rgba(10, 20, 38, .22) 100%),
        url('<?php echo e($about['hero_image']); ?>') center/cover no-repeat;
}

.about-modern-page .about-hero-modern::after {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 34%;
    background: linear-gradient(transparent, rgba(8, 16, 31, .78));
    pointer-events: none;
}

.about-modern-page .about-hero-content-modern {
    position: relative;
    z-index: 1;
    max-width: 760px;
    padding: 72px;
    color: #fff;
}

.about-modern-page .about-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 9px 15px;
    border: 1px solid rgba(255,255,255,.24);
    border-radius: 999px;
    background: rgba(255,255,255,.10);
    backdrop-filter: blur(10px);
    font-size: .84rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.about-modern-page .about-eyebrow i {
    color: #f6c969;
}

.about-modern-page .about-hero-title-modern {
    max-width: 700px;
    margin: 22px 0 18px;
    color: #fff;
    font-size: clamp(2.35rem, 5.5vw, 4.5rem);
    line-height: 1.04;
    font-weight: 850;
    letter-spacing: -.04em;
}

.about-modern-page .about-hero-copy-modern {
    max-width: 680px;
    color: rgba(255,255,255,.82);
    font-size: 1.08rem;
    line-height: 1.85;
}

.about-modern-page .about-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 30px;
}

.about-modern-page .about-glass-btn {
    color: #fff;
    border: 1px solid rgba(255,255,255,.28);
    background: rgba(255,255,255,.10);
    backdrop-filter: blur(10px);
}

.about-modern-page .about-glass-btn:hover {
    color: #fff;
    background: rgba(255,255,255,.18);
}

.about-modern-page .about-stat-strip {
    position: relative;
    z-index: 2;
    margin: -54px auto 0;
    max-width: calc(100% - 72px);
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: rgba(255,255,255,.94);
    border: 1px solid rgba(255,255,255,.75);
    border-radius: 26px;
    box-shadow: 0 24px 50px rgba(22, 32, 51, .12);
    backdrop-filter: blur(16px);
}

.about-modern-page .about-stat-item {
    padding: 24px 28px;
}

.about-modern-page .about-stat-item + .about-stat-item {
    border-left: 1px solid rgba(22, 32, 51, .09);
}

.about-modern-page .about-stat-item small {
    display: block;
    color: var(--about-muted);
    font-weight: 700;
    margin-bottom: 7px;
}

.about-modern-page .about-stat-item strong {
    color: var(--about-ink);
    font-size: 1.05rem;
}

.about-modern-page .about-section-space {
    padding: 88px 0;
}

.about-modern-page .about-section-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--about-gold-dark);
    font-size: .8rem;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.about-modern-page .about-section-label::before {
    content: '';
    width: 28px;
    height: 2px;
    background: var(--about-gold);
}

.about-modern-page .about-heading-modern {
    margin: 12px 0 16px;
    color: var(--about-ink);
    font-size: clamp(1.9rem, 4vw, 3rem);
    line-height: 1.15;
    font-weight: 850;
    letter-spacing: -.03em;
}

.about-modern-page .about-copy-modern {
    color: var(--about-muted);
    line-height: 1.9;
}

.about-modern-page .about-story-panel {
    position: relative;
    overflow: hidden;
    min-height: 100%;
    padding: 38px;
    border-radius: 30px;
    background: linear-gradient(145deg, var(--about-navy), #243f6d);
    color: #fff;
    box-shadow: 0 24px 54px rgba(19, 35, 63, .22);
}

.about-modern-page .about-story-panel::after {
    content: 'GRM';
    position: absolute;
    right: -22px;
    bottom: -35px;
    color: rgba(255,255,255,.045);
    font-size: 8rem;
    line-height: 1;
    font-weight: 900;
    letter-spacing: -.08em;
}

.about-modern-page .about-story-panel i {
    display: inline-flex;
    width: 58px;
    height: 58px;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: rgba(255,255,255,.10);
    color: #f6c969;
    font-size: 1.5rem;
}

.about-modern-page .about-story-panel p {
    position: relative;
    z-index: 1;
    margin: 24px 0 0;
    color: rgba(255,255,255,.82);
    font-size: 1.05rem;
    line-height: 1.9;
}

.about-modern-page .about-timeline-modern {
    position: relative;
    display: grid;
    gap: 18px;
    margin-top: 34px;
}

.about-modern-page .about-timeline-modern::before {
    content: '';
    position: absolute;
    left: 31px;
    top: 34px;
    bottom: 34px;
    width: 2px;
    background: linear-gradient(var(--about-gold), rgba(201,152,63,.12));
}

.about-modern-page .about-timeline-entry {
    position: relative;
    display: grid;
    grid-template-columns: 64px 1fr;
    gap: 20px;
    align-items: start;
}

.about-modern-page .about-timeline-dot {
    position: relative;
    z-index: 1;
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 7px solid #f7f3ed;
    border-radius: 50%;
    background: linear-gradient(135deg, #d7aa58, #a86f16);
    color: #fff;
    font-size: .77rem;
    font-weight: 850;
    text-align: center;
    box-shadow: 0 12px 24px rgba(168, 111, 22, .22);
}

.about-modern-page .about-timeline-card {
    padding: 24px 26px;
    border: 1px solid rgba(22,32,51,.08);
    border-radius: 24px;
    background: var(--about-surface);
    box-shadow: 0 16px 38px rgba(22,32,51,.07);
}

.about-modern-page .about-timeline-card h5 {
    margin-bottom: 8px;
    color: var(--about-ink);
    font-weight: 800;
}

.about-modern-page .about-timeline-card p {
    margin: 0;
    color: var(--about-muted);
    line-height: 1.75;
}

.about-modern-page .about-feature-card,
.about-modern-page .about-value-modern,
.about-modern-page .about-memory-card {
    height: 100%;
    border: 1px solid rgba(22,32,51,.08);
    border-radius: 26px;
    background: var(--about-surface);
    box-shadow: 0 18px 45px rgba(22,32,51,.07);
    transition: transform .22s ease, box-shadow .22s ease;
}

.about-modern-page .about-feature-card:hover,
.about-modern-page .about-value-modern:hover,
.about-modern-page .about-memory-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 55px rgba(22,32,51,.11);
}

.about-modern-page .about-feature-card {
    padding: 26px;
}

.about-modern-page .about-feature-icon {
    width: 58px;
    height: 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(201,152,63,.20), rgba(19,35,63,.08));
    color: var(--about-gold-dark);
    font-size: 1.45rem;
    margin-bottom: 20px;
}

.about-modern-page .about-feature-card h5,
.about-modern-page .about-value-modern h5,
.about-modern-page .about-memory-card h4 {
    color: var(--about-ink);
    font-weight: 820;
}

.about-modern-page .about-feature-card p,
.about-modern-page .about-value-modern p,
.about-modern-page .about-memory-card p {
    color: var(--about-muted);
    line-height: 1.75;
}

.about-modern-page .about-memory-card {
    overflow: hidden;
}

.about-modern-page .about-memory-image-wrap {
    position: relative;
    overflow: hidden;
}

.about-modern-page .about-memory-image {
    width: 100%;
    height: 245px;
    object-fit: cover;
    transition: transform .35s ease;
}

.about-modern-page .about-memory-card:hover .about-memory-image {
    transform: scale(1.04);
}

.about-modern-page .about-memory-year {
    position: absolute;
    top: 16px;
    right: 16px;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(13,25,46,.80);
    color: #fff;
    font-size: .78rem;
    font-weight: 800;
    backdrop-filter: blur(8px);
}

.about-modern-page .about-memory-body {
    padding: 24px;
}

.about-modern-page .about-memory-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
    color: var(--about-gold-dark);
    font-size: .82rem;
    font-weight: 750;
}

.about-modern-page .about-value-modern {
    padding: 26px;
    border-top: 4px solid rgba(201,152,63,.65);
}

.about-modern-page .about-value-number {
    display: block;
    margin-bottom: 18px;
    color: rgba(201,152,63,.75);
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
}

.about-modern-page .about-cta-modern {
    position: relative;
    overflow: hidden;
    padding: 54px 36px;
    border-radius: 32px;
    background:
        radial-gradient(circle at 85% 10%, rgba(246,201,105,.20), transparent 25%),
        linear-gradient(135deg, #13233f, #1f3b68);
    color: #fff;
    box-shadow: 0 26px 60px rgba(19,35,63,.22);
}

.about-modern-page .about-cta-modern h2 {
    color: #fff;
    font-weight: 850;
}

.about-modern-page .about-cta-modern p {
    max-width: 720px;
    margin: 0 auto;
    color: rgba(255,255,255,.78);
    line-height: 1.8;
}

@media (max-width: 991.98px) {
    .about-modern-page .about-hero-modern { min-height: 560px; }
    .about-modern-page .about-hero-content-modern { padding: 52px 34px 84px; }
    .about-modern-page .about-stat-strip { max-width: calc(100% - 30px); }
}

@media (max-width: 767.98px) {
    .about-modern-page .about-shell { border-radius: 24px; }
    .about-modern-page .about-hero-modern {
        min-height: 610px;
        background-position: 62% center;
    }
    .about-modern-page .about-hero-content-modern { padding: 42px 24px 98px; }
    .about-modern-page .about-stat-strip {
        grid-template-columns: 1fr;
        margin-top: -70px;
    }
    .about-modern-page .about-stat-item + .about-stat-item {
        border-left: 0;
        border-top: 1px solid rgba(22,32,51,.09);
    }
    .about-modern-page .about-section-space { padding: 68px 0; }
    .about-modern-page .about-timeline-entry { grid-template-columns: 52px 1fr; gap: 14px; }
    .about-modern-page .about-timeline-modern::before { left: 25px; }
    .about-modern-page .about-timeline-dot {
        width: 52px;
        height: 52px;
        border-width: 5px;
        font-size: .68rem;
    }
}
</style>

<main class="about-modern-page pb-5">
    <section class="pt-4 pt-lg-5">
        <div class="container">
            <div class="about-shell">
                <div class="about-hero-modern">
                    <div class="about-hero-content-modern">
                        <span class="about-eyebrow"><i class="bi bi-compass"></i><?php echo e($about['hero_badge']); ?></span>
                        <h1 class="about-hero-title-modern"><?php echo e($about['hero_title']); ?></h1>
                        <p class="about-hero-copy-modern"><?php echo e($about['hero_text']); ?></p>
                        <div class="about-hero-actions">
                            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand px-4">Find a Bus</a>
                            <a href="<?php echo BASE_URL; ?>tours.php" class="btn about-glass-btn px-4">Explore Tours</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="about-stat-strip">
                <?php foreach ($about['stats'] as $stat): ?>
                    <div class="about-stat-item">
                        <small><?php echo e($stat['label']); ?></small>
                        <strong><?php echo e($stat['value']); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section-space">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-7">
                    <span class="about-section-label">Who we are</span>
                    <h2 class="about-heading-modern"><?php echo e($about['intro_title']); ?></h2>
                    <p class="about-copy-modern mb-0"><?php echo e($about['intro_text']); ?></p>
                </div>
                <div class="col-lg-5">
                    <div class="about-story-panel">
                        <i class="bi bi-stars"></i>
                        <p>We began by helping travelers face to face. Our digital platform keeps that same human trust while making every journey easier to search, book, pay for, and remember.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="row align-items-end g-3 mb-4">
                <div class="col-lg-8">
                    <span class="about-section-label">Our journey</span>
                    <h2 class="about-heading-modern mb-0">Growing from a travel counter into a connected platform</h2>
                </div>
                <div class="col-lg-4">
                    <p class="about-copy-modern mb-0">Every stage taught us what travelers need: reliable information, clear choices, and support they can trust.</p>
                </div>
            </div>

            <div class="about-timeline-modern">
                <?php foreach ($about['timeline'] as $item): ?>
                    <div class="about-timeline-entry">
                        <div class="about-timeline-dot"><?php echo e($item['year']); ?></div>
                        <div class="about-timeline-card">
                            <h5><?php echo e($item['title']); ?></h5>
                            <p><?php echo e($item['text']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section-space">
        <div class="container">
            <div class="text-center mx-auto mb-5" style="max-width: 780px;">
                <span class="about-section-label">Digital direction</span>
                <h2 class="about-heading-modern"><?php echo e($about['web_shift_title']); ?></h2>
                <p class="about-copy-modern mb-0">Our move online is based on real customer behavior and the need for a faster, safer, and more organized travel experience.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($about['web_shift_reasons'] as $reason): ?>
                    <div class="col-lg-3 col-md-6">
                        <article class="about-feature-card">
                            <span class="about-feature-icon"><i class="bi <?php echo e($reason['icon']); ?>"></i></span>
                            <h5><?php echo e($reason['title']); ?></h5>
                            <p class="mb-0"><?php echo e($reason['text']); ?></p>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                <div>
                    <span class="about-section-label">Travel memories</span>
                    <h2 class="about-heading-modern mb-0">Journeys that shaped our service</h2>
                </div>
                <p class="about-copy-modern mb-0" style="max-width: 480px;">Real trips gave us practical lessons about comfort, timing, destination stories, and what makes travel memorable.</p>
            </div>

            <div class="row g-4">
                <?php foreach ($stories as $story): ?>
                    <div class="col-lg-4 col-md-6">
                        <article class="about-memory-card">
                            <div class="about-memory-image-wrap">
                                <img src="<?php echo e($story['cover_image']); ?>" alt="<?php echo e($story['title']); ?>" class="about-memory-image">
                                <span class="about-memory-year"><?php echo e($story['year']); ?></span>
                            </div>
                            <div class="about-memory-body">
                                <div class="about-memory-meta">
                                    <span><i class="bi bi-geo-alt"></i> <?php echo e($story['location']); ?></span>
                                    <span><i class="bi bi-clock"></i> <?php echo e($story['duration']); ?></span>
                                </div>
                                <h4><?php echo e($story['title']); ?></h4>
                                <p><?php echo e($story['excerpt']); ?></p>
                                <a href="<?php echo BASE_URL; ?>about_story.php?story=<?php echo urlencode($story['slug']); ?>" class="btn btn-nav-soft btn-sm">Read the Story <i class="bi bi-arrow-right ms-1"></i></a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="about-section-space pt-4">
        <div class="container">
            <div class="text-center mb-5">
                <span class="about-section-label">Our values</span>
                <h2 class="about-heading-modern mb-0">What guides every booking and journey</h2>
            </div>
            <div class="row g-4">
                <?php foreach ($about['values'] as $index => $value): ?>
                    <div class="col-lg-3 col-md-6">
                        <article class="about-value-modern">
                            <span class="about-value-number">0<?php echo e($index + 1); ?></span>
                            <h5><?php echo e($value['title']); ?></h5>
                            <p class="mb-0"><?php echo e($value['text']); ?></p>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="pb-5">
        <div class="container">
            <div class="about-cta-modern text-center">
                <span class="about-eyebrow mb-3"><i class="bi bi-map"></i>Plan your next trip</span>
                <h2 class="mb-3">Travel Myanmar with clearer choices and less stress</h2>
                <p>Search bus routes, discover curated tours, submit mobile payment proof, and keep your booking history in one convenient place.</p>
                <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand px-4">Search Bus Routes</a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn about-glass-btn px-4">Browse Tour Packages</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
