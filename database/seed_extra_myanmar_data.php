<?php
// database/seed_extra_myanmar_data.php
// Run once from browser or terminal to add more demo Myanmar bus companies, routes, buses, logos, schedules and tour packages.

require_once __DIR__ . '/../includes/db.php';

$conn = getDBConnection();
$conn->begin_transaction();

function seed_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

function seed_get_id(mysqli $conn, string $table, string $column, string $value): int
{
    $allowed = ['cities', 'companies'];
    if (!in_array($table, $allowed, true)) {
        throw new Exception('Invalid table lookup.');
    }

    $sql = "SELECT id FROM {$table} WHERE {$column} = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

function seed_city(mysqli $conn, string $name, string $region): int
{
    $slug = seed_slug($name);

    $sql = "
        INSERT INTO cities (name, state_region, slug, is_active, created_at, updated_at)
        VALUES (?, ?, ?, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            state_region = VALUES(state_region),
            is_active = 1,
            updated_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sss', $name, $region, $slug);
    $stmt->execute();
    $stmt->close();

    return seed_get_id($conn, 'cities', 'slug', $slug);
}

function seed_logo(string $name, string $short, string $bg1, string $bg2): string
{
    $dir = dirname(__DIR__) . '/assets/company_logos';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $file = seed_slug($name) . '.svg';
    $path = $dir . '/' . $file;
    $relative = 'assets/company_logos/' . $file;

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeShort = htmlspecialchars($short, ENT_QUOTES, 'UTF-8');

    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="420" height="220" viewBox="0 0 420 220">
  <defs>
    <linearGradient id="g" x1="0" x2="1" y1="0" y2="1">
      <stop offset="0%" stop-color="{$bg1}"/>
      <stop offset="100%" stop-color="{$bg2}"/>
    </linearGradient>
  </defs>
  <rect width="420" height="220" rx="34" fill="url(#g)"/>
  <circle cx="94" cy="95" r="48" fill="rgba(255,255,255,0.22)"/>
  <text x="94" y="112" text-anchor="middle" font-size="38" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">{$safeShort}</text>
  <text x="210" y="162" text-anchor="middle" font-size="25" font-family="Arial, sans-serif" font-weight="700" fill="#ffffff">{$safeName}</text>
  <path d="M245 78h62c22 0 40 18 40 40v5h-142v-5c0-22 18-40 40-40z" fill="rgba(255,255,255,0.18)"/>
  <circle cx="236" cy="134" r="14" fill="#ffffff"/>
  <circle cx="325" cy="134" r="14" fill="#ffffff"/>
</svg>
SVG;

    file_put_contents($path, $svg);
    @chmod($path, 0666);

    return $relative;
}

function seed_company(mysqli $conn, array $company): int
{
    $logo = seed_logo($company['name'], $company['short'], $company['color1'], $company['color2']);

    $sql = "
        INSERT INTO companies
            (name, company_type, license, phone, email, address, description, logo, status, approved_at, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            company_type = VALUES(company_type),
            phone = VALUES(phone),
            email = VALUES(email),
            address = VALUES(address),
            description = VALUES(description),
            logo = VALUES(logo),
            status = 'approved',
            approved_at = COALESCE(approved_at, NOW()),
            updated_at = NOW()
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssss',
        $company['name'],
        $company['type'],
        $company['license'],
        $company['phone'],
        $company['email'],
        $company['address'],
        $company['description'],
        $logo
    );
    $stmt->execute();
    $stmt->close();

    return seed_get_id($conn, 'companies', 'license', $company['license']);
}

function seed_bus(mysqli $conn, int $companyId, string $busNumber, string $plateNumber, string $busType, int $totalSeats, string $layout): int
{
    $sql = "
        INSERT INTO buses (company_id, bus_number, plate_number, bus_type, total_seats, layout_type, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            bus_type = VALUES(bus_type),
            total_seats = VALUES(total_seats),
            layout_type = VALUES(layout_type),
            status = 'active',
            updated_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssis', $companyId, $busNumber, $plateNumber, $busType, $totalSeats, $layout);
    $stmt->execute();
    $stmt->close();

    $lookup = "SELECT id FROM buses WHERE company_id = ? AND bus_number = ? LIMIT 1";
    $stmt = $conn->prepare($lookup);
    $stmt->bind_param('is', $companyId, $busNumber);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

function seed_bus_seats(mysqli $conn, int $busId, int $totalSeats, string $layout): void
{
    $check = $conn->prepare("SELECT COUNT(*) AS total FROM bus_seats WHERE bus_id = ?");
    $check->bind_param('i', $busId);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if ((int)($row['total'] ?? 0) > 0) {
        return;
    }

    $cols = in_array($layout, ['2x1', 'vip', 'sleeper'], true) ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];
    $seatType = in_array($layout, ['vip', '2x1'], true) ? 'vip' : ($layout === 'sleeper' ? 'sleeper' : 'normal');

    $insert = $conn->prepare("INSERT INTO bus_seats (bus_id, seat_number, seat_type, row_no, col_no, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $count = 0;
    $rowNo = 1;

    while ($count < $totalSeats) {
        foreach ($cols as $index => $col) {
            if ($count >= $totalSeats) {
                break;
            }
            $seatNumber = $rowNo . $col;
            $colNo = $index + 1;
            $insert->bind_param('issii', $busId, $seatNumber, $seatType, $rowNo, $colNo);
            $insert->execute();
            $count++;
        }
        $rowNo++;
    }

    $insert->close();
}

function seed_route(mysqli $conn, int $companyId, int $fromCityId, int $toCityId, float $distanceKm, int $durationMinutes, float $basePrice): int
{
    $sql = "
        INSERT INTO routes (company_id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            distance_km = VALUES(distance_km),
            duration_minutes = VALUES(duration_minutes),
            base_price = VALUES(base_price),
            status = 'active',
            updated_at = NOW()
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiidid', $companyId, $fromCityId, $toCityId, $distanceKm, $durationMinutes, $basePrice);
    $stmt->execute();
    $stmt->close();

    $lookup = "SELECT id FROM routes WHERE company_id = ? AND from_city_id = ? AND to_city_id = ? LIMIT 1";
    $stmt = $conn->prepare($lookup);
    $stmt->bind_param('iii', $companyId, $fromCityId, $toCityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['id'] ?? 0);
}

function seed_schedule(mysqli $conn, int $companyId, int $routeId, int $busId, string $departure, string $arrival, float $price): void
{
    $check = $conn->prepare("SELECT id FROM schedule_templates WHERE company_id = ? AND route_id = ? AND bus_id = ? AND departure_time = ? LIMIT 1");
    $check->bind_param('iiis', $companyId, $routeId, $busId, $departure);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    $check->close();

    if ($exists) {
        $update = $conn->prepare("UPDATE schedule_templates SET arrival_time = ?, price = ?, active_from = CURDATE(), active_to = DATE_ADD(CURDATE(), INTERVAL 90 DAY), status = 'active', updated_at = NOW() WHERE id = ?");
        $id = (int)$exists['id'];
        $update->bind_param('sdi', $arrival, $price, $id);
        $update->execute();
        $update->close();
        return;
    }

    $sql = "
        INSERT INTO schedule_templates
            (company_id, route_id, bus_id, departure_time, arrival_time, price, frequency, weekdays, active_from, active_to, status, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, 'daily', NULL, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active', NOW(), NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiissd', $companyId, $routeId, $busId, $departure, $arrival, $price);
    $stmt->execute();
    $stmt->close();
}

function seed_tour_package(mysqli $conn, int $companyId, array $package): int
{
    $check = $conn->prepare("SELECT id FROM tour_packages WHERE company_id = ? AND title = ? LIMIT 1");
    $check->bind_param('is', $companyId, $package['title']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $id = (int)$existing['id'];
        $sql = "
            UPDATE tour_packages
            SET description = ?, price = ?, duration_days = ?, hotel_info = ?, transport_info = ?, route_info = ?, itinerary = ?, included_services = ?, excluded_services = ?, status = 'active', updated_at = NOW()
            WHERE id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'sdissssssi',
            $package['description'],
            $package['price'],
            $package['days'],
            $package['hotel'],
            $package['transport'],
            $package['route'],
            $package['itinerary'],
            $package['included'],
            $package['excluded'],
            $id
        );
        $stmt->execute();
        $stmt->close();
        return $id;
    }

    $sql = "
        INSERT INTO tour_packages
            (company_id, title, description, price, duration_days, hotel_info, transport_info, route_info, itinerary, included_services, excluded_services, cover_image, status, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 'active', NOW(), NOW())
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'issdissssss',
        $companyId,
        $package['title'],
        $package['description'],
        $package['price'],
        $package['days'],
        $package['hotel'],
        $package['transport'],
        $package['route'],
        $package['itinerary'],
        $package['included'],
        $package['excluded']
    );
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id;
}

function seed_tour_batch(mysqli $conn, int $companyId, int $packageId, string $startOffset, int $days, int $capacity, float $price): void
{
    $startExpr = "DATE_ADD(CURDATE(), INTERVAL {$startOffset} DAY)";
    $endExpr = "DATE_ADD({$startExpr}, INTERVAL " . max(0, $days - 1) . " DAY)";

    $sql = "
        INSERT INTO tour_batches (company_id, tour_package_id, start_date, end_date, capacity, booked_count, price, status, created_at, updated_at)
        SELECT ?, ?, {$startExpr}, {$endExpr}, ?, 0, ?, 'open', NOW(), NOW()
        WHERE NOT EXISTS (
            SELECT 1 FROM tour_batches WHERE tour_package_id = ? AND start_date = {$startExpr}
        )
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiidi', $companyId, $packageId, $capacity, $price, $packageId);
    $stmt->execute();
    $stmt->close();
}

try {
    $cities = [
        ['Hpa-An', 'Kayin State'],
        ['Dawei', 'Tanintharyi Region'],
        ['Myeik', 'Tanintharyi Region'],
        ['Sittwe', 'Rakhine State'],
        ['Ngapali', 'Rakhine State'],
        ['Kalaw', 'Shan State'],
        ['Myitkyina', 'Kachin State'],
        ['Loikaw', 'Kayah State'],
    ];

    foreach ($cities as $city) {
        seed_city($conn, $city[0], $city[1]);
    }

    $companies = [
        ['name' => 'JJ Myanmar Express', 'short' => 'JJ', 'type' => 'bus_company', 'license' => 'LIC-BUS-JJ-2026', 'phone' => '09451000101', 'email' => 'jjexpress@demo.com', 'address' => 'Aung Mingalar Highway Station, Yangon', 'description' => 'Premium highway express routes across Myanmar.', 'color1' => '#1D4ED8', 'color2' => '#0F172A'],
        ['name' => 'Elite Highway Express', 'short' => 'EH', 'type' => 'bus_company', 'license' => 'LIC-BUS-ELITE-2026', 'phone' => '09451000102', 'email' => 'elitehighway@demo.com', 'address' => 'Chan Mya Shwe Pyi Highway Station, Mandalay', 'description' => 'Comfortable VIP and normal coaches for major cities.', 'color1' => '#B45309', 'color2' => '#111827'],
        ['name' => 'Bagan Min Thar Express', 'short' => 'BM', 'type' => 'bus_company', 'license' => 'LIC-BUS-BAGAN-2026', 'phone' => '09451000103', 'email' => 'baganminthar@demo.com', 'address' => 'Nyaung U, Bagan', 'description' => 'Routes connecting Bagan, Mandalay, Yangon and Shan State.', 'color1' => '#7C2D12', 'color2' => '#F59E0B'],
        ['name' => 'Mandalar Minn Express', 'short' => 'MM', 'type' => 'bus_company', 'license' => 'LIC-BUS-MANDALAR-2026', 'phone' => '09451000104', 'email' => 'mandalarminn@demo.com', 'address' => 'Mandalay', 'description' => 'Reliable daily routes for upper and lower Myanmar.', 'color1' => '#065F46', 'color2' => '#10B981'],
        ['name' => 'Khaing Mandalay Express', 'short' => 'KM', 'type' => 'bus_company', 'license' => 'LIC-BUS-KHAING-2026', 'phone' => '09451000105', 'email' => 'khaingmandalay@demo.com', 'address' => 'Mandalay', 'description' => 'Budget friendly routes with active daily schedules.', 'color1' => '#BE123C', 'color2' => '#4C1D95'],
        ['name' => 'Seven Diamond Travels', 'short' => 'SD', 'type' => 'both', 'license' => 'LIC-BOTH-SEVENDIAMOND-2026', 'phone' => '09451000106', 'email' => 'sevendiamond@demo.com', 'address' => 'Yangon', 'description' => 'Bus routes and domestic tour packages for demo system.', 'color1' => '#9333EA', 'color2' => '#1E1B4B'],
        ['name' => 'Myanmar Nature Holidays', 'short' => 'NH', 'type' => 'tour_operator', 'license' => 'LIC-TOUR-NATURE-2026', 'phone' => '09451000107', 'email' => 'natureholidays@demo.com', 'address' => 'Yangon', 'description' => 'Nature, beach, mountain and cultural tour packages.', 'color1' => '#15803D', 'color2' => '#0C4A6E'],
    ];

    $ids = [];
    foreach ($companies as $company) {
        $ids[$company['name']] = seed_company($conn, $company);
    }

    // Update existing company logos too.
    $existingLogoCompanies = [
        ['Shwe Mandalar Express', 'SM', '#B45309', '#7C2D12'],
        ['Ayar Highway Express', 'AY', '#0369A1', '#075985'],
        ['Royal Lotus Coaches', 'RL', '#7E22CE', '#312E81'],
        ['Royal Lotus Bus Lines', 'RL', '#6D28D9', '#1E1B4B'],
        ['Sunrise Delta Express', 'SD', '#F59E0B', '#92400E'],
        ['Myanmar Explorer Group', 'ME', '#0F766E', '#134E4A'],
        ['Myanmar Star Travel', 'MS', '#2563EB', '#172554'],
        ['Lotus Paradise Tours', 'LP', '#BE185D', '#831843'],
        ['Heritage Horizon Travels', 'HH', '#CA8A04', '#713F12'],
        ['Golden Land Holidays', 'GL', '#D97706', '#78350F'],
    ];

    foreach ($existingLogoCompanies as $row) {
        $logo = seed_logo($row[0], $row[1], $row[2], $row[3]);
        $stmt = $conn->prepare("UPDATE companies SET logo = ?, updated_at = NOW() WHERE name = ?");
        $stmt->bind_param('ss', $logo, $row[0]);
        $stmt->execute();
        $stmt->close();
    }

    $cityId = [];
    foreach (['Yangon','Mandalay','Naypyidaw','Bagan','Taunggyi','Inle','Pyin Oo Lwin','Bago','Mawlamyine','Pathein','Hpa-An','Dawei','Myeik','Sittwe','Ngapali','Kalaw','Myitkyina','Loikaw'] as $name) {
        $cityId[$name] = seed_get_id($conn, 'cities', 'name', $name);
    }

    $busPlan = [
        'JJ Myanmar Express' => [
            ['JJ-001', 'YGN-1J/1001', 'vip', 31, '2x1'],
            ['JJ-002', 'YGN-1J/1002', 'normal', 44, '2x2'],
        ],
        'Elite Highway Express' => [
            ['EL-001', 'MDY-2E/2001', 'vip', 31, '2x1'],
            ['EL-002', 'MDY-2E/2002', 'sleeper', 24, 'sleeper'],
        ],
        'Bagan Min Thar Express' => [
            ['BM-001', 'BGN-3B/3001', 'vip', 28, 'vip'],
            ['BM-002', 'BGN-3B/3002', 'normal', 40, '2x2'],
        ],
        'Mandalar Minn Express' => [
            ['MM-001', 'MDY-4M/4001', 'vip', 30, 'vip'],
            ['MM-002', 'MDY-4M/4002', 'normal', 44, '2x2'],
        ],
        'Khaing Mandalay Express' => [
            ['KM-001', 'MDY-5K/5001', 'normal', 44, '2x2'],
            ['KM-002', 'MDY-5K/5002', 'vip', 28, 'vip'],
        ],
        'Seven Diamond Travels' => [
            ['SDT-001', 'YGN-7D/7001', 'vip', 30, 'vip'],
            ['SDT-002', 'YGN-7D/7002', 'normal', 44, '2x2'],
        ],
    ];

    $busIds = [];
    foreach ($busPlan as $companyName => $buses) {
        $companyId = $ids[$companyName];
        foreach ($buses as $bus) {
            $busId = seed_bus($conn, $companyId, $bus[0], $bus[1], $bus[2], $bus[3], $bus[4]);
            seed_bus_seats($conn, $busId, $bus[3], $bus[4]);
            $busIds[$companyName][] = $busId;
        }
    }

    $routePlan = [
        'JJ Myanmar Express' => [
            ['Yangon', 'Mandalay', 625, 540, 28500, '08:00:00', '17:00:00', 28500, 0],
            ['Yangon', 'Bagan', 610, 600, 32000, '20:00:00', '06:00:00', 34000, 1],
            ['Yangon', 'Taunggyi', 650, 720, 38000, '18:30:00', '06:30:00', 39000, 0],
            ['Yangon', 'Ngapali', 390, 480, 36000, '21:00:00', '05:00:00', 36000, 1],
        ],
        'Elite Highway Express' => [
            ['Mandalay', 'Yangon', 625, 540, 28500, '09:00:00', '18:00:00', 28500, 0],
            ['Mandalay', 'Myitkyina', 770, 900, 45000, '16:00:00', '07:00:00', 47000, 1],
            ['Mandalay', 'Taunggyi', 260, 420, 26000, '07:30:00', '14:30:00', 26000, 0],
        ],
        'Bagan Min Thar Express' => [
            ['Bagan', 'Yangon', 610, 600, 32000, '19:30:00', '05:30:00', 34000, 0],
            ['Bagan', 'Mandalay', 180, 240, 15000, '08:30:00', '12:30:00', 15000, 1],
            ['Bagan', 'Kalaw', 270, 420, 24000, '09:00:00', '16:00:00', 24000, 0],
        ],
        'Mandalar Minn Express' => [
            ['Yangon', 'Hpa-An', 290, 360, 22000, '07:00:00', '13:00:00', 22000, 0],
            ['Yangon', 'Mawlamyine', 300, 390, 23000, '08:00:00', '14:30:00', 23000, 1],
            ['Yangon', 'Dawei', 615, 780, 42000, '17:00:00', '06:00:00', 42000, 0],
        ],
        'Khaing Mandalay Express' => [
            ['Mandalay', 'Pyin Oo Lwin', 70, 120, 9500, '08:00:00', '10:00:00', 9500, 0],
            ['Mandalay', 'Naypyidaw', 270, 300, 18000, '13:00:00', '18:00:00', 18000, 1],
            ['Mandalay', 'Loikaw', 360, 540, 30000, '18:00:00', '03:00:00', 31000, 0],
        ],
        'Seven Diamond Travels' => [
            ['Yangon', 'Pathein', 190, 300, 17000, '07:00:00', '12:00:00', 17000, 0],
            ['Yangon', 'Sittwe', 620, 840, 46000, '16:30:00', '06:30:00', 47000, 1],
            ['Dawei', 'Myeik', 230, 360, 24000, '09:00:00', '15:00:00', 24000, 0],
        ],
    ];

    foreach ($routePlan as $companyName => $routes) {
        $companyId = $ids[$companyName];
        foreach ($routes as $route) {
            [$from, $to, $km, $minutes, $basePrice, $dep, $arr, $price, $busIndex] = $route;
            $routeId = seed_route($conn, $companyId, $cityId[$from], $cityId[$to], (float)$km, (int)$minutes, (float)$basePrice);
            seed_schedule($conn, $companyId, $routeId, $busIds[$companyName][$busIndex], $dep, $arr, (float)$price);
        }
    }

    $tourCompanies = [
        'Seven Diamond Travels' => $ids['Seven Diamond Travels'],
        'Myanmar Nature Holidays' => $ids['Myanmar Nature Holidays'],
    ];

    $tourPackages = [
        'Seven Diamond Travels' => [
            ['title' => 'Ngapali Beach Relaxation', 'description' => '3D2N beach holiday with local seafood and sunset viewpoint.', 'price' => 310000.00, 'days' => 3, 'hotel' => 'Beach hotel with breakfast', 'transport' => 'Express bus + local transfer', 'route' => 'Yangon - Ngapali - Yangon', 'itinerary' => 'Day 1 travel and beach sunset. Day 2 island and seafood experience. Day 3 morning market and return.', 'included' => 'Hotel, breakfast, transport, local guide', 'excluded' => 'Lunch, dinner, personal expenses'],
            ['title' => 'Hpa-An Nature Weekend', 'description' => '2D1N Kayin State cave, mountain and river experience.', 'price' => 145000.00, 'days' => 2, 'hotel' => 'Standard hotel with breakfast', 'transport' => 'Tour van', 'route' => 'Yangon - Hpa-An - Yangon', 'itinerary' => 'Day 1 Saddan cave and river. Day 2 Mount Zwegabin viewpoint and return.', 'included' => 'Hotel, transport, guide', 'excluded' => 'Entrance fees, meals not listed'],
        ],
        'Myanmar Nature Holidays' => [
            ['title' => 'Kalaw Trekking Experience', 'description' => '3D2N soft trekking route around Kalaw villages.', 'price' => 275000.00, 'days' => 3, 'hotel' => 'Guesthouse and local stay', 'transport' => 'Private van', 'route' => 'Mandalay - Kalaw - Inle', 'itinerary' => 'Day 1 Kalaw arrival. Day 2 village trekking. Day 3 transfer to Inle.', 'included' => 'Guide, accommodation, breakfast, transport', 'excluded' => 'Personal expenses, camera fee'],
            ['title' => 'Dawei Coastal Discovery', 'description' => '4D3N Dawei beach and old town package.', 'price' => 385000.00, 'days' => 4, 'hotel' => '3-star hotel', 'transport' => 'Express bus + local car', 'route' => 'Yangon - Dawei - Maungmagan Beach', 'itinerary' => 'Day 1 travel. Day 2 old town and beach. Day 3 island viewpoint. Day 4 return.', 'included' => 'Hotel, breakfast, transport, guide', 'excluded' => 'Personal expense, meals not listed'],
            ['title' => 'Myitkyina Kachin Culture Trip', 'description' => '4D3N northern Myanmar culture and river experience.', 'price' => 420000.00, 'days' => 4, 'hotel' => 'City hotel with breakfast', 'transport' => 'Domestic transport arrangement', 'route' => 'Mandalay - Myitkyina - Irrawaddy River', 'itinerary' => 'Day 1 arrival. Day 2 Myitsone area. Day 3 local market and culture. Day 4 return.', 'included' => 'Hotel, breakfast, guide, local transport', 'excluded' => 'Flights, personal expenses'],
        ],
    ];

    foreach ($tourPackages as $companyName => $packages) {
        $companyId = $tourCompanies[$companyName];
        foreach ($packages as $package) {
            $packageId = seed_tour_package($conn, $companyId, $package);
            seed_tour_batch($conn, $companyId, $packageId, '7', (int)$package['days'], 20, (float)$package['price']);
            seed_tour_batch($conn, $companyId, $packageId, '14', (int)$package['days'], 20, (float)$package['price']);
            seed_tour_batch($conn, $companyId, $packageId, '21', (int)$package['days'], 20, (float)$package['price']);
        }
    }

    $conn->commit();
    $conn->close();

    $message = 'Extra Myanmar demo data added successfully. Now run auto_run.php to generate trips from new schedules.';
} catch (Throwable $e) {
    $conn->rollback();
    $conn->close();
    $message = 'Seed failed: ' . $e->getMessage();
}

if (PHP_SAPI === 'cli') {
    echo $message . PHP_EOL;
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Seed Extra Myanmar Data</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f5f7fb; padding:40px; }
        .box { max-width:780px; margin:auto; background:white; border-radius:16px; padding:28px; box-shadow:0 10px 30px rgba(0,0,0,.08); }
        code { background:#111827; color:#e5e7eb; padding:10px 14px; border-radius:8px; display:block; margin-top:12px; }
        a { display:inline-block; margin-top:14px; background:#0d6efd; color:white; text-decoration:none; padding:10px 16px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Golden Route Myanmar Seed Result</h2>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Next run:</p>
        <code>http://localhost/Golden-Route-Myanmar/auto_run.php</code>
        <a href="../admin/dashboard.php">Back to Admin Dashboard</a>
    </div>
</body>
</html>
