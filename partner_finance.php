<?php
require_once __DIR__ . '/includes/partner_program_helper.php';

$page_title = 'Partner Commission & Payments - Golden Route Myanmar';
$partnerConfig = partner_program_config();
$exampleGross = 1000000;
$exampleRefunds = 50000;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/partner_portal_nav.php';
?>

<main class="partner-portal-page">
    <section class="partner-page-hero partner-page-hero-finance">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="partner-kicker"><i class="bi bi-wallet2"></i> Partner finance</span>
                    <h1>Transparent commission and predictable settlement.</h1>
                    <p>See the standard platform rates, how eligible revenue is calculated, what appears in each report, and when your company receives payment.</p>
                </div>
                <div class="col-lg-4">
                    <div class="partner-hero-mini-card">
                        <small>Minimum settlement</small>
                        <strong><?php echo e(partner_money($partnerConfig['minimum_settlement'], $partnerConfig['currency'])); ?></strong>
                        <span><?php echo e($partnerConfig['settlement_cycle']); ?> payout cycle</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="partner-section-heading text-center">
                <span class="partner-section-label">Standard commission</span>
                <h2>Choose the rate that matches your company type.</h2>
                <p>Rates can be adjusted in the signed agreement for special campaigns, volume commitments, or custom integrations.</p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php
                $rateCards = [
                    ['type' => 'Bus Company', 'rate' => $partnerConfig['bus_commission'], 'icon' => 'bi-bus-front', 'text' => 'For route, schedule, seat, ticket, boarding, and bus booking management.'],
                    ['type' => 'Tour Operator', 'rate' => $partnerConfig['tour_commission'], 'icon' => 'bi-map', 'text' => 'For packages, batches, vouchers, check-in, and tour booking management.'],
                    ['type' => 'Bus + Tour', 'rate' => $partnerConfig['both_commission'], 'icon' => 'bi-stars', 'text' => 'For companies publishing both transport and tour services on one account.'],
                ];
                ?>
                <?php foreach ($rateCards as $index => $card): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="partner-rate-card <?php echo $index === 2 ? 'is-featured' : ''; ?>">
                            <?php if ($index === 2): ?><span class="partner-rate-card-tag">Combined access</span><?php endif; ?>
                            <span class="partner-rate-card-icon"><i class="bi <?php echo e($card['icon']); ?>"></i></span>
                            <h3><?php echo e($card['type']); ?></h3>
                            <div class="partner-rate-card-value"><?php echo e(partner_percentage($card['rate'])); ?><small>per eligible booking</small></div>
                            <p><?php echo e($card['text']); ?></p>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="partner-commercial-note"><i class="bi bi-info-circle"></i><span><?php echo e($partnerConfig['commercial_note']); ?></span></div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-6">
                    <div class="partner-calculator-card">
                        <span class="partner-section-label">Net settlement calculator</span>
                        <h2>Estimate what your company receives.</h2>
                        <p>This calculator is an estimate. The approved settlement report is the final record.</p>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="partnerCompanyType">Company type</label>
                                <select class="form-select partner-control" id="partnerCompanyType">
                                    <option value="<?php echo e($partnerConfig['bus_commission']); ?>">Bus Company</option>
                                    <option value="<?php echo e($partnerConfig['tour_commission']); ?>">Tour Operator</option>
                                    <option value="<?php echo e($partnerConfig['both_commission']); ?>">Bus + Tour Company</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="partnerGrossAmount">Eligible gross sales</label>
                                <input type="number" min="0" step="1000" class="form-control partner-control" id="partnerGrossAmount" value="<?php echo e($exampleGross); ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="partnerRefundAmount">Refunds / adjustments</label>
                                <input type="number" min="0" step="1000" class="form-control partner-control" id="partnerRefundAmount" value="<?php echo e($exampleRefunds); ?>">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Currency</label>
                                <input type="text" class="form-control partner-control" value="<?php echo e($partnerConfig['currency']); ?>" disabled>
                            </div>
                        </div>

                        <div class="partner-calculator-result">
                            <div><span>Eligible amount</span><strong id="partnerEligibleAmount">0</strong></div>
                            <div><span>Platform commission</span><strong id="partnerCommissionAmount">0</strong></div>
                            <div class="partner-calculator-net"><span>Estimated net settlement</span><strong id="partnerNetAmount">0</strong></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="partner-example-report-card">
                        <span class="partner-section-label">Calculation formula</span>
                        <h2>How the settlement total is formed.</h2>
                        <div class="partner-formula-box">
                            <span>Eligible gross sales</span><i class="bi bi-dash"></i><span>Refunds & adjustments</span><i class="bi bi-dash"></i><span>Platform commission</span><i class="bi bi-arrow-right"></i><strong>Net settlement</strong>
                        </div>
                        <div class="table-responsive">
                            <table class="table partner-report-table align-middle mb-0">
                                <tbody>
                                    <tr><td>Confirmed paid bookings</td><td class="text-end"><?php echo e(partner_money($exampleGross, $partnerConfig['currency'])); ?></td></tr>
                                    <tr><td>Approved refunds / adjustments</td><td class="text-end text-danger">− <?php echo e(partner_money($exampleRefunds, $partnerConfig['currency'])); ?></td></tr>
                                    <tr><td>Eligible amount</td><td class="text-end fw-bold"><?php echo e(partner_money($exampleGross - $exampleRefunds, $partnerConfig['currency'])); ?></td></tr>
                                    <tr><td>Bus platform commission (<?php echo e(partner_percentage($partnerConfig['bus_commission'])); ?>)</td><td class="text-end text-danger">− <?php echo e(partner_money(($exampleGross - $exampleRefunds) * $partnerConfig['bus_commission'] / 100, $partnerConfig['currency'])); ?></td></tr>
                                    <tr class="partner-report-total"><td>Example net settlement</td><td class="text-end"><?php echo e(partner_money(($exampleGross - $exampleRefunds) * (1 - $partnerConfig['bus_commission'] / 100), $partnerConfig['currency'])); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="partner-section-heading">
                <span class="partner-section-label">Payment schedule</span>
                <h2>Two clear settlement windows.</h2>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <article class="partner-cycle-card">
                        <span class="partner-cycle-number">A</span>
                        <div><small>First settlement window</small><h3><?php echo e($partnerConfig['period_one']); ?></h3><p>The report includes eligible payments inside the period, approved refunds, adjustments, and platform commission.</p></div>
                    </article>
                </div>
                <div class="col-lg-6">
                    <article class="partner-cycle-card">
                        <span class="partner-cycle-number">B</span>
                        <div><small>Second settlement window</small><h3><?php echo e($partnerConfig['period_two']); ?></h3><p>Amounts below the minimum settlement threshold roll forward to the next cycle.</p></div>
                    </article>
                </div>
            </div>

            <div class="partner-payment-method-panel">
                <div><span><i class="bi bi-bank"></i></span><div><small>Payment method</small><strong><?php echo e($partnerConfig['settlement_method']); ?></strong></div></div>
                <div><span><i class="bi bi-file-earmark-pdf"></i></span><div><small>Report delivery</small><strong><?php echo e($partnerConfig['report_delivery']); ?></strong></div></div>
                <div><span><i class="bi bi-cash-stack"></i></span><div><small>Minimum payout</small><strong><?php echo e(partner_money($partnerConfig['minimum_settlement'], $partnerConfig['currency'])); ?></strong></div></div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="partner-policy-card">
                        <span class="partner-section-label">Important settlement rules</span>
                        <h2>What can change a payout total?</h2>
                        <div class="partner-policy-list">
                            <div><i class="bi bi-x-circle"></i><span><strong>Cancelled or failed bookings</strong> are not included as eligible revenue.</span></div>
                            <div><i class="bi bi-arrow-counterclockwise"></i><span><strong>Approved refunds</strong> are deducted in the current or next available settlement.</span></div>
                            <div><i class="bi bi-exclamation-diamond"></i><span><strong>Chargebacks or disputed payments</strong> may be held until the review is complete.</span></div>
                            <div><i class="bi bi-pencil-square"></i><span><strong>Manual adjustments</strong> must include a reason and appear clearly in the report.</span></div>
                            <div><i class="bi bi-bank"></i><span><strong>Bank account changes</strong> require company verification before the next payout.</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="partner-help-card h-100">
                        <span class="partner-help-icon"><i class="bi bi-headset"></i></span>
                        <h2>Need a custom commercial plan?</h2>
                        <p>Contact the partner team for high-volume routes, exclusive packages, campaigns, or special integration requirements.</p>
                        <a href="<?php echo BASE_URL; ?>partner_contact.php" class="btn partner-btn-primary">Contact partner team</a>
                        <small><?php echo e($partnerConfig['support_email']); ?><br><?php echo e($partnerConfig['support_phone']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    const type = document.getElementById('partnerCompanyType');
    const gross = document.getElementById('partnerGrossAmount');
    const refunds = document.getElementById('partnerRefundAmount');
    const eligibleOut = document.getElementById('partnerEligibleAmount');
    const commissionOut = document.getElementById('partnerCommissionAmount');
    const netOut = document.getElementById('partnerNetAmount');
    const currency = <?php echo json_encode($partnerConfig['currency'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const formatMoney = (value) => new Intl.NumberFormat('en-US', {
        maximumFractionDigits: currency.toUpperCase() === 'MMK' ? 0 : 2
    }).format(Math.max(0, value)) + ' ' + currency.toUpperCase();

    function calculate() {
        const rate = Math.max(0, Number(type.value) || 0);
        const grossValue = Math.max(0, Number(gross.value) || 0);
        const refundValue = Math.max(0, Number(refunds.value) || 0);
        const eligible = Math.max(0, grossValue - refundValue);
        const commission = eligible * rate / 100;
        const net = eligible - commission;

        eligibleOut.textContent = formatMoney(eligible);
        commissionOut.textContent = '− ' + formatMoney(commission) + ' (' + rate + '%)';
        netOut.textContent = formatMoney(net);
    }

    [type, gross, refunds].forEach((element) => element && element.addEventListener('input', calculate));
    calculate();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
