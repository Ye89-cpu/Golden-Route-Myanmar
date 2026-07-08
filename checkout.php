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

            <form action="<?php echo BASE_URL; ?>actions/create_booking.php" method="POST" id="bookingForm" novalidate>
                <input type="hidden" name="trip_id" value="<?php echo e($trip['trip_id']); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo e($bookingCsrfToken); ?>">

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
                                                            <?php echo in_array((int)$seat['id'], $oldSelectedSeatIds, true) ? 'checked' : ''; ?>
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

<?php
if (!empty($checkoutOldInput)) {
    clear_old_input();
}
require_once __DIR__ . '/includes/footer.php';
?>