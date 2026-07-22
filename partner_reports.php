<?php
require_once __DIR__ . '/includes/partner_program_helper.php';

$page_title = 'Partner Reports - Golden Route Myanmar';
$partnerConfig = partner_program_config();
$currentRole = current_user_role();
$dashboardReportUrl = '';
$dashboardReportLabel = '';

if ($currentRole === 'super_admin') {
    $dashboardReportUrl = BASE_URL . 'admin/business_reports.php';
    $dashboardReportLabel = 'Open super admin reports';
} elseif ($currentRole === 'bus_admin') {
    $dashboardReportUrl = BASE_URL . 'bus_admin/dashboard.php';
    $dashboardReportLabel = 'Open bus admin dashboard';
} elseif ($currentRole === 'tour_admin') {
    $dashboardReportUrl = BASE_URL . 'tour_admin/business_reports.php';
    $dashboardReportLabel = 'Open tour business reports';
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/partner_portal_nav.php';
?>

<main class="partner-portal-page">
    <section class="partner-page-hero partner-page-hero-reports">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="partner-kicker"><i class="bi bi-bar-chart"></i> Partner reporting</span>
                    <h1>Every booking, fee, refund, and payout explained.</h1>
                    <p>Partner reports help operations and finance teams verify sales, investigate differences, and approve settlements using the same booking records.</p>
                </div>
                <?php if ($dashboardReportUrl !== ''): ?>
                    <div class="col-lg-4 text-lg-end">
                        <a href="<?php echo e($dashboardReportUrl); ?>" class="btn partner-btn-primary"><?php echo e($dashboardReportLabel); ?> <i class="bi bi-arrow-right"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="partner-section-heading text-center">
                <span class="partner-section-label">Available report groups</span>
                <h2>Reports for finance and daily operations.</h2>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3"><article class="partner-report-type-card"><span><i class="bi bi-receipt"></i></span><h3>Settlement Summary</h3><p>Gross eligible sales, commission, refunds, adjustments, and net amount payable.</p><small>Finance team</small></article></div>
                <div class="col-md-6 col-xl-3"><article class="partner-report-type-card"><span><i class="bi bi-list-check"></i></span><h3>Booking Detail</h3><p>Booking code, service, travel date, passenger count, total amount, and booking status.</p><small>Operations team</small></article></div>
                <div class="col-md-6 col-xl-3"><article class="partner-report-type-card"><span><i class="bi bi-credit-card-2-front"></i></span><h3>Payment & Refund</h3><p>Payment proof status, verification date, refund requests, approval, and adjustment totals.</p><small>Finance + support</small></article></div>
                <div class="col-md-6 col-xl-3"><article class="partner-report-type-card"><span><i class="bi bi-graph-up-arrow"></i></span><h3>Performance</h3><p>Top routes or packages, booking volume, revenue, capacity, and conversion trends.</p><small>Management team</small></article></div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="partner-section-heading">
                <span class="partner-section-label">Sample settlement report</span>
                <h2>Easy to reconcile line by line.</h2>
                <p>This is an example structure. Real reports use your company’s booking records and selected period.</p>
            </div>

            <div class="partner-report-preview">
                <div class="partner-report-preview-head">
                    <div><small>Report ID</small><strong>SET-202607-A001</strong></div>
                    <div><small>Company</small><strong>Example Express</strong></div>
                    <div><small>Period</small><strong>1–15 July 2026</strong></div>
                    <div><small>Status</small><strong class="text-success">Ready for review</strong></div>
                </div>
                <div class="table-responsive">
                    <table class="table partner-report-table align-middle mb-0">
                        <thead><tr><th>Booking</th><th>Service</th><th>Paid date</th><th>Status</th><th class="text-end">Gross</th><th class="text-end">Fee</th><th class="text-end">Net</th></tr></thead>
                        <tbody>
                            <tr><td>GRM-260701-A21</td><td>Yangon → Mandalay</td><td>02 Jul 2026</td><td><span class="badge bg-success-subtle text-success">Eligible</span></td><td class="text-end">90,000</td><td class="text-end">6,300</td><td class="text-end fw-bold">83,700</td></tr>
                            <tr><td>GRM-260705-B18</td><td>Yangon → Bagan</td><td>05 Jul 2026</td><td><span class="badge bg-success-subtle text-success">Eligible</span></td><td class="text-end">70,000</td><td class="text-end">4,900</td><td class="text-end fw-bold">65,100</td></tr>
                            <tr><td>GRM-260709-C09</td><td>Yangon → Taunggyi</td><td>09 Jul 2026</td><td><span class="badge bg-danger-subtle text-danger">Refunded</span></td><td class="text-end">85,000</td><td class="text-end">0</td><td class="text-end fw-bold">0</td></tr>
                            <tr class="partner-report-total"><td colspan="4">Example settlement total</td><td class="text-end">160,000</td><td class="text-end">11,200</td><td class="text-end">148,800 <?php echo e($partnerConfig['currency']); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="partner-reconciliation-card">
                        <span class="partner-section-label">Reconciliation workflow</span>
                        <h2>How your finance team approves a report.</h2>
                        <div class="partner-reconciliation-steps">
                            <div><span>1</span><div><strong>Select the report period</strong><p>Use the same date range shown on the settlement cycle notice.</p></div></div>
                            <div><span>2</span><div><strong>Confirm booking eligibility</strong><p>Check paid status, cancellations, completed refunds, and any manual adjustment.</p></div></div>
                            <div><span>3</span><div><strong>Compare gross and commission</strong><p>Verify rate, eligible amount, and commission on each booking or report total.</p></div></div>
                            <div><span>4</span><div><strong>Report a difference</strong><p>Send the report ID and affected booking code before settlement confirmation.</p></div></div>
                            <div><span>5</span><div><strong>Confirm the payout</strong><p>Match the bank transfer reference with the final net settlement amount.</p></div></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="partner-report-fields-card h-100">
                        <span class="partner-section-label">Included information</span>
                        <h2>Minimum fields in every settlement report.</h2>
                        <ul>
                            <li><i class="bi bi-check2"></i> Company and settlement period</li>
                            <li><i class="bi bi-check2"></i> Booking and payment references</li>
                            <li><i class="bi bi-check2"></i> Route, trip, package, or batch</li>
                            <li><i class="bi bi-check2"></i> Gross eligible amount</li>
                            <li><i class="bi bi-check2"></i> Commission rate and fee</li>
                            <li><i class="bi bi-check2"></i> Refunds and adjustments</li>
                            <li><i class="bi bi-check2"></i> Final net settlement</li>
                            <li><i class="bi bi-check2"></i> Transfer date and reference</li>
                        </ul>
                        <a href="<?php echo BASE_URL; ?>partner_manuals.php#finance-manual" class="partner-text-link">Read finance manual <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="partner-support-strip">
                <div><span><i class="bi bi-question-circle"></i></span><div><small>Report difference or missing payout?</small><strong>Include the report ID, booking code, expected amount, and supporting screenshot.</strong></div></div>
                <a href="<?php echo BASE_URL; ?>partner_contact.php" class="btn partner-btn-secondary">Contact partner finance</a>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
