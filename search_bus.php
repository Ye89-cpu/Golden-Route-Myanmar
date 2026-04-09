<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Search Bus - Myanmar Bus & Tour Booking';

$conn = getDBConnection();

function is_valid_date_ymd(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
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

while ($row = $cityResult->fetch_assoc()) {
    $cities[] = $row;
}
$cityStmt->close();

$fromCityId = (int)($_GET['from_city_id'] ?? 0);
$toCityId = (int)($_GET['to_city_id'] ?? 0);
$travelDate = trim($_GET['travel_date'] ?? '');
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
        $searchSql = "
            SELECT
                t.id AS trip_id,
                t.trip_date,
                t.departure_datetime,
                t.arrival_datetime,
                t.price,
                t.available_seats,
                t.status AS trip_status,
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
                </div>
            </div>

            <div class="col-lg-6">
                <div class="search-form-card h-100">
                    <form method="GET" action="<?php echo BASE_URL; ?>search_bus.php">
                        <input type="hidden" name="search" value="1">

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
                                <button type="submit" class="btn btn-brand w-100">Search Buses</button>
                            </div>
                        </div>
                    </form>
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
                </p>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state-card">
                <h3>No trips found</h3>
                <p>Try another date or route combination.</p>
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
            <h3>Search available bus trips</h3>
            <p>Use the form above to search by route and date.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>