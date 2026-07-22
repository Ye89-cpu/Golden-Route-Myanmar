<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_history_helper.php';

require_role('customer');

$page_title = 'My Bookings - Golden Route Myanmar';

$conn = getDBConnection();
$currentUserId = (int)current_user_id();

try {
    $allRows = fetch_customer_booking_history($conn, $currentUserId);
} catch (Throwable $e) {
    $conn->close();
    die('Booking history error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$conn->close();

$typeFilter = trim((string)($_GET['type'] ?? 'all'));
$paymentFilter = trim((string)($_GET['payment'] ?? 'all'));

$allowedTypeFilters = ['all', 'bus', 'tour'];
$allowedPaymentFilters = ['all', 'unpaid', 'pending_review', 'paid', 'failed', 'refunded'];

if (!in_array($typeFilter, $allowedTypeFilters, true)) {
    $typeFilter = 'all';
}

if (!in_array($paymentFilter, $allowedPaymentFilters, true)) {
    $paymentFilter = 'all';
}

$summary = summarize_customer_booking_history($allRows);
$rows = filter_customer_booking_history($allRows, $typeFilter, $paymentFilter);

$summaryCards = [
    ['label' => 'Total Bookings', 'value' => $summary['total_bookings'], 'icon' => 'bi-journal-check', 'tone' => 'navy'],
    ['label' => 'Bus Trips', 'value' => $summary['bus_bookings'], 'icon' => 'bi-bus-front', 'tone' => 'blue'],
    ['label' => 'Tour Packages', 'value' => $summary['tour_bookings'], 'icon' => 'bi-map', 'tone' => 'cyan'],
    ['label' => 'Refund Pending', 'value' => $summary['refund_pending'], 'icon' => 'bi-hourglass-split', 'tone' => 'amber'],
    ['label' => 'Refund Approved', 'value' => $summary['refund_approved'], 'icon' => 'bi-check2-circle', 'tone' => 'green'],
    ['label' => 'Total Paid', 'value' => number_format((float)$summary['total_spent'], 2) . ' MMK', 'icon' => 'bi-wallet2', 'tone' => 'gold'],
];

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.booking-history-page {
    --booking-navy: #15233d;
    --booking-muted: #667085;
    --booking-gold: #c89539;
    --booking-border: rgba(21, 35, 61, .09);
    background:
        radial-gradient(circle at top left, rgba(200,149,57,.12), transparent 24%),
        linear-gradient(180deg, #f8f5ef 0%, #f5f7fb 100%);
    min-height: 75vh;
}

.booking-history-page .booking-hero {
    position: relative;
    overflow: hidden;
    padding: 34px;
    border-radius: 30px;
    background:
        radial-gradient(circle at 88% 5%, rgba(246,201,105,.24), transparent 25%),
        linear-gradient(135deg, #14223c, #24436f);
    color: #fff;
    box-shadow: 0 24px 55px rgba(21,35,61,.20);
}

.booking-history-page .booking-hero::after {
    content: 'MY TRIPS';
    position: absolute;
    right: 24px;
    bottom: -22px;
    color: rgba(255,255,255,.045);
    font-size: clamp(3.8rem, 10vw, 7.5rem);
    font-weight: 900;
    letter-spacing: -.06em;
    white-space: nowrap;
}

.booking-history-page .booking-hero-content {
    position: relative;
    z-index: 1;
}

.booking-history-page .booking-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: #f3ca76;
    font-size: .78rem;
    font-weight: 850;
    letter-spacing: .12em;
    text-transform: uppercase;
}

.booking-history-page .booking-hero h1 {
    color: #fff;
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 850;
    letter-spacing: -.03em;
}

.booking-history-page .booking-hero p {
    max-width: 690px;
    color: rgba(255,255,255,.76);
    line-height: 1.75;
}

.booking-history-page .booking-hero .btn-outline-light:hover {
    color: var(--booking-navy);
}

.booking-history-page .booking-summary-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
}

.booking-history-page .booking-summary-card {
    min-width: 0;
    padding: 20px;
    border: 1px solid var(--booking-border);
    border-radius: 22px;
    background: rgba(255,255,255,.92);
    box-shadow: 0 14px 35px rgba(21,35,61,.065);
}

.booking-history-page .booking-summary-icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    border-radius: 14px;
    font-size: 1.15rem;
}

.booking-history-page .tone-navy { color: #253857; background: rgba(37,56,87,.10); }
.booking-history-page .tone-blue { color: #2563eb; background: rgba(37,99,235,.10); }
.booking-history-page .tone-cyan { color: #087f8c; background: rgba(8,127,140,.10); }
.booking-history-page .tone-amber { color: #a16207; background: rgba(245,158,11,.13); }
.booking-history-page .tone-green { color: #15803d; background: rgba(22,163,74,.11); }
.booking-history-page .tone-gold { color: #9a6917; background: rgba(200,149,57,.15); }

.booking-history-page .booking-summary-label {
    display: block;
    color: var(--booking-muted);
    font-size: .78rem;
    font-weight: 700;
    margin-bottom: 6px;
}

.booking-history-page .booking-summary-value {
    display: block;
    overflow-wrap: anywhere;
    color: var(--booking-navy);
    font-size: 1.25rem;
    font-weight: 850;
}

.booking-history-page .booking-filter-panel {
    padding: 24px;
    border: 1px solid var(--booking-border);
    border-radius: 25px;
    background: rgba(255,255,255,.92);
    box-shadow: 0 15px 38px rgba(21,35,61,.06);
}

.booking-history-page .booking-filter-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: var(--booking-navy);
    font-size: .84rem;
    font-weight: 800;
}

.booking-history-page .booking-filter-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.booking-history-page .booking-filter-pill {
    padding: 8px 14px;
    border: 1px solid rgba(21,35,61,.11);
    border-radius: 999px;
    background: #fff;
    color: #4b5565;
    font-size: .82rem;
    font-weight: 750;
    text-decoration: none;
    transition: .18s ease;
}

.booking-history-page .booking-filter-pill:hover {
    color: var(--booking-gold);
    border-color: rgba(200,149,57,.45);
}

.booking-history-page .booking-filter-pill.active {
    border-color: var(--booking-navy);
    background: var(--booking-navy);
    color: #fff;
    box-shadow: 0 10px 22px rgba(21,35,61,.16);
}

.booking-history-page .booking-card-modern {
    position: relative;
    overflow: hidden;
    border: 1px solid var(--booking-border);
    border-radius: 28px;
    background: rgba(255,255,255,.95);
    box-shadow: 0 18px 45px rgba(21,35,61,.075);
    transition: transform .2s ease, box-shadow .2s ease;
}

.booking-history-page .booking-card-modern:hover {
    transform: translateY(-3px);
    box-shadow: 0 24px 55px rgba(21,35,61,.11);
}

.booking-history-page .booking-type-band {
    width: 7px;
    position: absolute;
    inset: 0 auto 0 0;
}

.booking-history-page .booking-type-band.bus { background: linear-gradient(#2f6feb, #1647a5); }
.booking-history-page .booking-type-band.tour { background: linear-gradient(#18a5a8, #0b6f78); }

.booking-history-page .booking-card-body {
    padding: 27px 28px 27px 34px;
}

.booking-history-page .booking-card-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    align-items: flex-start;
}

.booking-history-page .booking-code-label {
    color: var(--booking-muted);
    font-size: .76rem;
    font-weight: 750;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.booking-history-page .booking-code {
    margin: 4px 0 5px;
    color: var(--booking-navy);
    font-size: 1.3rem;
    font-weight: 900;
}

.booking-history-page .booking-created {
    color: var(--booking-muted);
    font-size: .82rem;
}

.booking-history-page .booking-badges {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 7px;
}

.booking-history-page .booking-badges .badge {
    padding: 8px 11px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 750;
}

.booking-history-page .booking-route-panel {
    display: grid;
    grid-template-columns: minmax(0,1fr) auto minmax(0,1fr);
    gap: 18px;
    align-items: center;
    margin-top: 22px;
    padding: 20px;
    border: 1px solid rgba(21,35,61,.07);
    border-radius: 21px;
    background: linear-gradient(135deg, rgba(245,247,251,.95), rgba(249,244,234,.78));
}

.booking-history-page .booking-location small,
.booking-history-page .booking-info-item small {
    display: block;
    color: var(--booking-muted);
    font-size: .75rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.booking-history-page .booking-location strong {
    color: var(--booking-navy);
    font-size: 1.04rem;
}

.booking-history-page .booking-route-arrow {
    width: 48px;
    height: 48px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff;
    color: var(--booking-gold);
    box-shadow: 0 8px 20px rgba(21,35,61,.08);
}

.booking-history-page .booking-tour-panel {
    margin-top: 22px;
    padding: 20px;
    border: 1px solid rgba(8,127,140,.10);
    border-radius: 21px;
    background: linear-gradient(135deg, rgba(8,127,140,.07), rgba(255,255,255,.9));
}

.booking-history-page .booking-tour-title {
    margin-bottom: 5px;
    color: var(--booking-navy);
    font-size: 1.1rem;
    font-weight: 850;
}

.booking-history-page .booking-info-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: 10px;
    margin-top: 12px;
}

.booking-history-page .booking-info-item {
    padding: 13px 14px;
    border-radius: 15px;
    background: rgba(247,248,251,.88);
}

.booking-history-page .booking-info-item strong {
    color: #344054;
    font-size: .86rem;
    line-height: 1.45;
}

.booking-history-page .booking-total-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 14px;
}

.booking-history-page .booking-total-chip {
    padding: 9px 12px;
    border-radius: 13px;
    background: rgba(21,35,61,.055);
    color: #475467;
    font-size: .82rem;
}

.booking-history-page .booking-total-chip strong {
    color: var(--booking-navy);
}

.booking-history-page .booking-actions {
    display: flex;
    flex-direction: column;
    gap: 9px;
    width: 220px;
    flex: 0 0 220px;
}

.booking-history-page .booking-actions .btn {
    border-radius: 13px;
    font-weight: 750;
}

.booking-history-page .booking-empty-state {
    padding: 55px 28px;
    border: 1px dashed rgba(21,35,61,.18);
    border-radius: 27px;
    background: rgba(255,255,255,.86);
    text-align: center;
}

.booking-history-page .booking-empty-icon {
    width: 68px;
    height: 68px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 18px;
    border-radius: 22px;
    background: rgba(200,149,57,.13);
    color: var(--booking-gold);
    font-size: 1.8rem;
}

@media (max-width: 1199.98px) {
    .booking-history-page .booking-summary-grid { grid-template-columns: repeat(3, minmax(0,1fr)); }
    .booking-history-page .booking-info-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
}

@media (max-width: 991.98px) {
    .booking-history-page .booking-card-header { flex-direction: column; }
    .booking-history-page .booking-badges { justify-content: flex-start; }
    .booking-history-page .booking-actions { width: 100%; flex-basis: auto; }
}

@media (max-width: 575.98px) {
    .booking-history-page .booking-hero { padding: 26px 22px; }
    .booking-history-page .booking-summary-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .booking-history-page .booking-card-body { padding: 23px 20px 23px 27px; }
    .booking-history-page .booking-route-panel { grid-template-columns: 1fr; gap: 12px; }
    .booking-history-page .booking-route-arrow { transform: rotate(90deg); }
    .booking-history-page .booking-info-grid { grid-template-columns: 1fr; }
}
</style>

<main class="booking-history-page py-5">
    <div class="container">
        <section class="booking-hero mb-4">
            <div class="booking-hero-content d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4">
                <div>
                    <span class="booking-hero-kicker"><i class="bi bi-suitcase2"></i>Customer travel center</span>
                    <h1 class="mb-2">My Booking History</h1>
                    <p class="mb-0">Review bus and tour bookings, payment progress, tickets, vouchers, cancellations, and refund status in one place.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand"><i class="bi bi-bus-front me-1"></i> Book Bus</a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-outline-light"><i class="bi bi-map me-1"></i> Browse Tours</a>
                    <a href="<?php echo BASE_URL; ?>customer/profile.php" class="btn btn-outline-light"><i class="bi bi-person me-1"></i> Profile</a>
                </div>
            </div>
        </section>

        <?php if ($success = get_flash('success')): ?>
            <div class="alert alert-success rounded-4"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if ($error = get_flash('error')): ?>
            <div class="alert alert-danger rounded-4"><?php echo e($error); ?></div>
        <?php endif; ?>

        <section class="booking-summary-grid mb-4">
            <?php foreach ($summaryCards as $card): ?>
                <div class="booking-summary-card">
                    <span class="booking-summary-icon tone-<?php echo e($card['tone']); ?>"><i class="bi <?php echo e($card['icon']); ?>"></i></span>
                    <span class="booking-summary-label"><?php echo e($card['label']); ?></span>
                    <strong class="booking-summary-value"><?php echo e($card['value']); ?></strong>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="booking-filter-panel mb-4">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="booking-filter-title"><i class="bi bi-grid"></i> Booking Type</div>
                    <div class="booking-filter-pills">
                        <?php foreach ($allowedTypeFilters as $filter): ?>
                            <a
                                href="<?php echo BASE_URL; ?>customer/bookings.php?type=<?php echo e($filter); ?>&payment=<?php echo e($paymentFilter); ?>"
                                class="booking-filter-pill <?php echo $typeFilter === $filter ? 'active' : ''; ?>"
                            >
                                <?php echo e(ucfirst($filter)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="booking-filter-title"><i class="bi bi-credit-card"></i> Payment Status</div>
                    <div class="booking-filter-pills">
                        <?php foreach ($allowedPaymentFilters as $filter): ?>
                            <a
                                href="<?php echo BASE_URL; ?>customer/bookings.php?type=<?php echo e($typeFilter); ?>&payment=<?php echo e($filter); ?>"
                                class="booking-filter-pill <?php echo $paymentFilter === $filter ? 'active' : ''; ?>"
                            >
                                <?php echo e(customer_history_format_status($filter)); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if (empty($rows)): ?>
            <section class="booking-empty-state">
                <span class="booking-empty-icon"><i class="bi bi-calendar2-x"></i></span>
                <h3 class="fw-bold">No bookings found</h3>
                <p class="text-muted mb-4">There is no booking history matching the selected filters.</p>
                <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand">Start a New Booking</a>
            </section>
        <?php else: ?>
            <div class="d-grid gap-4">
                <?php foreach ($rows as $row): ?>
                    <?php
                    $isBus = ($row['booking_type'] ?? '') === 'bus';
                    $canRequestRefund = (
                        ($row['payment_status'] ?? '') === 'paid'
                        && ($row['booking_status'] ?? '') !== 'cancelled'
                        && !in_array((string)($row['refund_request_status'] ?? ''), ['pending', 'approved'], true)
                    );
                    $canCancelBooking = (
                        ($row['booking_status'] ?? '') !== 'cancelled'
                        && in_array((string)($row['payment_status'] ?? ''), ['unpaid', 'pending_review', 'failed'], true)
                    );
                    ?>
                    <article class="booking-card-modern">
                        <span class="booking-type-band <?php echo $isBus ? 'bus' : 'tour'; ?>"></span>
                        <div class="booking-card-body">
                            <div class="d-flex flex-column flex-lg-row gap-4">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="booking-card-header">
                                        <div>
                                            <span class="booking-code-label"><?php echo $isBus ? 'Bus booking' : 'Tour booking'; ?></span>
                                            <h3 class="booking-code"><?php echo e($row['booking_code']); ?></h3>
                                            <div class="booking-created"><i class="bi bi-clock me-1"></i>Booked <?php echo e(date('d M Y, H:i', strtotime((string)$row['booked_at']))); ?></div>
                                        </div>
                                        <div class="booking-badges">
                                            <span class="badge <?php echo $isBus ? 'bg-primary' : 'bg-info text-dark'; ?>"><?php echo e(strtoupper((string)$row['booking_type'])); ?></span>
                                            <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['booking_status'])); ?>">Booking: <?php echo e(customer_history_format_status((string)$row['booking_status'])); ?></span>
                                            <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['payment_status'])); ?>">Payment: <?php echo e(customer_history_format_status((string)$row['payment_status'])); ?></span>
                                            <?php if (!empty($row['refund_request_status'])): ?>
                                                <span class="badge bg-<?php echo e(customer_history_badge_class((string)$row['refund_request_status'])); ?>">Refund: <?php echo e(customer_history_format_status((string)$row['refund_request_status'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($isBus): ?>
                                        <div class="booking-route-panel">
                                            <div class="booking-location">
                                                <small>From</small>
                                                <strong><?php echo e($row['from_city_name']); ?></strong>
                                            </div>
                                            <span class="booking-route-arrow"><i class="bi bi-arrow-right"></i></span>
                                            <div class="booking-location text-lg-end">
                                                <small>To</small>
                                                <strong><?php echo e($row['to_city_name']); ?></strong>
                                            </div>
                                        </div>
                                        <div class="booking-info-grid">
                                            <div class="booking-info-item"><small>Company</small><strong><?php echo e($row['company_name']); ?></strong></div>
                                            <div class="booking-info-item"><small>Bus Number</small><strong><?php echo e($row['bus_number']); ?></strong></div>
                                            <div class="booking-info-item"><small>Departure</small><strong><?php echo e(date('d M Y, H:i', strtotime((string)$row['departure_datetime']))); ?></strong></div>
                                            <div class="booking-info-item"><small>Arrival</small><strong><?php echo e(date('d M Y, H:i', strtotime((string)$row['arrival_datetime']))); ?></strong></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="booking-tour-panel">
                                            <small class="text-muted d-block mb-1">Tour Package</small>
                                            <div class="booking-tour-title"><?php echo e($row['package_title']); ?></div>
                                            <div class="text-muted small"><i class="bi bi-building me-1"></i><?php echo e($row['company_name']); ?></div>
                                        </div>
                                        <div class="booking-info-grid">
                                            <div class="booking-info-item"><small>Start Date</small><strong><?php echo e($row['start_date']); ?></strong></div>
                                            <div class="booking-info-item"><small>End Date</small><strong><?php echo e($row['end_date']); ?></strong></div>
                                            <div class="booking-info-item"><small>Duration</small><strong><?php echo e((int)($row['duration_days'] ?? 0)); ?> day(s)</strong></div>
                                            <div class="booking-info-item"><small>Batch Status</small><strong><?php echo e(customer_history_format_status((string)($row['batch_status'] ?? ''))); ?></strong></div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="booking-total-row">
                                        <span class="booking-total-chip"><i class="bi bi-people me-1"></i>Passengers: <strong><?php echo e((int)$row['passenger_count']); ?></strong></span>
                                        <span class="booking-total-chip"><i class="bi bi-cash-stack me-1"></i>Total: <strong><?php echo e(number_format((float)$row['total_amount'], 2)); ?> MMK</strong></span>
                                        <?php if ($isBus && !empty($row['ticket_no'])): ?>
                                            <span class="booking-total-chip"><i class="bi bi-ticket-perforated me-1"></i>Ticket: <strong><?php echo e($row['ticket_no']); ?></strong></span>
                                        <?php elseif (!$isBus && !empty($row['voucher_code'])): ?>
                                            <span class="booking-total-chip"><i class="bi bi-receipt me-1"></i>Voucher: <strong><?php echo e($row['voucher_code']); ?></strong></span>
                                        <?php endif; ?>
                                        <?php if (!empty($row['refund_request_code'])): ?>
                                            <span class="booking-total-chip"><i class="bi bi-arrow-counterclockwise me-1"></i>Refund: <strong><?php echo e($row['refund_request_code']); ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <aside class="booking-actions">
                                    <?php if (in_array($row['payment_status'], ['unpaid', 'failed'], true)): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-warning"><i class="bi bi-phone me-1"></i> Pay Now</a>
                                    <?php elseif ($row['payment_status'] === 'pending_review'): ?>
                                        <a href="<?php echo BASE_URL; ?>payment.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-warning"><i class="bi bi-hourglass-split me-1"></i> Payment Review</a>
                                    <?php endif; ?>

                                    <?php if ($isBus && !empty($row['ticket_id'])): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/ticket.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-primary"><i class="bi bi-ticket-perforated me-1"></i> View Ticket</a>
                                    <?php elseif (!$isBus && !empty($row['voucher_id'])): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/voucher.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-info text-dark"><i class="bi bi-receipt me-1"></i> View Voucher</a>
                                    <?php endif; ?>

                                    <?php if ($canRequestRefund): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/refund_request.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-danger"><i class="bi bi-arrow-counterclockwise me-1"></i> Request Refund</a>
                                    <?php elseif (!empty($row['refund_request_status'])): ?>
                                        <a href="<?php echo BASE_URL; ?>customer/refund_request.php?booking_id=<?php echo e($row['booking_id']); ?>" class="btn btn-outline-secondary"><i class="bi bi-search me-1"></i> Refund Status</a>
                                    <?php endif; ?>

                                    <?php if ($canCancelBooking): ?>
                                        <form action="<?php echo BASE_URL; ?>actions/cancel_booking.php" method="POST" onsubmit="return confirm('Cancel this booking and release seats/slots?');">
                                            <input type="hidden" name="booking_id" value="<?php echo e($row['booking_id']); ?>">
                                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i> Cancel Booking</button>
                                        </form>
                                    <?php endif; ?>
                                </aside>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
