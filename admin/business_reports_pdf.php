<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/report_helper.php';
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

require_role('super_admin');

$startDateInput = $_GET['start_date'] ?? report_default_start_date();
$endDateInput = $_GET['end_date'] ?? report_default_end_date();
[$startDate, $endDate] = report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();
$summary = report_fetch_summary($conn, $startDate, $endDate);

$companyRows = [];
$sql = "
    SELECT
        c.name AS company_name,
        c.company_type,
        COUNT(DISTINCT x.booking_id) AS total_bookings,
        COALESCE(SUM(x.payment_status = 'paid'), 0) AS paid_bookings,
        COALESCE(SUM(CASE WHEN x.payment_status = 'paid' THEN x.total_amount ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(p.status = 'verified'), 0) AS verified_payments,
        COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END), 0) AS verified_amount
    FROM companies c
    LEFT JOIN (
        SELECT t.company_id, b.id AS booking_id, b.booking_type, b.payment_status, b.total_amount
        FROM bookings b
        INNER JOIN trips t ON t.id = b.trip_id
        WHERE b.booking_type = 'bus'
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
        UNION ALL
        SELECT tp.company_id, b.id AS booking_id, b.booking_type, b.payment_status, b.total_amount
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        INNER JOIN tour_packages tp ON tp.id = tb.tour_package_id
        WHERE b.booking_type = 'tour'
          AND DATE(COALESCE(b.booked_at, b.created_at)) BETWEEN ? AND ?
    ) x ON x.company_id = c.id
    LEFT JOIN payments p ON p.booking_id = x.booking_id
    WHERE c.status = 'approved'
    GROUP BY c.id, c.name, c.company_type
    ORDER BY revenue DESC, total_bookings DESC, c.name ASC
    LIMIT 30
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ssss', $startDate, $endDate, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $companyRows[] = $row;
}
$stmt->close();

$tourRows = report_fetch_tour_package_breakdown($conn, $startDate, $endDate, 20);
$paymentRows = report_fetch_recent_payments($conn, $startDate, $endDate, 20);
$conn->close();

class GRMReportPDF extends FPDF
{
    public function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 8, 'Golden Route Myanmar - Business Report', 0, 1, 'C');
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    public function SectionTitle(string $title)
    {
        $this->Ln(4);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->Ln(2);
    }

    public function SmallCell($w, $h, $txt, $border = 1, $align = 'L', $fill = false)
    {
        $txt = iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$txt);
        $this->Cell($w, $h, $txt, $border, 0, $align, $fill);
    }
}

$pdf = new GRMReportPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, 'Date Range: ' . $startDate . ' to ' . $endDate, 0, 1, 'L');
$pdf->Cell(0, 7, 'Generated: ' . date('Y-m-d H:i'), 0, 1, 'L');

$pdf->SectionTitle('Summary');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(68, 8, 'Total Bookings: ' . (int)$summary['total_bookings'], 1);
$pdf->Cell(68, 8, 'Bus Bookings: ' . (int)$summary['bus_bookings'], 1);
$pdf->Cell(68, 8, 'Tour Bookings: ' . (int)$summary['tour_bookings'], 1);
$pdf->Cell(68, 8, 'Paid Bookings: ' . (int)$summary['paid_bookings'], 1, 1);
$pdf->Cell(136, 8, 'Gross Revenue: ' . number_format((float)$summary['gross_revenue'], 2) . ' MMK', 1);
$pdf->Cell(136, 8, 'Verified Payment Amount: ' . number_format((float)$summary['verified_payment_amount'], 2) . ' MMK', 1, 1);

$pdf->SectionTitle('Company Business Table');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(245, 245, 245);
$pdf->SmallCell(62, 7, 'Company', 1, 'L', true);
$pdf->SmallCell(35, 7, 'Type', 1, 'L', true);
$pdf->SmallCell(25, 7, 'Bookings', 1, 'R', true);
$pdf->SmallCell(25, 7, 'Paid', 1, 'R', true);
$pdf->SmallCell(45, 7, 'Revenue', 1, 'R', true);
$pdf->SmallCell(35, 7, 'Verified Pay', 1, 'R', true);
$pdf->SmallCell(45, 7, 'Verified Amount', 1, 'R', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($companyRows as $row) {
    $pdf->SmallCell(62, 7, mb_strimwidth($row['company_name'], 0, 32, '...'));
    $pdf->SmallCell(35, 7, ucwords(str_replace('_', ' ', $row['company_type'])));
    $pdf->SmallCell(25, 7, (int)$row['total_bookings'], 1, 'R');
    $pdf->SmallCell(25, 7, (int)$row['paid_bookings'], 1, 'R');
    $pdf->SmallCell(45, 7, number_format((float)$row['revenue'], 2), 1, 'R');
    $pdf->SmallCell(35, 7, (int)$row['verified_payments'], 1, 'R');
    $pdf->SmallCell(45, 7, number_format((float)$row['verified_amount'], 2), 1, 'R');
    $pdf->Ln();
}

$pdf->SectionTitle('Tour Package Payment Table');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SmallCell(80, 7, 'Package', 1, 'L', true);
$pdf->SmallCell(60, 7, 'Company', 1, 'L', true);
$pdf->SmallCell(30, 7, 'Bookings', 1, 'R', true);
$pdf->SmallCell(35, 7, 'Passengers', 1, 'R', true);
$pdf->SmallCell(55, 7, 'Revenue', 1, 'R', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($tourRows as $row) {
    $pdf->SmallCell(80, 7, mb_strimwidth($row['package_title'], 0, 42, '...'));
    $pdf->SmallCell(60, 7, mb_strimwidth($row['company_name'], 0, 30, '...'));
    $pdf->SmallCell(30, 7, (int)$row['booking_count'], 1, 'R');
    $pdf->SmallCell(35, 7, (int)$row['passengers'], 1, 'R');
    $pdf->SmallCell(55, 7, number_format((float)$row['revenue'], 2), 1, 'R');
    $pdf->Ln();
}

$pdf->SectionTitle('Recent Payments');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SmallCell(45, 7, 'Booking', 1, 'L', true);
$pdf->SmallCell(25, 7, 'Type', 1, 'L', true);
$pdf->SmallCell(55, 7, 'Customer', 1, 'L', true);
$pdf->SmallCell(35, 7, 'Method', 1, 'L', true);
$pdf->SmallCell(45, 7, 'Amount', 1, 'R', true);
$pdf->SmallCell(35, 7, 'Status', 1, 'L', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($paymentRows as $row) {
    $pdf->SmallCell(45, 7, $row['booking_code']);
    $pdf->SmallCell(25, 7, strtoupper($row['booking_type']));
    $pdf->SmallCell(55, 7, mb_strimwidth($row['customer_name'], 0, 28, '...'));
    $pdf->SmallCell(35, 7, ucwords(str_replace('_', ' ', $row['payment_method'])));
    $pdf->SmallCell(45, 7, number_format((float)$row['amount'], 2), 1, 'R');
    $pdf->SmallCell(35, 7, ucfirst($row['status']));
    $pdf->Ln();
}

$pdf->Output('I', 'golden-route-business-report-' . $startDate . '-to-' . $endDate . '.pdf');
exit;
