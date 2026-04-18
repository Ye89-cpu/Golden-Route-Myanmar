<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/bus_booking_helper.php';

require_role('bus_admin');

$bookingId = (int)($_GET['booking_id'] ?? 0);
if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking ID.');
    redirect('bus_admin/bookings.php');
}

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

try {
    $booking = fetch_bus_admin_booking_detail($conn, (int)$company['company_id'], $bookingId);
    if (!$booking) {
        $conn->close();
        set_flash('error', 'Booking not found or not allowed.');
        redirect('bus_admin/bookings.php');
    }

    $passengers = fetch_bus_admin_booking_passengers($conn, $bookingId);
    $seats = fetch_bus_admin_booking_seats($conn, $bookingId);
} catch (Throwable $e) {
    $conn->close();
    die('Manifest error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

$page_title = 'Passenger Manifest - ' . $booking['booking_code'];
require_once __DIR__ . '/../includes/header.php';
?>
<style>
@media print {
    .no-print {
        display: none !important;
    }
    body {
        background: #fff !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #dee2e6 !important;
    }
}
</style>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 no-print">
        <div>
            <h2 class="fw-bold mb-1">Passenger Manifest</h2>
            <p class="text-muted mb-0"><?php echo e($booking['booking_code']); ?></p>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap gap-2">
            <a href="<?php echo BASE_URL; ?>bus_admin/booking_detail.php?booking_id=<?php echo e($booking['id']); ?>" class="btn btn-outline-secondary">Back to Detail</a>
            <button type="button" class="btn btn-dark" onclick="window.print()">Print Manifest</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-4"><strong>Company:</strong> <?php echo e($booking['company_name']); ?></div>
                <div class="col-md-4"><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                <div class="col-md-4"><strong>Ticket No:</strong> <?php echo e($booking['ticket_no'] ?: '-'); ?></div>

                <div class="col-md-4"><strong>Route:</strong> <?php echo e($booking['from_city_name']); ?> → <?php echo e($booking['to_city_name']); ?></div>
                <div class="col-md-4"><strong>Trip Date:</strong> <?php echo e($booking['trip_date']); ?></div>
                <div class="col-md-4"><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime((string)$booking['departure_datetime']))); ?></div>

                <div class="col-md-4"><strong>Bus:</strong> <?php echo e($booking['bus_number']); ?></div>
                <div class="col-md-4"><strong>Plate:</strong> <?php echo e($booking['plate_number'] ?: '-'); ?></div>
                <div class="col-md-4"><strong>Total Pax:</strong> <?php echo e($booking['passenger_count']); ?></div>

                <div class="col-md-6"><strong>Customer:</strong> <?php echo e($booking['customer_name']); ?> (<?php echo e($booking['customer_phone'] ?: '-'); ?>)</div>
                <div class="col-md-6"><strong>Seats:</strong>
                    <?php
                    $seatLabels = array_map(static fn($seat) => $seat['seat_number'], $seats);
                    echo e(!empty($seatLabels) ? implode(', ', $seatLabels) : '-');
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="p-4 border-bottom">
                <h5 class="fw-bold mb-0">Passenger List</h5>
            </div>

            <?php if (empty($passengers)): ?>
                <div class="p-4">
                    <div class="alert alert-info mb-0">No passenger rows found.</div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>NRC / Passport</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Note</th>
                                <th>Signature</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passengers as $index => $passenger): ?>
                                <tr>
                                    <td><?php echo e($index + 1); ?></td>
                                    <td class="fw-semibold"><?php echo e($passenger['full_name']); ?></td>
                                    <td><?php echo e($passenger['phone'] ?: '-'); ?></td>
                                    <td><?php echo e($passenger['nrc_passport'] ?: '-'); ?></td>
                                    <td><?php echo e($passenger['gender'] ?: '-'); ?></td>
                                    <td><?php echo e($passenger['age'] ?: '-'); ?></td>
                                    <td><?php echo e($passenger['special_note'] ?: '-'); ?></td>
                                    <td style="min-width: 120px;"></td>
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