<?php
require_once __DIR__ . '/db.php';

function tour_booking_table_column_exists(mysqli $conn, string $table, string $column): bool
{
    $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

function tour_booking_get_batch_package_column(mysqli $conn): string
{
    if (tour_booking_table_column_exists($conn, 'tour_batches', 'tour_package_id')) {
        return 'tour_package_id';
    }

    if (tour_booking_table_column_exists($conn, 'tour_batches', 'package_id')) {
        return 'package_id';
    }

    return 'tour_package_id';
}

function fetch_tour_package_for_company(mysqli $conn, int $packageId, int $companyId): ?array
{
    $sql = "
        SELECT *
        FROM tour_packages
        WHERE id = ? AND company_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $packageId, $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    $package = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $package;
}

function fetch_tour_batch_for_package(mysqli $conn, int $batchId, int $packageId): ?array
{
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        SELECT *
        FROM tour_batches
        WHERE id = ? AND {$packageColumn} = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $batchId, $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $batch = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $batch;
}

function fetch_tour_batches_for_package(mysqli $conn, int $packageId): array
{
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        SELECT *
        FROM tour_batches
        WHERE {$packageColumn} = ?
        ORDER BY start_date ASC, id DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_public_tour_packages(mysqli $conn): array
{
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        SELECT
            tp.*,
            c.name AS company_name,
            (
                SELECT COUNT(*)
                FROM tour_batches tb
                WHERE tb.{$packageColumn} = tp.id
                  AND tb.status IN ('open', 'active', 'full')
                  AND tb.end_date >= CURDATE()
            ) AS total_batches,
            (
                SELECT MIN(tb.price)
                FROM tour_batches tb
                WHERE tb.{$packageColumn} = tp.id
                  AND tb.status IN ('open', 'active', 'full')
                  AND tb.end_date >= CURDATE()
            ) AS min_batch_price
        FROM tour_packages tp
        INNER JOIN companies c ON c.id = tp.company_id
        WHERE tp.status = 'active'
          AND c.status = 'approved'
          AND c.company_type IN ('tour_operator', 'both')
        ORDER BY tp.id DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_public_tour_package_detail(mysqli $conn, int $packageId): ?array
{
    $sql = "
        SELECT
            tp.*, c.name AS company_name
        FROM tour_packages tp
        INNER JOIN companies c ON c.id = tp.company_id
        WHERE tp.id = ?
          AND tp.status = 'active'
          AND c.status = 'approved'
          AND c.company_type IN ('tour_operator', 'both')
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    $package = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $package;
}

function fetch_public_available_batches(mysqli $conn, int $packageId): array
{
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        SELECT *
        FROM tour_batches
        WHERE {$packageColumn} = ?
          AND status IN ('open', 'active', 'full')
          AND end_date >= CURDATE()
        ORDER BY start_date ASC, id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $packageId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function tour_batch_remaining_slots(array $batch): int
{
    $capacity = (int)($batch['capacity'] ?? 0);
    $booked = (int)($batch['booked_count'] ?? 0);

    return max($capacity - $booked, 0);
}

function tour_batch_is_bookable(array $batch): bool
{
    $status = (string)($batch['status'] ?? '');
    $remaining = tour_batch_remaining_slots($batch);

    return in_array($status, ['open', 'active'], true) && $remaining > 0;
}

function tour_batch_badge_class(string $status): string
{
    switch ($status) {
        case 'full':
            return 'danger';
        case 'closed':
        case 'inactive':
            return 'secondary';
        case 'cancelled':
            return 'dark';
        case 'open':
        case 'active':
        default:
            return 'success';
    }
}
?>
