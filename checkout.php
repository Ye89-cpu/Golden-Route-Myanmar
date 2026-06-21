<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/booking_helper.php';
require_once __DIR__ . '/includes/seat_layout_helper.php';

$page_title = 'Checkout - Golden Route Myanmar';

$conn = getDBConnection();

$tripId = (int)($_GET['trip_id'] ?? 0);
$trip = null;
$seats = [];
$rows = [];
$layoutConfig = ['labels' => [], 'aisle_after' => 0];
$availableSeatCount = 0;

if ($tripId > 0) {
    $trip = fetch_trip_checkout_details($conn, $tripId);

    if ($trip) {
        $seats = fetch_trip_seat_map($conn, $tripId, (int)$trip['bus_id']);
        $rows = group_seats_by_row($seats);
        $layoutConfig = get_layout_config((string)$trip['layout_type']);
        $availableSeatCount = count_current_available_seats($seats);
    }
}

$conn->close();

$currentUser = current_user();
$isCustomerLoggedIn = is_logged_in() && current_user_role() === 'customer';
$isLoggedInButNotCustomer = is_logged_in() && current_user_role() !== 'customer';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="checkout-header mb-4">
        <div>
            <span class="section-kicker">Checkout</span>
            <h1 class="page-title mb-2">Bus ticket checkout</h1>
            <p class="page-subtitle mb-0">Select seats and fill passenger details carefully before creating the booking.</p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-nav-soft">Back to Search</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if ($tripId <= 0): ?>
        <div class="empty-state-card"><h3>Invalid trip ID</h3><p>Please go back and search again.</p></div>
    <?php elseif (!$trip): ?>
        <div class="empty-state-card"><h3>Trip not found</h3><p>The selected trip does not exist or is no longer available.</p></div>
    <?php else: ?>
        <div class="trip-summary-shell mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <h3 class="mb-2"><?php echo e($trip['company_name']); ?></h3>
                    <div class="trip-route-line mb-3">
                        <div><small>From</small><strong><?php echo e($trip['from_city_name']); ?></strong></div>
                        <div class="trip-route-arrow">→</div>
                        <div class="text-end"><small>To</small><strong><?php echo e($trip['to_city_name']); ?></strong></div>
                    </div>

                    <div class="trip-meta-grid">
                        <div class="trip-meta-box">
                            <span>Date</span>
                            <strong><?php echo e($trip['trip_date']); ?></strong>
                        </div>
                        <div class="trip-meta-box">
                            <span>Departure</span>
                            <strong><?php echo e(date('Y-m-d H:i', strtotime($trip['departure_datetime']))); ?></strong>
                        </div>
                        <div class="trip-meta-box">
                            <span>Arrival</span>
                            <strong><?php echo e(date('Y-m-d H:i', strtotime($trip['arrival_datetime']))); ?></strong>
                        </div>
                        <div class="trip-meta-box">
                            <span>Bus / Type</span>
                            <strong><?php echo e($trip['bus_number']); ?> / <?php echo e(ucwords(str_replace('_', ' ', $trip['bus_type']))); ?></strong>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="trip-price-panel h-100">
                        <div class="trip-price-label">Seat Price</div>
                        <div class="trip-price-value"><?php echo e(number_format((float)$trip['price'], 2)); ?> MMK</div>
                        <div class="mt-3">
                            <?php if ($availableSeatCount > 0): ?>
                                <span class="badge text-bg-success">Available Seats: <?php echo e($availableSeatCount); ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-danger">Sold Out</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($trip['trip_status'] !== 'open' && $trip['trip_status'] !== 'full'): ?>
            <div class="alert alert-warning">This trip is not open for booking right now.</div>
        <?php elseif (empty($seats)): ?>
            <div class="alert alert-warning">No seat layout found for this trip's bus.</div>
        <?php else: ?>

            <?php if (!is_logged_in()): ?>
                <div class="alert alert-info">
                    Please login with a customer account to complete the booking.
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-brand btn-sm">Login</a>
                        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-nav-soft btn-sm">Register</a>
                    </div>
                </div>
            <?php elseif ($isLoggedInButNotCustomer): ?>
                <div class="alert alert-warning">Only customer accounts can place bookings.</div>
            <?php endif; ?>

            <form action="<?php echo BASE_URL; ?>actions/create_booking.php" method="POST" id="bookingForm">
                <input type="hidden" name="trip_id" value="<?php echo e($trip['trip_id']); ?>">

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h4>Select Seats</h4>
                                <p>Choose one or more seats. Passenger forms will appear automatically.</p>
                            </div>

                            <div class="seat-legend">
                                <span><i class="legend-dot legend-normal"></i> Normal</span>
                                <span><i class="legend-dot legend-vip"></i> VIP</span>
                                <span><i class="legend-dot legend-booked"></i> Booked</span>
                            </div>

                            <div class="seat-board-theme mt-3">
                                <?php foreach ($rows as $rowNo => $rowSeats): ?>
                                    <div class="seat-row-theme">
                                        <div class="seat-row-label">Row <?php echo e($rowNo); ?></div>

                                        <?php for ($i = 1; $i <= count($layoutConfig['labels']); $i++): ?>
                                            <?php if ($i === ((int)$layoutConfig['aisle_after'] + 1)): ?>
                                                <div class="seat-aisle-gap"></div>
                                            <?php endif; ?>

                                            <?php if (isset($rowSeats[$i])): ?>
                                                <?php $seat = $rowSeats[$i]; ?>
                                                <?php if (!empty($seat['is_booked'])): ?>
                                                    <div class="seat-box-theme seat-booked-theme"><?php echo e($seat['seat_number']); ?></div>
                                                <?php else: ?>
                                                    <div class="seat-select-wrap">
                                                        <input
                                                            type="checkbox"
                                                            class="seat-checkbox"
                                                            id="seat_<?php echo e($seat['id']); ?>"
                                                            name="selected_seats[]"
                                                            value="<?php echo e($seat['id']); ?>"
                                                            data-seat-id="<?php echo e($seat['id']); ?>"
                                                            data-seat-number="<?php echo e($seat['seat_number']); ?>"
                                                            data-seat-type="<?php echo e($seat['seat_type']); ?>"
                                                            <?php echo !$isCustomerLoggedIn ? 'disabled' : ''; ?>
                                                        >
                                                        <label
                                                            for="seat_<?php echo e($seat['id']); ?>"
                                                            class="seat-box-theme <?php echo $seat['seat_type'] === 'vip' ? 'seat-vip-theme' : 'seat-normal-theme'; ?>"
                                                        >
                                                            <?php echo e($seat['seat_number']); ?>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="seat-box-theme seat-empty-theme">—</div>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h4>Passenger Details</h4>
                                <p>Fill one contact passenger only. The system will create ticket records for all selected seats.</p>
                            </div>

                            <div class="booking-totals-box mb-3">
                                <div class="d-flex justify-content-between">
                                    <span>Selected Seats</span>
                                    <strong id="selectedSeatCount">0</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Total Amount</span>
                                    <strong id="selectedTotalAmount">0.00 MMK</strong>
                                </div>
                            </div>

                            <div id="selectedSeatList" class="selected-seat-list mb-3"></div>

                            <div id="bulkPassengerNotice" class="alert alert-info rounded-4 mb-3" style="display:none;"></div>

                            <div class="passenger-theme-card">
                                <h6 class="mb-3">Main Contact / Booker</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="booking_full_name" class="form-control" value="<?php echo e($currentUser['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="text" name="booking_phone" class="form-control" value="<?php echo e($currentUser['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">NRC / Passport</label>
                                        <input type="text" name="booking_nrc_passport" class="form-control">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Gender</label>
                                        <select name="booking_gender" class="form-select">
                                            <option value="">Select</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Age</label>
                                        <input type="number" min="0" name="booking_age" class="form-control">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Passenger Names (optional)</label>
                                        <textarea name="passenger_names_text" class="form-control" rows="4" placeholder="Optional. One passenger name per line. If left blank, main contact name will be used for all selected seats."></textarea>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Special Note</label>
                                        <input type="text" name="booking_special_note" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div id="passengerFormsContainer" class="passenger-forms-stack d-none"></div>

                            <div class="mt-3">
                                <label class="form-label">Customer Note</label>
                                <textarea name="customer_note" class="form-control" rows="4" placeholder="Optional note for this booking"></textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-brand w-100" <?php echo !$isCustomerLoggedIn ? 'disabled' : ''; ?>>
                                    Create Booking
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            (function () {
                const seatCheckboxes = document.querySelectorAll('.seat-checkbox');
                const passengerFormsContainer = document.getElementById('passengerFormsContainer');
                const selectedSeatList = document.getElementById('selectedSeatList');
                const selectedSeatCount = document.getElementById('selectedSeatCount');
                const selectedTotalAmount = document.getElementById('selectedTotalAmount');
                const seatPrice = <?php echo json_encode((float)$trip['price']); ?>;

                function escapeHtml(value) {
                    return String(value)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }

                function getSelectedSeats() {
                    return Array.from(seatCheckboxes)
                        .filter(cb => cb.checked)
                        .map(cb => ({
                            id: cb.dataset.seatId,
                            number: cb.dataset.seatNumber,
                            type: cb.dataset.seatType
                        }));
                }

                function renderPassengerForms() {
                    const seats = getSelectedSeats();
                    selectedSeatCount.textContent = seats.length;
                    selectedTotalAmount.textContent = (seats.length * seatPrice).toFixed(2) + ' MMK';

                    const bulkNotice = document.getElementById('bulkPassengerNotice');

                    if (!seats.length) {
                        selectedSeatList.innerHTML = '';
                        passengerFormsContainer.innerHTML = '';
                        bulkNotice.style.display = 'none';
                        return;
                    }

                    selectedSeatList.innerHTML = seats.map(seat =>
                        '<span class="selected-seat-pill">' + escapeHtml(seat.number) + '</span>'
                    ).join('');

                    bulkNotice.style.display = 'block';
                    if (seats.length >= 3) {
                        bulkNotice.className = 'alert alert-warning rounded-4 mb-3';
                        bulkNotice.innerHTML = '⚠️ 3 ယောက်နှင့်အထက် booking ဖြစ်သောကြောင့် ကားစီးချိန်တွင် NRC / ID card ယူလာရန် သတိပေးပါ။';
                    } else {
                        bulkNotice.className = 'alert alert-info rounded-4 mb-3';
                        bulkNotice.innerHTML = 'Selected seats: ' + seats.length + '. One contact detail is enough for this booking.';
                    }

                    passengerFormsContainer.innerHTML = seats.map((seat) => `
                        <input type="hidden" name="passenger_seat_id[]" value="${escapeHtml(seat.id)}">
                        <input type="hidden" name="passenger_seat_number[]" value="${escapeHtml(seat.number)}">
                    `).join('');
                }

                seatCheckboxes.forEach(cb => cb.addEventListener('change', renderPassengerForms));
                renderPassengerForms();
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>