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
        try {
            // Some buses were created without rows in bus_seats.
            // Create the layout on demand so a new trip is not shown as Sold Out.
            ensure_bus_seat_layout($conn, (int)$trip['bus_id']);
            refresh_trip_available_seats($conn, $tripId);
            $refreshedTrip = fetch_trip_checkout_details($conn, $tripId);
            if ($refreshedTrip) {
                $trip = $refreshedTrip;
            }
        } catch (Throwable $seatLayoutError) {
            // The page can still show the original warning below if layout creation fails.
        }

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

if (empty($_SESSION['booking_csrf_token'])) {
    $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
}
$bookingCsrfToken = (string)$_SESSION['booking_csrf_token'];

$checkoutOldInput = [];
$sessionOldInput = $_SESSION['old_input'] ?? null;
if (is_array($sessionOldInput) && (int)($sessionOldInput['trip_id'] ?? 0) === $tripId) {
    $checkoutOldInput = $sessionOldInput;
}
$oldSelectedSeatIds = array_values(array_unique(array_filter(array_map(
    'intval',
    is_array($checkoutOldInput['selected_seats'] ?? null) ? $checkoutOldInput['selected_seats'] : []
))));
$checkoutOld = static function (string $key, $default = '') use ($checkoutOldInput) {
    return array_key_exists($key, $checkoutOldInput) ? $checkoutOldInput[$key] : $default;
};

require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkout-seat-page {
    background:
        radial-gradient(circle at 8% 2%, rgba(200,149,57,.13), transparent 24%),
        linear-gradient(180deg, #f8f5ef 0%, #f5f7fb 100%);
    min-height: 75vh;
}

.checkout-seat-page .checkout-header {
    padding: 28px 30px;
    align-items: center;
    border: 1px solid rgba(20,34,60,.08);
    border-radius: 26px;
    background: rgba(255,255,255,.90);
    box-shadow: 0 16px 42px rgba(20,34,60,.07);
}

.checkout-seat-page .trip-summary-shell {
    border: 0;
    border-radius: 28px;
    background:
        radial-gradient(circle at 88% 10%, rgba(246,201,105,.20), transparent 25%),
        linear-gradient(135deg, #14233e, #24446f);
    color: #fff;
    box-shadow: 0 25px 55px rgba(20,35,62,.20);
}

.checkout-seat-page .trip-summary-shell h3,
.checkout-seat-page .trip-summary-shell strong,
.checkout-seat-page .trip-price-value {
    color: #fff;
}

.checkout-seat-page .trip-summary-shell small,
.checkout-seat-page .trip-summary-shell span,
.checkout-seat-page .trip-price-label {
    color: rgba(255,255,255,.70);
}

.checkout-seat-page .trip-meta-box,
.checkout-seat-page .trip-price-panel {
    border-color: rgba(255,255,255,.13);
    background: rgba(255,255,255,.09);
    box-shadow: none;
}

.checkout-seat-page .panel-card {
    border: 1px solid rgba(20,34,60,.08);
    border-radius: 28px;
    background: rgba(255,255,255,.94);
    box-shadow: 0 20px 48px rgba(20,34,60,.08);
}

.checkout-seat-page .panel-card-header {
    display: block;
}

.checkout-seat-page .panel-card-header h4 {
    margin-bottom: 5px;
    color: #17243d;
    font-weight: 850;
}

.checkout-seat-page .panel-card-header p {
    margin: 0;
    color: #667085;
}

.checkout-seat-page .seat-legend-modern {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-bottom: 15px;
}

.checkout-seat-page .seat-legend-modern span {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 11px;
    border: 1px solid rgba(20,34,60,.08);
    border-radius: 999px;
    background: #fff;
    color: #5f6978;
    font-size: .78rem;
    font-weight: 750;
}

.checkout-seat-page .seat-legend-modern i {
    width: 13px;
    height: 13px;
    display: inline-block;
    border-radius: 4px;
}

.checkout-seat-page .legend-seat-normal { background: #f8fafc; border: 1px solid #cfd7e3; }
.checkout-seat-page .legend-seat-vip { background: #f8e9c6; border: 1px solid #d7a84f; }
.checkout-seat-page .legend-seat-selected { background: #1d6b48; border: 1px solid #1d6b48; }
.checkout-seat-page .legend-seat-booked { background: #d9dee6; border: 1px solid #c5cbd5; }

.checkout-seat-page .bus-cabin-shell {
    position: relative;
    max-width: 590px;
    margin: 0 auto;
    padding: 18px 18px 24px;
    border: 3px solid #263852;
    border-radius: 38px 38px 26px 26px;
    background:
        linear-gradient(90deg, rgba(20,35,62,.035) 1px, transparent 1px) 0 0/24px 24px,
        #eef2f6;
    box-shadow:
        inset 0 0 0 6px rgba(255,255,255,.78),
        0 20px 45px rgba(20,35,62,.15);
}

.checkout-seat-page .bus-cabin-shell::before,
.checkout-seat-page .bus-cabin-shell::after {
    content: '';
    position: absolute;
    top: 112px;
    width: 8px;
    height: 68px;
    border-radius: 8px;
    background: #263852;
}

.checkout-seat-page .bus-cabin-shell::before { left: -8px; }
.checkout-seat-page .bus-cabin-shell::after { right: -8px; }

.checkout-seat-page .bus-front-zone {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    align-items: center;
    margin-bottom: 15px;
    padding: 13px 15px;
    border-radius: 24px 24px 16px 16px;
    background: linear-gradient(135deg, #dce6f1, #f8fafc);
    border: 1px solid rgba(20,35,62,.10);
}

.checkout-seat-page .bus-windshield {
    min-height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    background: linear-gradient(135deg, #203b61, #4a6d95);
    color: rgba(255,255,255,.82);
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.checkout-seat-page .driver-cockpit {
    width: 54px;
    height: 54px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 17px;
    background: #15243d;
    color: #fff;
    font-size: .65rem;
    font-weight: 750;
}

.checkout-seat-page .driver-cockpit i {
    font-size: 1rem;
    margin-bottom: 2px;
    color: #efc76f;
}

.checkout-seat-page .bus-direction-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin: 2px 0 12px;
    color: #778295;
    font-size: .72rem;
    font-weight: 800;
    letter-spacing: .11em;
    text-transform: uppercase;
}

.checkout-seat-page .seat-board-theme {
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    overflow-x: auto;
}

.checkout-seat-page .seat-row-theme {
    min-width: max-content;
    padding: 9px 11px;
    margin-bottom: 8px;
    border: 0;
    border-radius: 15px;
    background: rgba(255,255,255,.66);
}

.checkout-seat-page .seat-row-theme:last-child {
    margin-bottom: 0;
}

.checkout-seat-page .seat-row-label {
    min-width: 56px;
    color: #758095;
    font-size: .72rem;
    font-weight: 800;
}

.checkout-seat-page .seat-box-theme,
.checkout-seat-page .seat-empty-theme,
.checkout-seat-page .seat-booked-theme,
.checkout-seat-page .seat-vip-theme,
.checkout-seat-page .seat-normal-theme {
    position: relative;
    width: 50px;
    height: 48px;
    border-radius: 13px 13px 10px 10px;
    font-size: .82rem;
    font-weight: 900;
}

.checkout-seat-page .seat-box-theme::before {
    content: '';
    position: absolute;
    top: 5px;
    left: 7px;
    right: 7px;
    height: 7px;
    border-radius: 5px;
    background: currentColor;
    opacity: .12;
}

.checkout-seat-page .seat-normal-theme {
    border: 1px solid #c9d2df;
    background: #fff;
    color: #243650;
    box-shadow: 0 5px 10px rgba(20,35,62,.06);
}

.checkout-seat-page .seat-vip-theme {
    border: 1px solid #d3a247;
    background: linear-gradient(180deg, #fff8e8, #f8e6be);
    color: #8c5d10;
    box-shadow: 0 5px 12px rgba(179,120,22,.10);
}

.checkout-seat-page .seat-booked-theme {
    border: 1px solid #c8ced8;
    background: #dce1e8;
    color: #8d96a5;
    text-decoration: line-through;
    box-shadow: none;
}

.checkout-seat-page .seat-empty-theme {
    border: 1px dashed rgba(117,128,149,.25);
    background: transparent;
    color: rgba(117,128,149,.28);
}

.checkout-seat-page .seat-select-wrap label:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 18px rgba(20,35,62,.12);
}

.checkout-seat-page .seat-checkbox:checked + label,
.checkout-seat-page .seat-checkbox:checked + label.seat-normal-theme,
.checkout-seat-page .seat-checkbox:checked + label.seat-vip-theme {
    border-color: #155d3f !important;
    background: linear-gradient(145deg, #2e8b63, #176044) !important;
    color: #fff !important;
    box-shadow: 0 10px 22px rgba(23,96,68,.28);
    transform: translateY(-2px);
}

.checkout-seat-page .bus-exit-zone {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 15px;
    padding: 10px 13px;
    border-radius: 14px;
    background: rgba(20,35,62,.055);
    color: #6e798a;
    font-size: .73rem;
    font-weight: 750;
}

.checkout-seat-page .booking-totals-box {
    padding: 18px;
    border: 1px solid rgba(200,149,57,.18);
    background: linear-gradient(135deg, rgba(200,149,57,.12), rgba(255,255,255,.8));
}

.checkout-seat-page .booking-totals-box strong {
    color: #17243d;
    font-size: 1.02rem;
}

.checkout-seat-page .selected-seat-pill {
    background: #176044;
    border-color: #176044;
    color: #fff;
}

.checkout-seat-page .passenger-theme-card {
    background: #f8fafc;
}

@media (max-width: 575.98px) {
    .checkout-seat-page .checkout-header { padding: 23px 20px; }
    .checkout-seat-page .bus-cabin-shell { padding: 13px 11px 18px; border-radius: 28px 28px 22px 22px; }
    .checkout-seat-page .seat-row-label { min-width: 48px; }
    .checkout-seat-page .seat-box-theme,
    .checkout-seat-page .seat-empty-theme,
    .checkout-seat-page .seat-booked-theme,
    .checkout-seat-page .seat-vip-theme,
    .checkout-seat-page .seat-normal-theme { width: 43px; height: 43px; }
    .checkout-seat-page .seat-aisle-gap { width: 18px; flex-basis: 18px; }
}
</style>

<main class="checkout-seat-page py-5">
    <div class="container">
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

            <form action="<?php echo BASE_URL; ?>actions/create_booking.php" method="POST" id="bookingForm" novalidate>
                <input type="hidden" name="trip_id" value="<?php echo e($trip['trip_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e($bookingCsrfToken); ?>">

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h4><i class="bi bi-grid-3x3-gap me-2"></i>Select Your Seats</h4>
                                <p>Choose available seats from the bus layout. Selected seats will appear in green.</p>
                            </div>

                            <div class="seat-legend-modern">
                                <span><i class="legend-seat-normal"></i> Normal</span>
                                <span><i class="legend-seat-vip"></i> VIP</span>
                                <span><i class="legend-seat-selected"></i> Selected</span>
                                <span><i class="legend-seat-booked"></i> Booked</span>
                            </div>

                            <div class="bus-cabin-shell">
                                <div class="bus-front-zone">
                                    <div class="bus-windshield">Front of Bus</div>
                                    <div class="driver-cockpit"><i class="bi bi-steering-wheel"></i>Driver</div>
                                </div>
                                <div class="bus-direction-label"><i class="bi bi-arrow-up"></i> Travel direction</div>

                                <div class="seat-board-theme">
                                    <?php foreach ($rows as $rowNo => $rowSeats): ?>
                                        <div class="seat-row-theme">
                                            <div class="seat-row-label">Row <?php echo e($rowNo); ?></div>

                                            <?php for ($i = 1; $i <= count($layoutConfig['labels']); $i++): ?>
                                                <?php if ($i === ((int)$layoutConfig['aisle_after'] + 1)): ?>
                                                    <div class="seat-aisle-gap" aria-label="Aisle"></div>
                                                <?php endif; ?>

                                                <?php if (isset($rowSeats[$i])): ?>
                                                    <?php $seat = $rowSeats[$i]; ?>
                                                    <?php if (!empty($seat['is_booked'])): ?>
                                                        <div class="seat-box-theme seat-booked-theme" title="Seat <?php echo e($seat['seat_number']); ?> is booked"><?php echo e($seat['seat_number']); ?></div>
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
                                                                <?php echo in_array((int)$seat['id'], $oldSelectedSeatIds, true) ? 'checked' : ''; ?>
                                                                <?php echo !$isCustomerLoggedIn ? 'disabled' : ''; ?>
                                                            >
                                                            <label
                                                                for="seat_<?php echo e($seat['id']); ?>"
                                                                class="seat-box-theme <?php echo $seat['seat_type'] === 'vip' ? 'seat-vip-theme' : 'seat-normal-theme'; ?>"
                                                                title="Select seat <?php echo e($seat['seat_number']); ?>"
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

                                <div class="bus-exit-zone">
                                    <span><i class="bi bi-door-open me-1"></i> Passenger entrance</span>
                                    <span><?php echo e(ucwords(str_replace('_', ' ', (string)$trip['layout_type']))); ?> layout</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="panel-card h-100">
                            <div class="panel-card-header">
                                <h4><i class="bi bi-person-vcard me-2"></i>Passenger Details</h4>
                                <p>Enter the main contact information and review the selected-seat total.</p>
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

                            <div id="selectedSeatList" class="selected-seat-list mb-2"></div>
                            <div id="seatValidationMessage" class="text-danger small mb-3" role="alert" style="display:none;">
                                Please select at least one seat.
                            </div>

                            <div id="bulkPassengerNotice" class="alert alert-info rounded-4 mb-3" style="display:none;"></div>

                            <div class="passenger-theme-card">
                                <h6 class="mb-3">Main Contact / Booker</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="bookingFullName">Full Name <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="bookingFullName"
                                            name="booking_full_name"
                                            class="form-control"
                                            value="<?php echo e($checkoutOld('booking_full_name', $currentUser['name'] ?? '')); ?>"
                                            minlength="2"
                                            maxlength="120"
                                            autocomplete="name"
                                            required
                                        >
                                        <div class="invalid-feedback">Enter a valid full name (2–120 characters, no numbers).</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="bookingPhone">Phone <span class="text-danger">*</span></label>
                                        <input
                                            type="tel"
                                            id="bookingPhone"
                                            name="booking_phone"
                                            class="form-control"
                                            value="<?php echo e($checkoutOld('booking_phone', $currentUser['phone'] ?? '')); ?>"
                                            maxlength="20"
                                            inputmode="tel"
                                            autocomplete="tel"
                                            placeholder="09xxxxxxxxx or +959xxxxxxxxx"
                                            required
                                        >
                                        <div class="invalid-feedback">Enter a valid Myanmar phone number.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="bookingNrcPassport">NRC / Passport <span class="text-danger">*</span></label>
                                        <input
                                            type="text"
                                            id="bookingNrcPassport"
                                            name="booking_nrc_passport"
                                            class="form-control"
                                            value="<?php echo e($checkoutOld('booking_nrc_passport')); ?>"
                                            minlength="4"
                                            maxlength="50"
                                            autocomplete="off"
                                            required
                                        >
                                        <div class="invalid-feedback">Enter a valid NRC or passport number (4–50 characters).</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="bookingGender">Gender <span class="text-danger">*</span></label>
                                        <select id="bookingGender" name="booking_gender" class="form-select" required>
                                            <option value="">Select</option>
                                            <option value="male" <?php echo $checkoutOld('booking_gender') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo $checkoutOld('booking_gender') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo $checkoutOld('booking_gender') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                        <div class="invalid-feedback">Select a gender.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="bookingAge">Age <span class="text-danger">*</span></label>
                                        <input
                                            type="number"
                                            id="bookingAge"
                                            name="booking_age"
                                            class="form-control"
                                            value="<?php echo e($checkoutOld('booking_age')); ?>"
                                            min="0"
                                            max="120"
                                            step="1"
                                            inputmode="numeric"
                                            required
                                        >
                                        <div class="invalid-feedback">Enter an age from 0 to 120.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="passengerNamesText">Passenger Names <span class="text-muted">(optional)</span></label>
                                        <textarea
                                            id="passengerNamesText"
                                            name="passenger_names_text"
                                            class="form-control"
                                            rows="4"
                                            maxlength="2000"
                                            placeholder="One passenger name per line. Leave blank to use the main contact name for every selected seat."
                                        ><?php echo e($checkoutOld('passenger_names_text')); ?></textarea>
                                        <div class="form-text">The number of names cannot exceed the number of selected seats.</div>
                                        <div class="invalid-feedback">Check the passenger names and selected-seat count.</div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="bookingSpecialNote">Special Note <span class="text-muted">(optional)</span></label>
                                        <input
                                            type="text"
                                            id="bookingSpecialNote"
                                            name="booking_special_note"
                                            class="form-control"
                                            value="<?php echo e($checkoutOld('booking_special_note')); ?>"
                                            maxlength="200"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div id="passengerFormsContainer" class="passenger-forms-stack d-none"></div>

                            <div class="mt-3">
                                <label class="form-label" for="customerNote">Customer Note <span class="text-muted">(optional)</span></label>
                                <textarea
                                    id="customerNote"
                                    name="customer_note"
                                    class="form-control"
                                    rows="4"
                                    maxlength="2000"
                                    placeholder="Optional note for this booking"
                                ><?php echo e($checkoutOld('customer_note')); ?></textarea>
                            </div>

                            <div class="mt-4">
                                <button type="submit" id="bookingSubmitButton" class="btn btn-brand w-100" <?php echo !$isCustomerLoggedIn ? 'disabled' : ''; ?>>
                                    Create Booking &amp; Continue to Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <script>
            (function () {
                const bookingForm = document.getElementById('bookingForm');
                const seatCheckboxes = document.querySelectorAll('.seat-checkbox');
                const passengerFormsContainer = document.getElementById('passengerFormsContainer');
                const selectedSeatList = document.getElementById('selectedSeatList');
                const selectedSeatCount = document.getElementById('selectedSeatCount');
                const selectedTotalAmount = document.getElementById('selectedTotalAmount');
                const seatValidationMessage = document.getElementById('seatValidationMessage');
                const passengerNamesText = document.getElementById('passengerNamesText');
                const bookingFullName = document.getElementById('bookingFullName');
                const bookingPhone = document.getElementById('bookingPhone');
                const bookingNrcPassport = document.getElementById('bookingNrcPassport');
                const bookingAge = document.getElementById('bookingAge');
                const submitButton = document.getElementById('bookingSubmitButton');
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

                function isValidName(value) {
                    const name = value.trim().replace(/\s+/g, ' ');
                    if (name.length < 2 || name.length > 120 || /[0-9]/.test(name)) {
                        return false;
                    }

                    try {
                        return /^[\p{L}\p{M}][\p{L}\p{M}\s.'-]*$/u.test(name);
                    } catch (error) {
                        return /^[^0-9<>]{2,120}$/.test(name);
                    }
                }

                function normalizePhone(value) {
                    let phone = value.trim().replace(/[\s()-]/g, '');
                    if (phone.startsWith('0095')) {
                        phone = '+95' + phone.slice(4);
                    }
                    return phone;
                }

                function validateContactFields() {
                    const fullName = bookingFullName.value.trim().replace(/\s+/g, ' ');
                    bookingFullName.value = fullName;
                    bookingFullName.setCustomValidity(isValidName(fullName) ? '' : 'Enter a valid full name.');

                    const phone = normalizePhone(bookingPhone.value);
                    bookingPhone.value = phone;
                    bookingPhone.setCustomValidity(/^(?:09\d{7,9}|\+959\d{7,9})$/.test(phone) ? '' : 'Enter a valid Myanmar phone number.');

                    const nrcPassport = bookingNrcPassport.value.trim().replace(/\s+/g, ' ');
                    bookingNrcPassport.value = nrcPassport;
                    let validIdentity = nrcPassport.length >= 4 && nrcPassport.length <= 50;
                    try {
                        validIdentity = validIdentity && /^[\p{L}\p{M}\p{N}\/().\-\s]+$/u.test(nrcPassport);
                    } catch (error) {
                        validIdentity = validIdentity && !/[<>]/.test(nrcPassport);
                    }
                    bookingNrcPassport.setCustomValidity(validIdentity ? '' : 'Enter a valid NRC or passport number.');

                    const age = Number(bookingAge.value);
                    const validAge = bookingAge.value !== '' && Number.isInteger(age) && age >= 0 && age <= 120;
                    bookingAge.setCustomValidity(validAge ? '' : 'Enter an age from 0 to 120.');
                }

                function validatePassengerNames() {
                    const selectedCount = getSelectedSeats().length;
                    const names = passengerNamesText.value
                        .split(/\r?\n/)
                        .map(name => name.trim().replace(/\s+/g, ' '))
                        .filter(Boolean);

                    const valid = names.length <= selectedCount && names.every(isValidName);
                    passengerNamesText.setCustomValidity(valid ? '' : 'Passenger names are invalid or exceed the selected-seat count.');
                    return valid;
                }

                function validateSeats(showMessage) {
                    const hasSeat = getSelectedSeats().length > 0;
                    seatValidationMessage.style.display = !hasSeat && showMessage ? 'block' : 'none';
                    return hasSeat;
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
                        validatePassengerNames();
                        return;
                    }

                    seatValidationMessage.style.display = 'none';
                    selectedSeatList.innerHTML = seats.map(seat =>
                        '<span class="selected-seat-pill">' + escapeHtml(seat.number) + '</span>'
                    ).join('');

                    bulkNotice.style.display = 'block';
                    if (seats.length >= 3) {
                        bulkNotice.className = 'alert alert-warning rounded-4 mb-3';
                        bulkNotice.innerHTML = '⚠️ 3 ယောက်နှင့်အထက် booking ဖြစ်သောကြောင့် ကားစီးချိန်တွင် NRC / ID card ယူလာရန် သတိပေးပါ။';
                    } else {
                        bulkNotice.className = 'alert alert-info rounded-4 mb-3';
                        bulkNotice.innerHTML = 'Selected seats: ' + seats.length + '. One main contact is required for this booking.';
                    }

                    passengerFormsContainer.innerHTML = seats.map((seat) => `
                        <input type="hidden" name="passenger_seat_id[]" value="${escapeHtml(seat.id)}">
                        <input type="hidden" name="passenger_seat_number[]" value="${escapeHtml(seat.number)}">
                    `).join('');

                    validatePassengerNames();
                }

                seatCheckboxes.forEach(cb => cb.addEventListener('change', renderPassengerForms));
                [bookingFullName, bookingPhone, bookingNrcPassport, bookingAge].forEach(field => {
                    field.addEventListener('input', validateContactFields);
                    field.addEventListener('change', validateContactFields);
                });
                passengerNamesText.addEventListener('input', validatePassengerNames);

                bookingForm.addEventListener('submit', function (event) {
                    validateContactFields();
                    const seatsAreValid = validateSeats(true);
                    validatePassengerNames();

                    if (!bookingForm.checkValidity() || !seatsAreValid) {
                        event.preventDefault();
                        event.stopPropagation();
                        bookingForm.classList.add('was-validated');

                        if (!seatsAreValid) {
                            seatValidationMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            return;
                        }

                        const firstInvalid = bookingForm.querySelector(':invalid');
                        if (firstInvalid) {
                            firstInvalid.focus();
                        }
                        return;
                    }

                    submitButton.disabled = true;
                    submitButton.textContent = 'Creating booking...';
                });

                renderPassengerForms();
                validateContactFields();
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</main>

<?php
if (!empty($checkoutOldInput)) {
    clear_old_input();
}
require_once __DIR__ . '/includes/footer.php';
?>