<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/notification_helper.php';

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/bookings.php');
}

function submit_payment_redirect(int $bookingId): void
{
    redirect('payment.php?booking_id=' . $bookingId);
}

function submit_payment_store_old_input(array $input): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $_SESSION['old_input'] = $input;
}

function submit_payment_clear_old_input(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    unset($_SESSION['old_input']);
}

function submit_payment_safe_file_name(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return trim((string)$name, '_');
}

function submit_payment_prepare_upload_folder(string $folder): void
{
    if (!is_dir($folder) && !@mkdir($folder, 0755, true) && !is_dir($folder)) {
        throw new Exception('Payment proof upload folder could not be created.');
    }

    if (!is_writable($folder)) {
        throw new Exception('Payment proof upload folder is not writable. Please check the server folder permissions.');
    }
}

function submit_payment_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function submit_payment_clean_single_line(string $value): string
{
    $value = trim($value);
    $cleaned = preg_replace('/\s+/u', ' ', $value);
    return is_string($cleaned) ? $cleaned : $value;
}

function submit_payment_clean_notes(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
    return is_string($cleaned) ? $cleaned : $value;
}

function submit_payment_valid_transaction_reference(string $value): bool
{
    $length = submit_payment_text_length($value);
    if ($length < 4 || $length > 100) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\/#:()\-]{3,99}$/', $value) === 1;
}

function submit_payment_upload_error_message(int $error): string
{
    switch ($error) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The payment screenshot is larger than the server upload limit.';
        case UPLOAD_ERR_PARTIAL:
            return 'The payment screenshot was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Please upload a payment screenshot.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'The server temporary upload folder is unavailable.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not save the uploaded screenshot.';
        case UPLOAD_ERR_EXTENSION:
            return 'The screenshot upload was stopped by a server extension.';
        default:
            return 'The payment screenshot upload failed. Please try again.';
    }
}

$currentUserId = (int) current_user_id();

$bookingId = (int)($_POST['booking_id'] ?? 0);
$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
$transactionRef = submit_payment_clean_single_line((string)($_POST['transaction_ref'] ?? ''));
$notes = submit_payment_clean_notes((string)($_POST['notes'] ?? ''));

submit_payment_store_old_input([
    '_form' => 'payment',
    'booking_id' => $bookingId,
    'payment_method' => $paymentMethod,
    'transaction_ref' => $transactionRef,
    'notes' => $notes,
]);

$allowedPaymentMethods = [
    'wave_money',
    'kbzpay',
    'cash',
    'bank_transfer',
];

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking selected.');
    redirect('customer/bookings.php');
}

$sessionCsrfToken = (string)($_SESSION['payment_csrf_token'] ?? '');
if ($csrfToken === '' || $sessionCsrfToken === '' || !hash_equals($sessionCsrfToken, $csrfToken)) {
    set_flash('error', 'Your payment form session has expired. Please refresh the page and try again.');
    submit_payment_redirect($bookingId);
}

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    set_flash('error', 'Please select a valid payment method.');
    submit_payment_redirect($bookingId);
}

if (!submit_payment_valid_transaction_reference($transactionRef)) {
    set_flash('error', 'Transaction or receipt reference is required and must contain 4–100 valid characters.');
    submit_payment_redirect($bookingId);
}

$notesLength = submit_payment_text_length($notes);
if ($notesLength < 5 || $notesLength > 1000) {
    set_flash('error', 'Payment notes are required and must contain 5–1000 characters.');
    submit_payment_redirect($bookingId);
}

$conn = getDBConnection();
$savedScreenshotFs = null;

try {
    $conn->begin_transaction();

    $bookingSql = "
        SELECT
            id,
            booking_code,
            user_id,
            total_amount,
            status,
            payment_status
        FROM bookings
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ";

    $bookingStmt = $conn->prepare($bookingSql);

    if (!$bookingStmt) {
        throw new Exception('Failed to prepare booking lookup.');
    }

    $bookingStmt->bind_param('ii', $bookingId, $currentUserId);
    $bookingStmt->execute();

    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult ? $bookingResult->fetch_assoc() : null;

    $bookingStmt->close();

    if (!$booking) {
        throw new Exception('Booking not found or does not belong to your account.');
    }

    if (($booking['payment_status'] ?? '') === 'paid') {
        throw new Exception('This booking is already marked as paid.');
    }

    if (($booking['payment_status'] ?? '') === 'pending_review') {
        throw new Exception('A payment proof is already submitted and waiting for review.');
    }

    if (in_array(($booking['status'] ?? ''), ['cancelled', 'completed'], true)) {
        throw new Exception('This booking can no longer accept payment.');
    }

    $amount = (float)($booking['total_amount'] ?? 0);
    if ($amount <= 0) {
        throw new Exception('The booking amount is invalid. Please contact support.');
    }

    $duplicateReferenceSql = "
        SELECT id
        FROM payments
        WHERE payment_method = ?
          AND transaction_ref = ?
          AND status IN ('submitted', 'verified')
          AND booking_id <> ?
        LIMIT 1
        FOR UPDATE
    ";
    $duplicateReferenceStmt = $conn->prepare($duplicateReferenceSql);
    if (!$duplicateReferenceStmt) {
        throw new Exception('Failed to validate the transaction reference.');
    }
    $duplicateReferenceStmt->bind_param('ssi', $paymentMethod, $transactionRef, $bookingId);
    $duplicateReferenceStmt->execute();
    $duplicateReferenceResult = $duplicateReferenceStmt->get_result();
    $duplicateReferenceExists = $duplicateReferenceResult && $duplicateReferenceResult->fetch_assoc();
    $duplicateReferenceStmt->close();

    if ($duplicateReferenceExists) {
        throw new Exception('This transaction or receipt reference has already been used for another payment.');
    }

    /*
        Screenshot upload is required for payment proof.
    */
    if (
        !isset($_FILES['payment_screenshot']) ||
        (int)($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        throw new Exception('Please upload payment screenshot.');
    }

    $file = $_FILES['payment_screenshot'];

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($uploadError !== UPLOAD_ERR_OK) {
        throw new Exception(submit_payment_upload_error_message($uploadError));
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new Exception('Invalid uploaded screenshot file.');
    }

    $maxBytes = 5 * 1024 * 1024;
    $fileSize = (int)($file['size'] ?? 0);

    if ($fileSize <= 0) {
        throw new Exception('Uploaded screenshot is empty.');
    }

    if ($fileSize > $maxBytes) {
        throw new Exception('Screenshot must be smaller than 5MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : '';

    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeToExt[$mimeType])) {
        throw new Exception('Only JPG, PNG, and WEBP screenshots are allowed.');
    }

    $imageInfo = @getimagesize($tmpPath);
    if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
        throw new Exception('The uploaded file is not a valid image.');
    }

    $imageWidth = (int)$imageInfo[0];
    $imageHeight = (int)$imageInfo[1];
    $detectedImageMime = (string)($imageInfo['mime'] ?? '');

    if ($detectedImageMime !== '' && $detectedImageMime !== $mimeType) {
        throw new Exception('The screenshot file type does not match its image content.');
    }

    if ($imageWidth < 200 || $imageHeight < 200) {
        throw new Exception('The screenshot resolution is too small. Minimum size is 200 × 200 pixels.');
    }

    if ($imageWidth > 12000 || $imageHeight > 12000 || ($imageWidth * $imageHeight) > 50000000) {
        throw new Exception('The screenshot dimensions are too large.');
    }

    $extension = $allowedMimeToExt[$mimeType];

    $uploadDirFs = dirname(__DIR__) . '/uploads/payment_proofs';
    $uploadDirDb = 'uploads/payment_proofs';

    submit_payment_prepare_upload_folder($uploadDirFs);

    $baseName = 'payment_' . $bookingId . '_' . time() . '_' . bin2hex(random_bytes(4));
    $safeName = submit_payment_safe_file_name($baseName) . '.' . $extension;

    $destinationFs = $uploadDirFs . '/' . $safeName;

    if (!move_uploaded_file($tmpPath, $destinationFs)) {
        throw new Exception(
            'Failed to save uploaded screenshot. Please check uploads/payment_proofs folder permission.'
        );
    }

    @chmod($destinationFs, 0644);

    $savedScreenshotFs = $destinationFs;
    $screenshotPath = $uploadDirDb . '/' . $safeName;

    $paymentStatus = 'submitted';

    $paymentSql = "
        INSERT INTO payments
        (
            booking_id,
            amount,
            payment_method,
            transaction_ref,
            screenshot_path,
            notes,
            status,
            created_at,
            updated_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";

    $paymentStmt = $conn->prepare($paymentSql);

    if (!$paymentStmt) {
        throw new Exception('Failed to prepare payment insert.');
    }

    $paymentStmt->bind_param(
        'idsssss',
        $bookingId,
        $amount,
        $paymentMethod,
        $transactionRef,
        $screenshotPath,
        $notes,
        $paymentStatus
    );

    if (!$paymentStmt->execute()) {
        $paymentStmt->close();
        throw new Exception('Failed to save payment record.');
    }

    $paymentId = (int)$paymentStmt->insert_id;
    $paymentStmt->close();

    if ($paymentId <= 0) {
        throw new Exception('Payment record ID could not be generated.');
    }

    $updateBookingSql = "
        UPDATE bookings
        SET payment_status = 'pending_review',
            updated_at = NOW()
        WHERE id = ?
        LIMIT 1
    ";

    $updateBookingStmt = $conn->prepare($updateBookingSql);

    if (!$updateBookingStmt) {
        throw new Exception('Failed to prepare booking payment update.');
    }

    $updateBookingStmt->bind_param('i', $bookingId);

    if (!$updateBookingStmt->execute()) {
        $updateBookingStmt->close();
        throw new Exception('Failed to update booking payment status.');
    }

    $updateBookingStmt->close();

    $auditAction = 'payment_submitted';
    $entityType = 'payment';
    $auditDescription = 'Submitted payment for booking: ' . ($booking['booking_code'] ?? ('BOOKING-' . $bookingId));
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $auditSql = "
        INSERT INTO audit_logs
        (
            user_id,
            action,
            entity_type,
            entity_id,
            description,
            ip_address
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $auditStmt = $conn->prepare($auditSql);

    if ($auditStmt) {
        $auditStmt->bind_param(
            'ississ',
            $currentUserId,
            $auditAction,
            $entityType,
            $paymentId,
            $auditDescription,
            $ipAddress
        );

        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();

    try {
        if (function_exists('notify_event_payment_submitted_by_booking_id')) {
            notify_event_payment_submitted_by_booking_id($conn, $bookingId, $currentUserId);
        }
    } catch (Throwable $notifyError) {
        // Notification error should not block payment submission.
    }

    $conn->close();

    submit_payment_clear_old_input();
    unset($_SESSION['payment_csrf_token']);

    set_flash('success', 'Payment submitted successfully. Your payment is now pending review.');
    submit_payment_redirect($bookingId);

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    if ($savedScreenshotFs && file_exists($savedScreenshotFs)) {
        @unlink($savedScreenshotFs);
    }

    try {
        $conn->close();
    } catch (Throwable $closeError) {
    }

    set_flash('error', $e->getMessage());
    submit_payment_redirect($bookingId);
}