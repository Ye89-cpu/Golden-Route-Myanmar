<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/any_city_route_helper.php';

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

function bind_stmt_params(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    $stmt->bind_param($types, ...$refs);
}

function bus_service_condition(string $serviceType, string $alias): string
{
    if ($serviceType === 'vip') {
        return " AND {$alias}.bus_type IN ('vip','sleeper') ";
    }

    if ($serviceType === 'normal') {
        return " AND {$alias}.bus_type IN ('normal','mini_bus') ";
    }

    return '';
}

function bus_service_label(?string $busType): string
{
    $busType = strtolower((string)$busType);
    if ($busType === 'vip') {
        return 'VIP';
    }
    if ($busType === 'sleeper') {
        return 'VIP Sleeper';
    }
    if ($busType === 'mini_bus') {
        return 'Mini Bus';
    }
    return 'Normal';
}

function bus_service_badge_class(?string $busType): string
{
    $busType = strtolower((string)$busType);
    if (in_array($busType, ['vip', 'sleeper'], true)) {
        return 'vip-badge';
    }
    return 'normal-badge';
}

function format_minutes(?int $minutes): string
{
    $minutes = (int)$minutes;
    if ($minutes <= 0) {
        return '-';
    }
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    if ($hours <= 0) {
        return $mins . ' min';
    }
    return $mins > 0 ? ($hours . ' hr ' . $mins . ' min') : ($hours . ' hr');
}

$cities = [];
$cityNameMap = [];
$cityIdNameMap = [];

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
    $row['id'] = (int)$row['id'];
    $cities[] = $row;
    $cityNameMap[normalize_city_name((string)$row['name'])] = (int)$row['id'];
    $cityIdNameMap[(int)$row['id']] = (string)$row['name'];
}
$cityStmt->close();

// Add a complete Myanmar city catalog to the search datalist.
// Real DB cities keep their IDs. Catalog-only cities are created automatically when searched.
foreach (grm_any_city_catalog() as $catalogCityName => $catalogStateRegion) {
    $catalogKey = normalize_city_name($catalogCityName);
    if (!isset($cityNameMap[$catalogKey])) {
        $cities[] = [
            'id' => 0,
            'name' => $catalogCityName,
            'state_region' => $catalogStateRegion,
        ];
    }
}

usort($cities, function (array $a, array $b): int {
    return strcasecmp((string)$a['name'], (string)$b['name']);
});

$popularCityNames = array_keys(grm_any_city_catalog());

$popularCities = array_slice($popularCityNames, 0, 24);

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
$minimumTravelDate = date('Y-m-d');

$fromName = trim((string)($_GET['from'] ?? ''));
$toName = trim((string)($_GET['to'] ?? ''));

if ($fromCityId > 0 && isset($cityIdNameMap[$fromCityId])) {
    $fromName = $cityIdNameMap[$fromCityId];
}
if ($toCityId > 0 && isset($cityIdNameMap[$toCityId])) {
    $toName = $cityIdNameMap[$toCityId];
}

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

$serviceType = strtolower(trim((string)($_GET['service_type'] ?? 'all')));
$allowedServiceTypes = ['all', 'vip', 'normal'];
if (!in_array($serviceType, $allowedServiceTypes, true)) {
    $serviceType = 'all';
}

$isSubmitted = isset($_GET['search']) && $_GET['search'] === '1';
$formError = null;
$results = [];
$searchedDirectOnly = false;

if ($isSubmitted) {
    // When a customer selects a catalog city that is not in the database yet,
    // create it automatically so any city can be searched and booked.
    if ($fromCityId <= 0 && $fromName !== '') {
        $fromCityId = grm_find_or_create_city($conn, $fromName);
        if ($fromCityId > 0) {
            $fromName = grm_get_city_name_by_id($conn, $fromCityId) ?: $fromName;
            $cityNameMap[normalize_city_name($fromName)] = $fromCityId;
            $cityIdNameMap[$fromCityId] = $fromName;
        }
    }

    if ($toCityId <= 0 && $toName !== '') {
        $toCityId = grm_find_or_create_city($conn, $toName);
        if ($toCityId > 0) {
            $toName = grm_get_city_name_by_id($conn, $toCityId) ?: $toName;
            $cityNameMap[normalize_city_name($toName)] = $toCityId;
            $cityIdNameMap[$toCityId] = $toName;
        }
    }

    if ($fromCityId <= 0 || $toCityId <= 0 || $travelDate === '') {
        $formError = 'Please choose valid departure city, arrival city, and travel date.';
    } elseif ($fromCityId === $toCityId) {
        $formError = 'From city and To city cannot be the same.';
    } elseif (!is_valid_date_ymd($travelDate)) {
        $formError = 'Invalid travel date format.';
    } elseif ($travelDate < $minimumTravelDate) {
        $formError = 'Past travel dates are not allowed. Please choose today or a future date.';
    } else {
        // Main fix: ensure the selected city pair has open VIP/Normal trip options for the selected date.
        // This makes Any City → Any City searchable and bookable in the demo project.
        grm_ensure_any_city_direct_options($conn, $fromCityId, $toCityId, $travelDate, $serviceType, $selectedCompanyId);

        $serviceSql = bus_service_condition($serviceType, 'b');

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
              AND t.available_seats > 0
              {$serviceSql}
        ";

        $types = 'sii';
        $params = [$travelDate, $fromCityId, $toCityId];

        if ($selectedCompanyId > 0) {
            $searchSql .= ' AND t.company_id = ? ';
            $types .= 'i';
            $params[] = $selectedCompanyId;
        }

        $searchSql .= "
            ORDER BY
                CASE WHEN b.bus_type IN ('vip','sleeper') THEN 0 ELSE 1 END,
                t.departure_datetime ASC,
                t.price ASC
            LIMIT 60
        ";

        $searchStmt = $conn->prepare($searchSql);
        bind_stmt_params($searchStmt, $types, $params);
        $searchStmt->execute();
        $searchResult = $searchStmt->get_result();

        while ($row = $searchResult->fetch_assoc()) {
            $row['is_multi_hop'] = false;
            $results[] = $row;
        }
        $searchStmt->close();

        $searchedDirectOnly = !empty($results);

        if (empty($results)) {
            $serviceSqlLeg1 = bus_service_condition($serviceType, 'b1');
            $serviceSqlLeg2 = bus_service_condition($serviceType, 'b2');

            $multiSql = "
                SELECT
                    t1.id AS leg1_trip_id,
                    t1.trip_date AS leg1_trip_date,
                    t1.departure_datetime AS leg1_departure_datetime,
                    t1.arrival_datetime AS leg1_arrival_datetime,
                    t1.price AS leg1_price,
                    t1.available_seats AS leg1_available_seats,
                    c1.name AS leg1_company_name,
                    b1.bus_number AS leg1_bus_number,
                    b1.bus_type AS leg1_bus_type,
                    b1.layout_type AS leg1_layout_type,
                    r1.duration_minutes AS leg1_duration_minutes,
                    fc.name AS from_city_name,
                    mc.name AS transfer_city_name,

                    t2.id AS leg2_trip_id,
                    t2.trip_date AS leg2_trip_date,
                    t2.departure_datetime AS leg2_departure_datetime,
                    t2.arrival_datetime AS leg2_arrival_datetime,
                    t2.price AS leg2_price,
                    t2.available_seats AS leg2_available_seats,
                    c2.name AS leg2_company_name,
                    b2.bus_number AS leg2_bus_number,
                    b2.bus_type AS leg2_bus_type,
                    b2.layout_type AS leg2_layout_type,
                    r2.duration_minutes AS leg2_duration_minutes,
                    tc.name AS to_city_name
                FROM trips t1
                INNER JOIN routes r1 ON r1.id = t1.route_id
                INNER JOIN companies c1 ON c1.id = t1.company_id
                INNER JOIN buses b1 ON b1.id = t1.bus_id
                INNER JOIN cities fc ON fc.id = r1.from_city_id
                INNER JOIN cities mc ON mc.id = r1.to_city_id
                INNER JOIN routes r2 ON r2.from_city_id = r1.to_city_id
                INNER JOIN trips t2 ON t2.route_id = r2.id
                INNER JOIN companies c2 ON c2.id = t2.company_id
                INNER JOIN buses b2 ON b2.id = t2.bus_id
                INNER JOIN cities tc ON tc.id = r2.to_city_id
                WHERE t1.trip_date = ?
                  AND t2.trip_date = ?
                  AND r1.from_city_id = ?
                  AND r2.to_city_id = ?
                  AND t1.status = 'open'
                  AND t2.status = 'open'
                  AND r1.status = 'active'
                  AND r2.status = 'active'
                  AND c1.status = 'approved'
                  AND c2.status = 'approved'
                  AND b1.status = 'active'
                  AND b2.status = 'active'
                  AND t1.available_seats > 0
                  AND t2.available_seats > 0
                  AND t2.departure_datetime >= t1.arrival_datetime
                  {$serviceSqlLeg1}
                  {$serviceSqlLeg2}
            ";

            $multiTypes = 'ssii';
            $multiParams = [$travelDate, $travelDate, $fromCityId, $toCityId];

            if ($selectedCompanyId > 0) {
                $multiSql .= ' AND t1.company_id = ? AND t2.company_id = ? ';
                $multiTypes .= 'ii';
                $multiParams[] = $selectedCompanyId;
                $multiParams[] = $selectedCompanyId;
            }

            $multiSql .= "
                ORDER BY
                    CASE WHEN b1.bus_type IN ('vip','sleeper') AND b2.bus_type IN ('vip','sleeper') THEN 0 ELSE 1 END,
                    t1.departure_datetime ASC,
                    t2.departure_datetime ASC
                LIMIT 20
            ";

            $multiStmt = $conn->prepare($multiSql);
            if ($multiStmt) {
                bind_stmt_params($multiStmt, $multiTypes, $multiParams);
                $multiStmt->execute();
                $multiResult = $multiStmt->get_result();
                while ($row = $multiResult->fetch_assoc()) {
                    $row['is_multi_hop'] = true;
                    $row['total_price'] = (float)$row['leg1_price'] + (float)$row['leg2_price'];
                    $row['available_seats'] = min((int)$row['leg1_available_seats'], (int)$row['leg2_available_seats']);
                    $results[] = $row;
                }
                $multiStmt->close();
            }
        }
    }
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<style>
    .bus-search-shell { padding-top: 22px; }
    .search-intro-card, .search-form-card, .trip-result-card, .empty-state-card {
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 28px;
        background: #fff;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .08);
    }
    .search-intro-card, .search-form-card { padding: 30px; }
    .city-input-wrap { position: relative; }
    .city-input-wrap .city-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #b8860b;
        pointer-events: none;
    }
    .city-input-wrap .form-control { padding-left: 42px; min-height: 52px; border-radius: 16px; }
    .service-toggle {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        background: #f8fafc;
        padding: 8px;
        border-radius: 18px;
        border: 1px solid #e5e7eb;
    }
    .service-toggle input { display: none; }
    .service-toggle label {
        cursor: pointer;
        text-align: center;
        border-radius: 14px;
        padding: 12px 10px;
        font-weight: 800;
        color: #334155;
        background: transparent;
        border: 1px solid transparent;
    }
    .service-toggle input:checked + label {
        background: linear-gradient(135deg, #c99a2e, #a97912);
        color: #fff;
        box-shadow: 0 12px 24px rgba(184, 134, 11, .25);
    }
    .popular-city-box { margin-top: 18px; }
    .popular-city-chip {
        border: 1px solid rgba(184, 134, 11, .25);
        background: #fff8e8;
        color: #8a5f00;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 13px;
        font-weight: 700;
        margin: 4px 4px 0 0;
    }
    .swap-btn {
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        background: #fff;
        min-height: 52px;
        font-weight: 800;
    }
    .trip-result-card { padding: 24px; }
    .trip-route-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        background: linear-gradient(135deg, #fffaf0, #f8fafc);
        border: 1px solid #f0dfbd;
        border-radius: 22px;
        padding: 18px;
    }
    .trip-route-line small { display: block; color: #64748b; font-weight: 700; }
    .trip-route-line strong { color: #0f172a; font-size: 18px; }
    .trip-route-arrow { font-size: 25px; color: #b8860b; font-weight: 900; }
    .trip-meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .trip-meta-box { border: 1px solid #e5e7eb; border-radius: 16px; padding: 11px 13px; background: #fff; }
    .trip-meta-box span { display: block; font-size: 12px; color: #64748b; font-weight: 700; }
    .trip-meta-box strong { color: #0f172a; font-size: 14px; }
    .trip-price-panel { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 24px; padding: 22px; }
    .trip-price-label { color: #64748b; font-weight: 800; }
    .trip-price-value { color: #0f172a; font-size: 28px; font-weight: 900; }
    .soft-badge, .vip-badge, .normal-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 7px 12px;
        font-size: 12px;
        font-weight: 900;
    }
    .soft-badge { background: #f1f5f9; color: #334155; }
    .vip-badge { background: #fff3cd; color: #8a5f00; border: 1px solid #f0cf7a; }
    .normal-badge { background: #e0f2fe; color: #075985; border: 1px solid #bae6fd; }
    .result-summary-card {
        border-radius: 24px;
        background: #0f172a;
        color: #fff;
        padding: 20px 24px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
    }
    .empty-state-card { padding: 36px; text-align: center; }
    .transfer-leg {
        border: 1px dashed #cbd5e1;
        border-radius: 16px;
        padding: 12px;
        background: #fff;
        margin-top: 10px;
    }
    @media (max-width: 768px) {
        .trip-meta-grid { grid-template-columns: repeat(2, 1fr); }
        .service-toggle { grid-template-columns: 1fr; }
        .trip-route-line { flex-direction: column; align-items: stretch; text-align: left !important; }
    }
</style>

<section class="page-hero bus-search-shell">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-5">
                <div class="search-intro-card h-100">
                    <span class="section-kicker">Bus Search</span>
                    <h1 class="page-title">Choose Normal or VIP buses easily</h1>
                    <p class="page-subtitle">
                        Search many Myanmar cities, compare VIP and Normal bus options, then choose seats and book directly.
                    </p>

                    <div class="search-benefit-list mt-4">
                        <div class="search-benefit-item">VIP / Normal filter</div>
                        <div class="search-benefit-item">Major Myanmar city search</div>
                        <div class="search-benefit-item">Direct and transfer route support</div>
                    </div>

                    <?php if ($selectedCompany): ?>
                        <div class="mt-4 p-3 rounded-4 border bg-white shadow-sm">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="small text-muted mb-1">Selected Company</div>
                                    <strong><?php echo e($selectedCompany['name']); ?></strong>
                                    <div class="small text-muted mt-1">Results will show only this company.</div>
                                </div>
                                <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-outline-secondary btn-sm">Clear filter</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="search-form-card h-100">
                    <form id="busSearchForm" method="GET" action="<?php echo BASE_URL; ?>search_bus.php">
                        <input type="hidden" name="search" value="1">
                        <?php if ($selectedCompanyId > 0): ?>
                            <input type="hidden" name="company_id" value="<?php echo e((string)$selectedCompanyId); ?>">
                        <?php endif; ?>

                        <datalist id="myanmarCityList">
                            <?php foreach ($cities as $city): ?>
                                <option value="<?php echo e($city['name']); ?>" label="<?php echo e($city['state_region']); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label class="form-label fw-bold">From City</label>
                                <div class="city-input-wrap">
                                    <span class="city-icon">📍</span>
                                    <input type="text" name="from" id="fromCityInput" list="myanmarCityList" class="form-control" value="<?php echo e($fromName); ?>" placeholder="Yangon, Mandalay, Pathein..." autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-md-2 d-grid">
                                <button type="button" class="swap-btn" id="swapCitiesBtn">⇄ Swap</button>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold">To City</label>
                                <div class="city-input-wrap">
                                    <span class="city-icon">🎯</span>
                                    <input type="text" name="to" id="toCityInput" list="myanmarCityList" class="form-control" value="<?php echo e($toName); ?>" placeholder="Choose arrival city" autocomplete="off" required>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold" for="travelDateInput">Travel Date</label>
                                <input
                                    type="date"
                                    name="travel_date"
                                    id="travelDateInput"
                                    class="form-control"
                                    value="<?php echo e($travelDate); ?>"
                                    min="<?php echo e($minimumTravelDate); ?>"
                                    aria-describedby="travelDateHelp"
                                    required
                                    style="min-height:52px;border-radius:16px;">
                                <div id="travelDateHelp" class="form-text">Past dates cannot be searched. Choose today or a future date.</div>
                            </div>

                            <div class="col-md-7">
                                <label class="form-label fw-bold">Bus Class</label>
                                <div class="service-toggle">
                                    <input type="radio" name="service_type" id="serviceAll" value="all" <?php echo $serviceType === 'all' ? 'checked' : ''; ?>>
                                    <label for="serviceAll">All</label>

                                    <input type="radio" name="service_type" id="serviceVip" value="vip" <?php echo $serviceType === 'vip' ? 'checked' : ''; ?>>
                                    <label for="serviceVip">VIP</label>

                                    <input type="radio" name="service_type" id="serviceNormal" value="normal" <?php echo $serviceType === 'normal' ? 'checked' : ''; ?>>
                                    <label for="serviceNormal">Normal</label>
                                </div>
                            </div>

                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-brand btn-lg" style="border-radius:18px;">
                                    <?php echo $selectedCompany ? 'Search ' . e($selectedCompany['name']) . ' Trips' : 'Search Buses'; ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($popularCities)): ?>
                        <div class="popular-city-box">
                            <div class="small text-muted fw-bold mb-2">Popular Myanmar Cities</div>
                            <?php foreach ($popularCities as $city): ?>
                                <button type="button" class="popular-city-chip" data-city="<?php echo e($city); ?>"><?php echo e($city); ?></button>
                            <?php endforeach; ?>
                            <div class="small text-muted mt-2">Tip: Click a city chip, then click From or To input to fill faster.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container pb-5">
    <?php if ($formError): ?>
        <div class="alert alert-danger rounded-4"><?php echo e($formError); ?></div>
    <?php endif; ?>

    <?php if ($isSubmitted && !$formError): ?>
        <div class="result-summary-card mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h3 class="mb-1">Search Results</h3>
                    <div class="text-white-50">
                        <?php echo e($fromName); ?> → <?php echo e($toName); ?> · <?php echo e($travelDate); ?> ·
                        <?php echo $serviceType === 'all' ? 'All classes' : strtoupper($serviceType); ?>
                    </div>
                </div>
                <div class="fs-5 fw-bold"><?php echo count($results); ?> option(s)</div>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="empty-state-card">
                <h3>No trips found</h3>
                <p class="text-muted mb-3">
                    No open <?php echo $serviceType === 'all' ? '' : e(strtoupper($serviceType)); ?> bus trips were found for this route and date.
                </p>
                <p class="text-muted mb-0">
                    Try another date, choose <strong>All</strong>, or add schedules from Bus Admin for this route.
                </p>
            </div>
        <?php else: ?>
            <?php if (!$searchedDirectOnly && !empty($results)): ?>
                <div class="alert alert-info rounded-4">
                    No direct bus found. Showing transfer routes that can be booked together.
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <?php foreach ($results as $trip): ?>
                    <?php
                    $isMultiHop = !empty($trip['is_multi_hop']);

                    if ($isMultiHop) {
                        $departureTime = date('H:i', strtotime($trip['leg1_departure_datetime']));
                        $arrivalTime = date('H:i', strtotime($trip['leg2_arrival_datetime']));
                        $tripDateFormatted = date('Y-m-d', strtotime($trip['leg1_trip_date']));
                        $availableSeats = min((int)$trip['leg1_available_seats'], (int)$trip['leg2_available_seats']);
                        $displayPrice = (float)$trip['total_price'];
                        $checkoutUrl = BASE_URL . 'checkout_multi.php?trip1_id=' . (int)$trip['leg1_trip_id'] . '&trip2_id=' . (int)$trip['leg2_trip_id'];
                        $serviceLabel = bus_service_label($trip['leg1_bus_type']) . ' + ' . bus_service_label($trip['leg2_bus_type']);
                    } else {
                        $departureTime = date('H:i', strtotime($trip['departure_datetime']));
                        $arrivalTime = date('H:i', strtotime($trip['arrival_datetime']));
                        $tripDateFormatted = date('Y-m-d', strtotime($trip['trip_date']));
                        $availableSeats = (int)$trip['available_seats'];
                        $displayPrice = (float)$trip['price'];
                        $checkoutUrl = BASE_URL . 'checkout.php?trip_id=' . (int)$trip['trip_id'];
                        $serviceLabel = bus_service_label($trip['bus_type']);
                    }
                    ?>
                    <div class="col-12">
                        <div class="trip-result-card">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-8">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                        <?php if ($isMultiHop): ?>
                                            <span class="soft-badge">2-step route</span>
                                            <span class="<?php echo bus_service_badge_class($trip['leg1_bus_type']); ?>"><?php echo e(bus_service_label($trip['leg1_bus_type'])); ?></span>
                                            <span class="<?php echo bus_service_badge_class($trip['leg2_bus_type']); ?>"><?php echo e(bus_service_label($trip['leg2_bus_type'])); ?></span>
                                            <span class="soft-badge"><?php echo e($trip['leg1_company_name']); ?></span>
                                            <span class="soft-badge"><?php echo e($trip['leg2_company_name']); ?></span>
                                        <?php else: ?>
                                            <span class="<?php echo bus_service_badge_class($trip['bus_type']); ?>"><?php echo e($serviceLabel); ?></span>
                                            <span class="soft-badge"><?php echo e($trip['company_name']); ?></span>
                                            <span class="soft-badge"><?php echo e(strtoupper($trip['layout_type'])); ?> Layout</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="trip-route-line mb-3">
                                        <div>
                                            <small>From</small>
                                            <strong><?php echo e($trip['from_city_name']); ?></strong>
                                        </div>
                                        <div class="trip-route-arrow">→</div>
                                        <?php if ($isMultiHop): ?>
                                            <div class="text-center">
                                                <small>Transfer</small>
                                                <strong><?php echo e($trip['transfer_city_name']); ?></strong>
                                            </div>
                                            <div class="trip-route-arrow">→</div>
                                        <?php endif; ?>
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
                                            <span>Bus Class</span>
                                            <strong><?php echo e($serviceLabel); ?></strong>
                                        </div>
                                    </div>

                                    <?php if ($isMultiHop): ?>
                                        <div class="transfer-leg">
                                            <strong>Leg 1:</strong>
                                            <?php echo e($trip['from_city_name']); ?> → <?php echo e($trip['transfer_city_name']); ?> ·
                                            <?php echo e($trip['leg1_company_name']); ?> · Bus <?php echo e($trip['leg1_bus_number']); ?> ·
                                            <?php echo number_format((float)$trip['leg1_price'], 2); ?> MMK
                                        </div>
                                        <div class="transfer-leg">
                                            <strong>Leg 2:</strong>
                                            <?php echo e($trip['transfer_city_name']); ?> → <?php echo e($trip['to_city_name']); ?> ·
                                            <?php echo e($trip['leg2_company_name']); ?> · Bus <?php echo e($trip['leg2_bus_number']); ?> ·
                                            <?php echo number_format((float)$trip['leg2_price'], 2); ?> MMK
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-lg-4">
                                    <div class="trip-price-panel">
                                        <div class="trip-price-label"><?php echo $isMultiHop ? 'Combined Price' : 'Ticket Price'; ?></div>
                                        <div class="trip-price-value"><?php echo number_format($displayPrice, 2); ?> MMK</div>

                                        <div class="mt-3 mb-4">
                                            <span class="badge text-bg-success">Available Seats: <?php echo (int)$availableSeats; ?></span>
                                        </div>

                                        <a href="<?php echo e($checkoutUrl); ?>" class="btn btn-brand w-100" style="border-radius:16px;">
                                            <?php echo $isMultiHop ? 'Choose Seats for Both Buses' : 'Choose Seats / Book'; ?>
                                        </a>
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
            <p class="text-muted mb-0">Type or choose a city, filter VIP/Normal, and search available buses.</p>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const fromInput = document.getElementById('fromCityInput');
    const toInput = document.getElementById('toCityInput');
    const swapBtn = document.getElementById('swapCitiesBtn');
    const cityButtons = document.querySelectorAll('.popular-city-chip');
    const searchForm = document.getElementById('busSearchForm');
    const travelDateInput = document.getElementById('travelDateInput');
    let lastFocused = fromInput;

    function validateTravelDate() {
        if (!travelDateInput) {
            return true;
        }

        travelDateInput.setCustomValidity('');

        if (travelDateInput.value && travelDateInput.min && travelDateInput.value < travelDateInput.min) {
            travelDateInput.setCustomValidity('Past travel dates are not allowed. Please choose today or a future date.');
            return false;
        }

        return true;
    }

    if (travelDateInput) {
        travelDateInput.addEventListener('input', validateTravelDate);
        travelDateInput.addEventListener('change', function () {
            if (!validateTravelDate()) {
                travelDateInput.reportValidity();
            }
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', function (event) {
            if (!validateTravelDate()) {
                event.preventDefault();
                travelDateInput.reportValidity();
                travelDateInput.focus();
            }
        });
    }

    [fromInput, toInput].forEach(input => {
        input.addEventListener('focus', () => lastFocused = input);
    });

    cityButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const city = this.getAttribute('data-city') || '';
            if (lastFocused) {
                lastFocused.value = city;
                if (lastFocused === fromInput) {
                    toInput.focus();
                }
            }
        });
    });

    if (swapBtn) {
        swapBtn.addEventListener('click', function () {
            const temp = fromInput.value;
            fromInput.value = toInput.value;
            toInput.value = temp;
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
