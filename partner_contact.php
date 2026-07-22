<?php
require_once __DIR__ . '/includes/partner_program_helper.php';

$page_title = 'Partner Application & Contact - Golden Route Myanmar';
$partnerConfig = partner_program_config();
$csrfToken = partner_csrf_token();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/partner_portal_nav.php';
?>

<main class="partner-portal-page">
    <section class="partner-page-hero partner-page-hero-contact">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-8">
                    <span class="partner-kicker"><i class="bi bi-chat-square-text"></i> Apply and contact</span>
                    <h1>Tell us about your travel company.</h1>
                    <p>Submit one application for a bus company, tour operator, or combined business. You will receive an application reference for follow-up.</p>
                </div>
                <div class="col-lg-4">
                    <div class="partner-hero-mini-card">
                        <small>Partner support</small>
                        <strong><?php echo e($partnerConfig['support_phone']); ?></strong>
                        <span><?php echo e($partnerConfig['support_email']); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section">
        <div class="container">
            <?php if ($success = get_flash('success')): ?>
                <div class="alert alert-success partner-alert"><i class="bi bi-check-circle"></i><span><?php echo e($success); ?></span></div>
            <?php endif; ?>
            <?php if ($error = get_flash('error')): ?>
                <div class="alert alert-danger partner-alert"><i class="bi bi-exclamation-circle"></i><span><?php echo e($error); ?></span></div>
            <?php endif; ?>

            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="partner-contact-sidebar">
                        <span class="partner-section-label">Before applying</span>
                        <h2>Prepare your company details.</h2>
                        <p>Complete information helps the partner team review your application faster.</p>

                        <div class="partner-contact-info-list">
                            <a href="mailto:<?php echo e($partnerConfig['support_email']); ?>"><span><i class="bi bi-envelope"></i></span><div><small>Partner email</small><strong><?php echo e($partnerConfig['support_email']); ?></strong></div></a>
                            <a href="tel:<?php echo e($partnerConfig['support_phone']); ?>"><span><i class="bi bi-telephone"></i></span><div><small>Partner phone</small><strong><?php echo e($partnerConfig['support_phone']); ?></strong></div></a>
                            <div><span><i class="bi bi-clock"></i></span><div><small>Suggested support hours</small><strong>Monday–Friday, 9:00 AM–5:00 PM</strong></div></div>
                        </div>

                        <div class="partner-document-mini-list">
                            <strong>Recommended documents</strong>
                            <span><i class="bi bi-check2"></i> Registration / license</span>
                            <span><i class="bi bi-check2"></i> Company bank account</span>
                            <span><i class="bi bi-check2"></i> Routes or package list</span>
                            <span><i class="bi bi-check2"></i> Refund policy</span>
                        </div>

                        <div class="partner-response-note"><i class="bi bi-info-circle"></i><span>Submitting this form does not automatically approve or publish a company. Final access is created after verification and agreement.</span></div>
                    </div>
                </div>

                <div class="col-lg-8" id="partner-application">
                    <div class="partner-application-card">
                        <div class="partner-application-head">
                            <div><span class="partner-section-label">Company application</span><h2>Partner onboarding request</h2><p>Fields marked with * are required.</p></div>
                            <span class="partner-application-icon"><i class="bi bi-buildings"></i></span>
                        </div>

                        <form action="<?php echo BASE_URL; ?>actions/submit_partner_application.php" method="POST" class="partner-application-form">
                            <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label">Company name *</label>
                                    <input type="text" name="company_name" class="form-control partner-control" value="<?php echo e(old('company_name')); ?>" maxlength="180" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Company type *</label>
                                    <?php $oldCompanyType = old('company_type', 'bus_company'); ?>
                                    <select name="company_type" class="form-select partner-control" required>
                                        <option value="bus_company" <?php echo $oldCompanyType === 'bus_company' ? 'selected' : ''; ?>>Bus Company</option>
                                        <option value="tour_operator" <?php echo $oldCompanyType === 'tour_operator' ? 'selected' : ''; ?>>Tour Operator</option>
                                        <option value="both" <?php echo $oldCompanyType === 'both' ? 'selected' : ''; ?>>Bus + Tour Company</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business license number</label>
                                    <input type="text" name="license_no" class="form-control partner-control" value="<?php echo e(old('license_no')); ?>" maxlength="120">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Authorized contact name *</label>
                                    <input type="text" name="contact_name" class="form-control partner-control" value="<?php echo e(old('contact_name')); ?>" maxlength="150" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone number *</label>
                                    <input type="tel" name="phone" class="form-control partner-control" value="<?php echo e(old('phone')); ?>" maxlength="80" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email address *</label>
                                    <input type="email" name="email" class="form-control partner-control" value="<?php echo e(old('email')); ?>" maxlength="190" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred contact *</label>
                                    <?php $oldPreferred = old('preferred_contact', 'phone'); ?>
                                    <select name="preferred_contact" class="form-select partner-control" required>
                                        <option value="phone" <?php echo $oldPreferred === 'phone' ? 'selected' : ''; ?>>Phone</option>
                                        <option value="email" <?php echo $oldPreferred === 'email' ? 'selected' : ''; ?>>Email</option>
                                        <option value="viber" <?php echo $oldPreferred === 'viber' ? 'selected' : ''; ?>>Viber</option>
                                        <option value="telegram" <?php echo $oldPreferred === 'telegram' ? 'selected' : ''; ?>>Telegram</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Estimated monthly bookings</label>
                                    <input type="number" name="monthly_booking_estimate" class="form-control partner-control" value="<?php echo e(old('monthly_booking_estimate')); ?>" min="0" max="1000000">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Business address</label>
                                    <textarea name="business_address" class="form-control partner-control" rows="2" maxlength="1000"><?php echo e(old('business_address')); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Website or social page</label>
                                    <input type="url" name="website" class="form-control partner-control" value="<?php echo e(old('website')); ?>" maxlength="255" placeholder="https://">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current routes / destinations</label>
                                    <input type="text" name="current_routes" class="form-control partner-control" value="<?php echo e(old('current_routes')); ?>" maxlength="1000" placeholder="Example: Yangon, Mandalay, Bagan">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message or integration needs</label>
                                    <textarea name="message" class="form-control partner-control" rows="5" maxlength="3000" placeholder="Tell us about your services, number of buses, packages, preferred launch date, or special requirements."><?php echo e(old('message')); ?></textarea>
                                </div>
                            </div>

                            <div class="partner-application-consent">
                                <i class="bi bi-shield-check"></i>
                                <span>By submitting, you confirm that the information is accurate and may be used to contact your company about partner onboarding.</span>
                            </div>

                            <button type="submit" class="btn partner-btn-primary partner-submit-btn">Submit partner application <i class="bi bi-send"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="partner-section partner-section-soft">
        <div class="container">
            <div class="partner-section-heading text-center"><span class="partner-section-label">What happens next</span><h2>Application review in four stages.</h2></div>
            <div class="partner-next-step-grid">
                <div><span>1</span><strong>Application received</strong><p>A unique reference number is created.</p></div>
                <div><span>2</span><strong>Company contacted</strong><p>The partner team confirms business and document details.</p></div>
                <div><span>3</span><strong>Terms reviewed</strong><p>Commission, settlements, access, and responsibilities are agreed.</p></div>
                <div><span>4</span><strong>Admin activated</strong><p>Your company receives approved access and onboarding support.</p></div>
            </div>
        </div>
    </section>
</main>

<?php
clear_old_input();
require_once __DIR__ . '/includes/footer.php';
?>
