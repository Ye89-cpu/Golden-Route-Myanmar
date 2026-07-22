<?php
require_once __DIR__ . '/includes/partner_program_helper.php';

$page_title = 'Partner Program - Golden Route Myanmar';
$partnerConfig = partner_program_config();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/partner_portal_nav.php';
?>

<main class="partner-portal-page">
    <section class="partner-hero">
        <div class="partner-hero-orb partner-hero-orb-one"></div>
        <div class="partner-hero-orb partner-hero-orb-two"></div>
        <div class="container position-relative">
            <div class="row g-5 align-items-center">
                <div class="col-lg-7">
                    <span class="partner-kicker"><i class="bi bi-patch-check-fill"></i> Golden Route Partner Network</span>
                    <h1>Grow bookings with one trusted travel platform.</h1>
                    <p class="partner-hero-lead">
                        Connect your bus company or tour business to Golden Route Myanmar, publish services,
                        receive verified bookings, track revenue, and reconcile every settlement from one admin account.
                    </p>
                    <div class="partner-hero-actions">
                        <a href="<?php echo BASE_URL; ?>partner_contact.php#partner-application" class="btn partner-btn-primary">
                            Apply as a partner <i class="bi bi-arrow-up-right"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>partner_manuals.php" class="btn partner-btn-secondary">
                            <i class="bi bi-journal-text"></i> Read admin manuals
                        </a>
                    </div>
                    <div class="partner-trust-row">
                        <span><i class="bi bi-shield-check"></i> Approved companies only</span>
                        <span><i class="bi bi-file-earmark-bar-graph"></i> Transparent reports</span>
                        <span><i class="bi bi-headset"></i> Partner support</span>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="partner-hero-dashboard">
                        <div class="partner-dashboard-head">
                            <div>
                                <small>Partner dashboard preview</small>
                                <strong>Revenue overview</strong>
                            </div>
                            <span><i class="bi bi-graph-up-arrow"></i></span>
                        </div>
                        <div class="partner-dashboard-total">
                            <small>Gross confirmed bookings</small>
                            <strong><?php echo e(partner_money(4850000, $partnerConfig['currency'])); ?></strong>
                            <span><i class="bi bi-arrow-up"></i> Example monthly summary</span>
                        </div>
                        <div class="partner-dashboard-grid">
                            <div><small>Platform fee</small><strong><?php echo e(partner_percentage($partnerConfig['bus_commission'])); ?></strong></div>
                            <div><small>Settlement cycle</small><strong><?php echo e($partnerConfig['settlement_cycle']); ?></strong></div>
                            <div><small>Report</small><strong>PDF + Dashboard</strong></div>
                            <div><small>Status</small><strong class="text-success">Ready</strong></div>
                        </div>
                        <div class="partner-dashboard-route">
                            <span class="partner-dashboard-route-icon"><i class="bi bi-bus-front"></i></span>
                            <div><small>Top example route</small><strong>Yangon → Mandalay</strong></div>
                            <span class="partner-dashboard-badge">128 bookings</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="partner-section-heading text-center">
                <span class="partner-section-label">Why partner with us</span>
                <h2>Everything needed to sell and manage travel services.</h2>
                <p>Your team stays in control while Golden Route Myanmar provides the booking workflow and reporting tools.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-xl-3">
                    <article class="partner-benefit-card">
                        <span class="partner-benefit-icon"><i class="bi bi-shop-window"></i></span>
                        <h3>More visibility</h3>
                        <p>Display routes, schedules, seats, tour packages, prices, and company information to customers.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-3">
                    <article class="partner-benefit-card">
                        <span class="partner-benefit-icon"><i class="bi bi-ticket-perforated"></i></span>
                        <h3>Managed bookings</h3>
                        <p>Review bookings, payment proof, tickets, vouchers, boarding, check-in, and customer status.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-3">
                    <article class="partner-benefit-card">
                        <span class="partner-benefit-icon"><i class="bi bi-pie-chart"></i></span>
                        <h3>Clear reports</h3>
                        <p>See sales, refunds, payment verification, route or package performance, and settlement totals.</p>
                    </article>
                </div>
                <div class="col-md-6 col-xl-3">
                    <article class="partner-benefit-card">
                        <span class="partner-benefit-icon"><i class="bi bi-people"></i></span>
                        <h3>Team access</h3>
                        <p>Create company admin accounts and grant only the permissions each team member needs.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-5">
                    <div class="partner-finance-summary h-100">
                        <span class="partner-section-label">Commercial terms</span>
                        <h2>Simple commission, visible before settlement.</h2>
                        <p>Commission is calculated only from eligible confirmed and paid bookings.</p>

                        <div class="partner-rate-list">
                            <div class="partner-rate-row">
                                <span><i class="bi bi-bus-front"></i> Bus Company</span>
                                <strong><?php echo e(partner_percentage($partnerConfig['bus_commission'])); ?></strong>
                            </div>
                            <div class="partner-rate-row">
                                <span><i class="bi bi-map"></i> Tour Operator</span>
                                <strong><?php echo e(partner_percentage($partnerConfig['tour_commission'])); ?></strong>
                            </div>
                            <div class="partner-rate-row">
                                <span><i class="bi bi-stars"></i> Bus + Tour</span>
                                <strong><?php echo e(partner_percentage($partnerConfig['both_commission'])); ?></strong>
                            </div>
                        </div>

                        <a href="<?php echo BASE_URL; ?>partner_finance.php" class="partner-text-link">
                            View full commission and payment policy <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="partner-settlement-card h-100">
                        <div class="partner-settlement-head">
                            <div>
                                <span class="partner-section-label">Settlement schedule</span>
                                <h2>Know when and how your money is paid.</h2>
                            </div>
                            <span class="partner-settlement-cycle"><?php echo e($partnerConfig['settlement_cycle']); ?></span>
                        </div>

                        <div class="partner-timeline">
                            <div class="partner-timeline-item">
                                <span>01</span>
                                <div><strong>Booking is paid</strong><p>Only verified, eligible payments enter the settlement report.</p></div>
                            </div>
                            <div class="partner-timeline-item">
                                <span>02</span>
                                <div><strong>Report is prepared</strong><p><?php echo e($partnerConfig['report_delivery']); ?> with fees and adjustments.</p></div>
                            </div>
                            <div class="partner-timeline-item">
                                <span>03</span>
                                <div><strong>Company reviews</strong><p>Raise any difference before the settlement confirmation deadline.</p></div>
                            </div>
                            <div class="partner-timeline-item">
                                <span>04</span>
                                <div><strong>Net amount is transferred</strong><p><?php echo e($partnerConfig['settlement_method']); ?>.</p></div>
                            </div>
                        </div>

                        <div class="partner-period-grid">
                            <div><small>Cycle A</small><strong><?php echo e($partnerConfig['period_one']); ?></strong></div>
                            <div><small>Cycle B</small><strong><?php echo e($partnerConfig['period_two']); ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <div class="partner-section-heading">
                <span class="partner-section-label">Onboarding process</span>
                <h2>From application to your first live booking.</h2>
            </div>

            <div class="partner-onboarding-grid">
                <article><span>1</span><i class="bi bi-send-check"></i><h3>Submit application</h3><p>Share company, license, contact, routes or tour service information.</p></article>
                <article><span>2</span><i class="bi bi-search"></i><h3>Verification</h3><p>Our team reviews documents, business type, service coverage, and contact details.</p></article>
                <article><span>3</span><i class="bi bi-file-earmark-text"></i><h3>Partner agreement</h3><p>Confirm commission, settlement account, refund rules, and operational responsibilities.</p></article>
                <article><span>4</span><i class="bi bi-person-gear"></i><h3>Admin setup</h3><p>Receive role-based access and follow the bus or tour admin manual.</p></article>
                <article><span>5</span><i class="bi bi-rocket-takeoff"></i><h3>Go live</h3><p>Add schedules or packages, verify test data, and publish services to customers.</p></article>
            </div>
        </div>
    </section>

    <section class="partner-section partner-requirements-section">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-6">
                    <div class="partner-document-panel">
                        <span class="partner-section-label">Prepare these documents</span>
                        <h2>A faster review starts with complete information.</h2>
                        <div class="partner-document-list">
                            <span><i class="bi bi-check2-circle"></i> Company registration or business license</span>
                            <span><i class="bi bi-check2-circle"></i> Authorized contact person and identity details</span>
                            <span><i class="bi bi-check2-circle"></i> Verified company bank account information</span>
                            <span><i class="bi bi-check2-circle"></i> Routes, buses, schedules, or tour package catalogue</span>
                            <span><i class="bi bi-check2-circle"></i> Refund, cancellation, and customer service policy</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="partner-final-cta">
                        <span class="partner-final-icon"><i class="bi bi-buildings"></i></span>
                        <h2>Ready to connect your company?</h2>
                        <p>Send an application and receive a reference number for follow-up with the partner team.</p>
                        <a href="<?php echo BASE_URL; ?>partner_contact.php#partner-application" class="btn partner-btn-primary">
                            Start partner application <i class="bi bi-arrow-right"></i>
                        </a>
                        <small>Questions? <?php echo e($partnerConfig['support_email']); ?> · <?php echo e($partnerConfig['support_phone']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
