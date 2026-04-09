<?php
require_once __DIR__ . '/../config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_booking_ticket_email(array $booking, array $ticket, array $passengers, array $seats): array
{
    if (!defined('MAIL_ENABLED') || MAIL_ENABLED !== true) {
        return [
            'success' => false,
            'message' => 'Mail sending is disabled in config.php'
        ];
    }

    $phpMailerBase = dirname(__DIR__) . '/libs/PHPMailer/src/';
    $exceptionFile = $phpMailerBase . 'Exception.php';
    $phpMailerFile = $phpMailerBase . 'PHPMailer.php';
    $smtpFile = $phpMailerBase . 'SMTP.php';

    if (!file_exists($exceptionFile) || !file_exists($phpMailerFile) || !file_exists($smtpFile)) {
        return [
            'success' => false,
            'message' => 'PHPMailer library files are missing.'
        ];
    }

    require_once $exceptionFile;
    require_once $phpMailerFile;
    require_once $smtpFile;

    $toEmail = trim($booking['customer_email'] ?? '');
    $toName = trim($booking['customer_name'] ?? '');

    if ($toEmail === '') {
        return [
            'success' => false,
            'message' => 'Customer email is missing.'
        ];
    }

    $seatNumbers = implode(', ', array_map(fn($row) => $row['seat_number'], $seats));
    $passengerNames = implode(', ', array_map(fn($row) => $row['full_name'], $passengers));

    $pdfAbsolutePath = dirname(__DIR__) . '/' . ($ticket['pdf_file'] ?? '');
    $qrAbsolutePath = dirname(__DIR__) . '/' . ($ticket['qr_image'] ?? '');

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->Port = (int)MAIL_PORT;

        if (MAIL_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        if (file_exists($pdfAbsolutePath)) {
            $mail->addAttachment($pdfAbsolutePath, basename($pdfAbsolutePath));
        }

        if (file_exists($qrAbsolutePath)) {
            $mail->addEmbeddedImage($qrAbsolutePath, 'ticketqr');
        }

        $mail->isHTML(true);
        $mail->Subject = 'Your Bus Ticket - ' . $booking['booking_code'];

        $mail->Body = '
            <h2>Your Bus Ticket</h2>
            <p>Hello ' . htmlspecialchars($toName ?: 'Customer') . ',</p>
            <p>Your payment has been verified and your bus ticket is ready.</p>

            <p><strong>Booking Code:</strong> ' . htmlspecialchars($booking['booking_code']) . '</p>
            <p><strong>Ticket Number:</strong> ' . htmlspecialchars($ticket['ticket_no']) . '</p>
            <p><strong>Company:</strong> ' . htmlspecialchars($booking['company_name']) . '</p>
            <p><strong>Route:</strong> ' . htmlspecialchars($booking['from_city_name']) . ' → ' . htmlspecialchars($booking['to_city_name']) . '</p>
            <p><strong>Trip Date:</strong> ' . htmlspecialchars($booking['trip_date']) . '</p>
            <p><strong>Departure:</strong> ' . htmlspecialchars(date('Y-m-d H:i', strtotime($booking['departure_datetime']))) . '</p>
            <p><strong>Arrival:</strong> ' . htmlspecialchars(date('Y-m-d H:i', strtotime($booking['arrival_datetime']))) . '</p>
            <p><strong>Passengers:</strong> ' . htmlspecialchars($passengerNames) . '</p>
            <p><strong>Seat Numbers:</strong> ' . htmlspecialchars($seatNumbers) . '</p>

            <p>Please find your PDF ticket attached.</p>
            <p><img src="cid:ticketqr" alt="Ticket QR" style="max-width:220px;"></p>
            <p>Thank you.</p>
        ';

        $mail->AltBody =
            "Your Bus Ticket\n" .
            "Booking Code: {$booking['booking_code']}\n" .
            "Ticket Number: {$ticket['ticket_no']}\n" .
            "Route: {$booking['from_city_name']} -> {$booking['to_city_name']}\n" .
            "Trip Date: {$booking['trip_date']}\n" .
            "Departure: " . date('Y-m-d H:i', strtotime($booking['departure_datetime'])) . "\n" .
            "Arrival: " . date('Y-m-d H:i', strtotime($booking['arrival_datetime'])) . "\n" .
            "Passengers: {$passengerNames}\n" .
            "Seats: {$seatNumbers}\n";

        $mail->send();

        return [
            'success' => true,
            'message' => 'Ticket email sent successfully.'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Ticket email failed: ' . $e->getMessage()
        ];
    }
}