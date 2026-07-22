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

    if ($dbLogoPath !== '') {
        $cleanPath = ltrim($dbLogoPath, '/');
        $fullPath = __DIR__ . '/' . $cleanPath;

        if (is_file($fullPath)) {
            return BASE_URL . $cleanPath;
        }
    }

    if ($companyId > 0) {
        $generatedFile = home_safe_logo_file_name($companyName) . '-' . $companyId . '.svg';
        $generatedPath = 'uploads/company_logos/' . $generatedFile;
        $generatedFullPath = __DIR__ . '/' . $generatedPath;

        if (is_file($generatedFullPath)) {
            return BASE_URL . $generatedPath;
        }
    }

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
            $stats[$key] = (int)($row['total'] ?? 0);
            $stmt->close();
        }
    }

    ensure_events_table_exists($conn);
    $sliderEvents = get_slider_events($conn, 5);
    $featuredBusCompanies = fetch_featured_bus_companies($conn, 6);
} catch (Throwable $e) {
    $sliderEvents = [];
    $featuredBusCompanies = [];
}

$conn->close();

$minimumTravelDate = date('Y-m-d');
$popularCities = [
    'Yangon', 'Mandalay', 'Nay Pyi Taw', 'Bagan', 'Taunggyi', 'Inle',
    'Kalaw', 'Bago', 'Mawlamyine', 'Hpa-An', 'Pyin Oo Lwin', 'Pathein',
    'Monywa', 'Magway', 'Meiktila', 'Dawei', 'Myeik', 'Myitkyina'
];

require_once __DIR__ . '/includes/header.php';
?>

<main class="home-v2">
    <section class="home-hero-v2">
        <div class="home-hero-orb home-hero-orb-one"></div>
        <div class="home-hero-orb home-hero-orb-two"></div>

        <div class="container position-relative">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="home-hero-copy">
                        <div class="home-eyebrow">
                            <span class="home-eyebrow-dot"></span>
                            Travel across Myanmar with confidence
                        </div>

                        <h1>One simple place for your <span>next journey.</span></h1>
                        <p>
                            Find trusted bus routes, choose your seat, explore curated tours,
                            and keep every booking organized from departure to destination.
                        </p>

                        <div class="home-hero-actions">
                            <a href="#quick-booking" class="btn home-primary-btn">
                                Start booking <i class="bi bi-arrow-right"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>tours.php" class="btn home-ghost-btn">
                                <i class="bi bi-compass"></i> Explore tours
                            </a>
                        </div>

                        <div class="home-trust-row">
                            <div class="home-avatar-stack" aria-hidden="true">
                                <span>Y</span><span>M</span><span>B</span>
                            </div>
                            <div>
                                <strong>Easy, secure and reliable</strong>
                                <small>Bus tickets, tours and travel support</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="home-visual-card">
                        <img src="<?php echo BASE_URL; ?>assets/images/bus.png" alt="Bus travel across Myanmar">
                        <div class="home-visual-shade"></div>

                        <div class="home-route-float">
                            <span class="home-float-icon"><i class="bi bi-geo-alt-fill"></i></span>
                            <div>
                                <small>Popular journey</small>
                                <strong>Yangon <i class="bi bi-arrow-right"></i> Mandalay</strong>
                            </div>
                        </div>

                        <div class="home-rating-float">
                            <span><i class="bi bi-shield-check"></i></span>
                            <div>
                                <strong>Trusted partners</strong>
                                <small>Verified travel companies</small>
                            </div>
                        </div>

                        <div class="home-visual-caption">
                            <span>Golden Route Myanmar</span>
                            <strong>Comfortable travel begins here.</strong>
                        </div>
                    </div>
                </div>
            </div>

            <form id="quick-booking" class="home-search-panel" action="<?php echo BASE_URL; ?>search_bus.php" method="get">
                <input type="hidden" name="search" value="1">

                <div class="home-search-heading">
                    <span class="home-search-icon"><i class="bi bi-bus-front"></i></span>
                    <div>
                        <strong>Find your bus</strong>
                        <small>Search available trips in seconds</small>
                    </div>
                </div>

                <div class="home-search-fields">
                    <label class="home-search-field">
                        <span>From</span>
                        <div class="home-field-control">
                            <i class="bi bi-geo-alt"></i>
                            <input type="text" name="from" list="home-city-list" placeholder="Departure city" required autocomplete="off">
                        </div>
                    </label>

                    <button type="button" class="home-swap-btn" data-home-swap aria-label="Swap departure and arrival cities">
                        <i class="bi bi-arrow-left-right"></i>
                    </button>

                    <label class="home-search-field">
                        <span>To</span>
                        <div class="home-field-control">
                            <i class="bi bi-geo-alt-fill"></i>
                            <input type="text" name="to" list="home-city-list" placeholder="Arrival city" required autocomplete="off">
                        </div>
                    </label>

                    <label class="home-search-field">
                        <span>Travel date</span>
                        <div class="home-field-control">
                            <i class="bi bi-calendar3"></i>
                            <input type="date" name="travel_date" min="<?php echo e($minimumTravelDate); ?>" required>
                        </div>
                    </label>

                    <label class="home-search-field home-service-field">
                        <span>Bus type</span>
                        <div class="home-field-control">
                            <i class="bi bi-stars"></i>
                            <select name="service_type">
                                <option value="all">All services</option>
                                <option value="vip">VIP / Sleeper</option>
                                <option value="normal">Normal / Mini Bus</option>
                            </select>
                        </div>
                    </label>

                    <button type="submit" class="btn home-search-btn">
                        <i class="bi bi-search"></i>
                        <span>Search trips</span>
                    </button>
                </div>

                <datalist id="home-city-list">
                    <?php foreach ($popularCities as $city): ?>
                        <option value="<?php echo e($city); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </form>
        </div>
    </section>

    <section class="home-stat-section">
        <div class="container">
            <div class="home-stat-bar">
                <div class="home-stat-item">
                    <span><i class="bi bi-bus-front"></i></span>
                    <div><strong><?php echo number_format($stats['open_trips']); ?></strong><small>Open trips</small></div>
                </div>
                <div class="home-stat-item">
                    <span><i class="bi bi-map"></i></span>
                    <div><strong><?php echo number_format($stats['active_tours']); ?></strong><small>Active tours</small></div>
                </div>
                <div class="home-stat-item">
                    <span><i class="bi bi-patch-check"></i></span>
                    <div><strong><?php echo number_format($stats['approved_companies']); ?></strong><small>Trusted partners</small></div>
                </div>
                <div class="home-stat-item">
                    <span><i class="bi bi-ticket-perforated"></i></span>
                    <div><strong><?php echo number_format($stats['tickets_issued']); ?></strong><small>Tickets issued</small></div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-destination-section">
        <div class="container">
            <div class="home-section-head home-section-head-split">
                <div>
                    <span class="home-section-label">Popular experiences</span>
                    <h2>Choose how you want to travel</h2>
                    <p>Book a comfortable bus journey or discover Myanmar through curated tour packages.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>tours.php" class="home-text-link">See all tours <i class="bi bi-arrow-up-right"></i></a>
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="home-experience-card home-experience-large">
                        <img src="<?php echo BASE_URL; ?>assets/images/bus.png" alt="Myanmar bus ticket booking">
                        <span class="home-experience-overlay"></span>
                        <span class="home-experience-top"><i class="bi bi-bus-front"></i> Bus tickets</span>
                        <span class="home-experience-content">
                            <small>Travel between cities</small>
                            <strong>Find the right route, schedule and seat.</strong>
                            <span>Search buses <i class="bi bi-arrow-right"></i></span>
                        </span>
                    </a>
                </div>

                <div class="col-lg-5">
                    <div class="row g-4 h-100">
                        <div class="col-12">
                            <a href="<?php echo BASE_URL; ?>tours.php" class="home-experience-card home-experience-small">
                                <img src="<?php echo BASE_URL; ?>assets/images/tour.png" alt="Myanmar tour packages">
                                <span class="home-experience-overlay"></span>
                                <span class="home-experience-top"><i class="bi bi-compass"></i> Tour packages</span>
                                <span class="home-experience-content">
                                    <small>Discover Myanmar</small>
                                    <strong>Memorable places and local experiences.</strong>
                                    <span>Explore tours <i class="bi bi-arrow-right"></i></span>
                                </span>
                            </a>
                        </div>
                        <div class="col-12">
                            <div class="home-support-card">
                                <div class="home-support-icon"><i class="bi bi-headset"></i></div>
                                <div>
                                    <small>Need travel support?</small>
                                    <strong>Your bookings stay organized in one account.</strong>
                                </div>
                                <?php if (is_logged_in()): ?>
                                    <a href="<?php echo BASE_URL; ?>customer/bookings.php" aria-label="Open my bookings"><i class="bi bi-arrow-right"></i></a>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>register.php" aria-label="Create an account"><i class="bi bi-arrow-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-promo-v2-section">
        <div class="container">
            <div class="home-section-head text-center">
                <span class="home-section-label">Offers & events</span>
                <h2>More reasons to start your journey</h2>
                <p>Discover seasonal promotions, special routes and travel experiences.</p>
            </div>

            <div class="home-promo-shell">
                <div id="homePromoCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5500">
                    <div class="carousel-inner">
                        <?php if (!empty($sliderEvents)): ?>
                            <?php foreach ($sliderEvents as $index => $event): ?>
                                <?php
                                    $eventImage = event_public_image($event['image_path'] ?? null, $event['event_type'] ?? null);
                                    $eventTitle = htmlspecialchars($event['title'] ?? 'Travel Event');
                                    $eventType = htmlspecialchars($event['event_type'] ?? 'Travel Event');
                                    $eventDescription = htmlspecialchars($event['description'] ?? 'Discover new travel opportunities and limited-time offers.');
                                    $eventDate = !empty($event['event_date']) ? htmlspecialchars($event['event_date']) : '';
                                    $eventLocation = !empty($event['location']) ? htmlspecialchars($event['location']) : '';
                                ?>
                                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                    <div class="home-promo-slide">
                                        <img src="<?php echo $eventImage; ?>" alt="<?php echo $eventTitle; ?>">
                                        <div class="home-promo-gradient"></div>
                                        <div class="home-promo-content">
                                            <span><?php echo $eventType; ?></span>
                                            <h3><?php echo $eventTitle; ?></h3>
                                            <p><?php echo $eventDescription; ?></p>
                                            <?php if ($eventDate || $eventLocation): ?>
                                                <div class="home-promo-meta">
                                                    <?php if ($eventDate): ?><small><i class="bi bi-calendar-event"></i> <?php echo $eventDate; ?></small><?php endif; ?>
                                                    <?php if ($eventLocation): ?><small><i class="bi bi-geo-alt"></i> <?php echo $eventLocation; ?></small><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <a href="<?php echo BASE_URL; ?>events.php#event-<?php echo (int)($event['id'] ?? 0); ?>" class="btn home-light-btn">View event <i class="bi bi-arrow-right"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="carousel-item active">
                                <div class="home-promo-slide">
                                    <img src="<?php echo BASE_URL; ?>assets/images/thin.jpg" alt="Thingyan travel promotion">
                                    <div class="home-promo-gradient"></div>
                                    <div class="home-promo-content">
                                        <span>Seasonal travel</span>
                                        <h3>Make every holiday journey easier.</h3>
                                        <p>Search available routes early and keep your travel plans together in one place.</p>
                                        <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn home-light-btn">Check routes <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="carousel-item">
                                <div class="home-promo-slide">
                                    <img src="<?php echo BASE_URL; ?>assets/images/tourh.png" alt="Myanmar tour promotion">
                                    <div class="home-promo-gradient"></div>
                                    <div class="home-promo-content">
                                        <span>Curated tours</span>
                                        <h3>Discover beautiful places across Myanmar.</h3>
                                        <p>Browse packages for cultural destinations, nature escapes and memorable local experiences.</p>
                                        <a href="<?php echo BASE_URL; ?>tours.php" class="btn home-light-btn">Explore tours <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="home-promo-controls">
                        <button type="button" data-bs-target="#homePromoCarousel" data-bs-slide="prev" aria-label="Previous promotion">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button type="button" data-bs-target="#homePromoCarousel" data-bs-slide="next" aria-label="Next promotion">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-partner-section">
        <div class="container">
            <div class="home-section-head home-section-head-split">
                <div>
                    <span class="home-section-label">Trusted bus partners</span>
                    <h2>Travel with approved companies</h2>
                    <p>Compare active routes, available trips and starting prices from verified operators.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>search_bus.php" class="home-text-link">Find a bus <i class="bi bi-arrow-up-right"></i></a>
            </div>

            <?php if (!empty($featuredBusCompanies)): ?>
                <div class="home-partner-grid">
                    <?php foreach ($featuredBusCompanies as $company): ?>
                        <?php
                            $companyName = (string)($company['name'] ?? 'Bus Partner');
                            $companyType = (string)($company['company_type'] ?? 'bus_company');
                            $companyLogoUrl = home_company_logo_url($company);
                            $companyDescription = company_short_description($company['description'] ?? null);
                            $companyRoute = trim((string)($company['highlight_route'] ?? ''));
                            $openTrips = (int)($company['open_trips'] ?? 0);
                            $activeRoutes = (int)($company['active_routes'] ?? 0);
                            $startingPrice = (float)($company['starting_price'] ?? 0);
                            $searchUrl = BASE_URL . 'search_bus.php?' . http_build_query([
                                'company_id' => (int)($company['id'] ?? 0),
                            ]);
                        ?>
                        <article class="home-partner-card">
                            <div class="home-partner-top">
                                <div class="home-partner-logo">
                                    <?php if ($companyLogoUrl !== ''): ?>
                                        <img src="<?php echo e($companyLogoUrl); ?>" alt="<?php echo e($companyName); ?> logo">
                                    <?php else: ?>
                                        <span><?php echo e(company_initials($companyName)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="home-verified-badge"><i class="bi bi-patch-check-fill"></i> Approved</span>
                            </div>

                            <span class="home-partner-type"><?php echo e(company_type_label($companyType)); ?></span>
                            <h3><?php echo e($companyName); ?></h3>
                            <p><?php echo e($companyDescription); ?></p>

                            <div class="home-partner-route">
                                <i class="bi bi-sign-turn-right"></i>
                                <span><?php echo e($companyRoute !== '' ? $companyRoute : 'Multiple routes available'); ?></span>
                            </div>

                            <div class="home-partner-data">
                                <div><strong><?php echo number_format($activeRoutes); ?></strong><small>Routes</small></div>
                                <div><strong><?php echo number_format($openTrips); ?></strong><small>Open trips</small></div>
                                <div><strong><?php echo $startingPrice > 0 ? number_format($startingPrice / 1000, 0) . 'K' : 'Ask'; ?></strong><small>From MMK</small></div>
                            </div>

                            <a href="<?php echo e($searchUrl); ?>" class="home-partner-link">View available trips <i class="bi bi-arrow-right"></i></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="home-empty-state">
                    <span><i class="bi bi-building-check"></i></span>
                    <div>
                        <h4>Partner companies will appear here</h4>
                        <p>Approved bus operators are displayed automatically when company data is available.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-section home-how-section">
        <div class="container">
            <div class="home-section-head text-center">
                <span class="home-section-label">Simple booking flow</span>
                <h2>From search to boarding in four steps</h2>
                <p>A clear process designed to make every booking easier to complete and manage.</p>
            </div>

            <div class="home-step-grid">
                <article class="home-step-card">
                    <span class="home-step-number">01</span>
                    <div class="home-step-icon"><i class="bi bi-search"></i></div>
                    <h3>Search</h3>
                    <p>Choose departure, destination, date and preferred bus type.</p>
                </article>
                <article class="home-step-card">
                    <span class="home-step-number">02</span>
                    <div class="home-step-icon"><i class="bi bi-grid-3x3-gap"></i></div>
                    <h3>Select</h3>
                    <p>Compare trips and choose the seat that works best for you.</p>
                </article>
                <article class="home-step-card">
                    <span class="home-step-number">03</span>
                    <div class="home-step-icon"><i class="bi bi-credit-card"></i></div>
                    <h3>Pay</h3>
                    <p>Submit payment proof and follow the verification status online.</p>
                </article>
                <article class="home-step-card">
                    <span class="home-step-number">04</span>
                    <div class="home-step-icon"><i class="bi bi-qr-code"></i></div>
                    <h3>Travel</h3>
                    <p>Download your QR ticket or voucher and enjoy the journey.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-final-section">
        <div class="container">
            <div class="home-final-card">
                <div class="home-final-pattern"></div>
                <div class="row align-items-center g-4 position-relative">
                    <div class="col-lg-8">
                        <span>Ready for your next trip?</span>
                        <h2>Search, book and travel with less effort.</h2>
                        <p>Join Golden Route Myanmar and keep all your journeys in one convenient place.</p>
                    </div>
                    <div class="col-lg-4">
                        <div class="home-final-actions">
                            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn home-light-btn">Find a bus <i class="bi bi-arrow-right"></i></a>
                            <?php if (!is_logged_in()): ?>
                                <a href="<?php echo BASE_URL; ?>register.php" class="btn home-outline-light-btn">Create account</a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn home-outline-light-btn">My bookings</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var swapButton = document.querySelector('[data-home-swap]');
    var fromInput = document.querySelector('#quick-booking input[name="from"]');
    var toInput = document.querySelector('#quick-booking input[name="to"]');

    if (swapButton && fromInput && toInput) {
        swapButton.addEventListener('click', function () {
            var fromValue = fromInput.value;
            fromInput.value = toInput.value;
            toInput.value = fromValue;
            swapButton.classList.add('is-swapping');
            window.setTimeout(function () {
                swapButton.classList.remove('is-swapping');
            }, 280);
        });
    }
});
</script>

<?php
$hide_footer_cta = true;
require_once __DIR__ . '/includes/footer.php';
?>
