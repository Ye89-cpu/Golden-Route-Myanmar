<?php
require_once __DIR__ . '/../config.php';

function company_logo_public_url(?string $logoPath): string
{
    $logoPath = trim((string) $logoPath);

    if ($logoPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $logoPath)) {
        return $logoPath;
    }

    $logoPath = str_replace('\\', '/', $logoPath);
    $logoPath = ltrim($logoPath, './');

    return BASE_URL . ltrim($logoPath, '/');
}

function company_initials(string $companyName): string
{
    $companyName = trim($companyName);
    if ($companyName === '') {
        return 'GR';
    }

    $parts = preg_split('/\s+/', $companyName) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'GR';
}

function company_type_label(string $companyType): string
{
    return match ($companyType) {
        'both' => 'Bus + Tour Operator',
        'bus_company' => 'Bus Company',
        default => 'Travel Partner',
    };
}

function company_status_label(array $company): string
{
    $openTrips = (int) ($company['open_trips'] ?? 0);
    $activeRoutes = (int) ($company['active_routes'] ?? 0);

    if ($openTrips > 0) {
        return 'Booking Open';
    }

    if ($activeRoutes > 0) {
        return 'Routes Ready';
    }

    return 'Trusted Partner';
}

function company_short_description(?string $description): string
{
    $description = trim((string) $description);

    if ($description !== '') {
        return $description;
    }

    return 'Trusted transport partner working with Golden Route Myanmar for smoother intercity ticket booking.';
}

function fetch_featured_bus_companies(mysqli $conn, int $limit = 9): array
{
    $companies = [];
    $limit = max(1, min($limit, 12));

    $sql = "
        SELECT
            c.id,
            c.name,
            c.company_type,
            c.description,
            c.address,
            c.logo,
            COALESCE((
                SELECT COUNT(*)
                FROM buses b
                WHERE b.company_id = c.id
                  AND b.status = 'active'
            ), 0) AS active_buses,
            COALESCE((
                SELECT COUNT(*)
                FROM routes r
                WHERE r.company_id = c.id
                  AND r.status = 'active'
            ), 0) AS active_routes,
            COALESCE((
                SELECT COUNT(*)
                FROM trips t
                WHERE t.company_id = c.id
                  AND t.status = 'open'
                  AND t.trip_date >= CURDATE()
            ), 0) AS open_trips,
            COALESCE((
                SELECT MIN(t.price)
                FROM trips t
                WHERE t.company_id = c.id
                  AND t.status = 'open'
                  AND t.trip_date >= CURDATE()
            ), 0) AS starting_price,
            (
                SELECT CONCAT(fc.name, ' → ', tc.name)
                FROM routes r
                INNER JOIN cities fc ON fc.id = r.from_city_id
                INNER JOIN cities tc ON tc.id = r.to_city_id
                WHERE r.company_id = c.id
                  AND r.status = 'active'
                ORDER BY r.id ASC
                LIMIT 1
            ) AS highlight_route
        FROM companies c
        WHERE c.status = 'approved'
          AND c.company_type IN ('bus_company', 'both')
        ORDER BY open_trips DESC, active_routes DESC, active_buses DESC, c.name ASC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $companies;
    }

    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $routeSql = "
            SELECT
                r.id AS route_id,
                r.from_city_id,
                r.to_city_id,
                fc.name AS from_city_name,
                tc.name AS to_city_name,
                MIN(CASE
                    WHEN t.status = 'open' AND t.trip_date >= CURDATE() THEN t.trip_date
                    ELSE NULL
                END) AS next_trip_date
            FROM routes r
            INNER JOIN cities fc ON fc.id = r.from_city_id
            INNER JOIN cities tc ON tc.id = r.to_city_id
            LEFT JOIN trips t
                ON t.route_id = r.id
               AND t.company_id = r.company_id
            WHERE r.company_id = ?
              AND r.status = 'active'
            GROUP BY
                r.id,
                r.from_city_id,
                r.to_city_id,
                fc.name,
                tc.name
            ORDER BY
                (next_trip_date IS NULL) ASC,
                next_trip_date ASC,
                r.id ASC
            LIMIT 1
        ";

        $routeStmt = $conn->prepare($routeSql);
        if ($routeStmt) {
            $companyId = (int)($row['id'] ?? 0);
            $routeStmt->bind_param('i', $companyId);
            $routeStmt->execute();
            $routeResult = $routeStmt->get_result();
            $route = $routeResult ? $routeResult->fetch_assoc() : null;
            $routeStmt->close();

            if ($route) {
                $row['highlight_route_id'] = (int)($route['route_id'] ?? 0);
                $row['highlight_from_city_id'] = (int)($route['from_city_id'] ?? 0);
                $row['highlight_to_city_id'] = (int)($route['to_city_id'] ?? 0);
                $row['highlight_from_city_name'] = (string)($route['from_city_name'] ?? '');
                $row['highlight_to_city_name'] = (string)($route['to_city_name'] ?? '');
                $row['highlight_next_trip_date'] = (string)($route['next_trip_date'] ?? '');
                $row['highlight_route'] = trim(
                    $row['highlight_from_city_name'] . ' → ' . $row['highlight_to_city_name'],
                    ' →'
                );
            }
        }

        $companies[] = $row;
    }

    $stmt->close();

    return $companies;
}