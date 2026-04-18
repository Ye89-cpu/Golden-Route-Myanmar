<?php
require_once __DIR__ . '/ticket_helper.php';

function fetch_booking_for_ticket_generation(mysqli $conn, int $bookingId): ?array
{
    $sql = "
        SELECT
            b.id AS booking_id,
            b.booking_code,
            b.user_id,
            b.booking_type,
            b.trip_id,
            b.passenger_count,
            b.total_amount,
            b.status AS booking_status,
            b.payment_status,
            b.created_at AS booking_created_at,

            u.name AS customer_name,
            u.email AS customer_email,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,

            c.name AS company_name,
            bus.bus_number,
            bus.bus_type,
            r.id AS route_id,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM bookings b
        INNER JOIN users u ON u.id = b.user_id
        INNER JOIN trips t ON t.id = b.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE b.id = ?
          AND b.booking_type = 'bus'
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $booking;
}

function create_or_get_ticket_for_booking(mysqli $conn, int $bookingId, ?int $allowedUserId = null, ?int $actorUserId = null): array
{
    $booking = fetch_booking_for_ticket_generation($conn, $bookingId);

    if (!$booking) {
        throw new Exception('Booking not found.');
    }

    if ($allowedUserId !== null && (int)$booking['user_id'] !== (int)$allowedUserId) {
        throw new Exception('You are not allowed to generate this ticket.');
    }

    if ($booking['payment_status'] !== 'paid' || $booking['booking_status'] !== 'paid') {
        throw new Exception('Ticket can only be generated after payment verification.');
    }

    $passengers = fetch_booking_passengers($conn, $bookingId);
    $seats = fetch_booking_seats($conn, $bookingId);

    if (empty($passengers) || empty($seats)) {
        throw new Exception('Passenger or seat data is missing for this booking.');
    }

    $existingTicket = fetch_existing_ticket($conn, $bookingId);
    if ($existingTicket) {
        return [
            'booking' => $booking,
            'ticket' => $existingTicket,
            'passengers' => $passengers,
            'seats' => $seats,
            'created' => false
        ];
    }

    ensure_ticket_directories_exist();

    $ticketNo = generate_unique_ticket_no($conn);
    $qrToken = generate_unique_qr_token($conn);

    $qrRelativePath = 'uploads/qr_codes/' . $ticketNo . '.png';
    $pdfRelativePath = 'uploads/tickets/' . $ticketNo . '.pdf';

    $qrAbsolutePath = dirname(__DIR__) . '/' . $qrRelativePath;
    $pdfAbsolutePath = dirname(__DIR__) . '/' . $pdfRelativePath;

    $qrLibPath = dirname(__DIR__) . '/libs/phpqrcode/qrlib.php';
    if (!file_exists($qrLibPath)) {
        throw new Exception('QR library not found. Please install phpqrcode in libs/phpqrcode/qrlib.php');
    }
    require_once $qrLibPath;

    $qrPayload = json_encode([
        'ticket_no' => $ticketNo,
        'qr_token' => $qrToken,
        'booking_code' => $booking['booking_code'],
        'type' => 'bus_ticket'
    ], JSON_UNESCAPED_UNICODE);

    QRcode::png($qrPayload, $qrAbsolutePath, QR_ECLEVEL_M, 6, 2);

    if (!file_exists($qrAbsolutePath)) {
        throw new Exception('Failed to generate QR image.');
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
    $pdf->Cell(0, 12, 'Myanmar Bus Ticket', 0, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, 'Company: ' . $booking['company_name'], 0, 1);
    $pdf->Cell(0, 8, 'Booking Code: ' . $booking['booking_code'], 0, 1);
    $pdf->Cell(0, 8, 'Ticket Number: ' . $ticketNo, 0, 1);
    $pdf->Cell(0, 8, 'Route: ' . $booking['from_city_name'] . ' -> ' . $booking['to_city_name'], 0, 1);
    $pdf->Cell(0, 8, 'Trip Date: ' . $booking['trip_date'], 0, 1);
    $pdf->Cell(0, 8, 'Departure: ' . date('Y-m-d H:i', strtotime($booking['departure_datetime'])), 0, 1);
    $pdf->Cell(0, 8, 'Arrival: ' . date('Y-m-d H:i', strtotime($booking['arrival_datetime'])), 0, 1);
    $pdf->Cell(0, 8, 'Bus: ' . $booking['bus_number'], 0, 1);

    $seatNumbers = array_map(fn($row) => $row['seat_number'], $seats);
    $pdf->Cell(0, 8, 'Seat Numbers: ' . implode(', ', $seatNumbers), 0, 1);

    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 13);
    $pdf->Cell(0, 8, 'Passengers', 0, 1);

    $pdf->SetFont('Arial', '', 11);
    foreach ($passengers as $index => $passenger) {
        $pdf->Cell(0, 7, ($index + 1) . '. ' . $passenger['full_name'], 0, 1);
    }

    $pdf->Ln(5);
    $pdf->Cell(0, 8, 'QR Code:', 0, 1);
    $pdf->Image($qrAbsolutePath, 15, $pdf->GetY(), 45, 45);

    $pdf->Ln(50);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->MultiCell(0, 6, 'Please keep this ticket and present the QR code during boarding.');

    $pdf->Output('F', $pdfAbsolutePath);

    if (!file_exists($pdfAbsolutePath)) {
        throw new Exception('Failed to generate ticket PDF.');
    }

    $tripId = (int)$booking['trip_id'];
    $ticketStatus = 'valid';

    $insertSql = "
        INSERT INTO tickets
        (
            booking_id,
            trip_id,
            ticket_no,
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
        $tripId,
        $ticketNo,
        $qrToken,
        $qrRelativePath,
        $pdfRelativePath,
        $ticketStatus
    );

    if (!$insertStmt->execute()) {
        $insertStmt->close();
        throw new Exception('Failed to save ticket record.');
    }

    $ticketId = (int)$insertStmt->insert_id;
    $insertStmt->close();

    if ($actorUserId !== null) {
        $action = 'ticket_generated';
        $entityType = 'ticket';
        $description = 'Generated ticket ' . $ticketNo . ' for booking ' . $booking['booking_code'];
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

        $auditSql = "
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $auditStmt = $conn->prepare($auditSql);
        $auditStmt->bind_param('ississ', $actorUserId, $action, $entityType, $ticketId, $description, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $ticket = fetch_existing_ticket($conn, $bookingId);

    return [
        'booking' => $booking,
        'ticket' => $ticket,
        'passengers' => $passengers,
        'seats' => $seats,
        'created' => true
    ];
}