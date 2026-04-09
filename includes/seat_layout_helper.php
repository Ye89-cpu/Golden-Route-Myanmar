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