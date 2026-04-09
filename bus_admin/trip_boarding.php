<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/boarding_helper.php';

require_role('bus_admin');

$page_title = 'Trip Boarding / Check-in';

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$tripDate = trim($_GET['trip_date'] ?? '');
$tripStatus = trim($_GET['trip_status'] ?? 'all');
$allowedTripStatuses = ['all', 'scheduled', 'open', 'full', 'departed', 'completed', 'cancelled'];

if (!in_array($tripStatus, $allowedTripStatuses, true)) {
    $tripStatus = 'all';
}

try {
    $rows = fetch_bus_admin_trip_boarding_list($conn, (int)$company['company_id'], [
        'trip_date' => $tripDate,
        'trip_status' => $tripStatus,
    ]);
} catch (Throwable $e) {
    $conn->close();
    die('Trip boarding error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Trip Boarding / Check-in</h2>
            <p class="text-muted mb-0">Company: <?php echo e($company['company_name']); ?></p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>bus_admin/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>
    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Trip Date</label>
                    <input type="date" name="trip_date" class="form-control" value="<?php echo e($tripDate); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Trip Status</label>
                    <select name="trip_status" class="form-select">
                        <?php foreach ($allowedTripStatuses as $status): ?>
                            <option value="<?php echo e($status); ?>" <?php echo $tripStatus === $status ? 'selected' : ''; ?>>
                                <?php echo e(boarding_format_status($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <a href="<?php echo BASE_URL; ?>bus_admin/trip_boarding.php" class="btn btn-outline-secondary ms-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <?php if (empty($rows)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No trips found for boarding.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Trip</th>
                                <th>Bus</th>
                                <th>Passengers</th>
                                <th>Tickets</th>
                                <th>Status</th>
                                <th style="min-width: 240px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $waitingCount = max(0, (int)$row['issued_tickets'] - (int)$row['boarded_tickets']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo e($row['from_city_name']); ?> → <?php echo e($row['to_city_name']); ?></div>
                                        <div class="small text-muted">
                                            <?php echo e($row['trip_date']); ?> |
                                            <?php echo e(date('H:i', strtotime((string)$row['departure_datetime']))); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['bus_number']); ?></div>
                                        <div class="small text-muted"><?php echo e($row['bus_type']); ?> | Total seats: <?php echo e($row['total_seats']); ?></div>
                                    </td>
                                    <td>
                                        <div>Paid bookings: <?php echo e($row['paid_bookings']); ?></div>
                                        <div class="small text-muted">Paid passengers: <?php echo e($row['paid_passengers']); ?></div>
                                    </td>
                                    <td>
                                        <div>Issued: <?php echo e($row['issued_tickets']); ?></div>
                                        <div class="small text-muted">Boarded: <?php echo e($row['boarded_tickets']); ?></div>
                                        <div class="small text-muted">Waiting: <?php echo e($waitingCount); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo e(boarding_status_badge_class((string)$row['trip_status'])); ?>">
                                            <?php echo e(boarding_format_status((string)$row['trip_status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="<?php echo BASE_URL; ?>bus_admin/board_trip.php?trip_id=<?php echo e($row['trip_id']); ?>" class="btn btn-sm btn-primary">
                                                Open Boarding
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>bus_admin/bookings.php?trip_date=<?php echo e($row['trip_date']); ?>" class="btn btn-sm btn-outline-primary">
                                                Bookings
                                            </a>
                                            <a href="<?php echo BASE_URL; ?>bus_admin/scan_ticket.php" class="btn btn-sm btn-outline-dark">
                                                Generic Scan
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>