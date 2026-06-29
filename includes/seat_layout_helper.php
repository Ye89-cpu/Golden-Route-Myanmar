<?php
// includes/seat_layout_helper.php

require_once __DIR__ . '/db.php';

function get_layout_config(string $layoutType): array
{
    switch ($layoutType) {
        case '2x1':
            return [
                'labels' => ['A', 'B', 'C'],
                'aisle_after' => 2
            ];

        case 'sleeper':
            return [
                'labels' => ['A', 'B'],
                'aisle_after' => 1
            ];

        case 'vip':
            return [
                'labels' => ['A', 'B', 'C'],
                'aisle_after' => 2
            ];

        case 'custom':
            return [
                'labels' => ['A', 'B', 'C', 'D'],
                'aisle_after' => 2
            ];

        case '2x2':
        default:
            return [
                'labels' => ['A', 'B', 'C', 'D'],
                'aisle_after' => 2
            ];
    }
}

function get_default_seat_type(string $busType, string $layoutType): string
{
    if ($layoutType === 'sleeper' || $busType === 'sleeper') {
        return 'sleeper';
    }

    if ($busType === 'vip' || $layoutType === 'vip') {
        return 'vip';
    }

    return 'normal';
}

function generate_seat_records(array $bus): array
{
    $totalSeats = (int)$bus['total_seats'];
    $layoutType = (string)$bus['layout_type'];
    $busType = (string)$bus['bus_type'];

    $config = get_layout_config($layoutType);
    $labels = $config['labels'];
    $defaultSeatType = get_default_seat_type($busType, $layoutType);

    $seats = [];
    $created = 0;
    $rowNo = 1;

    while ($created < $totalSeats) {
        foreach ($labels as $index => $label) {
            if ($created >= $totalSeats) {
                break;
            }

            $seats[] = [
                'seat_number' => $rowNo . $label,
                'seat_type'   => $defaultSeatType,
                'row_no'      => $rowNo,
                'col_no'      => $index + 1,
                'is_active'   => 1
            ];

            $created++;
        }

        $rowNo++;
    }

    return $seats;
}

function fetch_bus_seats(mysqli $conn, int $busId): array
{
    $sql = "
        SELECT id, bus_id, seat_number, seat_type, row_no, col_no, is_active
        FROM bus_seats
        WHERE bus_id = ?
        ORDER BY row_no ASC, col_no ASC, id ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();

    $seats = [];
    while ($row = $result->fetch_assoc()) {
        $seats[] = $row;
    }

    $stmt->close();
    return $seats;
}

function save_generated_seats(mysqli $conn, int $busId, array $seats): void
{
    $conn->begin_transaction();

    try {
        $deleteSql = "DELETE FROM bus_seats WHERE bus_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('i', $busId);

        if (!$deleteStmt->execute()) {
            throw new Exception('Failed to clear old seat records.');
        }
        $deleteStmt->close();

        $insertSql = "
            INSERT INTO bus_seats
            (bus_id, seat_number, seat_type, row_no, col_no, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $insertStmt = $conn->prepare($insertSql);

        foreach ($seats as $seat) {
            $insertStmt->bind_param(
                'issiii',
                $busId,
                $seat['seat_number'],
                $seat['seat_type'],
                $seat['row_no'],
                $seat['col_no'],
                $seat['is_active']
            );

            if (!$insertStmt->execute()) {
                throw new Exception('Failed to insert generated seats.');
            }
        }

        $insertStmt->close();
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}


function count_bookable_bus_seats(mysqli $conn, int $busId): int
{
    if ($busId <= 0) {
        return 0;
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM bus_seats
        WHERE bus_id = ?
          AND is_active = 1
          AND seat_type NOT IN ('driver', 'assistant')
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int)($row['total'] ?? 0);
}

function bus_has_booking_seat_usage(mysqli $conn, int $busId): bool
{
    if ($busId <= 0) {
        return false;
    }

    $sql = "
        SELECT bs.id
        FROM booking_seats bks
        INNER JOIN bus_seats bs ON bs.id = bks.bus_seat_id
        WHERE bs.bus_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return true;
    }

    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();
    $hasUsage = $result && $result->num_rows > 0;
    $stmt->close();

    return $hasUsage;
}

function fetch_bus_for_seat_generation(mysqli $conn, int $busId): ?array
{
    if ($busId <= 0) {
        return null;
    }

    $sql = "
        SELECT id, bus_number, bus_type, total_seats, layout_type
        FROM buses
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $busId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bus = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $bus ?: null;
}

function ensure_bus_seat_layout(mysqli $conn, int $busId, bool $repairMismatch = false): int
{
    $bus = fetch_bus_for_seat_generation($conn, $busId);
    if (!$bus) {
        return 0;
    }

    $totalSeats = (int)($bus['total_seats'] ?? 0);
    if ($totalSeats <= 0) {
        return 0;
    }

    $activeSeatCount = count_bookable_bus_seats($conn, $busId);

    if ($activeSeatCount > 0) {
        if ($repairMismatch && $activeSeatCount !== $totalSeats && !bus_has_booking_seat_usage($conn, $busId)) {
            $generatedSeats = generate_seat_records($bus);
            save_generated_seats($conn, $busId, $generatedSeats);
            return count_bookable_bus_seats($conn, $busId);
        }

        return $activeSeatCount;
    }

    $generatedSeats = generate_seat_records($bus);
    if (empty($generatedSeats)) {
        return 0;
    }

    save_generated_seats($conn, $busId, $generatedSeats);
    return count_bookable_bus_seats($conn, $busId);
}

function seat_badge_class(string $seatType, int $isActive): string
{
    if ((int)$isActive !== 1) {
        return 'secondary';
    }

    switch ($seatType) {
        case 'vip':
            return 'warning text-dark';
        case 'sleeper':
            return 'info text-dark';
        case 'normal':
        default:
            return 'primary';
    }
}
?>