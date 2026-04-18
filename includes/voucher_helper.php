<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tour_booking_helper.php';

function generate_unique_voucher_code(mysqli $conn): string
{
    do {
        $voucherCode = 'VCH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $sql = 'SELECT id FROM vouchers WHERE voucher_code = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $voucherCode);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $voucherCode;
}

function generate_unique_voucher_token(mysqli $conn): string
{
    do {
        $token = bin2hex(random_bytes(32));

        $sql = 'SELECT id FROM vouchers WHERE qr_token = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);

    return $token;
}

function voucher_qr_directory(): string
{
    return dirname(__DIR__) . '/uploads/voucher_qr/';
}

function voucher_pdf_directory(): string
{
    return dirname(__DIR__) . '/uploads/vouchers/';
}

function ensure_voucher_directories_exist(): void
{
    foreach ([voucher_qr_directory(), voucher_pdf_directory()] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function voucher_table_has_qr_image(mysqli $conn): bool
{
    return tour_booking_table_column_exists($conn, 'vouchers', 'qr_image');
}

function fetch_paid_tour_booking_for_voucher(mysqli $conn, int $bookingId, ?int $userId = null): ?array
{
    $packageColumn = tour_booking_get_batch_package_column($conn);

    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.user_id,
            b.booking_type,
            b.tour_batch_id,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.created_at AS booking_created_at,
            u.name AS customer_name,
            u.email AS customer_email,
            tb.id AS batch_id,
            tb.start_date,
            tb.end_date,
            tb.price AS batch_price,
            tp.id AS package_id,
            tp.title AS package_title,
            tp.description,
            tp.duration_days,
            tp.hotel_info,
            tp.transport_info,
            tp.itinerary,
            tp.included_services,
            tp.excluded_services,
            c.name AS company_name
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.{$packageColumn}
        INNER JOIN companies c ON c.id = tp.company_id
        WHERE b.id = ?
          AND b.booking_type = 'tour'
    ";

    if ($userId !== null) {
        $sql .= ' AND b.user_id = ?';
    }

    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    if ($userId !== null) {
        $stmt->bind_param('ii', $bookingId, $userId);
    } else {
        $stmt->bind_param('i', $bookingId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $booking;
}

function fetch_voucher_passengers(mysqli $conn, int $bookingId): array
{
    $sql = "
        SELECT id, full_name, phone, nrc_passport, gender, age, special_note
        FROM booking_passengers
        WHERE booking_id = ?
        ORDER BY id ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    return $rows;
}

function fetch_existing_voucher(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT *
        FROM vouchers
        WHERE booking_id = ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $voucher = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $voucher;
}

function create_or_get_voucher_for_booking(mysqli $conn, int $bookingId, ?int $allowedUserId = null, ?int $actorUserId = null): array
{
    $booking = fetch_paid_tour_booking_for_voucher($conn, $bookingId, $allowedUserId);
    if (!$booking) {
        throw new Exception('Tour booking not found.');
    }

    if ($booking['payment_status'] !== 'paid' || $booking['booking_status'] !== 'paid') {
        throw new Exception('Voucher can only be generated after payment verification.');
    }

    $existingVoucher = fetch_existing_voucher($conn, $bookingId);
    $passengers = fetch_voucher_passengers($conn, $bookingId);

    if (empty($passengers)) {
        throw new Exception('Traveler data is missing for this booking.');
    }

    if ($existingVoucher) {
        return [
            'booking' => $booking,
            'voucher' => $existingVoucher,
            'passengers' => $passengers,
            'created' => false,
        ];
    }

    ensure_voucher_directories_exist();

    $voucherCode = generate_unique_voucher_code($conn);
    $qrToken = generate_unique_voucher_token($conn);

    $qrRelativePath = 'uploads/voucher_qr/' . $voucherCode . '.png';
    $pdfRelativePath = 'uploads/vouchers/' . $voucherCode . '.pdf';

    $qrAbsolutePath = dirname(__DIR__) . '/' . $qrRelativePath;
    $pdfAbsolutePath = dirname(__DIR__) . '/' . $pdfRelativePath;

    $qrLibPath = dirname(__DIR__) . '/libs/phpqrcode/qrlib.php';
    if (!file_exists($qrLibPath)) {
        throw new Exception('QR library not found. Please install phpqrcode in libs/phpqrcode/qrlib.php');
    }
    require_once $qrLibPath;

    $qrPayload = json_encode([
        'voucher_code' => $voucherCode,
        'qr_token' => $qrToken,
        'booking_code' => $booking['booking_code'],
        'type' => 'tour_voucher',
    ], JSON_UNESCAPED_UNICODE);

    QRcode::png($qrPayload, $qrAbsolutePath, QR_ECLEVEL_M, 6, 2);

    if (!file_exists($qrAbsolutePath)) {
        throw new Exception('Failed to generate voucher QR image.');
    }

    $fpdfPath = dirname(__DIR__) . '/libs/fpdf/fpdf.php';
    if (!file_exists($fpdfPath)) {
        throw new Exception('FPDF library not found. Please install FPDF in libs/fpdf/fpdf.php');
    }
    require_once $fpdfPath;

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 15);

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 12, 'Myanmar Tour Voucher', 0, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Company: ' . $booking['company_name'], 0, 1);
    $pdf->Cell(0, 8, 'Booking Code: ' . $booking['booking_code'], 0, 1);
    $pdf->Cell(0, 8, 'Voucher Code: ' . $voucherCode, 0, 1);
    $pdf->Cell(0, 8, 'Package: ' . $booking['package_title'], 0, 1);
    $pdf->Cell(0, 8, 'Batch: ' . $booking['start_date'] . ' to ' . $booking['end_date'], 0, 1);
    $pdf->Cell(0, 8, 'Passenger Count: ' . $booking['passenger_count'], 0, 1);
    $pdf->Cell(0, 8, 'Total Amount: ' . number_format((float)$booking['total_amount'], 2) . ' MMK', 0, 1);

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'Travelers', 0, 1);

    $pdf->SetFont('Arial', '', 11);
    foreach ($passengers as $index => $passenger) {
        $pdf->Cell(0, 7, ($index + 1) . '. ' . $passenger['full_name'], 0, 1);
    }

    $pdf->Ln(5);
    $pdf->Cell(0, 8, 'QR Code:', 0, 1);
    $pdf->Image($qrAbsolutePath, 15, $pdf->GetY(), 45, 45);

    $pdf->Ln(50);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->MultiCell(0, 6, 'Please keep this voucher and present the QR code during check-in / departure.');
    $pdf->Output('F', $pdfAbsolutePath);

    if (!file_exists($pdfAbsolutePath)) {
        throw new Exception('Failed to generate voucher PDF.');
    }

    $voucherStatus = 'valid';
    $batchId = (int)$booking['batch_id'];

    if (voucher_table_has_qr_image($conn)) {
        $insertSql = "
            INSERT INTO vouchers
            (
                booking_id,
                tour_batch_id,
                voucher_code,
                qr_token,
                qr_image,
                pdf_file,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param(
            'iisssss',
            $bookingId,
            $batchId,
            $voucherCode,
            $qrToken,
            $qrRelativePath,
            $pdfRelativePath,
            $voucherStatus
        );
    } else {
        $insertSql = "
            INSERT INTO vouchers
            (
                booking_id,
                tour_batch_id,
                voucher_code,
                qr_token,
                pdf_file,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param(
            'iissss',
            $bookingId,
            $batchId,
            $voucherCode,
            $qrToken,
            $pdfRelativePath,
            $voucherStatus
        );
    }

    if (!$insertStmt->execute()) {
        $insertStmt->close();
        throw new Exception('Failed to save voucher record.');
    }

    $voucherId = (int)$insertStmt->insert_id;
    $insertStmt->close();

    if ($actorUserId !== null) {
        $action = 'voucher_generated';
        $entityType = 'voucher';
        $description = 'Generated voucher ' . $voucherCode . ' for booking ' . $booking['booking_code'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $auditSql = "
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $auditStmt = $conn->prepare($auditSql);
        $auditStmt->bind_param('ississ', $actorUserId, $action, $entityType, $voucherId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $voucher = fetch_existing_voucher($conn, $bookingId);

    return [
        'booking' => $booking,
        'voucher' => $voucher,
        'passengers' => $passengers,
        'created' => true,
    ];
}
?>
