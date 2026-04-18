<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/seat_layout_helper.php';

require_role('bus_admin');

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$busId = (int)($_GET['bus_id'] ?? 0);

if ($busId <= 0) {
    $conn->close();
    set_flash('error', 'Invalid bus ID.');
    redirect('bus_admin/dashboard.php');
}

$busSql = "
    SELECT id, company_id, bus_number, plate_number, bus_type, total_seats, layout_type, status
    FROM buses
    WHERE id = ? AND company_id = ?
    LIMIT 1
";
$busStmt = $conn->prepare($busSql);
$busStmt->bind_param('ii', $busId, $company['company_id']);
$busStmt->execute();
$busResult = $busStmt->get_result();
$bus = $busResult->fetch_assoc();
$busStmt->close();

if (!$bus) {
    $conn->close();
    set_flash('error', 'Bus not found or not allowed.');
    redirect('bus_admin/dashboard.php');
}

$seats = fetch_bus_seats($conn, $busId);
$conn->close();

$page_title = 'Seat Layout - ' . $bus['bus_number'];
require_once __DIR__ . '/../includes/header.php';

$layoutConfig = get_layout_config($bus['layout_type']);
$labels = $layoutConfig['labels'];
$aisleAfter = (int)$layoutConfig['aisle_after'];

$rows = [];
foreach ($seats as $seat) {
    $rows[(int)$seat['row_no']][(int)$seat['col_no']] = $seat;
}
ksort($rows);

$seatTypeOptions = ['normal', 'vip', 'sleeper'];
?>

<style>
.seat-preview-board {
    background: #fff;
    border-radius: 18px;
    padding: 20px;
    border: 1px solid #e9ecef;
}

.seat-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: nowrap;
}

.seat-box,
.seat-empty {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.seat-box {
    color: #fff;
}

.seat-empty {
    background: #f1f3f5;
    border: 1px dashed #ced4da;
    color: #adb5bd;
}

.seat-aisle {
    width: 26px;
    flex: 0 0 26px;
}

.row-label {
    width: 48px;
    font-weight: 700;
    color: #6c757d;
}
</style>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Seat Layout Management</h2>
            <p class="text-muted mb-0">
                Company: <?php echo e($company['company_name']); ?>
            </p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>bus_admin/buses.php" class="btn btn-outline-secondary">
                Back to Buses
            </a>
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
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Bus Number</div>
                    <div class="fw-bold"><?php echo e($bus['bus_number']); ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Bus Type</div>
                    <div class="fw-bold"><?php echo e(ucwords(str_replace('_', ' ', $bus['bus_type']))); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted">Layout Type</div>
                    <div class="fw-bold"><?php echo e(strtoupper($bus['layout_type'])); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted">Total Seats</div>
                    <div class="fw-bold"><?php echo e($bus['total_seats']); ?></div>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted">Generated</div>
                    <div class="fw-bold"><?php echo e(count($seats)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">
                        <?php echo empty($seats) ? 'Generate Seat Layout' : 'Regenerate Seat Layout'; ?>
                    </h5>

                    <p class="text-muted">
                        This will generate seats automatically from the bus layout type and total seats.
                        Existing seat records for this bus will be replaced.
                    </p>

                    <ul class="small text-muted mb-4">
                        <li>Seat numbers are auto-generated like 1A, 1B, 1C...</li>
                        <li>Row numbers and column numbers are calculated automatically.</li>
                        <li>You can edit seat type and active status after generation.</li>
                    </ul>

                    <form action="<?php echo BASE_URL; ?>actions/generate_bus_seats.php" method="POST">
                        <input type="hidden" name="bus_id" value="<?php echo e($bus['id']); ?>">

                        <div class="mb-3">
                            <label class="form-label">Bus Number</label>
                            <input type="text" class="form-control" value="<?php echo e($bus['bus_number']); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Layout Type</label>
                            <input type="text" class="form-control" value="<?php echo e(strtoupper($bus['layout_type'])); ?>" readonly>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Total Seats</label>
                            <input type="text" class="form-control" value="<?php echo e($bus['total_seats']); ?>" readonly>
                        </div>

                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                            onclick="return confirm('This will replace existing seat records for this bus. Continue?');"
                        >
                            <?php echo empty($seats) ? 'Generate Seats' : 'Regenerate Seats'; ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php if (!empty($seats)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Seat Settings</h5>

                        <form action="<?php echo BASE_URL; ?>actions/update_seat_layout.php" method="POST">
                            <input type="hidden" name="bus_id" value="<?php echo e($bus['id']); ?>">

                            <div class="table-responsive mb-3" style="max-height: 500px;">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Seat</th>
                                            <th>Row</th>
                                            <th>Col</th>
                                            <th>Type</th>
                                            <th>Active</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($seats as $seat): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo e($seat['seat_number']); ?></td>
                                                <td><?php echo e($seat['row_no']); ?></td>
                                                <td><?php echo e($seat['col_no']); ?></td>
                                                <td>
                                                    <select
                                                        name="seat_type[<?php echo e($seat['id']); ?>]"
                                                        class="form-select form-select-sm"
                                                    >
                                                        <?php foreach ($seatTypeOptions as $option): ?>
                                                            <option
                                                                value="<?php echo e($option); ?>"
                                                                <?php echo ($seat['seat_type'] === $option) ? 'selected' : ''; ?>
                                                            >
                                                                <?php echo e(ucfirst($option)); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="is_active[<?php echo e($seat['id']); ?>]"
                                                            value="1"
                                                            <?php echo ((int)$seat['is_active'] === 1) ? 'checked' : ''; ?>
                                                        >
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <button type="submit" class="btn btn-success">
                                Save Seat Settings
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Seat Preview</h5>

                    <?php if (empty($seats)): ?>
                        <div class="alert alert-info mb-0">
                            No seats generated yet. Click <strong>Generate Seats</strong> first.
                        </div>
                    <?php else: ?>
                        <div class="seat-preview-board">
                            <?php foreach ($rows as $rowNo => $rowSeats): ?>
                                <div class="seat-row">
                                    <div class="row-label">Row <?php echo e($rowNo); ?></div>

                                    <?php for ($i = 1; $i <= count($labels); $i++): ?>
                                        <?php if ($i === ($aisleAfter + 1)): ?>
                                            <div class="seat-aisle"></div>
                                        <?php endif; ?>

                                        <?php if (isset($rowSeats[$i])): ?>
                                            <?php
                                                $seat = $rowSeats[$i];
                                                $badgeClass = seat_badge_class($seat['seat_type'], (int)$seat['is_active']);
                                                $bgClass = 'bg-' . $badgeClass;
                                            ?>
                                            <div class="seat-box <?php echo e($bgClass); ?>">
                                                <?php echo e($seat['seat_number']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="seat-empty">-</div>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3 d-flex flex-wrap gap-2">
                            <span class="badge bg-primary">Regular</span>
                            <span class="badge bg-warning text-dark">VIP</span>
                            <span class="badge bg-info text-dark">Sleeper</span>
                            <span class="badge bg-secondary">Inactive</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>