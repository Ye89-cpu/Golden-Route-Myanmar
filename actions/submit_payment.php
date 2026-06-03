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
    if (!is_dir($folder)) {
        if (!@mkdir($folder, 0777, true)) {
            throw new Exception('Failed to create upload folder: ' . $folder);
        }
    }

    @chmod($folder, 0777);

    if (!is_writable($folder)) {
        throw new Exception(
            'Upload folder is not writable. Please run: sudo chmod -R 777 uploads'
        );
    }
}

$currentUserId = (int) current_user_id();

$bookingId = (int)($_POST['booking_id'] ?? 0);
$paymentMethod = trim((string)($_POST['payment_method'] ?? ''));
$transactionRef = trim((string)($_POST['transaction_ref'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));

submit_payment_store_old_input([
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

if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
    set_flash('error', 'Please select a valid payment method.');
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

    if (($booking['status'] ?? '') === 'cancelled') {
        throw new Exception('Cancelled bookings cannot accept payment.');
    }

    $amount = (float)($booking['total_amount'] ?? 0);

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
        throw new Exception('Payment screenshot upload failed. Error code: ' . $uploadError);
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

    @chmod($destinationFs, 0666);

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
        VALUES (?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, NOW(), NOW())
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