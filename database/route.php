<?php
// database/seed_vip_normal_search_demo.php
// Run once to add many Myanmar city options and demo VIP/Normal bus schedules.
// Then the search page can show both VIP and Normal choices for the same route.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auto_schedule_runner.php';

$conn = getDBConnection();
$conn->begin_transaction();

function grm_seed_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string)$text, '-');
}

function grm_seed_city(mysqli $conn, string $name, string $region): int
{
    $slug = grm_seed_slug($name);

    $stmt = $conn->prepare("SELECT id FROM cities WHERE name = ? OR slug = ? LIMIT 1");
    $stmt->bind_param('ss', $name, $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int)$row['id'];
        $update = $conn->prepare("UPDATE cities SET state_region = ?, slug = ?, is_active = 1, updated_at = NOW() WHERE id = ?");
        $update->bind_param('ssi', $region, $slug, $id);
        $update->execute();
        $update->close();
        return $id;
    }

    $insert = $conn->prepare("INSERT INTO cities (name, state_region, slug, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
    $insert->bind_param('sss', $name, $region, $slug);
    $insert->execute();
    $id = (int)$insert->insert_id;
    $insert->close();

    return $id;
}

function grm_seed_company(mysqli $conn, string $name, string $license, string $type = 'bus_company'): int
{
    $stmt = $conn->prepare("SELECT id FROM companies WHERE license = ? LIMIT 1");
    $stmt->bind_param('s', $license);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int)$row['id'];
        $update = $conn->prepare("UPDATE companies SET name = ?, company_type = ?, status = 'approved', approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ?");
        $update->bind_param('ssi', $name, $type, $id);
        $update->execute();
        $update->close();
        return $id;
    }

    $phone = '09' . random_int(410000000, 499999999);
    $email = strtolower(str_replace(' ', '', $name)) . '@demo.com';
    $address = 'Myanmar';
    $description = 'Demo VIP and Normal highway bus schedules for Golden Route Myanmar.';
    $logo = 'assets/images/bus.png';

    $insert = $conn->prepare("INSERT INTO companies (name, company_type, license, phone, email, address, description, logo, status, approved_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), NOW(), NOW())");
    $insert->bind_param('ssssssss', $name, $type, $license, $phone, $email, $address, $description, $logo);
    $insert->execute();
    $id = (int)$insert->insert_id;
    $insert->close();

    return $id;
}

function grm_seed_bus(mysqli $conn, int $companyId, string $busNumber, string $plate, string $busType, int $seats, string $layout): int
{
    $stmt = $conn->prepare("SELECT id FROM buses WHERE company_id = ? AND bus_number = ? LIMIT 1");
    $stmt->bind_param('is', $companyId, $busNumber);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int)$row['id'];
        $update = $conn->prepare("UPDATE buses SET plate_number = ?, bus_type = ?, total_seats = ?, layout_type = ?, status = 'active', updated_at = NOW() WHERE id = ?");
        $update->bind_param('ssisi', $plate, $busType, $seats, $layout, $id);
        $update->execute();
        $update->close();
        return $id;
    }

    $insert = $conn->prepare("INSERT INTO buses (company_id, bus_number, plate_number, bus_type, total_seats, layout_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
    $insert->bind_param('isssis', $companyId, $busNumber, $plate, $busType, $seats, $layout);
    $insert->execute();
    $id = (int)$insert->insert_id;
    $insert->close();

    return $id;
}

function grm_seed_seats(mysqli $conn, int $busId, int $totalSeats, string $layout): void
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM bus_seats WHERE bus_id = ?");
    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ((int)($row['total'] ?? 0) > 0) {
        return;
    }

    $cols = in_array($layout, ['2x1', 'vip', 'sleeper'], true) ? ['A', 'B', 'C'] : ['A', 'B', 'C', 'D'];
    $seatType = in_array($layout, ['2x1', 'vip'], true) ? 'vip' : ($layout === 'sleeper' ? 'sleeper' : 'normal');
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

function grm_seed_route(mysqli $conn, int $companyId, int $fromCityId, int $toCityId, float $distance, int $minutes, float $price): int
{
    $stmt = $conn->prepare("SELECT id FROM routes WHERE company_id = ? AND from_city_id = ? AND to_city_id = ? LIMIT 1");
    $stmt->bind_param('iii', $companyId, $fromCityId, $toCityId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int)$row['id'];
        $update = $conn->prepare("UPDATE routes SET distance_km = ?, duration_minutes = ?, base_price = ?, status = 'active', updated_at = NOW() WHERE id = ?");
        $update->bind_param('didi', $distance, $minutes, $price, $id);
        $update->execute();
        $update->close();
        return $id;
    }

    $insert = $conn->prepare("INSERT INTO routes (company_id, from_city_id, to_city_id, distance_km, duration_minutes, base_price, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
    $insert->bind_param('iiidid', $companyId, $fromCityId, $toCityId, $distance, $minutes, $price);
    $insert->execute();
    $id = (int)$insert->insert_id;
    $insert->close();

    return $id;
}

function grm_seed_schedule(mysqli $conn, int $companyId, int $routeId, int $busId, string $dep, string $arr, float $price): void
{
    $stmt = $conn->prepare("SELECT id FROM schedule_templates WHERE company_id = ? AND route_id = ? AND bus_id = ? AND departure_time = ? LIMIT 1");
    $stmt->bind_param('iiis', $companyId, $routeId, $busId, $dep);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $id = (int)$row['id'];
        $update = $conn->prepare("UPDATE schedule_templates SET arrival_time = ?, price = ?, active_from = CURDATE(), active_to = DATE_ADD(CURDATE(), INTERVAL 90 DAY), status = 'active', updated_at = NOW() WHERE id = ?");
        $update->bind_param('sdi', $arr, $price, $id);
        $update->execute();
        $update->close();
        return;
    }

    $insert = $conn->prepare("INSERT INTO schedule_templates (company_id, route_id, bus_id, departure_time, arrival_time, price, frequency, weekdays, active_from, active_to, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'daily', NULL, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active', NOW(), NOW())");
    $insert->bind_param('iiissd', $companyId, $routeId, $busId, $dep, $arr, $price);
    $insert->execute();
    $insert->close();
}

try {
    $cityData = [
        ['Yangon', 'Yangon Region'], ['Mandalay', 'Mandalay Region'], ['Naypyidaw', 'Nay Pyi Taw'],
        ['Bagan', 'Mandalay Region'], ['Nyaung U', 'Mandalay Region'], ['Taunggyi', 'Shan State'],
        ['Inle', 'Shan State'], ['Nyaung Shwe', 'Shan State'], ['Kalaw', 'Shan State'],
        ['Lashio', 'Shan State'], ['Muse', 'Shan State'], ['Pyin Oo Lwin', 'Mandalay Region'],
        ['Bago', 'Bago Region'], ['Taungoo', 'Bago Region'], ['Pyay', 'Bago Region'],
        ['Mawlamyine', 'Mon State'], ['Kyaikto', 'Mon State'], ['Hpa-An', 'Kayin State'],
        ['Myawaddy', 'Kayin State'], ['Pathein', 'Ayeyarwady Region'], ['Hinthada', 'Ayeyarwady Region'],
        ['Myaungmya', 'Ayeyarwady Region'], ['Pyapon', 'Ayeyarwady Region'], ['Monywa', 'Sagaing Region'],
        ['Shwebo', 'Sagaing Region'], ['Meiktila', 'Mandalay Region'], ['Magway', 'Magway Region'],
        ['Pakokku', 'Magway Region'], ['Sittwe', 'Rakhine State'], ['Thandwe', 'Rakhine State'],
        ['Ngapali', 'Rakhine State'], ['Dawei', 'Tanintharyi Region'], ['Myeik', 'Tanintharyi Region'],
        ['Kawthaung', 'Tanintharyi Region'], ['Myitkyina', 'Kachin State'], ['Bhamo', 'Kachin State'],
        ['Loikaw', 'Kayah State']
    ];

    $cityIds = [];
    foreach ($cityData as $row) {
        $cityIds[$row[0]] = grm_seed_city($conn, $row[0], $row[1]);
    }

    $vipCompanyId = grm_seed_company($conn, 'Golden Route VIP Express', 'GRM-VIP-EXPRESS-2026');
    $normalCompanyId = grm_seed_company($conn, 'Golden Route Normal Express', 'GRM-NORMAL-EXPRESS-2026');

    $vipBusId = grm_seed_bus($conn, $vipCompanyId, 'VIP-001', 'GRM-VIP/001', 'vip', 30, 'vip');
    $vipBus2Id = grm_seed_bus($conn, $vipCompanyId, 'VIP-002', 'GRM-VIP/002', 'vip', 30, 'vip');
    $normalBusId = grm_seed_bus($conn, $normalCompanyId, 'NOR-001', 'GRM-NOR/001', 'normal', 44, '2x2');
    $normalBus2Id = grm_seed_bus($conn, $normalCompanyId, 'NOR-002', 'GRM-NOR/002', 'normal', 44, '2x2');

    grm_seed_seats($conn, $vipBusId, 30, 'vip');
    grm_seed_seats($conn, $vipBus2Id, 30, 'vip');
    grm_seed_seats($conn, $normalBusId, 44, '2x2');
    grm_seed_seats($conn, $normalBus2Id, 44, '2x2');

    $popularRoutes = [
        ['Yangon', 'Mandalay', 625, 540, 31000, 42000, '08:00:00', '17:00:00', '09:00:00', '18:00:00'],
        ['Mandalay', 'Yangon', 625, 540, 31000, 42000, '08:00:00', '17:00:00', '09:00:00', '18:00:00'],
        ['Yangon', 'Naypyidaw', 370, 330, 22000, 30000, '07:00:00', '12:30:00', '08:00:00', '13:30:00'],
        ['Naypyidaw', 'Yangon', 370, 330, 22000, 30000, '13:00:00', '18:30:00', '14:00:00', '19:30:00'],
        ['Yangon', 'Pathein', 190, 300, 17000, 24000, '07:00:00', '12:00:00', '08:00:00', '13:00:00'],
        ['Pathein', 'Yangon', 190, 300, 17000, 24000, '13:00:00', '18:00:00', '14:00:00', '19:00:00'],
        ['Yangon', 'Taunggyi', 650, 720, 33000, 45000, '18:00:00', '06:00:00', '19:00:00', '07:00:00'],
        ['Mandalay', 'Taunggyi', 260, 420, 22000, 31000, '07:00:00', '14:00:00', '08:00:00', '15:00:00'],
        ['Yangon', 'Mawlamyine', 300, 390, 23000, 32000, '07:00:00', '13:30:00', '08:30:00', '15:00:00'],
        ['Yangon', 'Hpa-An', 290, 360, 22000, 30000, '07:30:00', '13:30:00', '09:00:00', '15:00:00'],
        ['Mandalay', 'Bagan', 180, 240, 15000, 22000, '08:00:00', '12:00:00', '09:00:00', '13:00:00'],
        ['Bagan', 'Mandalay', 180, 240, 15000, 22000, '13:00:00', '17:00:00', '14:00:00', '18:00:00'],
    ];

    foreach ($popularRoutes as $route) {
        [$from, $to, $km, $minutes, $normalPrice, $vipPrice, $normalDep, $normalArr, $vipDep, $vipArr] = $route;
        if (!isset($cityIds[$from], $cityIds[$to])) {
            continue;
        }

        $normalRouteId = grm_seed_route($conn, $normalCompanyId, $cityIds[$from], $cityIds[$to], (float)$km, (int)$minutes, (float)$normalPrice);
        $vipRouteId = grm_seed_route($conn, $vipCompanyId, $cityIds[$from], $cityIds[$to], (float)$km, (int)$minutes, (float)$vipPrice);

        grm_seed_schedule($conn, $normalCompanyId, $normalRouteId, $normalBusId, $normalDep, $normalArr, (float)$normalPrice);
        grm_seed_schedule($conn, $vipCompanyId, $vipRouteId, $vipBusId, $vipDep, $vipArr, (float)$vipPrice);
    }

    // Transfer-route demo: Mandalay -> Yangon -> Pathein, both Normal and VIP.
    // Direct Mandalay -> Pathein is intentionally not added, so search can show two-step booking.

    $conn->commit();
    $conn->close();

    $runner = grm_auto_schedule_runner(true, 90, true);
    $message = 'VIP/Normal city search demo data added. Auto schedule runner generated ' . (int)($runner['generated'] ?? 0) . ' trips and skipped ' . (int)($runner['skipped'] ?? 0) . ' existing trips.';
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
    <title>Seed VIP Normal Search Demo</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;padding:40px}.box{max-width:820px;margin:auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 12px 35px rgba(0,0,0,.08)}
        code{display:block;background:#0f172a;color:#e5e7eb;border-radius:10px;padding:14px;margin:12px 0}a{display:inline-block;padding:10px 16px;border-radius:10px;background:#b8860b;color:#fff;text-decoration:none;font-weight:700}
    </style>
</head>
<body>
    <div class="box">
        <h2>VIP / Normal Search Demo Seed</h2>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Now test:</p>
        <code>Find Bus → Yangon to Mandalay → choose All / VIP / Normal</code>
        <code>Find Bus → Mandalay to Pathein → shows Mandalay → Yangon → Pathein transfer route</code>
        <a href="../search_bus.php">Open Search Bus</a>
    </div>
</body>
</html>
