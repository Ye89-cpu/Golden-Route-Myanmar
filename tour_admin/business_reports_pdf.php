<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/permission_helper.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';
require_once __DIR__ . '/../includes/report_helper.php';
require_once __DIR__ . '/../libs/fpdf/fpdf.php';

require_role('tour_admin');

$startDateInput = $_GET['start_date'] ?? report_default_start_date();
$endDateInput = $_GET['end_date'] ?? report_default_end_date();
[$startDate, $endDate] = report_normalize_range($startDateInput, $endDateInput);

$conn = getDBConnection();
require_company_permission($conn, 'view_business_reports');
$company = require_tour_admin_company($conn);
$companyId = (int)$company['company_id'];

$summary = report_fetch_tour_company_summary($conn, $companyId, $startDate, $endDate);
$packageRows = report_fetch_tour_company_package_breakdown($conn, $companyId, $startDate, $endDate, 30);
$paymentRows = report_fetch_tour_company_recent_payments($conn, $companyId, $startDate, $endDate, 25);
$conn->close();

class GRMTourReportPDF extends FPDF
{
    public string $companyName = '';

    public function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 8, 'Golden Route Myanmar - Tour Business Report', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 6, $this->cleanText($this->companyName), 0, 1, 'C');
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    public function SectionTitle(string $title): void
    {
        $this->Ln(4);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(0, 8, $this->cleanText($title), 0, 1, 'L', true);
        $this->Ln(2);
    }

    public function SmallCell($w, $h, $txt, $border = 1, $align = 'L', $fill = false): void
    {
        $this->Cell($w, $h, $this->cleanText((string)$txt), $border, 0, $align, $fill);
    }

    public function cleanText(string $txt): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT', $txt) ?: $txt;
    }
}

$pdf = new GRMTourReportPDF('L', 'mm', 'A4');
$pdf->companyName = (string)($company['company_name'] ?? 'Tour Company');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, 'Date Range: ' . $startDate . ' to ' . $endDate, 0, 1, 'L');
$pdf->Cell(0, 7, 'Generated: ' . date('Y-m-d H:i'), 0, 1, 'L');

$pdf->SectionTitle('Summary');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(68, 8, 'Total Bookings: ' . (int)$summary['total_bookings'], 1);
$pdf->Cell(68, 8, 'Paid Bookings: ' . (int)$summary['paid_bookings'], 1);
$pdf->Cell(68, 8, 'Pending Payments: ' . (int)$summary['pending_review_bookings'], 1);
$pdf->Cell(68, 8, 'Refunded: ' . (int)$summary['refunded_bookings'], 1, 1);
$pdf->Cell(136, 8, 'Gross Revenue: ' . number_format((float)$summary['gross_revenue'], 2) . ' MMK', 1);
$pdf->Cell(136, 8, 'Verified Payment Amount: ' . number_format((float)$summary['verified_payment_amount'], 2) . ' MMK', 1, 1);
$pdf->Cell(136, 8, 'Tour Utilization: ' . number_format((float)$summary['tour_utilization_percent'], 2) . '%', 1);
$pdf->Cell(136, 8, 'Slots Sold / Capacity: ' . (int)$summary['tour_sold_slots'] . ' / ' . (int)$summary['tour_capacity'], 1, 1);

$pdf->SectionTitle('Package Business Table');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetFillColor(245, 245, 245);
$pdf->SmallCell(110, 7, 'Package', 1, 'L', true);
$pdf->SmallCell(35, 7, 'Bookings', 1, 'R', true);
$pdf->SmallCell(35, 7, 'Passengers', 1, 'R', true);
$pdf->SmallCell(45, 7, 'Pending', 1, 'R', true);
$pdf->SmallCell(50, 7, 'Revenue', 1, 'R', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($packageRows as $row) {
    $pdf->SmallCell(110, 7, mb_strimwidth((string)$row['package_title'], 0, 58, '...'));
    $pdf->SmallCell(35, 7, (int)$row['booking_count'], 1, 'R');
    $pdf->SmallCell(35, 7, (int)$row['passengers'], 1, 'R');
    $pdf->SmallCell(45, 7, (int)$row['pending_review'], 1, 'R');
    $pdf->SmallCell(50, 7, number_format((float)$row['revenue'], 2), 1, 'R');
    $pdf->Ln();
}

$pdf->SectionTitle('Recent Payments');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SmallCell(45, 7, 'Booking', 1, 'L', true);
$pdf->SmallCell(85, 7, 'Package', 1, 'L', true);
$pdf->SmallCell(55, 7, 'Customer', 1, 'L', true);
$pdf->SmallCell(35, 7, 'Method', 1, 'L', true);
$pdf->SmallCell(35, 7, 'Amount', 1, 'R', true);
$pdf->SmallCell(25, 7, 'Status', 1, 'L', true);
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($paymentRows as $row) {
    $pdf->SmallCell(45, 7, $row['booking_code']);
    $pdf->SmallCell(85, 7, mb_strimwidth((string)$row['package_title'], 0, 45, '...'));
    $pdf->SmallCell(55, 7, mb_strimwidth((string)$row['customer_name'], 0, 28, '...'));
    $pdf->SmallCell(35, 7, ucwords(str_replace('_', ' ', (string)$row['payment_method'])));
    $pdf->SmallCell(35, 7, number_format((float)$row['amount'], 2), 1, 'R');
    $pdf->SmallCell(25, 7, ucfirst((string)$row['status']));
    $pdf->Ln();
}

$safeCompany = preg_replace('/[^a-z0-9]+/i', '-', strtolower((string)($company['company_name'] ?? 'tour-company')));
$safeCompany = trim((string)$safeCompany, '-') ?: 'tour-company';
$pdf->Output('I', $safeCompany . '-business-report-' . $startDate . '-to-' . $endDate . '.pdf');
exit;
