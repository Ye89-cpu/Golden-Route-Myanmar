<?php
require_once __DIR__ . '/includes/partner_program_helper.php';

$page_title = 'Partner Admin Manuals - Golden Route Myanmar';
$partnerConfig = partner_program_config();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/partner_portal_nav.php';
?>

<main class="partner-portal-page partner-manual-page">
    <section class="partner-page-hero partner-page-hero-manuals">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="partner-kicker"><i class="bi bi-journal-check"></i> Partner admin manuals</span>
                    <h1>Step-by-step guides for every company admin role.</h1>
                    <p>Use these manuals during onboarding, staff training, and daily operations. Each section follows the actual Golden Route Myanmar workflow.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button type="button" class="btn partner-btn-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-manual-index-section">
        <div class="container">
            <div class="partner-manual-index">
                <a href="#first-login"><span><i class="bi bi-key"></i></span><div><strong>First Login</strong><small>Account and security setup</small></div></a>
                <a href="#bus-admin-manual"><span><i class="bi bi-bus-front"></i></span><div><strong>Bus Admin</strong><small>Routes, buses, trips, seats</small></div></a>
                <a href="#tour-admin-manual"><span><i class="bi bi-map"></i></span><div><strong>Tour Admin</strong><small>Packages, batches, vouchers</small></div></a>
                <a href="#finance-manual"><span><i class="bi bi-wallet2"></i></span><div><strong>Finance</strong><small>Payments, refunds, reports</small></div></a>
                <a href="#support-manual"><span><i class="bi bi-headset"></i></span><div><strong>Support</strong><small>Issue reporting and escalation</small></div></a>
            </div>
        </div>
    </section>

    <section class="partner-section" id="first-login">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-5">
                    <div class="partner-manual-heading-card sticky-lg-top">
                        <span class="partner-manual-number">01</span>
                        <span class="partner-section-label">First login checklist</span>
                        <h2>Secure the account before adding services.</h2>
                        <p>Complete these steps immediately after the super admin creates your company admin account.</p>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="partner-checklist-card">
                        <div><span>1</span><div><strong>Open the login page</strong><p>Use the email address provided during onboarding and the temporary password sent by the platform team.</p></div></div>
                        <div><span>2</span><div><strong>Review your profile</strong><p>Confirm name, phone number, company, role, and profile image.</p></div></div>
                        <div><span>3</span><div><strong>Change the password</strong><p>Use a unique password and never share one account among multiple employees.</p></div></div>
                        <div><span>4</span><div><strong>Check permissions</strong><p>Confirm that your menu only shows the operations your role is allowed to manage.</p></div></div>
                        <div><span>5</span><div><strong>Verify company data</strong><p>Check company name, logo, contact details, license, and approval status before publishing.</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft" id="bus-admin-manual">
        <div class="container">
            <div class="partner-section-heading">
                <span class="partner-section-label">Bus company manual</span>
                <h2>Manage buses, routes, schedules, seats, and boarding.</h2>
            </div>

            <div class="accordion partner-manual-accordion" id="busManualAccordion">
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#busStepOne"><span>1</span> Add a bus and seat capacity</button></h3>
                    <div id="busStepOne" class="accordion-collapse collapse show" data-bs-parent="#busManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Bus Admin → Buses</strong>.</li><li>Select <strong>Add Bus</strong>.</li><li>Enter bus number, model, service type, capacity, and status.</li><li>Save the bus, then open <strong>Seat Layout</strong>.</li><li>Generate or update seat numbers and confirm the total matches capacity.</li></ol><div class="partner-manual-tip"><i class="bi bi-lightbulb"></i> Never reduce capacity below seats already assigned to future bookings.</div></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#busStepTwo"><span>2</span> Create and maintain routes</button></h3>
                    <div id="busStepTwo" class="accordion-collapse collapse" data-bs-parent="#busManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Bus Admin → Routes</strong>.</li><li>Add origin, destination, duration, distance, and route status.</li><li>Confirm origin and destination are different.</li><li>Edit route information before attaching new schedules.</li><li>Do not delete a route that still has future trips.</li></ol></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#busStepThree"><span>3</span> Generate schedules and trips</button></h3>
                    <div id="busStepThree" class="accordion-collapse collapse" data-bs-parent="#busManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Generate Schedule</strong>.</li><li>Select route, bus, departure time, arrival time, fare, active dates, and operating days.</li><li>Check that the same bus is not used for overlapping trips.</li><li>Generate trips and review the created dates.</li><li>Update trip status when a service is cancelled or unavailable.</li></ol><div class="partner-manual-warning"><i class="bi bi-exclamation-triangle"></i> A schedule end date must not be earlier than its start date.</div></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#busStepFour"><span>4</span> Review bookings and payments</button></h3>
                    <div id="busStepFour" class="accordion-collapse collapse" data-bs-parent="#busManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Bookings</strong> and filter by trip or status.</li><li>Open the booking detail and compare payment proof with booking total.</li><li>Verify valid payments or reject invalid proof with a clear reason.</li><li>Confirm the booking and issue the ticket only after payment verification.</li><li>Use the manifest before departure to check passengers and assigned seats.</li></ol></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#busStepFive"><span>5</span> Board passengers using ticket QR</button></h3>
                    <div id="busStepFive" class="accordion-collapse collapse" data-bs-parent="#busManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Trip Boarding</strong> or <strong>Scan Ticket</strong>.</li><li>Select the correct trip before scanning.</li><li>Scan the ticket QR or enter the ticket reference.</li><li>Confirm passenger, seat, trip, and ticket status.</li><li>Mark the passenger as boarded once only.</li></ol></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section" id="tour-admin-manual">
        <div class="container">
            <div class="partner-section-heading">
                <span class="partner-section-label">Tour operator manual</span>
                <h2>Publish packages and manage tour operations.</h2>
            </div>

            <div class="accordion partner-manual-accordion" id="tourManualAccordion">
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#tourStepOne"><span>1</span> Create a tour package</button></h3>
                    <div id="tourStepOne" class="accordion-collapse collapse show" data-bs-parent="#tourManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Tour Admin → Packages</strong>.</li><li>Enter package title, destination, duration, price, description, inclusions, and status.</li><li>Use clear customer-facing information and confirm the final selling price.</li><li>Add or update the package before creating batches.</li><li>Publish only packages ready for sale.</li></ol></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tourStepTwo"><span>2</span> Create departure batches</button></h3>
                    <div id="tourStepTwo" class="accordion-collapse collapse" data-bs-parent="#tourManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Batches</strong> and choose a package.</li><li>Set travel dates, booking deadline, capacity, price, and status.</li><li>Confirm the end date is after the start date.</li><li>Do not reduce capacity below confirmed passengers.</li><li>Close or cancel batches that can no longer accept bookings.</li></ol></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tourStepThree"><span>3</span> Confirm booking and issue voucher</button></h3>
                    <div id="tourStepThree" class="accordion-collapse collapse" data-bs-parent="#tourManualAccordion"><div class="accordion-body"><ol><li>Open the booking detail and review passenger count and total amount.</li><li>Compare payment proof with the required amount.</li><li>Verify or reject the payment.</li><li>Confirm the booking after successful verification.</li><li>Generate the voucher and send the customer the booking reference.</li></ol></div></div>
                </div>
                <div class="accordion-item">
                    <h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tourStepFour"><span>4</span> Check in guests</button></h3>
                    <div id="tourStepFour" class="accordion-collapse collapse" data-bs-parent="#tourManualAccordion"><div class="accordion-body"><ol><li>Open <strong>Voucher Check-in</strong>.</li><li>Scan the voucher QR or enter the voucher number.</li><li>Confirm the package, batch, travel date, and customer name.</li><li>Mark the voucher checked in once.</li><li>Escalate duplicate, cancelled, or invalid vouchers to support.</li></ol></div></div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft" id="finance-manual">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="partner-manual-card h-100">
                        <span class="partner-manual-card-icon"><i class="bi bi-credit-card"></i></span>
                        <span class="partner-section-label">Payment verification</span>
                        <h2>Approve payment proof safely.</h2>
                        <ol>
                            <li>Match booking reference and customer details.</li>
                            <li>Confirm paid amount, account, transaction date, and transaction reference.</li>
                            <li>Check that the proof has not been used for another booking.</li>
                            <li>Verify only when all details match.</li>
                            <li>Write a specific rejection reason when proof is invalid.</li>
                        </ol>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="partner-manual-card h-100">
                        <span class="partner-manual-card-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
                        <span class="partner-section-label">Refund management</span>
                        <h2>Process refunds with a clear record.</h2>
                        <ol>
                            <li>Open the refund request and verify the original booking.</li>
                            <li>Review reason, cancellation policy, paid amount, and requested refund.</li>
                            <li>Approve or reject according to the partner agreement.</li>
                            <li>Record the approved amount and notes.</li>
                            <li>Confirm the adjustment appears in the settlement report.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="partner-manual-report-flow">
                <div><span>1</span><strong>Choose report period</strong></div><i class="bi bi-arrow-right"></i>
                <div><span>2</span><strong>Review bookings</strong></div><i class="bi bi-arrow-right"></i>
                <div><span>3</span><strong>Check adjustments</strong></div><i class="bi bi-arrow-right"></i>
                <div><span>4</span><strong>Confirm net total</strong></div><i class="bi bi-arrow-right"></i>
                <div><span>5</span><strong>Match transfer</strong></div>
            </div>
        </div>
    </section>

    <section class="partner-section" id="support-manual">
        <div class="container">
            <div class="row g-4 align-items-stretch">
                <div class="col-lg-7">
                    <div class="partner-support-manual-card h-100">
                        <span class="partner-section-label">Issue escalation</span>
                        <h2>Send enough information for a faster solution.</h2>
                        <div class="partner-support-data-grid">
                            <div><i class="bi bi-hash"></i><strong>Reference</strong><span>Booking, ticket, voucher, payment, refund, report, or application ID</span></div>
                            <div><i class="bi bi-building"></i><strong>Company</strong><span>Company name and admin account email</span></div>
                            <div><i class="bi bi-clock-history"></i><strong>Date & time</strong><span>When the issue happened and affected travel date</span></div>
                            <div><i class="bi bi-card-text"></i><strong>Description</strong><span>Expected result, actual result, and steps already tried</span></div>
                            <div><i class="bi bi-image"></i><strong>Evidence</strong><span>Screenshot or report showing the problem</span></div>
                            <div><i class="bi bi-exclamation-circle"></i><strong>Urgency</strong><span>State whether a departure, check-in, or payout is blocked</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="partner-help-card h-100">
                        <span class="partner-help-icon"><i class="bi bi-headset"></i></span>
                        <h2>Partner support</h2>
                        <p>Use the contact page for onboarding, account access, operations, report, or settlement support.</p>
                        <a href="<?php echo BASE_URL; ?>partner_contact.php" class="btn partner-btn-primary">Open contact page</a>
                        <small><?php echo e($partnerConfig['support_email']); ?><br><?php echo e($partnerConfig['support_phone']); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
