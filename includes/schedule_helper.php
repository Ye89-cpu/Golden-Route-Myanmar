<?php
// includes/schedule_helper.php

require_once __DIR__ . '/db.php';

function get_weekday_options(): array
{
    return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
}

function normalize_weekdays(array $days): array
{
    $allowed = get_weekday_options();
    $clean = [];

    foreach ($days as $day) {
        $day = trim((string)$day);
        if (in_array($day, $allowed, true) && !in_array($day, $clean, true)) {
            $clean[] = $day;
        }
    }

    return $clean;
}

function weekdays_to_storage(array $days): ?string
{
    $days = normalize_weekdays($days);
    return empty($days) ? null : implode(',', $days);
}

function weekdays_from_storage(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $parts = explode(',', $value);
    return normalize_weekdays($parts);
}

function format_weekdays_display(?string $value): string
{
    $days = weekdays_from_storage($value);
    return empty($days) ? '-' : implode(', ', $days);
}

function is_valid_time_hhmm(string $time): bool
{
    $dt = DateTime::createFromFormat('H:i', $time);
    return $dt && $dt->format('H:i') === $time;
}

function should_generate_for_date(DateTime $date, string $frequency, array $weekdays): bool
{
    if ($frequency === 'daily') {
        return true;
    }

    $dayName = $date->format('D'); // Mon, Tue, Wed...
    return in_array($dayName, $weekdays, true);
}

function build_trip_datetimes(string $tripDate, string $departureTime, string $arrivalTime): array
{
    $departure = new DateTime($tripDate . ' ' . $departureTime);
    $arrival = new DateTime($tripDate . ' ' . $arrivalTime);

    // Overnight trip support
    if ($arrival <= $departure) {
        $arrival->modify('+1 day');
    }

    return [
        'departure_datetime' => $departure->format('Y-m-d H:i:s'),
        'arrival_datetime'   => $arrival->format('Y-m-d H:i:s'),
    ];
}

function get_available_seats_for_bus(mysqli $conn, int $busId, int $fallbackTotalSeats): int
{
    $sql = "
        SELECT COUNT(*) AS active_seat_count
        FROM bus_seats
        WHERE bus_id = ? AND is_active = 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $count = (int)($row['active_seat_count'] ?? 0);
    return $count > 0 ? $count : $fallbackTotalSeats;
}

function generate_trips_from_template(mysqli $conn, array $template, int $availableSeats): array
{
    $generatedCount = 0;
    $skippedCount = 0;

    $startDate = new DateTime($template['active_from']);
    $endDate = new DateTime($template['active_to']);

    if ($endDate < $startDate) {
        throw new Exception('Active To date must be the same as or later than Active From date.');
    }

    $weekdays = weekdays_from_storage($template['weekdays']);

    $checkSql = "
        SELECT id
        FROM trips
        WHERE company_id = ?
          AND route_id = ?
          AND bus_id = ?
          AND trip_date = ?
          AND departure_datetime = ?
        LIMIT 1
    ";
    $checkStmt = $conn->prepare($checkSql);

    $insertSql = "
        INSERT INTO trips
        (
            company_id,
            route_id,
            bus_id,
            schedule_template_id,
            trip_date,
            departure_datetime,
            arrival_datetime,
            price,
            available_seats,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $insertStmt = $conn->prepare($insertSql);

    $current = clone $startDate;

    while ($current <= $endDate) {
        if (should_generate_for_date($current, $template['frequency'], $weekdays)) {
            $tripDate = $current->format('Y-m-d');

            $datetimes = build_trip_datetimes(
                $tripDate,
                $template['departure_time'],
                $template['arrival_time']
            );

            $departureDatetime = $datetimes['departure_datetime'];
            $arrivalDatetime = $datetimes['arrival_datetime'];

            $checkStmt->bind_param(
                'iiiss',
                $template['company_id'],
                $template['route_id'],
                $template['bus_id'],
                $tripDate,
                $departureDatetime
            );
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $skippedCount++;
            } else {
                $status = 'open';
                $insertStmt->bind_param(
                    'iiiisssdis',
                    $template['company_id'],
                    $template['route_id'],
                    $template['bus_id'],
                    $template['id'],
                    $tripDate,
                    $departureDatetime,
                    $arrivalDatetime,
                    $template['price'],
                    $availableSeats,
                    $status
                );

                if (!$insertStmt->execute()) {
                    throw new Exception('Failed to insert trip for date ' . $tripDate);
                }

                $generatedCount++;
            }
        }

        $current->modify('+1 day');
    }

    $checkStmt->close();
    $insertStmt->close();

    return [
        'generated_count' => $generatedCount,
        'skipped_count'   => $skippedCount,
    ];
}

function schedule_frequency_badge(string $frequency): string
{
    switch ($frequency) {
        case 'weekly':
            return 'warning text-dark';
        case 'custom':
            return 'info text-dark';
        case 'daily':
        default:
            return 'primary';
    }
}

function schedule_status_badge(string $status): string
{
    return $status === 'active' ? 'success' : 'secondary';
}
function get_active_bus_seat_count(mysqli $conn, int $busId, int $fallbackTotalSeats = 0): int
{
    if ($busId <= 0) {
        return max(0, $fallbackTotalSeats);
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM bus_seats
        WHERE bus_id = ?
          AND is_active = 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return max(0, $fallbackTotalSeats);
    }

    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $activeSeatCount = (int)($row['total'] ?? 0);

    return $activeSeatCount > 0 ? $activeSeatCount : max(0, $fallbackTotalSeats);
}
?>