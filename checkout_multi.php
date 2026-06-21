<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/booking_helper.php';
require_once __DIR__ . '/includes/seat_layout_helper.php';

$page_title = 'Two-Step Bus Checkout - Golden Route Myanmar';

$conn = getDBConnection();

$trip1Id = (int)($_GET['trip1_id'] ?? 0);
$trip2Id = (int)($_GET['trip2_id'] ?? 0);

$leg1 = $trip1Id > 0 ? fetch_trip_checkout_details($conn, $trip1Id) : null;
$leg2 = $trip2Id > 0 ? fetch_trip_checkout_details($conn, $trip2Id) : null;

$leg1Seats = $leg1 ? fetch_trip_seat_map($conn, $trip1Id, (int)$leg1['bus_id']) : [];
$leg2Seats = $leg2 ? fetch_trip_seat_map($conn, $trip2Id, (int)$leg2['bus_id']) : [];

$leg1Rows = group_seats_by_row($leg1Seats);
$leg2Rows = group_seats_by_row($leg2Seats);

$leg1Layout = $leg1 ? get_layout_config((string)$leg1['layout_type']) : ['labels' => [], 'aisle_after' => 0];
$leg2Layout = $leg2 ? get_layout_config((string)$leg2['layout_type']) : ['labels' => [], 'aisle_after' => 0];

$conn->close();

$currentUser = current_user();
$isCustomerLoggedIn = is_logged_in() && current_user_role() === 'customer';
$isLoggedInButNotCustomer = is_logged_in() && current_user_role() !== 'customer';

function render_multi_seat_board(string $legKey, array $rows, array $layoutConfig, bool $enabled): void
{
    foreach ($rows as $rowNo => $rowSeats): ?>
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
                                class="seat-checkbox <?php echo e($legKey); ?>-seat-checkbox"
                                id="<?php echo e($legKey); ?>_seat_<?php echo e($seat['id']); ?>"
                                name="<?php echo e($legKey); ?>_selected_seats[]"
                                value="<?php echo e($seat['id']); ?>"
                                data-leg="<?php echo e($legKey); ?>"
                                data-seat-id="<?php echo e($seat['id']); ?>"
                                data-seat-number="<?php echo e($seat['seat_number']); ?>"
                                <?php echo !$enabled ? 'disabled' : ''; ?>
                            >
                            <label
                                for="<?php echo e($legKey); ?>_seat_<?php echo e($seat['id']); ?>"
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
    <?php endforeach;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="checkout-header mb-4">
        <div>
            <span class="section-kicker">Two-Step Booking</span>
            <h1 class="page-title mb-2">Choose seats for both buses</h1>
            <p class="page-subtitle mb-0">This checkout books both legs together and combines the total price.</p>
        </div>
        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-nav-soft">Back to Search</a>
        </div>
    </div>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$leg1 || !$leg2): ?>
        <div class="empty-state-card"><h3>Invalid two-step trip</h3><p>Please go back and search again.</p></div>
    <?php elseif ($leg1['trip_status'] !== 'open' || $leg2['trip_status'] !== 'open'): ?>
        <div class="alert alert-warning">One of these trips is no longer open for booking.</div>
    <?php else: ?>
        <div class="trip-summary-shell mb-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h5 class="fw-bold">Leg 1: <?php echo e($leg1['from_city_name']); ?> → <?php echo e($leg1['to_city_name']); ?></h5>
                    <div class="small text-muted mb-2"><?php echo e($leg1['company_name']); ?> | Bus <?php echo e($leg1['bus_number']); ?></div>
                    <div><?php echo e(date('Y-m-d H:i', strtotime($leg1['departure_datetime']))); ?> → <?php echo e(date('Y-m-d H:i', strtotime($leg1['arrival_datetime']))); ?></div>
                    <strong><?php echo e(number_format((float)$leg1['price'], 2)); ?> MMK</strong>
                </div>
                <div class="col-lg-6">
                    <h5 class="fw-bold">Leg 2: <?php echo e($leg2['from_city_name']); ?> → <?php echo e($leg2['to_city_name']); ?></h5>
                    <div class="small text-muted mb-2"><?php echo e($leg2['company_name']); ?> | Bus <?php echo e($leg2['bus_number']); ?></div>
                    <div><?php echo e(date('Y-m-d H:i', strtotime($leg2['departure_datetime']))); ?> → <?php echo e(date('Y-m-d H:i', strtotime($leg2['arrival_datetime']))); ?></div>
                    <strong><?php echo e(number_format((float)$leg2['price'], 2)); ?> MMK</strong>
                </div>
            </div>
        </div>

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

        <form action="<?php echo BASE_URL; ?>actions/create_multi_booking.php" method="POST" id="multiBookingForm">
            <input type="hidden" name="trip1_id" value="<?php echo e($trip1Id); ?>">
            <input type="hidden" name="trip2_id" value="<?php echo e($trip2Id); ?>">

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="panel-card h-100">
                        <div class="panel-card-header">
                            <h4>Bus 1 Seats</h4>
                            <p><?php echo e($leg1['from_city_name']); ?> → <?php echo e($leg1['to_city_name']); ?></p>
                        </div>
                        <div class="seat-board-theme mt-3">
                            <?php render_multi_seat_board('leg1', $leg1Rows, $leg1Layout, $isCustomerLoggedIn); ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="panel-card h-100">
                        <div class="panel-card-header">
                            <h4>Bus 2 Seats</h4>
                            <p><?php echo e($leg2['from_city_name']); ?> → <?php echo e($leg2['to_city_name']); ?></p>
                        </div>
                        <div class="seat-board-theme mt-3">
                            <?php render_multi_seat_board('leg2', $leg2Rows, $leg2Layout, $isCustomerLoggedIn); ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="panel-card">
                        <div class="panel-card-header">
                            <h4>Passenger Details</h4>
                            <p>Select the same number of seats for both buses. Fill one contact person only.</p>
                        </div>

                        <div class="booking-totals-box mb-3">
                            <div class="d-flex justify-content-between"><span>Bus 1 Seats</span><strong id="leg1Count">0</strong></div>
                            <div class="d-flex justify-content-between"><span>Bus 2 Seats</span><strong id="leg2Count">0</strong></div>
                            <div class="d-flex justify-content-between"><span>Total Amount</span><strong id="totalAmount">0.00 MMK</strong></div>
                        </div>

                        <div id="multiNotice" class="alert alert-info rounded-4" style="display:none;"></div>
                        <div id="hiddenSelectedSeats"></div>

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
                                <textarea name="passenger_names_text" class="form-control" rows="4" placeholder="Optional. One passenger name per line."></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Customer Note</label>
                                <textarea name="customer_note" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-brand w-100" <?php echo !$isCustomerLoggedIn ? 'disabled' : ''; ?>>
                                    Create Two-Step Booking
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <script>
        (function () {
            const leg1Price = <?php echo json_encode((float)$leg1['price']); ?>;
            const leg2Price = <?php echo json_encode((float)$leg2['price']); ?>;
            const leg1Boxes = document.querySelectorAll('.leg1-seat-checkbox');
            const leg2Boxes = document.querySelectorAll('.leg2-seat-checkbox');
            const leg1Count = document.getElementById('leg1Count');
            const leg2Count = document.getElementById('leg2Count');
            const totalAmount = document.getElementById('totalAmount');
            const multiNotice = document.getElementById('multiNotice');

            function selected(boxes) {
                return Array.from(boxes).filter(cb => cb.checked).map(cb => cb.dataset.seatNumber);
            }

            function refresh() {
                const s1 = selected(leg1Boxes);
                const s2 = selected(leg2Boxes);
                leg1Count.textContent = s1.length;
                leg2Count.textContent = s2.length;
                totalAmount.textContent = (Math.min(s1.length, s2.length) * (leg1Price + leg2Price)).toFixed(2) + ' MMK';

                if (!s1.length && !s2.length) {
                    multiNotice.style.display = 'none';
                    return;
                }

                multiNotice.style.display = 'block';
                if (s1.length !== s2.length) {
                    multiNotice.className = 'alert alert-danger rounded-4';
                    multiNotice.textContent = 'Bus 1 and Bus 2 selected seat counts must be the same.';
                } else if (s1.length >= 3) {
                    multiNotice.className = 'alert alert-warning rounded-4';
                    multiNotice.textContent = '⚠️ 3 ယောက်နှင့်အထက် booking ဖြစ်သောကြောင့် ကားစီးချိန်တွင် NRC / ID card ယူလာရန် သတိပေးပါ။';
                } else {
                    multiNotice.className = 'alert alert-info rounded-4';
                    multiNotice.textContent = 'Ready to book ' + s1.length + ' passenger(s) for both buses.';
                }
            }

            leg1Boxes.forEach(cb => cb.addEventListener('change', refresh));
            leg2Boxes.forEach(cb => cb.addEventListener('change', refresh));
            refresh();
        })();
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
