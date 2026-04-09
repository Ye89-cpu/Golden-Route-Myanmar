<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tour_booking_helper.php';

$page_title = 'Tour Package Detail';

$conn = getDBConnection();
$packageId = (int)($_GET['package_id'] ?? 0);
$package = null;
$batches = [];

if ($packageId > 0) {
    $package = fetch_public_tour_package_detail($conn, $packageId);

    if ($package) {
        $batches = fetch_public_available_batches($conn, $packageId);
    }
}

$conn->close();

$isCustomer = is_logged_in() && current_user_role() === 'customer';
$isOtherRole = is_logged_in() && current_user_role() !== 'customer';

require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="mb-4">
        <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-outline-secondary">Back to Tours</a>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$package): ?>
        <div class="alert alert-danger rounded-4">Tour package not found.</div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-4">
                    <?php if (!empty($package['cover_image'])): ?>
                        <img
                            src="<?php echo BASE_URL . e($package['cover_image']); ?>"
                            class="card-img-top rounded-top-4"
                            alt="<?php echo e($package['title']); ?>"
                            style="max-height: 360px; object-fit: cover;"
                        >
                    <?php endif; ?>

                    <div class="card-body p-4">
                        <h2 class="fw-bold mb-2"><?php echo e($package['title']); ?></h2>
                        <div class="text-muted mb-3"><?php echo e($package['company_name']); ?></div>

                        <div class="mb-2"><strong>Duration:</strong> <?php echo e((int)$package['duration_days']); ?> days</div>
                        <div class="mb-3"><strong>Base Price:</strong> <?php echo e(number_format((float)$package['price'], 2)); ?> MMK</div>

                        <hr>

                        <h5 class="fw-bold">Description</h5>
                        <p><?php echo nl2br(e($package['description'])); ?></p>

                        <?php if (!empty($package['hotel_info'])): ?>
                            <h6 class="fw-bold mt-4">Hotel Info</h6>
                            <p><?php echo nl2br(e($package['hotel_info'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($package['transport_info'])): ?>
                            <h6 class="fw-bold mt-4">Transport Info</h6>
                            <p><?php echo nl2br(e($package['transport_info'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($package['itinerary'])): ?>
                            <h6 class="fw-bold mt-4">Itinerary</h6>
                            <p><?php echo nl2br(e($package['itinerary'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($package['included_services'])): ?>
                            <h6 class="fw-bold mt-4">Included Services</h6>
                            <p><?php echo nl2br(e($package['included_services'])); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($package['excluded_services'])): ?>
                            <h6 class="fw-bold mt-4">Excluded Services</h6>
                            <p><?php echo nl2br(e($package['excluded_services'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Available Batches</h5>

                        <?php if (empty($batches)): ?>
                            <div class="alert alert-warning mb-0">No open batches available for this package.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Price</th>
                                            <th>Seats Left</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($batches as $batch): ?>
                                            <tr>
                                                <td>
                                                    <?php echo e($batch['start_date']); ?><br>
                                                    <span class="small text-muted">to <?php echo e($batch['end_date']); ?></span>
                                                </td>
                                                <td><?php echo e(number_format((float)$batch['price'], 2)); ?></td>
                                                <td><?php echo e(tour_batch_remaining_slots($batch)); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo e(tour_batch_badge_class($batch['status'])); ?>">
                                                        <?php echo e(ucfirst($batch['status'])); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Create Tour Booking</h5>

                        <?php if (!is_logged_in()): ?>
                            <div class="alert alert-info rounded-4">Please login with a customer account to book this tour.</div>
                            <div class="d-grid">
                                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-primary">Login</a>
                            </div>
                        <?php elseif ($isOtherRole): ?>
                            <div class="alert alert-warning rounded-4 mb-0">Only customer accounts can create tour bookings.</div>
                        <?php elseif (empty($batches)): ?>
                            <div class="alert alert-warning rounded-4 mb-0">No open batches available for booking.</div>
                        <?php else: ?>
                            <form action="<?php echo BASE_URL; ?>actions/create_tour_booking.php" method="POST">
                                <input type="hidden" name="package_id" value="<?php echo e($package['id']); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Choose Batch</label>
                                    <select name="batch_id" class="form-select" required>
                                        <option value="">Select batch</option>
                                        <?php foreach ($batches as $batch): ?>
                                            <?php $remaining = tour_batch_remaining_slots($batch); ?>
                                            <?php if (tour_batch_is_bookable($batch)): ?>
                                                <option value="<?php echo e($batch['id']); ?>">
                                                    <?php echo e($batch['start_date']); ?> to <?php echo e($batch['end_date']); ?>
                                                    | <?php echo e(number_format((float)$batch['price'], 2)); ?> MMK
                                                    | Seats left: <?php echo e($remaining); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Passenger Count</label>
                                    <input type="number" name="passenger_count" class="form-control" min="1" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Traveler Names</label>
                                    <textarea name="traveler_names" class="form-control" rows="5" placeholder="One traveler name per line" required></textarea>
                                    <small class="text-muted">Passenger count နဲ့ name line count တူရမယ်.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Customer Note</label>
                                    <textarea name="customer_note" class="form-control" rows="3" placeholder="Optional note"></textarea>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Create Tour Booking</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
