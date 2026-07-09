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

$paymentQrImage = 'assets/images/QR1.png';
$paymentQrImageFs = __DIR__ . '/' . $paymentQrImage;
$paymentQrImageUrl = file_exists($paymentQrImageFs)
    ? BASE_URL . $paymentQrImage
    : '';

$paymentMethodNotes = [
    'wave_money' => 'Scan this QR with Wave Money and upload the transfer screenshot.',
    'kbzpay' => 'Scan this QR with KBZ Pay and upload the transfer screenshot.',
    'bank_transfer' => 'Transfer to the account shown in the QR / account details and upload the proof.',
    'cash' => 'For cash payment, enter the office receipt/reference number and upload a clear receipt or confirmation photo.',
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

<div class="container py-5">
    <div class="checkout-header mb-4">
        <div>
            <span class="section-kicker">Payment</span>
            <h1 class="page-title mb-2">Submit payment proof</h1>
            <p class="page-subtitle mb-0">Upload screenshot and transaction details for admin verification.</p>
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
                        <div class="payment-qr-box mb-4 p-3 rounded-4 border bg-light">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4 text-center">
                                    <?php if ($paymentQrImageUrl !== ''): ?>
                                        <img
                                            src="<?php echo e($paymentQrImageUrl); ?>"
                                            alt="Payment QR Code"
                                            class="img-fluid rounded-3 border bg-white p-2"
                                            style="max-width: 180px;"
                                        >
                                    <?php else: ?>
                                        <div class="alert alert-warning mb-0">QR image not found.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="mb-2">Scan QR to Pay</h5>
                                    <p class="text-muted mb-2" id="paymentQrNote">
                                        Select a payment method, scan this QR, then upload the payment screenshot.
                                    </p>
                                    <div class="small">
                                        <div><strong>Amount:</strong> <?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK</div>
                                        <div><strong>Booking Code:</strong> <?php echo e($booking['booking_code']); ?></div>
                                        <div><strong>Reference:</strong> Enter the transaction ID or cash receipt number.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <form action="<?php echo BASE_URL; ?>actions/submit_payment.php" method="POST" enctype="multipart/form-data" id="paymentForm" novalidate>
                            <input type="hidden" name="booking_id" value="<?php echo e($booking['id']); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo e($paymentCsrfToken); ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="paymentMethod" class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select name="payment_method" id="paymentMethod" class="form-select" required aria-describedby="paymentMethodFeedback">
                                        <option value="">Select payment method</option>
                                        <?php foreach ($paymentMethods as $value => $label): ?>
                                            <option value="<?php echo e($value); ?>" <?php echo $paymentOld('payment_method') === $value ? 'selected' : ''; ?>>
                                                <?php echo e($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="paymentMethodFeedback" class="invalid-feedback">Please select a valid payment method.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Amount</label>
                                    <input type="text" class="form-control" value="<?php echo e(number_format((float)$booking['total_amount'], 2)); ?> MMK" readonly>
                                </div>

                                <div class="col-12">
                                    <label for="transactionRef" class="form-label">Transaction / Receipt Reference <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="transaction_ref"
                                        id="transactionRef"
                                        class="form-control"
                                        value="<?php echo e($paymentOld('transaction_ref')); ?>"
                                        placeholder="Example: WAVE-123456789 or CASH-RECEIPT-001"
                                        minlength="4"
                                        maxlength="100"
                                        pattern="[A-Za-z0-9][A-Za-z0-9 ._/#:()\-]{3,99}"
                                        autocomplete="off"
                                        required
                                        aria-describedby="transactionRefHelp transactionRefFeedback"
                                    >
                                    <div id="transactionRefHelp" class="form-text">Enter 4–100 characters. Letters, numbers, spaces and . _ / # : ( ) - are allowed.</div>
                                    <div id="transactionRefFeedback" class="invalid-feedback">Enter a valid transaction or receipt reference.</div>
                                </div>

                                <div class="col-12">
                                    <label for="paymentScreenshot" class="form-label">Payment Screenshot <span class="text-danger">*</span></label>
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
                                        placeholder="Enter payer name, transfer time, account/phone used, or other verification details"
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

<script>
const paymentMethodNotes = <?php echo json_encode($paymentMethodNotes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const paymentForm = document.getElementById('paymentForm');
const paymentMethodSelect = document.getElementById('paymentMethod');
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

function updatePaymentQrNote() {
    if (!paymentMethodSelect || !paymentQrNote) {
        return;
    }

    const selectedMethod = paymentMethodSelect.value;
    paymentQrNote.textContent = paymentMethodNotes[selectedMethod]
        || 'Select a payment method, scan this QR, then upload the payment screenshot.';
}

function validateTransactionReference() {
    if (!transactionRefInput) {
        return true;
    }

    const value = transactionRefInput.value.trim();
    transactionRefInput.value = value;

    if (value === '') {
        transactionRefInput.setCustomValidity('Transaction or receipt reference is required.');
    } else if (!transactionReferencePattern.test(value)) {
        transactionRefInput.setCustomValidity('Use 4–100 valid characters for the reference.');
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

if (paymentMethodSelect) {
    paymentMethodSelect.addEventListener('change', updatePaymentQrNote);
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
        const transactionValid = validateTransactionReference();
        const screenshotValid = validateScreenshot();
        const notesValid = validatePaymentNotes();
        const formValid = paymentForm.checkValidity()
            && transactionValid
            && screenshotValid
            && notesValid;

        paymentForm.classList.add('was-validated');

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