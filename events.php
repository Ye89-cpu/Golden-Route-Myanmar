<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/event_helper.php';

$page_title = 'Events & Promotions - Golden Route Myanmar';

function public_promotions_table_exists(mysqli $conn): bool
{
    $result = $conn->query("SHOW TABLES LIKE 'promotions'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function public_promotion_is_active(array $promotion, ?DateTime $now = null): bool
{
    $now = $now ?: new DateTime();
    if (($promotion['status'] ?? '') !== 'active') {
        return false;
    }
    if (!empty($promotion['starts_at']) && new DateTime((string)$promotion['starts_at']) > $now) {
        return false;
    }
    if (!empty($promotion['ends_at']) && new DateTime((string)$promotion['ends_at']) < $now) {
        return false;
    }
    return true;
}

function public_promotion_discount_text(array $promotion): string
{
    $value = (float)($promotion['discount_value'] ?? 0);
    if (($promotion['discount_type'] ?? 'percentage') === 'fixed') {
        return number_format($value, 0) . ' MMK OFF';
    }
    return rtrim(rtrim(number_format($value, 2), '0'), '.') . '% OFF';
}

function public_promotion_date_text(array $promotion): string
{
    $start = !empty($promotion['starts_at']) ? date('M d, Y', strtotime((string)$promotion['starts_at'])) : 'Now';
    $end = !empty($promotion['ends_at']) ? date('M d, Y', strtotime((string)$promotion['ends_at'])) : 'No expiry';
    return $start . ' – ' . $end;
}

$conn = getDBConnection();
$events = [];
$promotions = [];
$promotionTableAvailable = false;
$checkedCode = strtoupper(trim((string)($_GET['promo_code'] ?? '')));
$checkedPromotion = null;
$promoCheckType = '';
$promoCheckMessage = '';

try {
    ensure_events_table_exists($conn);
    $events = get_public_events($conn, 100);
} catch (Throwable $e) {
    $events = [];
}

try {
    $promotionTableAvailable = public_promotions_table_exists($conn);
    if ($promotionTableAvailable) {
        $promotionSql = "
            SELECT id, title, promo_code, description, discount_type, discount_value,
                   starts_at, ends_at, status, created_at, updated_at
            FROM promotions
            WHERE status = 'active'
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at IS NULL OR ends_at >= NOW())
            ORDER BY
                CASE WHEN ends_at IS NULL THEN 1 ELSE 0 END,
                ends_at ASC,
                id DESC
            LIMIT 30
        ";
        $promotionResult = $conn->query($promotionSql);
        if ($promotionResult) {
            while ($row = $promotionResult->fetch_assoc()) {
                $promotions[] = $row;
            }
            $promotionResult->free();
        }

        if ($checkedCode !== '') {
            if (strlen($checkedCode) > 50 || preg_match('/^[A-Z0-9_-]+$/', $checkedCode) !== 1) {
                $promoCheckType = 'danger';
                $promoCheckMessage = 'Please enter a valid promotion code using letters, numbers, hyphens or underscores.';
            } else {
                $stmt = $conn->prepare("SELECT id, title, promo_code, description, discount_type, discount_value, starts_at, ends_at, status FROM promotions WHERE UPPER(promo_code) = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('s', $checkedCode);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $checkedPromotion = $result ? $result->fetch_assoc() : null;
                    $stmt->close();
                }

                if (!$checkedPromotion) {
                    $promoCheckType = 'danger';
                    $promoCheckMessage = 'This promotion code was not found.';
                } elseif (($checkedPromotion['status'] ?? '') !== 'active') {
                    $promoCheckType = 'warning';
                    $promoCheckMessage = 'This promotion is currently inactive.';
                } elseif (!empty($checkedPromotion['starts_at']) && strtotime((string)$checkedPromotion['starts_at']) > time()) {
                    $promoCheckType = 'info';
                    $promoCheckMessage = 'This promotion starts on ' . date('M d, Y H:i', strtotime((string)$checkedPromotion['starts_at'])) . '.';
                } elseif (!empty($checkedPromotion['ends_at']) && strtotime((string)$checkedPromotion['ends_at']) < time()) {
                    $promoCheckType = 'danger';
                    $promoCheckMessage = 'This promotion expired on ' . date('M d, Y H:i', strtotime((string)$checkedPromotion['ends_at'])) . '.';
                } else {
                    $promoCheckType = 'success';
                    $promoCheckMessage = 'Valid code: ' . public_promotion_discount_text($checkedPromotion) . ' — ' . ($checkedPromotion['title'] ?? 'Promotion');
                }
            }
        }
    }
} catch (Throwable $e) {
    $promotionTableAvailable = false;
    $promotions = [];
}

$conn->close();
require_once __DIR__ . '/includes/header.php';
?>

<style>
.events-page { --event-ink:#182133; --event-muted:#697386; --event-brand:#f2ad00; --event-soft:#fff7dc; }
.events-hero { background:linear-gradient(130deg,#151e2f 0%,#273755 63%,#3b4f72 100%); color:#fff; border-radius:30px; padding:48px; overflow:hidden; position:relative; }
.events-hero::before,.events-hero::after { content:""; position:absolute; border-radius:50%; background:rgba(255,193,7,.14); }
.events-hero::before { width:260px;height:260px;right:-75px;top:-110px; }
.events-hero::after { width:130px;height:130px;right:175px;bottom:-78px; }
.events-kicker { display:inline-flex; align-items:center; gap:8px; color:#ffd873; font-weight:800; text-transform:uppercase; letter-spacing:.12em; font-size:.76rem; }
.promo-checker { background:#fff; border:1px solid rgba(255,255,255,.5); border-radius:22px; padding:20px; box-shadow:0 18px 45px rgba(0,0,0,.18); }
.promo-checker .form-control { min-height:50px; border-radius:13px 0 0 13px; text-transform:uppercase; font-weight:800; letter-spacing:.08em; }
.promo-checker .btn { border-radius:0 13px 13px 0; font-weight:800; }
.section-heading h2 { color:var(--event-ink); font-weight:850; }
.promotion-card { background:#fff; border:1px solid #eceff4; border-radius:24px; padding:24px; height:100%; position:relative; overflow:hidden; box-shadow:0 14px 38px rgba(24,33,51,.07); transition:.22s ease; }
.promotion-card:hover { transform:translateY(-4px); box-shadow:0 20px 48px rgba(24,33,51,.12); }
.promotion-card::after { content:"%"; position:absolute; right:-12px; top:-40px; font-size:150px; line-height:1; font-weight:900; color:rgba(242,173,0,.075); }
.promo-value { font-size:2rem; font-weight:900; color:#c47e00; }
.promo-code-box { display:flex; align-items:center; justify-content:space-between; gap:12px; background:var(--event-soft); border:1px dashed #e3a400; border-radius:15px; padding:12px 14px; position:relative; z-index:1; }
.promo-code { font-weight:900; letter-spacing:.1em; color:#5a3c00; overflow-wrap:anywhere; }
.event-filter-bar { display:flex; flex-wrap:wrap; gap:9px; }
.event-filter-btn { border:1px solid #e2e6ed; background:#fff; color:#566074; border-radius:999px; padding:9px 16px; font-weight:700; }
.event-filter-btn.active,.event-filter-btn:hover { background:#202c43; border-color:#202c43; color:#fff; }
.public-event-card { border:1px solid #e9edf3; border-radius:24px; overflow:hidden; background:#fff; height:100%; box-shadow:0 12px 34px rgba(24,33,51,.065); transition:.22s ease; }
.public-event-card:hover { transform:translateY(-4px); box-shadow:0 18px 44px rgba(24,33,51,.11); }
.public-event-image { height:225px; width:100%; object-fit:cover; }
.public-event-body { padding:22px; }
.public-event-meta { display:flex; flex-wrap:wrap; gap:8px; color:var(--event-muted); font-size:.84rem; }
.empty-events { border:1px dashed #ccd3df; border-radius:24px; padding:45px 24px; text-align:center; background:#fbfcfe; }
.copy-feedback { position:fixed; right:24px; bottom:24px; z-index:1080; background:#172033; color:#fff; border-radius:14px; padding:12px 17px; box-shadow:0 12px 35px rgba(0,0,0,.2); opacity:0; transform:translateY(12px); pointer-events:none; transition:.2s ease; }
.copy-feedback.show { opacity:1; transform:translateY(0); }
@media(max-width:767.98px){ .events-hero{padding:30px 22px}.promo-checker .input-group{display:block}.promo-checker .form-control,.promo-checker .btn{width:100%;border-radius:13px!important}.promo-checker .btn{margin-top:10px}.public-event-image{height:205px} }
</style>

<div class="container py-5 events-page">
    <section class="events-hero mb-5">
        <div class="row align-items-center g-4 position-relative" style="z-index:1;">
            <div class="col-lg-7">
                <span class="events-kicker"><i class="bi bi-stars"></i>Golden Route Myanmar</span>
                <h1 class="display-5 fw-bold mt-3 mb-3">Events, offers and travel inspiration</h1>
                <p class="lead text-white-50 mb-0">Discover current campaigns, verify real promotion codes and explore public travel events published by the admin team.</p>
            </div>
            <div class="col-lg-5" id="promo-checker">
                <div class="promo-checker">
                    <div class="d-flex align-items-center gap-2 mb-2 text-dark"><i class="bi bi-patch-check-fill text-warning fs-4"></i><strong>Check a promotion code</strong></div>
                    <p class="text-muted small mb-3">The code is checked against status, start time and expiry time in the database.</p>
                    <form method="GET" action="<?php echo BASE_URL; ?>events.php#promo-checker">
                        <div class="input-group">
                            <input type="text" name="promo_code" class="form-control" value="<?php echo e($checkedCode); ?>" maxlength="50" placeholder="ENTER CODE" aria-label="Promotion code">
                            <button class="btn btn-warning px-4" type="submit">Check code</button>
                        </div>
                    </form>
                    <?php if ($promoCheckMessage !== ''): ?>
                        <div class="alert alert-<?php echo e($promoCheckType); ?> mt-3 mb-0 rounded-3 py-2 small">
                            <i class="bi bi-<?php echo $promoCheckType === 'success' ? 'check-circle-fill' : ($promoCheckType === 'danger' ? 'x-circle-fill' : 'info-circle-fill'); ?> me-1"></i><?php echo e($promoCheckMessage); ?>
                            <?php if ($promoCheckType === 'success' && !empty($checkedPromotion['promo_code'])): ?>
                                <button type="button" class="btn btn-sm btn-link p-0 ms-1 fw-bold copy-promo" data-code="<?php echo e($checkedPromotion['promo_code']); ?>">Copy</button>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!$promotionTableAvailable): ?>
                        <div class="alert alert-warning mt-3 mb-0 rounded-3 py-2 small">Promotion data is not available in the current database.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4 section-heading">
            <div><span class="text-warning fw-bold text-uppercase small">Live database offers</span><h2 class="mb-1">Active promotions</h2><p class="text-muted mb-0">Only active promotions inside their valid date range are shown.</p></div>
            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-dark rounded-pill px-4"><i class="bi bi-search me-1"></i>Find a bus</a>
        </div>

        <?php if (empty($promotions)): ?>
            <div class="empty-events"><i class="bi bi-percent fs-1 text-warning"></i><h4 class="mt-3">No active promotion codes</h4><p class="text-muted mb-0">New valid offers will appear here automatically when they are activated.</p></div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($promotions as $promotion): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="promotion-card">
                            <div class="d-flex justify-content-between align-items-start gap-3 position-relative" style="z-index:1;">
                                <span class="badge rounded-pill text-bg-success"><i class="bi bi-lightning-charge-fill me-1"></i>Active now</span>
                                <span class="promo-value"><?php echo e(public_promotion_discount_text($promotion)); ?></span>
                            </div>
                            <h3 class="h5 fw-bold mt-4 mb-2 position-relative" style="z-index:1;"><?php echo e($promotion['title']); ?></h3>
                            <p class="text-muted position-relative" style="z-index:1;"><?php echo e(trim((string)$promotion['description']) !== '' ? $promotion['description'] : 'Use this offer during its valid promotion period.'); ?></p>
                            <div class="small text-muted mb-3 position-relative" style="z-index:1;"><i class="bi bi-calendar3 me-1"></i><?php echo e(public_promotion_date_text($promotion)); ?></div>
                            <?php if (!empty($promotion['promo_code'])): ?>
                                <div class="promo-code-box"><span class="promo-code"><?php echo e($promotion['promo_code']); ?></span><button type="button" class="btn btn-sm btn-warning copy-promo" data-code="<?php echo e($promotion['promo_code']); ?>"><i class="bi bi-copy me-1"></i>Copy</button></div>
                            <?php else: ?>
                                <div class="promo-code-box"><span class="promo-code">AUTOMATIC OFFER</span><span class="badge text-bg-warning">No code</span></div>
                            <?php endif; ?>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4 section-heading">
            <div><span class="text-warning fw-bold text-uppercase small">What's happening</span><h2 class="mb-1">Events and announcements</h2><p class="text-muted mb-0">Filter active public content by category.</p></div>
            <div class="event-filter-bar" id="eventFilters"><button type="button" class="event-filter-btn active" data-filter="all">All</button><button type="button" class="event-filter-btn" data-filter="Promotion">Promotions</button><button type="button" class="event-filter-btn" data-filter="Bus Event">Bus</button><button type="button" class="event-filter-btn" data-filter="Tour Event">Tours</button><button type="button" class="event-filter-btn" data-filter="Festival">Festivals</button><button type="button" class="event-filter-btn" data-filter="Announcement">Announcements</button></div>
        </div>

        <?php if (empty($events)): ?>
            <div class="empty-events"><i class="bi bi-calendar2-event fs-1 text-warning"></i><h4 class="mt-3">No active events right now</h4><p class="text-muted mb-0">Published events will appear here automatically.</p></div>
        <?php else: ?>
            <div class="row g-4" id="eventGrid">
                <?php foreach ($events as $event): ?>
                    <?php
                    $eventTitle = $event['title'] ?? 'Travel Event';
                    $eventType = $event['event_type'] ?? 'Promotion';
                    $eventDescription = trim((string)($event['description'] ?? ''));
                    $eventDate = trim((string)($event['event_date'] ?? ''));
                    $eventLocation = trim((string)($event['location'] ?? ''));
                    ?>
                    <div class="col-md-6 col-xl-4 public-event-item" data-event-type="<?php echo e($eventType); ?>" id="event-<?php echo (int)$event['id']; ?>">
                        <article class="public-event-card">
                            <img src="<?php echo e(event_public_image($event['image_path'] ?? null, $eventType)); ?>" alt="<?php echo e($eventTitle); ?>" class="public-event-image" loading="lazy">
                            <div class="public-event-body">
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><span class="badge rounded-pill text-bg-warning"><?php echo e($eventType); ?></span><?php if ($eventDate !== ''): ?><span class="small text-muted"><?php echo e(date('M d, Y', strtotime($eventDate))); ?></span><?php endif; ?></div>
                                <h3 class="h5 fw-bold mb-2"><?php echo e($eventTitle); ?></h3>
                                <div class="public-event-meta mb-3"><?php if ($eventLocation !== ''): ?><span><i class="bi bi-geo-alt me-1"></i><?php echo e($eventLocation); ?></span><?php endif; ?><span><i class="bi bi-megaphone me-1"></i>Public event</span></div>
                                <p class="text-muted mb-0"><?php echo e($eventDescription !== '' ? $eventDescription : 'More details will be announced soon.'); ?></p>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="empty-events d-none" id="noFilteredEvents"><i class="bi bi-funnel fs-1 text-muted"></i><h4 class="mt-3">No events in this category</h4><p class="text-muted mb-0">Choose another filter to view available events.</p></div>
        <?php endif; ?>
    </section>
</div>

<div class="copy-feedback" id="copyFeedback"><i class="bi bi-check-circle-fill text-warning me-2"></i>Promotion code copied</div>

<script>
(function () {
    const feedback = document.getElementById('copyFeedback');
    function showFeedback(message) {
        if (!feedback) return;
        feedback.lastChild.textContent = message;
        feedback.classList.add('show');
        window.setTimeout(() => feedback.classList.remove('show'), 1800);
    }
    function fallbackCopy(text) {
        const area = document.createElement('textarea');
        area.value = text;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const copied = document.execCommand('copy');
        document.body.removeChild(area);
        return copied;
    }
    document.querySelectorAll('.copy-promo').forEach((button) => {
        button.addEventListener('click', async function () {
            const code = this.dataset.code || '';
            if (!code) return;
            try {
                if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(code);
                else if (!fallbackCopy(code)) throw new Error('Copy failed');
                showFeedback('Promotion code ' + code + ' copied');
            } catch (error) {
                showFeedback('Copy the code manually: ' + code);
            }
        });
    });

    const filters = document.querySelectorAll('.event-filter-btn');
    const items = document.querySelectorAll('.public-event-item');
    const empty = document.getElementById('noFilteredEvents');
    filters.forEach((button) => button.addEventListener('click', function () {
        filters.forEach((item) => item.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        let visible = 0;
        items.forEach((item) => {
            const matches = filter === 'all' || item.dataset.eventType === filter;
            item.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        if (empty) empty.classList.toggle('d-none', visible !== 0);
    }));
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
