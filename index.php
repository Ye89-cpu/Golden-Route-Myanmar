<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/event_helper.php';
require_once __DIR__ . '/includes/company_showcase_helper.php';

$page_title = 'Home - Golden Route Myanmar';

$conn = getDBConnection();

$stats = [
    'open_trips' => 0,
    'active_tours' => 0,
    'approved_companies' => 0,
    'tickets_issued' => 0,
];

$sliderEvents = [];
$featuredBusCompanies = [];

/*
    FIX FOR COMPANY LOGO DISPLAY

    Problem:
    Some company logo paths in database are like:
    assets/company_logos/company-name.svg

    But generated logo files are inside:
    uploads/company_logos/company-name-id.svg

    This helper first checks database logo path.
    If file does not exist, it tries the generated upload logo path.
*/

function home_safe_logo_file_name(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    $name = trim($name, '-');

    return $name !== '' ? $name : 'company';
}

function home_company_logo_url(array $company): string
{
    $companyId = (int)($company['id'] ?? 0);
    $companyName = (string)($company['name'] ?? 'company');
    $dbLogoPath = trim((string)($company['logo'] ?? ''));

    // 1. Try database logo path first.
    if ($dbLogoPath !== '') {
        $cleanPath = ltrim($dbLogoPath, '/');
        $fullPath = __DIR__ . '/' . $cleanPath;

        if (is_file($fullPath)) {
            return BASE_URL . $cleanPath;
        }
    }

    // 2. Try generated upload logo path.
    if ($companyId > 0) {
        $generatedFile = home_safe_logo_file_name($companyName) . '-' . $companyId . '.svg';
        $generatedPath = 'uploads/company_logos/' . $generatedFile;
        $generatedFullPath = __DIR__ . '/' . $generatedPath;

        if (is_file($generatedFullPath)) {
            return BASE_URL . $generatedPath;
        }
    }

    // 3. No valid logo found.
    return '';
}

try {
    $queries = [
        'open_trips' => "SELECT COUNT(*) AS total FROM trips WHERE status = 'open' AND trip_date >= CURDATE()",
        'active_tours' => "SELECT COUNT(*) AS total FROM tour_packages WHERE status = 'active'",
        'approved_companies' => "SELECT COUNT(*) AS total FROM companies WHERE status = 'approved'",
        'tickets_issued' => "SELECT COUNT(*) AS total FROM tickets",
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stats[$key] = (int) ($row['total'] ?? 0);
            $stmt->close();
        }
    }

    ensure_events_table_exists($conn);
    $sliderEvents = get_slider_events($conn, 5);
    $featuredBusCompanies = fetch_featured_bus_companies($conn, 9);
} catch (Throwable $e) {
    $sliderEvents = [];
    $featuredBusCompanies = [];
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-section">
    <div class="container">
        <div class="hero-card">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="section-kicker">Fast • Secure • Organized</span>
                    <h1 class="hero-title">Book bus tickets and tour packages across Myanmar</h1>
                    <p class="hero-text">
                        Search routes, choose seats, submit payment proof, receive QR tickets,
                        and manage bookings in one clean platform designed for customers and admins.
                    </p>

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand btn-lg">Find Bus Now</a>
                        <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft btn-lg">Explore Tour Packages</a>
                    </div>

                    <div class="home-highlight-grid mt-4">
                        <div class="feature-chip">QR ticket validation</div>
                        <div class="feature-chip">PDF voucher download</div>
                        <div class="feature-chip">Payment review workflow</div>
                        <div class="feature-chip">Role-based admin access</div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-side-panel">
                        <div class="route-showcase">
                            <div class="route-stop">
                                <small>Popular route</small>
                                <strong>Yangon</strong>
                            </div>
                            <div class="route-divider">→</div>
                            <div class="route-stop text-end">
                                <small>Destination</small>
                                <strong>Mandalay</strong>
                            </div>
                        </div>

                        <div class="route-meta-grid mt-4">
                            <div class="route-meta-card">
                                <span>Trips Open</span>
                                <strong><?php echo number_format($stats['open_trips']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Tour Packages</span>
                                <strong><?php echo number_format($stats['active_tours']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Approved Companies</span>
                                <strong><?php echo number_format($stats['approved_companies']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Tickets Issued</span>
                                <strong><?php echo number_format($stats['tickets_issued']); ?></strong>
                            </div>
                        </div>

                        <div class="hero-note mt-4">
                            Designed for customers, bus companies, tour operators and super admin control.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="promo-section py-5">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <span class="section-kicker">Events & Promotions</span>
            <h2 class="page-title mb-2">Latest offers, seasonal trips and travel events</h2>
            <p class="page-subtitle mx-auto">
                Use this slider to advertise promotions, public holidays, special bus discounts and tour campaigns.
            </p>
        </div>

        <div class="promo-shell">
            <?php if (!empty($sliderEvents)): ?>
                <div id="homePromoCarousel" class="carousel slide promo-carousel" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <?php foreach ($sliderEvents as $index => $event): ?>
                            <button
                                type="button"
                                data-bs-target="#homePromoCarousel"
                                data-bs-slide-to="<?php echo $index; ?>"
                                class="<?php echo $index === 0 ? 'active' : ''; ?>"
                                <?php echo $index === 0 ? 'aria-current="true"' : ''; ?>
                                aria-label="Slide <?php echo $index + 1; ?>">
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="carousel-inner">
                        <?php foreach ($sliderEvents as $index => $event): ?>
                            <?php
                                $eventImage = event_public_image($event['image_path'] ?? null, $event['event_type'] ?? null);
                                $eventTitle = htmlspecialchars($event['title'] ?? 'Travel Event');
                                $eventType = htmlspecialchars($event['event_type'] ?? 'Travel Event');
                                $eventDescription = htmlspecialchars($event['description'] ?? 'Discover new travel opportunities, seasonal promotions and limited-time offers.');
                                $eventDate = !empty($event['event_date']) ? htmlspecialchars($event['event_date']) : '';
                                $eventLocation = !empty($event['location']) ? htmlspecialchars($event['location']) : '';
                            ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <img src="<?php echo $eventImage; ?>" alt="<?php echo $eventTitle; ?>">
                                <div class="promo-caption">
                                    <div class="promo-content">
                                        <span class="promo-tag"><?php echo $eventType; ?></span>
                                        <h2><?php echo $eventTitle; ?></h2>
                                        <p><?php echo $eventDescription; ?></p>

                                        <?php if ($eventDate || $eventLocation): ?>
                                            <div class="d-flex flex-wrap gap-3 mb-3 text-white small fw-semibold">
                                                <?php if ($eventDate): ?>
                                                    <span><i class="bi bi-calendar-event"></i> <?php echo $eventDate; ?></span>
                                                <?php endif; ?>

                                                <?php if ($eventLocation): ?>
                                                    <span><i class="bi bi-geo-alt"></i> <?php echo $eventLocation; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <a href="<?php echo BASE_URL; ?>events.php#event-<?php echo (int) ($event['id'] ?? 0); ?>" class="btn btn-brand">View Events</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($sliderEvents) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#homePromoCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>

                        <button class="carousel-control-next" type="button" data-bs-target="#homePromoCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div id="homePromoCarousel" class="carousel slide promo-carousel" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#homePromoCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#homePromoCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#homePromoCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>

                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="<?php echo BASE_URL; ?>assets/images/thin.jpg" alt="Thingyan promotion">
                            <div class="promo-caption">
                                <div class="promo-content">
                                    <span class="promo-tag">Thingyan Travel Event</span>
                                    <h2>Special New Year travel promotion</h2>
                                    <p>
                                        Feature festival travel routes, holiday demand and limited-time discounts for customers.
                                    </p>
                                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand">Check Routes</a>
                                </div>
                            </div>
                        </div>

                        <div class="carousel-item">
                            <img src="<?php echo BASE_URL; ?>assets/images/tourh.png" alt="Tour package promotion">
                            <div class="promo-caption">
                                <div class="promo-content">
                                    <span class="promo-tag">Top Tour Package</span>
                                    <h2>Explore Bagan, Inle and beach destinations</h2>
                                    <p>
                                        Promote best-selling tour packages with stronger visuals, call-to-action buttons and travel highlights.
                                    </p>
                                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-brand">Explore Tours</a>
                                </div>
                            </div>
                        </div>

                        <div class="carousel-item">
                            <img src="<?php echo BASE_URL; ?>assets/images/QR.png" alt="Booking platform promotion">
                            <div class="promo-caption">
                                <div class="promo-content">
                                    <span class="promo-tag">Platform Features</span>
                                    <h2>Fast booking, QR tickets and easier payment review</h2>
                                    <p>
                                        Show the strongest points of the platform in a more modern and premium way.
                                    </p>
                                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-brand">Create Account</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="carousel-control-prev" type="button" data-bs-target="#homePromoCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>

                    <button class="carousel-control-next" type="button" data-bs-target="#homePromoCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="py-4">
    <div class="container">
        <div class="quick-stat-strip">
            <div class="quick-stat-box">
                <strong><?php echo number_format($stats['open_trips']); ?></strong>
                <span>Open Trips</span>
            </div>

            <div class="quick-stat-box">
                <strong><?php echo number_format($stats['active_tours']); ?></strong>
                <span>Tour Packages</span>
            </div>

            <div class="quick-stat-box">
                <strong><?php echo number_format($stats['approved_companies']); ?></strong>
                <span>Approved Companies</span>
            </div>

            <div class="quick-stat-box">
                <strong><?php echo number_format($stats['tickets_issued']); ?></strong>
                <span>Tickets Issued</span>
            </div>
        </div>
    </div>
</section>

<section class="bus-company-showcase-section py-5">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <span class="section-kicker">Trusted Bus Partners</span>
            <h2 class="page-title mb-2">Choose from companies working with Golden Route Myanmar</h2>
        </div>

        <?php if (!empty($featuredBusCompanies)): ?>
            <div class="bus-company-showcase" data-company-slider>
                <button type="button" class="bus-showcase-nav prev" data-slider-prev aria-label="Previous company">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="bus-showcase-stage">
                    <?php $totalCompanies = count($featuredBusCompanies); ?>

                    <?php foreach ($featuredBusCompanies as $index => $company): ?>
                        <?php
                            $companyName = (string) ($company['name'] ?? 'Bus Partner');
                            $companyType = (string) ($company['company_type'] ?? 'bus_company');
                            $companyLogoUrl = home_company_logo_url($company);
                            $companyDescription = company_short_description($company['description'] ?? null);
                            $companyRoute = trim((string) ($company['highlight_route'] ?? ''));
                            $companyAddress = trim((string) ($company['address'] ?? 'Myanmar'));
                            $activeBuses = (int) ($company['active_buses'] ?? 0);
                            $activeRoutes = (int) ($company['active_routes'] ?? 0);
                            $openTrips = (int) ($company['open_trips'] ?? 0);
                            $startingPrice = (float) ($company['starting_price'] ?? 0);

                            $fallbackClass = '';

                            if ($index === 0) {
                                $fallbackClass = 'is-active';
                            } elseif ($index === 1) {
                                $fallbackClass = 'is-next';
                            } elseif ($index === $totalCompanies - 1) {
                                $fallbackClass = 'is-prev';
                            }

                            $routeFrom = '';
                            $routeTo = '';

                            if ($companyRoute !== '') {
                                $normalizedRoute = html_entity_decode($companyRoute, ENT_QUOTES, 'UTF-8');

                                if (strpos($normalizedRoute, '→') !== false) {
                                    $routeParts = array_map('trim', explode('→', $normalizedRoute, 2));
                                    $routeFrom = $routeParts[0] ?? '';
                                    $routeTo = $routeParts[1] ?? '';
                                } elseif (strpos($normalizedRoute, '->') !== false) {
                                    $routeParts = array_map('trim', explode('->', $normalizedRoute, 2));
                                    $routeFrom = $routeParts[0] ?? '';
                                    $routeTo = $routeParts[1] ?? '';
                                } elseif (strpos($normalizedRoute, '-') !== false) {
                                    $routeParts = array_map('trim', explode('-', $normalizedRoute, 2));
                                    $routeFrom = $routeParts[0] ?? '';
                                    $routeTo = $routeParts[1] ?? '';
                                }
                            }

                            $searchParams = [
                                'company_id' => (int) ($company['id'] ?? 0),
                            ];

                            if ($routeFrom !== '') {
                                $searchParams['from'] = $routeFrom;
                            }

                            if ($routeTo !== '') {
                                $searchParams['to'] = $routeTo;
                            }

                            $searchUrl = BASE_URL . 'search_bus.php?' . http_build_query($searchParams);
                        ?>

                        <article class="bus-showcase-card <?php echo $fallbackClass; ?>" data-slide-index="<?php echo $index; ?>">
                            <div class="bus-showcase-glow"></div>

                            <div class="bus-showcase-media">
                                <?php if ($companyLogoUrl !== ''): ?>
                                    <img
                                        src="<?php echo e($companyLogoUrl); ?>"
                                        alt="<?php echo e($companyName); ?> logo"
                                        style="width: 100%; height: 100%; object-fit: cover; display: block;"
                                    >
                                <?php else: ?>
                                    <div class="bus-showcase-placeholder">
                                        <?php echo e(company_initials($companyName)); ?>
                                    </div>
                                <?php endif; ?>

                                <span class="bus-showcase-type-badge">
                                    <?php echo e(company_type_label($companyType)); ?>
                                </span>

                                <span class="bus-showcase-status-badge">
                                    <?php echo e(company_status_label($company)); ?>
                                </span>
                            </div>

                            <div class="bus-showcase-body">
                                <h3><?php echo e($companyName); ?></h3>

                                <p class="bus-showcase-desc">
                                    <?php echo e($companyDescription); ?>
                                </p>

                                <div class="bus-showcase-route">
                                    <i class="bi bi-sign-turn-right-fill"></i>
                                    <span>
                                        <?php echo e($companyRoute !== '' ? $companyRoute : 'Multiple active routes available'); ?>
                                    </span>
                                </div>

                                <div class="bus-showcase-meta-grid">
                                    <div class="bus-showcase-stat">
                                        <strong><?php echo number_format($activeBuses); ?></strong>
                                        <span>Active Buses</span>
                                    </div>

                                    <div class="bus-showcase-stat">
                                        <strong><?php echo number_format($activeRoutes); ?></strong>
                                        <span>Routes</span>
                                    </div>

                                    <div class="bus-showcase-stat">
                                        <strong><?php echo number_format($openTrips); ?></strong>
                                        <span>Open Trips</span>
                                    </div>

                                    <div class="bus-showcase-stat">
                                        <strong>
                                            <?php echo $startingPrice > 0 ? 'MMK ' . number_format($startingPrice, 0) : 'Contact'; ?>
                                        </strong>
                                        <span>Starting From</span>
                                    </div>
                                </div>

                                <div class="bus-showcase-footer-row">
                                    <div class="bus-showcase-location">
                                        <i class="bi bi-geo-alt-fill"></i>
                                        <span><?php echo e($companyAddress !== '' ? $companyAddress : 'Myanmar'); ?></span>
                                    </div>

                                    <a href="<?php echo e($searchUrl); ?>" class="btn btn-brand btn-sm">
                                        Search Trips
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="bus-showcase-nav next" data-slider-next aria-label="Next company">
                    <i class="bi bi-chevron-right"></i>
                </button>

                <div class="bus-showcase-dots" data-slider-dots></div>
            </div>
        <?php else: ?>
            <div class="empty-state-card text-center">
                <h4 class="mb-2">No approved bus companies yet</h4>
                <p class="text-muted mb-0">
                    Once company logos and approved bus operators are available in the database, they will appear here automatically.
                </p>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-kicker">Core Features</span>
            <h2 class="page-title mb-2">Everything needed for booking and operations</h2>
            <p class="page-subtitle mx-auto">A smoother experience from search to payment verification and QR check-in.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="power-feature-card">
                    <div class="power-feature-icon">🚌</div>
                    <h4>Bus Ticket Search</h4>
                    <p class="mb-0">Choose route, trip date, company and seat layout with a cleaner customer flow.</p>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">💳</div>
                    <h5>Payment Submission</h5>
                    <p>Upload payment proof and track verification status from your booking history.</p>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">🎫</div>
                    <h5>QR / PDF Output</h5>
                    <p>Generate tickets and vouchers after approval for easier boarding and check-in.</p>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">📊</div>
                    <h5>Admin Dashboard</h5>
                    <p>Monitor approvals, payments, notifications, refunds, and system activity in one place.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-split-space py-5">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <div class="photo-feature-card">
                    <img src="<?php echo BASE_URL; ?>assets/images/bus.png" alt="Bus travel in Myanmar">
                    <div class="photo-overlay">
                        <div class="photo-overlay-content">
                            <span class="photo-badge">Premium Bus Routes</span>
                            <h3>Travel comfortably across Myanmar</h3>
                            <p>
                                Search routes, compare schedules, choose seats and complete booking in a cleaner, faster way.
                            </p>
                            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand">Book a Bus</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="photo-feature-card">
                    <img src="<?php echo BASE_URL; ?>assets/images/tour.png" alt="Myanmar tour destination">
                    <div class="photo-overlay">
                        <div class="photo-overlay-content">
                            <span class="photo-badge">Beautiful Tour Destinations</span>
                            <h3>Discover tours, events and local experiences</h3>
                            <p>
                                Highlight famous places, seasonal packages and guided travel experiences for tourists and families.
                            </p>
                            <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-brand">View Tours</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="cta-banner">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="section-kicker">Ready to start?</span>
                    <h3 class="mb-2">Search available trips or browse curated tour packages</h3>
                    <p class="mb-0 text-muted">Use the platform as a customer, or manage the system with admin dashboards.</p>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand me-2">Search Bus</a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft">Tours</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>