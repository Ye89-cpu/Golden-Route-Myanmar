<?php
/**
 * Any City Route Helper
 * Makes the demo system searchable from any major Myanmar city to any other city.
 * It can create missing cities, active routes, and open VIP/Normal trips for the selected date.
 */

function grm_any_city_catalog(): array
{
    return [
        'Yangon' => 'Yangon Region',
        'Mandalay' => 'Mandalay Region',
        'Naypyidaw' => 'Nay Pyi Taw',
        'Bagan' => 'Mandalay Region',
        'Taunggyi' => 'Shan State',
        'Inle' => 'Shan State',
        'Nyaung Shwe' => 'Shan State',
        'Kalaw' => 'Shan State',
        'Lashio' => 'Shan State',
        'Kyaing Tong' => 'Shan State',
        'Pathein' => 'Ayeyarwady Region',
        'Mawlamyine' => 'Mon State',
        'Pyin Oo Lwin' => 'Mandalay Region',
        'Bago' => 'Bago Region',
        'Hpa-An' => 'Kayin State',
        'Dawei' => 'Tanintharyi Region',
        'Myeik' => 'Tanintharyi Region',
        'Sittwe' => 'Rakhine State',
        'Ngapali' => 'Rakhine State',
        'Thandwe' => 'Rakhine State',
        'Myitkyina' => 'Kachin State',
        'Loikaw' => 'Kayah State',
        'Magway' => 'Magway Region',
        'Pakokku' => 'Magway Region',
        'Monywa' => 'Sagaing Region',
        'Sagaing' => 'Sagaing Region',
        'Meiktila' => 'Mandalay Region',
        'Pyay' => 'Bago Region',
        'Toungoo' => 'Bago Region',
        'Mogok' => 'Mandalay Region',
        'Muse' => 'Shan State',
        'Myawaddy' => 'Kayin State',
        'Kyaikto' => 'Mon State',
        'Mrauk U' => 'Rakhine State',
        'Hinthada' => 'Ayeyarwady Region',
    ];
}

function grm_normalize_city(string $name): string
{
    $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
    $name = trim(preg_replace('/\s+/', ' ', $name));
    return mb_strtolower($name, 'UTF-8');
}

function grm_slug_city(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : ('city-' . substr(sha1($name), 0, 8));
}

function grm_catalog_state_for_city(string $name): string
{
    $catalog = grm_any_city_catalog();
    $target = grm_normalize_city($name);
    foreach ($catalog as $city => $state) {
        if (grm_normalize_city($city) === $target) {
            return $state;
        }
    }
    return 'Myanmar';
}

function grm_find_or_create_city(mysqli $conn, string $name): int
{
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return 0;
    }

    $sql = "SELECT id, name FROM cities WHERE LOWER(name) = LOWER(?) LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        return (int)$row['id'];
    }

    $state = grm_catalog_state_for_city($name);
    $baseSlug = grm_slug_city($name);
    $slug = $baseSlug;
    $try = 1;

    while (true) {
        $check = $conn->prepare("SELECT id FROM cities WHERE slug = ? LIMIT 1");
        if (!$check) {
            return 0;
        }
        $check->bind_param('s', $slug);
        $check->execute();
        $exists = $check->get_result();
        $hasSlug = $exists && $exists->num_rows > 0;
        $check->close();

        if (!$hasSlug) {
            break;
        }
        $try++;
        $slug = $baseSlug . '-' . $try;
    }

    $insert = $conn->prepare("INSERT INTO cities (name, state_region, slug, is_active) VALUES (?, ?, ?, 1)");
    if (!$insert) {
        return 0;
    }
    $insert->bind_param('sss', $name, $state, $slug);
    $insert->execute();
    $id = (int)$conn->insert_id;
    $insert->close();

    return $id;
}

function grm_get_city_name_by_id(mysqli $conn, int $cityId): string
{
    $stmt = $conn->prepare("SELECT name FROM cities WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return '';
    }
    $stmt->bind_param('i', $cityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['name'] ?? '');
}

function grm_price_duration_estimate(string $fromName, string $toName): array
{
    $pair = grm_normalize_city($fromName) . '|' . grm_normalize_city($toName);
    $reversePair = grm_normalize_city($toName) . '|' . grm_normalize_city($fromName);

    $known = [
        'yangon|mandalay' => [620, 540, 38000],
        'yangon|naypyidaw' => [370, 360, 22000],
        'yangon|bagan' => [610, 570, 38000],
        'yangon|taunggyi' => [650, 600, 42000],
        'yangon|inle' => [660, 610, 43000],
        'yangon|pathein' => [190, 240, 17000],
        'yangon|mawlamyine' => [300, 420, 26000],
        'yangon|bago' => [90, 120, 9000],
        'yangon|hpa-an' => [290, 360, 24000],
        'yangon|dawei' => [615, 660, 45000],
        'yangon|myeik' => [880, 900, 60000],
        'yangon|sittwe' => [650, 720, 52000],
        'yangon|ngapali' => [400, 540, 42000],
        'mandalay|bagan' => [180, 240, 18000],
        'mandalay|taunggyi' => [260, 360, 25000],
        'mandalay|inle' => [320, 420, 27000],
        'mandalay|pyin oo lwin' => [70, 120, 10000],
        'mandalay|naypyidaw' => [270, 300, 20000],
        'mandalay|pathein' => [740, 720, 52000],
        'taunggyi|inle' => [35, 60, 9000],
        'taunggyi|kalaw' => [70, 120, 10000],
        'naypyidaw|taunggyi' => [320, 420, 27000],
        'pathein|ngapali' => [300, 480, 32000],
        'mawlamyine|hpa-an' => [60, 90, 8000],
        'bagan|pakokku' => [45, 70, 7000],
        'mandalay|muse' => [470, 600, 45000],
        'mandalay|myitkyina' => [780, 780, 60000],
        'mandalay|monywa' => [135, 180, 14000],
        'mandalay|mogok' => [200, 300, 25000],
    ];

    if (isset($known[$pair])) {
        return $known[$pair];
    }
    if (isset($known[$reversePair])) {
        return $known[$reversePair];
    }

    $hash = hexdec(substr(md5($pair), 0, 4));
    $distance = 160 + ($hash % 620);
    $duration = max(120, (int)round($distance * 1.05));
    $price = 12000 + (int)(round($distance / 10) * 900);

    return [$distance, $duration, $price];
}

function grm_get_or_create_demo_bus(mysqli $conn, int $companyId, string $serviceClass): ?array
{
    $serviceClass = $serviceClass === 'vip' ? 'vip' : 'normal';
    $types = $serviceClass === 'vip' ? ["vip", "sleeper"] : ["normal", "mini_bus"];
    $placeholders = implode(',', array_fill(0, count($types), '?'));

    $sql = "
        SELECT b.id, b.company_id, b.bus_number, b.bus_type, b.layout_type, b.total_seats
        FROM buses b
        WHERE b.company_id = ?
          AND b.status = 'active'
          AND b.bus_type IN ($placeholders)
        ORDER BY b.id ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = array_merge([$companyId], $types);
        $bindTypes = 'i' . str_repeat('s', count($types));
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        $stmt->bind_param($bindTypes, ...$refs);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            grm_ensure_bus_seats($conn, (int)$row['id'], (string)$row['bus_type'], (int)$row['total_seats']);
            return $row;
        }
    }

    $busType = $serviceClass === 'vip' ? 'vip' : 'normal';
    $layout = $serviceClass === 'vip' ? 'vip' : '2x2';
    $totalSeats = $serviceClass === 'vip' ? 30 : 44;
    $prefix = $serviceClass === 'vip' ? 'AUTO-VIP' : 'AUTO-NOR';
    $busNumber = $prefix . '-' . $companyId;
    $plate = 'AUTO-' . $companyId . '-' . strtoupper($serviceClass);

    $insert = $conn->prepare("
        INSERT INTO buses (company_id, bus_number, plate_number, bus_type, total_seats, layout_type, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE status = 'active', bus_type = VALUES(bus_type), total_seats = VALUES(total_seats), layout_type = VALUES(layout_type)
    ");
    if (!$insert) {
        return null;
    }
    $insert->bind_param('isssis', $companyId, $busNumber, $plate, $busType, $totalSeats, $layout);
    $insert->execute();
    $insert->close();

    $select = $conn->prepare("SELECT id, company_id, bus_number, bus_type, layout_type, total_seats FROM buses WHERE company_id = ? AND bus_number = ? LIMIT 1");
    if (!$select) {
        return null;
    }
    $select->bind_param('is', $companyId, $busNumber);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $select->close();

    if ($row) {
        grm_ensure_bus_seats($conn, (int)$row['id'], (string)$row['bus_type'], (int)$row['total_seats']);
    }

    return $row ?: null;
}

function grm_ensure_bus_seats(mysqli $conn, int $busId, string $busType, int $totalSeats): void
{
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM bus_seats WHERE bus_id = ? AND is_active = 1");
    if (!$countStmt) {
        return;
    }
    $countStmt->bind_param('i', $busId);
    $countStmt->execute();
    $row = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    if ((int)($row['total'] ?? 0) > 0) {
        return;
    }

    $isVip = in_array(strtolower($busType), ['vip', 'sleeper'], true);
    $labels = $isVip ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];
    $seatType = $isVip ? 'vip' : 'normal';
    $cols = count($labels);
    $seatNo = 1;

    $insert = $conn->prepare("
        INSERT INTO bus_seats (bus_id, seat_number, seat_type, row_no, col_no, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    if (!$insert) {
        return;
    }

    $rows = (int)ceil(max(1, $totalSeats) / $cols);
    for ($r = 1; $r <= $rows && $seatNo <= $totalSeats; $r++) {
        for ($c = 1; $c <= $cols && $seatNo <= $totalSeats; $c++) {
            $seatNumber = $r . $labels[$c - 1];
            $insert->bind_param('issii', $busId, $seatNumber, $seatType, $r, $c);
            $insert->execute();
            $seatNo++;
        }
    }
    $insert->close();
}

function grm_pick_company_for_class(mysqli $conn, string $serviceClass, int $selectedCompanyId = 0): int
{
    if ($selectedCompanyId > 0) {
        $stmt = $conn->prepare("
            SELECT id
            FROM companies
            WHERE id = ?
              AND status = 'approved'
              AND company_type IN ('bus_company','both')
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('i', $selectedCompanyId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return (int)$row['id'];
            }
        }
    }

    $types = $serviceClass === 'vip' ? ["vip", "sleeper"] : ["normal", "mini_bus"];
    $placeholders = implode(',', array_fill(0, count($types), '?'));
    $sql = "
        SELECT c.id
        FROM companies c
        INNER JOIN buses b ON b.company_id = c.id
        WHERE c.status = 'approved'
          AND c.company_type IN ('bus_company','both')
          AND b.status = 'active'
          AND b.bus_type IN ($placeholders)
        ORDER BY c.id ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $params = $types;
        $refs = [];
        foreach ($params as $k => $v) {
            $refs[$k] = &$params[$k];
        }
        $stmt->bind_param(str_repeat('s', count($types)), ...$refs);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int)$row['id'];
        }
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM companies
        WHERE status = 'approved'
          AND company_type IN ('bus_company','both')
        ORDER BY id ASC
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return (int)$row['id'];
        }
    }

    return 0;
}

function grm_get_or_create_route(mysqli $conn, int $companyId, int $fromCityId, int $toCityId, float $distanceKm, int $durationMinutes, float $basePrice): int
{
    $select = $conn->prepare("
        SELECT id
        FROM routes
        WHERE company_id = ? AND from_city_id = ? AND to_city_id = ?
        LIMIT 1
    ");
    if ($select) {
        $select->bind_param('iii', $companyId, $fromCityId, $toCityId);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();
        if ($row) {
            return (int)$row['id'];
        }
    }

    $insert = $conn->prepare("
        INSERT INTO routes (company_id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status)
        VALUES (?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE status = 'active', distance_km = VALUES(distance_km), duration_minutes = VALUES(duration_minutes), base_price = VALUES(base_price)
    ");
    if (!$insert) {
        return 0;
    }
    $insert->bind_param('iiidid', $companyId, $fromCityId, $toCityId, $distanceKm, $durationMinutes, $basePrice);
    $insert->execute();
    $insert->close();

    $select = $conn->prepare("
        SELECT id
        FROM routes
        WHERE company_id = ? AND from_city_id = ? AND to_city_id = ?
        LIMIT 1
    ");
    if (!$select) {
        return 0;
    }
    $select->bind_param('iii', $companyId, $fromCityId, $toCityId);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $select->close();

    return (int)($row['id'] ?? 0);
}

function grm_ensure_open_trip(mysqli $conn, int $companyId, int $routeId, int $busId, string $travelDate, string $serviceClass, float $price, int $durationMinutes): int
{
    $select = $conn->prepare("
        SELECT id
        FROM trips
        WHERE route_id = ?
          AND bus_id = ?
          AND trip_date = ?
          AND status = 'open'
          AND available_seats > 0
        ORDER BY departure_datetime ASC
        LIMIT 1
    ");
    if ($select) {
        $select->bind_param('iis', $routeId, $busId, $travelDate);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();
        if ($row) {
            return (int)$row['id'];
        }
    }

    $departureTime = $serviceClass === 'vip' ? '08:00:00' : '09:30:00';
    if ($durationMinutes >= 480) {
        $departureTime = $serviceClass === 'vip' ? '19:00:00' : '20:00:00';
    }

    $departure = $travelDate . ' ' . $departureTime;
    $arrival = date('Y-m-d H:i:s', strtotime($departure . ' +' . max(60, $durationMinutes) . ' minutes'));

    $seatStmt = $conn->prepare("SELECT COUNT(*) AS total FROM bus_seats WHERE bus_id = ? AND is_active = 1");
    $availableSeats = 0;
    if ($seatStmt) {
        $seatStmt->bind_param('i', $busId);
        $seatStmt->execute();
        $seatRow = $seatStmt->get_result()->fetch_assoc();
        $seatStmt->close();
        $availableSeats = (int)($seatRow['total'] ?? 0);
    }
    if ($availableSeats <= 0) {
        $busStmt = $conn->prepare("SELECT total_seats FROM buses WHERE id = ? LIMIT 1");
        if ($busStmt) {
            $busStmt->bind_param('i', $busId);
            $busStmt->execute();
            $busRow = $busStmt->get_result()->fetch_assoc();
            $busStmt->close();
            $availableSeats = (int)($busRow['total_seats'] ?? 0);
        }
    }
    if ($availableSeats <= 0) {
        $availableSeats = $serviceClass === 'vip' ? 30 : 44;
    }

    $insert = $conn->prepare("
        INSERT IGNORE INTO trips (company_id, route_id, bus_id, schedule_template_id, trip_date, departure_datetime, arrival_datetime, price, available_seats, status)
        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, 'open')
    ");
    if (!$insert) {
        return 0;
    }
    $insert->bind_param('iiisssdi', $companyId, $routeId, $busId, $travelDate, $departure, $arrival, $price, $availableSeats);
    $insert->execute();
    $insert->close();

    $select = $conn->prepare("
        SELECT id
        FROM trips
        WHERE company_id = ? AND route_id = ? AND bus_id = ? AND trip_date = ? AND departure_datetime = ?
        LIMIT 1
    ");
    if (!$select) {
        return 0;
    }
    $select->bind_param('iiiss', $companyId, $routeId, $busId, $travelDate, $departure);
    $select->execute();
    $row = $select->get_result()->fetch_assoc();
    $select->close();

    return (int)($row['id'] ?? 0);
}

function grm_ensure_any_city_direct_options(mysqli $conn, int $fromCityId, int $toCityId, string $travelDate, string $serviceType = 'all', int $selectedCompanyId = 0): void
{
    if ($fromCityId <= 0 || $toCityId <= 0 || $fromCityId === $toCityId || $travelDate === '') {
        return;
    }

    $fromName = grm_get_city_name_by_id($conn, $fromCityId);
    $toName = grm_get_city_name_by_id($conn, $toCityId);
    if ($fromName === '' || $toName === '') {
        return;
    }

    [$distanceKm, $durationMinutes, $normalPrice] = grm_price_duration_estimate($fromName, $toName);

    $classes = ['vip', 'normal'];
    if ($serviceType === 'vip') {
        $classes = ['vip'];
    } elseif ($serviceType === 'normal') {
        $classes = ['normal'];
    }

    foreach ($classes as $class) {
        $companyId = grm_pick_company_for_class($conn, $class, $selectedCompanyId);
        if ($companyId <= 0) {
            continue;
        }

        $bus = grm_get_or_create_demo_bus($conn, $companyId, $class);
        if (!$bus) {
            continue;
        }

        $price = $class === 'vip' ? round($normalPrice * 1.35, -2) : (float)$normalPrice;
        $routeId = grm_get_or_create_route($conn, $companyId, $fromCityId, $toCityId, (float)$distanceKm, (int)$durationMinutes, (float)$normalPrice);
        if ($routeId <= 0) {
            continue;
        }

        grm_ensure_open_trip($conn, $companyId, $routeId, (int)$bus['id'], $travelDate, $class, $price, (int)$durationMinutes);
    }
}
