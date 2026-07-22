<?php
require_once __DIR__ . '/includes/role_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_history_helper.php';

require_role('customer');

$page_title = 'Payment Submission - Golden Route Myanmar';

$conn = getDBConnection();

$bookingId = (int)($_GET['booking_id'] ?? 0);
$currentUserId = (int)current_user_id();
$packageColumn = customer_history_get_tour_batch_package_column($conn);

$booking = null;
$latestPayment = null;

if ($bookingId > 0) {
    $bookingSql = "
        SELECT
            b.*,
            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            bc.name AS bus_company_name,
            tb.start_date AS tour_start_date,
            tb.end_date AS tour_end_date,
            tp.title AS tour_title,
            tc2.name AS tour_company_name
        FROM bookings b
        LEFT JOIN trips t ON t.id = b.trip_id
        LEFT JOIN buses bus ON bus.id = t.bus_id
        LEFT JOIN routes r ON r.id = t.route_id
        LEFT JOIN cities fc ON fc.id = r.from_city_id
        LEFT JOIN cities tc ON tc.id = r.to_city_id
        LEFT JOIN companies bc ON bc.id = t.company_id
        LEFT JOIN tour_batches tb ON tb.id = b.tour_batch_id
        LEFT JOIN tour_packages tp ON tp.id = tb.{$packageColumn}
        LEFT JOIN companies tc2 ON tc2.id = tp.company_id
        WHERE b.id = ?
          AND b.user_id = ?
        LIMIT 1
    ";
    $bookingStmt = $conn->prepare($bookingSql);
    $bookingStmt->bind_param('ii', $bookingId, $currentUserId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult->fetch_assoc() ?: null;
    $bookingStmt->close();

    if ($booking) {
        $paymentSql = "
            SELECT *
            FROM payments
            WHERE booking_id = ?
            ORDER BY id DESC
            LIMIT 1
        ";
        $paymentStmt = $conn->prepare($paymentSql);
        $paymentStmt->bind_param('i', $bookingId);
        $paymentStmt->execute();
        $paymentResult = $paymentStmt->get_result();
        $latestPayment = $paymentResult->fetch_assoc() ?: null;
        $paymentStmt->close();
    }
}

$conn->close();

if (empty($_SESSION['payment_csrf_token'])) {
    $_SESSION['payment_csrf_token'] = bin2hex(random_bytes(32));
}
$paymentCsrfToken = (string)$_SESSION['payment_csrf_token'];

$paymentOldInput = [];
$sessionOldInput = $_SESSION['old_input'] ?? null;
if (
    is_array($sessionOldInput)
    && ($sessionOldInput['_form'] ?? '') === 'payment'
    && (int)($sessionOldInput['booking_id'] ?? 0) === $bookingId
) {
    $paymentOldInput = $sessionOldInput;
}
$paymentOld = static function (string $key, $default = '') use ($paymentOldInput) {
    return array_key_exists($key, $paymentOldInput) ? $paymentOldInput[$key] : $default;
};

$paymentMethods = [
    'wave_money' => 'Wave Money',
    'kbzpay' => 'KBZ Pay',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
];

$mobilePaymentMethods = [
    'wave_money' => [
        'label' => 'Wave Money',
        'short' => 'Wave',
        'description' => 'Pay with your Wave Money mobile wallet.',
    ],
    'kbzpay' => [
        'label' => 'KBZ Pay',
        'short' => 'KBZ',
        'description' => 'Pay with your KBZPay mobile wallet.',
    ],
];

$paymentQrImage = 'assets/images/QR1.png';
$paymentQrImageFs = __DIR__ . '/' . $paymentQrImage;
$paymentQrImageUrl = file_exists($paymentQrImageFs)
    ? BASE_URL . $paymentQrImage
    : '';

$paymentMethodNotes = [
    'wave_money' => 'Open Wave Money, scan the QR code, complete the payment, and upload the successful transaction screenshot.',
    'kbzpay' => 'Open KBZPay, scan the QR code, complete the payment, and upload the successful transaction screenshot.',
];

function payment_badge_class(string $status): string
{
    switch ($status) {
        case 'verified':
            return 'success';
        case 'rejected':
            return 'danger';
        case 'submitted':
        default:
            return 'warning text-dark';
    }
}

$formBlocked = false;
$blockMessage = '';

if ($booking) {
    if (($booking['payment_status'] ?? '') === 'paid') {
        $formBlocked = true;
        $blockMessage = 'This booking is already paid.';
    } elseif (($latestPayment['status'] ?? '') === 'submitted') {
        $formBlocked = true;
        $blockMessage = 'A payment proof is already submitted and waiting for review.';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.mobile-payment-page {
    background:
        radial-gradient(circle at 9% 3%, rgba(200,149,57,.13), transparent 24%),
        linear-gradient(180deg, #f8f5ef 0%, #f5f7fb 100%);
    min-height: 75vh;
}

.mobile-payment-page .payment-page-hero {
    position: relative;
    overflow: hidden;
    padding: 30px 32px;
    border-radius: 28px;
    background:
        radial-gradient(circle at 88% 10%, rgba(246,201,105,.22), transparent 25%),
        linear-gradient(135deg, #14233e, #24446f);
    color: #fff;
    box-shadow: 0 24px 55px rgba(20,35,62,.20);
}

.mobile-payment-page .payment-page-hero h1 {
    color: #fff;
    font-weight: 850;
    letter-spacing: -.03em;
}

.mobile-payment-page .payment-page-hero p {
    color: rgba(255,255,255,.75);
}

.mobile-payment-page .payment-page-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #f2ca77;
    font-size: .78rem;
    font-weight: 850;
    letter-spacing: .11em;
    text-transform: uppercase;
}

.mobile-payment-page .panel-card {
    border: 1px solid rgba(20,35,62,.08);
    border-radius: 28px;
    background: rgba(255,255,255,.94);
    box-shadow: 0 20px 48px rgba(20,35,62,.08);
}

.mobile-payment-page .panel-card-header {
    display: block;
}

.mobile-payment-page .panel-card-header h4 {
    margin-bottom: 5px;
    color: #17243d;
    font-weight: 850;
}

.mobile-payment-page .panel-card-header p {
    margin: 0;
    color: #667085;
}

.mobile-payment-page .payment-summary-code {
    padding: 18px;
    border-radius: 19px;
    background: linear-gradient(135deg, rgba(20,35,62,.06), rgba(200,149,57,.09));
    border: 1px solid rgba(20,35,62,.07);
}

.mobile-payment-page .summary-row {
    gap: 16px;
}

.mobile-payment-page .summary-row strong {
    text-align: right;
}

.mobile-payment-page .mobile-wallet-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.mobile-payment-page .mobile-wallet-option {
    position: relative;
}

.mobile-payment-page .mobile-wallet-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.mobile-payment-page .mobile-wallet-card {
    min-height: 126px;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px;
    border: 2px solid rgba(20,35,62,.09);
    border-radius: 21px;
    background: #fff;
    cursor: pointer;
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease, background .18s ease;
}

.mobile-payment-page .mobile-wallet-card:hover {
    transform: translateY(-2px);
    border-color: rgba(200,149,57,.45);
    box-shadow: 0 12px 28px rgba(20,35,62,.09);
}

.mobile-payment-page .mobile-wallet-logo {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    color: #fff;
    font-size: .82rem;
    font-weight: 900;
    letter-spacing: .03em;
}

.mobile-payment-page .mobile-wallet-logo.wave {
    background: linear-gradient(145deg, #e8348a, #8f2470);
}

.mobile-payment-page .mobile-wallet-logo.kbz {
    background: linear-gradient(145deg, #2369c7, #13478e);
}

.mobile-payment-page .mobile-wallet-copy strong {
    display: block;
    margin-bottom: 4px;
    color: #17243d;
    font-size: 1rem;
}

.mobile-payment-page .mobile-wallet-copy span {
    display: block;
    color: #667085;
    font-size: .78rem;
    line-height: 1.45;
}

.mobile-payment-page .mobile-wallet-check {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(20,35,62,.14);
    border-radius: 50%;
    background: #fff;
    color: transparent;
    font-size: .72rem;
}

.mobile-payment-page .mobile-wallet-option input:checked + .mobile-wallet-card {
    border-color: #1d6b48;
    background: linear-gradient(145deg, rgba(29,107,72,.08), #fff);
    box-shadow: 0 14px 30px rgba(29,107,72,.14);
}

.mobile-payment-page .mobile-wallet-option input:checked + .mobile-wallet-card .mobile-wallet-check {
    border-color: #1d6b48;
    background: #1d6b48;
    color: #fff;
}

.mobile-payment-page .mobile-wallet-option input:focus-visible + .mobile-wallet-card {
    outline: 3px solid rgba(37,99,235,.22);
    outline-offset: 2px;
}

.mobile-payment-page .payment-qr-modern {
    overflow: hidden;
    border: 1px solid rgba(20,35,62,.08);
    border-radius: 24px;
    background: linear-gradient(145deg, #f9fbfd, #f6efe3);
}

.mobile-payment-page .payment-qr-image-wrap {
    display: inline-flex;
    padding: 10px;
    border-radius: 19px;
    background: #fff;
    box-shadow: 0 12px 28px rgba(20,35,62,.10);
}

.mobile-payment-page .payment-qr-image-wrap img {
    width: 180px;
    max-width: 100%;
    border-radius: 12px;
}

.mobile-payment-page .payment-step-list {
    display: grid;
    gap: 10px;
    margin-top: 16px;
}

.mobile-payment-page .payment-step {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    color: #536072;
    font-size: .86rem;
}

.mobile-payment-page .payment-step-number {
    width: 24px;
    height: 24px;
    flex: 0 0 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #17243d;
    color: #fff;
    font-size: .7rem;
    font-weight: 800;
}

.mobile-payment-page .payment-upload-zone {
    padding: 18px;
    border: 1px dashed rgba(20,35,62,.22);
    border-radius: 19px;
    background: rgba(248,250,252,.82);
}

.mobile-payment-page #screenshotPreviewImage {
    max-height: 420px;
    object-fit: contain;
    background: #f4f6f8;
}

@media (max-width: 575.98px) {
    .mobile-payment-page .payment-page-hero { padding: 25px 21px; }
    .mobile-payment-page .mobile-wallet-grid { grid-template-columns: 1fr; }
}
</style>

<main class="mobile-payment-page py-5">
    <div class="container">
    <div class="payment-page-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <span class="payment-page-kicker"><i class="bi bi-phone"></i>Mobile Payment</span>
                <h1 class="mb-2 mt-2">Pay with a Mobile Wallet</h1>
                <p class="mb-0">Choose Wave Money or KBZPay, scan the QR code, and submit the successful payment screenshot.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>customer/bookings.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i> Back to Bookings</a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <?php if (!$booking): ?>
        <div class="empty-state-card">
            <h3>Booking not found</h3>
            <p>The booking does not exist or does not belong to your account.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <h4>Booking Summary</h4>
                        <p>Check your booking details before payment submission.</p>
                    </div>

                    <div class="payment-summary-code mb-3">
                        <small class="text-muted d-block mb-1">Booking Code</small>
                        <strong class="fs-5"><?php echo e($booking['booking_code']); ?></strong>
                    </div>

                    <div class="summary-list">
                        <div class="summary-row"><span>Booking Code</span><strong><?php echo e($booking['booking_code']); ?></strong></div>
                        <div class="summary-row"><span>Type</span><strong><?php echo e(ucfirst($booking['booking_type'])); ?></strong></div>
                        <div class="summary-row"><span>Total Amount</span><strong><?php echo number_format((float)$booking['total_amount'], 2); ?> MMK</strong></div>
                        <div class="summary-row"><span>Booking Status</span><strong><?php echo e(customer_history_format_status((string)$booking['status'])); ?></strong></div>
                        <div class="summary-row"><span>Payment Status</span><strong><?php echo e(customer_history_format_status((string)$booking['payment_status'])); ?></strong></div>
                    </div>

                    <?php if (($booking['booking_type'] ?? '') === 'bus'): ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Bus Trip</h6>
                            <div class="summary-row"><span>Company</span><strong><?php echo e($booking['bus_company_name'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Route</span><strong><?php echo e(($booking['from_city_name'] ?? '-') . ' → ' . ($booking['to_city_name'] ?? '-')); ?></strong></div>
                            <div class="summary-row"><span>Travel Date</span><strong><?php echo e($booking['trip_date'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Bus Number</span><strong><?php echo e($booking['bus_number'] ?? '-'); ?></strong></div>
                        </div>
                    <?php else: ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Tour Booking</h6>
                            <div class="summary-row"><span>Company</span><strong><?php echo e($booking['tour_company_name'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Package</span><strong><?php echo e($booking['tour_title'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Start Date</span><strong><?php echo e($booking['tour_start_date'] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>End Date</span><strong><?php echo e($booking['tour_end_date'] ?? '-'); ?></strong></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($latestPayment): ?>
                        <div class="detail-panel mt-4">
                            <h6 class="mb-3">Latest Submitted Payment</h6>
                            <div class="summary-row"><span>Method</span><strong><?php echo e($paymentMethods[$latestPayment['payment_method'] ?? ''] ?? '-'); ?></strong></div>
                            <div class="summary-row"><span>Amount</span><strong><?php echo number_format((float)($latestPayment['amount'] ?? 0), 2); ?> MMK</strong></div>
                            <div class="summary-row"><span>Transaction Ref</span><strong><?php echo e($latestPayment['transaction_ref'] ?? '-'); ?></strong></div>
                            <div class="summary-row">
                                <span>Status</span>
                                <strong><span class="badge bg-<?php echo payment_badge_class((string)($latestPayment['status'] ?? 'submitted')); ?>"><?php echo e(ucfirst($latestPayment['status'] ?? 'submitted')); ?></span></strong>
                            </div>

                            <?php if (!empty($latestPayment['screenshot_path'] ?? '')): ?>
                                <div class="mt-3">
                                    <a href="<?php echo BASE_URL . ltrim((string)$latestPayment['screenshot_path'], '/'); ?>" target="_blank" class="btn btn-nav-soft w-100">
                                        View Uploaded Screenshot
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="panel-card h-100">
                    <div class="panel-card-header">
                        <h4>Submit Payment</h4>
                        <p>Complete the form with method, reference and screenshot.</p>
                    </div>

                    <?php if ($formBlocked): ?>
                        <div class="alert alert-warning mb-0"><?php echo e($blockMessage); ?></div>
                    <?php else: ?>
                        <div class="payment-qr-modern mb-4 p-4">
                            <div class="row g-4 align-items-center">
                                <div class="col-md-5 text-center">
                                    <?php if ($paymentQrImageUrl !== ''): ?>
                                        <div class="payment-qr-image-wrap">
                                            <img src="<?php echo e($paymentQrImageUrl); ?>" alt="Mobile payment QR code">
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">QR image not found.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7">
                                    <span class="payment-page-kicker text-dark"><i class="bi bi-qr-code-scan"></i>Scan to pay</span>
                                    <h5 class="mt-2 mb-2 fw-bold">Complete your mobile payment</h5>
                                    <p class="text-muted mb-0" id="paymentQrNote">Choose a mobile payment app below to see the payment instruction.</p>
                                    <div class="payment-step-list">
                                        <div class="payment-step"><span class="payment-step-number">1</span><span>Select Wave Money or KBZPay.</span></div>
                                        <div class="payment-step"><span class="payment-step-number">2</span><span>Scan the QR and pay <strong><?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK</strong>.</span></div>
                                        <div class="payment-step"><span class="payment-step-number">3</span><span>Copy the transaction ID and upload the success screenshot.</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="<?php echo BASE_URL; ?>actions/submit_payment.php" method="POST" enctype="multipart/form-data" id="paymentForm" novalidate>
                            <input type="hidden" name="booking_id" value="<?php echo e($booking['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo e($paymentCsrfToken); ?>">

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Select Mobile Payment App <span class="text-danger">*</span></label>
                                    <div class="mobile-wallet-grid" id="paymentMethodGroup" role="radiogroup" aria-describedby="paymentMethodFeedback">
                                        <?php foreach ($mobilePaymentMethods as $value => $method): ?>
                                            <div class="mobile-wallet-option">
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    id="paymentMethod_<?php echo e($value); ?>"
                                                    value="<?php echo e($value); ?>"
                                                    <?php echo $paymentOld('payment_method') === $value ? 'checked' : ''; ?>
                                                    required
                                                >
                                                <label for="paymentMethod_<?php echo e($value); ?>" class="mobile-wallet-card">
                                                    <span class="mobile-wallet-logo <?php echo $value === 'wave_money' ? 'wave' : 'kbz'; ?>"><?php echo e($method['short']); ?></span>
                                                    <span class="mobile-wallet-copy">
                                                        <strong><?php echo e($method['label']); ?></strong>
                                                        <span><?php echo e($method['description']); ?></span>
                                                    </span>
                                                    <span class="mobile-wallet-check"><i class="bi bi-check-lg"></i></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div id="paymentMethodFeedback" class="invalid-feedback d-block" style="display:none !important;">Please select Wave Money or KBZPay.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Payment Amount</label>
                                    <input type="text" class="form-control fw-bold" value="<?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK" readonly>
                                </div>

                                <div class="col-12">
                                    <label for="transactionRef" class="form-label">Mobile Transaction ID <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="transaction_ref"
                                        id="transactionRef"
                                        class="form-control"
                                        value="<?php echo e($paymentOld('transaction_ref')); ?>"
                                        placeholder="Example: WAVE-123456789 or KBZ-987654321"
                                        minlength="4"
                                        maxlength="100"
                                        pattern="[A-Za-z0-9][A-Za-z0-9 ._/#:()\-]{3,99}"
                                        autocomplete="off"
                                        required
                                        aria-describedby="transactionRefHelp transactionRefFeedback"
                                    >
                                    <div id="transactionRefHelp" class="form-text">Enter 4–100 characters. Letters, numbers, spaces and . _ / # : ( ) - are allowed.</div>
                                    <div id="transactionRefFeedback" class="invalid-feedback">Enter a valid mobile transaction ID.</div>
                                </div>

                                <div class="col-12 payment-upload-zone">
                                    <label for="paymentScreenshot" class="form-label"><i class="bi bi-cloud-arrow-up me-1"></i>Successful Payment Screenshot <span class="text-danger">*</span></label>
                                    <input
                                        type="file"
                                        name="payment_screenshot"
                                        id="paymentScreenshot"
                                        class="form-control"
                                        accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                        required
                                        aria-describedby="paymentScreenshotHelp paymentScreenshotFeedback"
                                    >
                                    <div id="paymentScreenshotHelp" class="form-text">JPG, PNG or WEBP only. Maximum file size: 5 MB.</div>
                                    <div id="paymentScreenshotFeedback" class="invalid-feedback">Choose a valid JPG, PNG or WEBP screenshot under 5 MB.</div>

                                    <div id="screenshotPreviewContainer" class="mt-3" style="display:none;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <strong>Screenshot Preview</strong>
                                            <button type="button" id="removeScreenshotPreview" class="btn btn-sm btn-danger">Remove</button>
                                        </div>
                                        <img id="screenshotPreviewImage" src="" alt="Payment screenshot preview" style="max-width:100%; border-radius:10px; cursor:pointer; border:1px solid #ddd;">
                                        <small class="text-muted d-block mt-1">Click image to zoom</small>
                                    </div>

                                    <div id="screenshotZoomModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:9999; align-items:center; justify-content:center; padding:20px;">
                                        <img id="screenshotZoomImage" src="" style="max-width:95%; max-height:95%; border-radius:12px;">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="paymentNotes" class="form-label">Payment Notes <span class="text-danger">*</span></label>
                                    <textarea
                                        name="notes"
                                        id="paymentNotes"
                                        rows="4"
                                        class="form-control"
                                        placeholder="Enter payer name, mobile wallet phone number, transfer time, or other verification details"
                                        minlength="5"
                                        maxlength="1000"
                                        required
                                        aria-describedby="paymentNotesHelp paymentNotesFeedback"
                                    ><?php echo e($paymentOld('notes')); ?></textarea>
                                    <div id="paymentNotesHelp" class="form-text"><span id="paymentNotesCount">0</span>/1000 characters</div>
                                    <div id="paymentNotesFeedback" class="invalid-feedback">Payment notes are required and must contain 5–1000 characters.</div>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-brand w-100" id="submitPaymentButton">
                                        <span class="submit-label">Submit Payment</span>
                                        <span class="submit-loading d-none">Submitting...</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>
</main>

<script>
const paymentMethodNotes = <?php echo json_encode($paymentMethodNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const paymentForm = document.getElementById('paymentForm');
const paymentMethodInputs = Array.from(document.querySelectorAll('input[name="payment_method"]'));
const paymentMethodFeedback = document.getElementById('paymentMethodFeedback');
const paymentQrNote = document.getElementById('paymentQrNote');
const transactionRefInput = document.getElementById('transactionRef');
const paymentScreenshotInput = document.getElementById('paymentScreenshot');
const paymentNotesInput = document.getElementById('paymentNotes');
const paymentNotesCount = document.getElementById('paymentNotesCount');
const submitPaymentButton = document.getElementById('submitPaymentButton');

const transactionReferencePattern = /^[A-Za-z0-9][A-Za-z0-9 ._/#:()\-]{3,99}$/;
const allowedScreenshotTypes = ['image/jpeg', 'image/png', 'image/webp'];
const allowedScreenshotExtensions = ['jpg', 'jpeg', 'png', 'webp'];
const maximumScreenshotBytes = 5 * 1024 * 1024;

function getSelectedPaymentMethod() {
    const selected = paymentMethodInputs.find(input => input.checked);
    return selected ? selected.value : '';
}

function updatePaymentQrNote() {
    if (!paymentQrNote) {
        return;
    }

    const selectedMethod = getSelectedPaymentMethod();
    paymentQrNote.textContent = paymentMethodNotes[selectedMethod]
        || 'Choose Wave Money or KBZPay, scan the QR, and upload the successful payment screenshot.';

    if (paymentMethodFeedback) {
        const showMethodError = paymentForm
            && paymentForm.classList.contains('was-validated')
            && selectedMethod === '';
        paymentMethodFeedback.style.setProperty('display', showMethodError ? 'block' : 'none', 'important');
    }
}

function validateTransactionReference() {
    if (!transactionRefInput) {
        return true;
    }

    const value = transactionRefInput.value.trim();
    transactionRefInput.value = value;

    if (value === '') {
        transactionRefInput.setCustomValidity('Mobile transaction ID is required.');
    } else if (!transactionReferencePattern.test(value)) {
        transactionRefInput.setCustomValidity('Use 4–100 valid characters for the mobile transaction ID.');
    } else {
        transactionRefInput.setCustomValidity('');
    }

    return transactionRefInput.checkValidity();
}

function validatePaymentNotes() {
    if (!paymentNotesInput) {
        return true;
    }

    const value = paymentNotesInput.value.trim();
    const length = Array.from(value).length;

    if (paymentNotesCount) {
        paymentNotesCount.textContent = String(Array.from(paymentNotesInput.value).length);
    }

    if (value === '') {
        paymentNotesInput.setCustomValidity('Payment notes are required.');
    } else if (length < 5 || length > 1000) {
        paymentNotesInput.setCustomValidity('Payment notes must contain 5–1000 characters.');
    } else {
        paymentNotesInput.setCustomValidity('');
    }

    return paymentNotesInput.checkValidity();
}

function validateScreenshot() {
    if (!paymentScreenshotInput) {
        return true;
    }

    const file = paymentScreenshotInput.files && paymentScreenshotInput.files[0]
        ? paymentScreenshotInput.files[0]
        : null;

    if (!file) {
        paymentScreenshotInput.setCustomValidity('Payment screenshot is required.');
        return false;
    }

    const extension = file.name.includes('.')
        ? file.name.split('.').pop().toLowerCase()
        : '';
    const validType = allowedScreenshotTypes.includes(file.type)
        || (file.type === '' && allowedScreenshotExtensions.includes(extension));

    if (!validType) {
        paymentScreenshotInput.setCustomValidity('Only JPG, PNG and WEBP files are allowed.');
    } else if (file.size <= 0) {
        paymentScreenshotInput.setCustomValidity('The selected screenshot is empty.');
    } else if (file.size > maximumScreenshotBytes) {
        paymentScreenshotInput.setCustomValidity('Screenshot must be 5 MB or smaller.');
    } else {
        paymentScreenshotInput.setCustomValidity('');
    }

    return paymentScreenshotInput.checkValidity();
}

if (paymentMethodInputs.length) {
    paymentMethodInputs.forEach(input => input.addEventListener('change', updatePaymentQrNote));
    updatePaymentQrNote();
}

if (transactionRefInput) {
    transactionRefInput.addEventListener('input', () => {
        transactionRefInput.setCustomValidity('');
        if (paymentForm && paymentForm.classList.contains('was-validated')) {
            validateTransactionReference();
        }
    });
    transactionRefInput.addEventListener('blur', validateTransactionReference);
}

if (paymentScreenshotInput) {
    const previewContainer = document.getElementById('screenshotPreviewContainer');
    const previewImage = document.getElementById('screenshotPreviewImage');
    const removePreviewButton = document.getElementById('removeScreenshotPreview');
    const zoomModal = document.getElementById('screenshotZoomModal');
    const zoomImage = document.getElementById('screenshotZoomImage');

    paymentScreenshotInput.addEventListener('change', function () {
        validateScreenshot();

        const file = this.files && this.files[0];
        if (!file) {
            previewContainer.style.display = 'none';
            return;
        }

        if (!file.type.startsWith('image/') || file.size > maximumScreenshotBytes) {
            previewContainer.style.display = 'none';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            zoomImage.src = e.target.result;
            previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
    });

    removePreviewButton.addEventListener('click', function(){
        paymentScreenshotInput.value = '';
        previewImage.src = '';
        zoomImage.src = '';
        previewContainer.style.display = 'none';
        validateScreenshot();
    });

    previewImage.addEventListener('click', function(){
        zoomModal.style.display = 'flex';
    });

    zoomModal.addEventListener('click', function(){
        zoomModal.style.display = 'none';
    });
}

if (paymentNotesInput) {
    paymentNotesInput.addEventListener('input', () => {
        paymentNotesInput.setCustomValidity('');
        validatePaymentNotes();
    });
    validatePaymentNotes();
}

if (paymentForm) {
    paymentForm.addEventListener('submit', (event) => {
        paymentForm.classList.add('was-validated');
        const paymentMethodValid = getSelectedPaymentMethod() !== '';
        updatePaymentQrNote();
        const transactionValid = validateTransactionReference();
        const screenshotValid = validateScreenshot();
        const notesValid = validatePaymentNotes();
        const formValid = paymentForm.checkValidity()
            && paymentMethodValid
            && transactionValid
            && screenshotValid
            && notesValid;

        if (!formValid) {
            event.preventDefault();
            event.stopPropagation();

            const firstInvalid = paymentForm.querySelector(':invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        if (submitPaymentButton) {
            submitPaymentButton.disabled = true;
            const submitLabel = submitPaymentButton.querySelector('.submit-label');
            const submitLoading = submitPaymentButton.querySelector('.submit-loading');
            if (submitLabel) {
                submitLabel.classList.add('d-none');
            }
            if (submitLoading) {
                submitLoading.classList.remove('d-none');
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>