<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Search Bus - Golden Route Myanmar';

$conn = getDBConnection();

function is_valid_date_ymd(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function normalize_city_name(string $name): string
{
    $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name);
    return mb_strtolower($name, 'UTF-8');
}

$cities = [];
$citySql = "
    SELECT id, name, state_region
    FROM cities
    WHERE is_active = 1
    ORDER BY name ASC
";
$cityStmt = $conn->prepare($citySql);
$cityStmt->execute();
$cityResult = $cityStmt->get_result();

$cityNameMap = [];

while ($row = $cityResult->fetch_assoc()) {
    $cities[] = $row;
    $cityNameMap[normalize_city_name((string)$row['name'])] = (int)$row['id'];
}
$cityStmt->close();

$selectedCompanyId = (int)($_GET['company_id'] ?? 0);
$selectedCompany = null;

if ($selectedCompanyId > 0) {
    $companySql = "
        SELECT id, name, company_type, status, logo
        FROM companies
        WHERE id = ?
          AND status = 'approved'
          AND company_type IN ('bus_company', 'both')
        LIMIT 1
    ";

    $companyStmt = $conn->prepare($companySql);
    if ($companyStmt) {
        $companyStmt->bind_param('i', $selectedCompanyId);
        $companyStmt->execute();
        $companyResult = $companyStmt->get_result();
        $selectedCompany = $companyResult ? $companyResult->fetch_assoc() : null;
        $companyStmt->close();
    }

    if (!$selectedCompany) {
        $selectedCompanyId = 0;
    }
}

$fromCityId = (int)($_GET['from_city_id'] ?? 0);
$toCityId = (int)($_GET['to_city_id'] ?? 0);
$travelDate = trim((string)($_GET['travel_date'] ?? ''));

$fromName = trim((string)($_GET['from'] ?? ''));
$toName = trim((string)($_GET['to'] ?? ''));

if ($fromCityId <= 0 && $fromName !== '') {
    $normalizedFrom = normalize_city_name($fromName);
    if (isset($cityNameMap[$normalizedFrom])) {
        $fromCityId = $cityNameMap[$normalizedFrom];
    }
}

if ($toCityId <= 0 && $toName !== '') {
    $normalizedTo = normalize_city_name($toName);
    if (isset($cityNameMap[$normalizedTo])) {
        $toCityId = $cityNameMap[$normalizedTo];
    }
}

$isSubmitted = isset($_GET['search']) && $_GET['search'] === '1';

$formError = null;
$results = [];

if ($isSubmitted) {
    if ($fromCityId <= 0 || $toCityId <= 0 || $travelDate === '') {
        $formError = 'Please select departure city, arrival city, and travel date.';
    } elseif ($fromCityId === $toCityId) {
        $formError = 'From city and To city cannot be the same.';
    } elseif (!is_valid_date_ymd($travelDate)) {
        $formError = 'Invalid travel date format.';
    } else {
        if ($selectedCompanyId > 0) {
            $searchSql = "
                SELECT
                    t.id AS trip_id,
                    t.trip_date,
                    t.departure_datetime,
                    t.arrival_datetime,
                    t.price,
                    t.available_seats,
                    t.status AS trip_status,
                    c.id AS company_id,
                    c.name AS company_name,
                    b.bus_number,
                    b.bus_type,
                    b.layout_type,
                    r.distance_km,
                    r.duration_minutes,
                    fc.name AS from_city_name,
                    tc.name AS to_city_name
                FROM trips t
                INNER JOIN companies c ON c.id = t.company_id
                INNER JOIN routes r ON r.id = t.route_id
                INNER JOIN buses b ON b.id = t.bus_id
                INNER JOIN cities fc ON fc.id = r.from_city_id
                INNER JOIN cities tc ON tc.id = r.to_city_id
                WHERE t.trip_date = ?
                  AND r.from_city_id = ?
                  AND r.to_city_id = ?
                  AND t.company_id = ?
                  AND t.status = 'open'
                  AND c.status = 'approved'
                  AND r.status = 'active'
                  AND b.status = 'active'
                ORDER BY t.departure_datetime ASC, t.price ASC
            ";

            $searchStmt = $conn->prepare($searchSql);
            $searchStmt->bind_param('siii', $travelDate, $fromCityId, $toCityId, $selectedCompanyId);
        } else {
            $searchSql = "
                SELECT
                    t.id AS trip_id,
                    t.trip_date,
                    t.departure_datetime,
                    t.arrival_datetime,
                    t.price,
                    t.available_seats,
                    t.status AS trip_status,
                    c.id AS company_id,
                    c.name AS company_name,
                    b.bus_number,
                    b.bus_type,
                    b.layout_type,
                    r.distance_km,
                    r.duration_minutes,
                    fc.name AS from_city_name,
                    tc.name AS to_city_name
                FROM trips t
                INNER JOIN companies c ON c.id = t.company_id
                INNER JOIN routes r ON r.id = t.route_id
                INNER JOIN buses b ON b.id = t.bus_id
                INNER JOIN cities fc ON fc.id = r.from_city_id
                INNER JOIN cities tc ON tc.id = r.to_city_id
                WHERE t.trip_date = ?
                  AND r.from_city_id = ?
                  AND r.to_city_id = ?
                  AND t.status = 'open'
                  AND c.status = 'approved'
                  AND r.status = 'active'
                  AND b.status = 'active'
                ORDER BY t.departure_datetime ASC, t.price ASC
            ";

            $searchStmt = $conn->prepare($searchSql);
            $searchStmt->bind_param('sii', $travelDate, $fromCityId, $toCityId);
        }

        $searchStmt->execute();
        $searchResult = $searchStmt->get_result();

        while ($row = $searchResult->fetch_assoc()) {
            $results[] = $row;
        }
        $searchStmt->close();
    }
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <div class="search-intro-card h-100">
                    <span class="section-kicker">Bus Search</span>
                    <h1 class="page-title">Find your trip in minutes</h1>
                    <p class="page-subtitle">
                        Pick your departure city, arrival city, and travel date.
                        Then compare routes, seat availability, and price clearly.
                    </p>

                    <div class="search-benefit-list mt-4">
                        <div class="search-benefit-item">Open trips only</div>
                        <div class="search-benefit-item">Live available seats</div>
                        <div class="search-benefit-item">Clear VIP / sleeper / normal labels</div>
                    </div>

                    <?php if ($selectedCompany): ?>
                        <div class="mt-4 p-3 rounded-4 border bg-white shadow-sm">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="small text-muted mb-1">Selected Company</div>
                                    <strong><?php echo e($selectedCompany['name']); ?></strong>
                                    <div class="small text-muted mt-1">
                                        Your search results will show only this bus company.
                                    </div>
                                </div>
                                <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-outline-secondary btn-sm">
                                    Clear company filter
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="search-form-card h-100">
                    <form id="busSearchForm" method="GET" action="<?php echo BASE_URL; ?>search_bus.php">
                        <input type="hidden" name="search" value="1">

                        <?php if ($selectedCompanyId > 0): ?>
                            <input type="hidden" name="company_id" value="<?php echo e((string)$selectedCompanyId); ?>">
                        <?php endif; ?>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">From City</label>
                                <select name="from_city_id" class="form-select" required>
                                    <option value="">Select departure city</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo ((string)$fromCityId === (string)$city['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">To City</label>
                                <select name="to_city_id" class="form-select" required>
                                    <option value="">Select arrival city</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo e($city['id']); ?>" <?php echo ((string)$toCityId === (string)$city['id']) ? 'selected' : ''; ?>>
                                            <?php echo e($city['name'] . ($city['state_region'] ? ' (' . $city['state_region'] . ')' : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Travel Date</label>
                                <input type="date" name="travel_date" class="form-control" value="<?php echo e($travelDate); ?>" required>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-brand w-100">
                                    <?php echo $selectedCompany ? 'Search ' . e($selectedCompany['name']) . ' Trips' : 'Search Buses'; ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (!$isSubmitted && ($selectedCompanyId > 0 || $fromCityId > 0 || $toCityId > 0)): ?>
                        <div class="mt-3 small text-muted">
                            Route and company have been prefilled from the selected slider card.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container pb-5">
    <?php if ($formError): ?>
        <div class="alert alert-danger"><?php echo e($formError); ?></div>
    <?php endif; ?>

    <?php if ($isSubmitted && !$formError): ?>
        <div class="results-header mb-4">
            <div>
                <h3 class="mb-1">Search Results</h3>
                <p class="mb-0 text-muted">
                    Found <?php echo count($results); ?> trip(s) for <?php echo e($travelDate); ?>
                    <?php if ($selectedCompany): ?>
                        from <strong><?php echo e($selectedCompany['name']); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state-card">
                <h3>No trips found</h3>
                <p>
                    <?php if ($selectedCompany): ?>
                        No trips found for <?php echo e($selectedCompany['name']); ?> on this route and date.
                    <?php else: ?>
                        Try another date or route combination.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($results as $trip): ?>
                    <?php
                    $departureTime = date('H:i', strtotime($trip['departure_datetime']));
                    $arrivalTime = date('H:i', strtotime($trip['arrival_datetime']));
                    $tripDateFormatted = date('Y-m-d', strtotime($trip['trip_date']));
                    $availableSeats = (int)$trip['available_seats'];
                    $isSoldOut = $availableSeats <= 0;
                    ?>
                    <div class="col-12">
                        <div class="trip-result-card">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-7">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                        <span class="soft-badge"><?php echo e($trip['company_name']); ?></span>
                                        <span class="soft-badge"><?php echo e(ucwords(str_replace('_', ' ', $trip['bus_type']))); ?></span>
                                        <span class="soft-badge"><?php echo e(strtoupper($trip['layout_type'])); ?> Layout</span>
                                    </div>

                                    <div class="trip-route-line mb-3">
                                        <div>
                                            <small>From</small>
                                            <strong><?php echo e($trip['from_city_name']); ?></strong>
                                        </div>
                                        <div class="trip-route-arrow">→</div>
                                        <div class="text-end">
                                            <small>To</small>
                                            <strong><?php echo e($trip['to_city_name']); ?></strong>
                                        </div>
                                    </div>

                                    <div class="trip-meta-grid">
                                        <div class="trip-meta-box">
                                            <span>Date</span>
                                            <strong><?php echo e($tripDateFormatted); ?></strong>
                                        </div>
                                        <div class="trip-meta-box">
                                            <span>Departure</span>
                                            <strong><?php echo e($departureTime); ?></strong>
                                        </div>
                                        <div class="trip-meta-box">
                                            <span>Arrival</span>
                                            <strong><?php echo e($arrivalTime); ?></strong>
                                        </div>
                                        <div class="trip-meta-box">
                                            <span>Bus</span>
                                            <strong><?php echo e($trip['bus_number']); ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <div class="trip-price-panel">
                                        <div class="trip-price-label">Ticket Price</div>
                                        <div class="trip-price-value"><?php echo number_format((float)$trip['price'], 2); ?> MMK</div>

                                        <div class="mt-3 mb-4">
                                            <?php if ($isSoldOut): ?>
                                                <span class="badge text-bg-danger">Sold Out</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-success">Available Seats: <?php echo $availableSeats; ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($isSoldOut): ?>
                                            <button type="button" class="btn btn-secondary w-100" disabled>Sold Out</button>
                                        <?php else: ?>
                                            <a href="<?php echo BASE_URL; ?>checkout.php?trip_id=<?php echo e($trip['trip_id']); ?>" class="btn btn-brand w-100">
                                                Choose Seats / Checkout
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state-card">
            <h3><?php echo $selectedCompany ? 'Search available trips for ' . e($selectedCompany['name']) : 'Search available bus trips'; ?></h3>
            <p>
                <?php if ($selectedCompany): ?>
                    Use the form above to search routes and dates for this selected bus company.
                <?php else: ?>
                    Use the form above to search by route and date.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>