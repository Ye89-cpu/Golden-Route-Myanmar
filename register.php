<?php
require_once __DIR__ . '/includes/auth.php';
require_guest();

$page_title = 'Register - Golden Route Myanmar';
require_once __DIR__ . '/includes/header.php';

$error = get_flash('error');
$success = get_flash('success');
$registrationEnabled = system_setting_runtime_bool('registration_enabled', true);
$registerErrors = $_SESSION['register_errors'] ?? [];
unset($_SESSION['register_errors']);

function register_field_error($errors, $field)
{
    return $errors[$field] ?? '';
}

function register_field_class($errors, $field)
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}
?>

<main class="auth-page auth-page-register auth-v3">
    <div class="auth-v3-orb auth-v3-orb-one"></div>
    <div class="auth-v3-orb auth-v3-orb-two"></div>

    <div class="container position-relative">
        <div class="auth-page-grid auth-register-grid">
            <section class="auth-visual-card auth-visual-register">
                <div class="auth-visual-overlay"></div>
                <div class="auth-v3-pattern" aria-hidden="true"></div>

                <div class="auth-visual-content">
                    <div>
                        <a href="<?php echo BASE_URL; ?>index.php" class="auth-back-link">
                            <i class="bi bi-arrow-left"></i>
                            Back to home
                        </a>

                        <span class="auth-mini-badge">
                            <i class="bi bi-stars"></i>
                            New customer registration
                        </span>

                        <h1 class="auth-visual-title">Create one account for every adventure.</h1>
                        <p class="auth-visual-text">
                            Book buses and tours, upload payment proof, download tickets, and follow your
                            travel status without repeating your details each time.
                        </p>
                    </div>

                    <div class="auth-journey-steps">
                        <div class="auth-journey-step is-active">
                            <span>1</span>
                            <div><strong>Create profile</strong><small>Add your basic information</small></div>
                        </div>
                        <div class="auth-journey-step">
                            <span>2</span>
                            <div><strong>Choose a trip</strong><small>Compare approved companies</small></div>
                        </div>
                        <div class="auth-journey-step">
                            <span>3</span>
                            <div><strong>Travel easily</strong><small>Access tickets and updates</small></div>
                        </div>
                    </div>

                    <div class="auth-highlight-grid auth-highlight-grid-inline">
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-qr-code"></i></span>
                            <div>
                                <strong>QR-ready tickets</strong>
                                <small>Open your ticket from your account</small>
                            </div>
                        </div>
                        <div class="auth-highlight-card">
                            <span class="auth-highlight-icon"><i class="bi bi-credit-card"></i></span>
                            <div>
                                <strong>Payment tracking</strong>
                                <small>Follow proof review and confirmation</small>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="auth-form-card-wrap">
                <div class="auth-form-card-modern auth-form-card-register">
                    <div class="auth-brand-row">
                        <div class="auth-brand-mark">
                            <img src="<?php echo BASE_URL; ?>assets/images/logo.png" alt="Golden Route Myanmar">
                        </div>
                        <div>
                            <strong>Golden Route Myanmar</strong>
                            <span>Your account for easier travel</span>
                        </div>
                    </div>

                    <div class="auth-form-topbar">
                        <span class="auth-form-badge"><i class="bi bi-person-plus"></i> Register</span>
                        <span class="auth-form-note"><i class="bi bi-clock"></i> Takes a few minutes</span>
                    </div>

                    <div class="auth-form-header-modern">
                        <h2>Create your account</h2>
                        <p>Complete the fields below to start booking with a secure customer profile.</p>
                    </div>

                    <div class="auth-progress-line" aria-hidden="true">
                        <span class="is-complete"></span><span class="is-complete"></span><span></span>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger auth-alert" role="alert">
                            <i class="bi bi-exclamation-circle"></i>
                            <span><?php echo e($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success auth-alert" role="alert">
                            <i class="bi bi-check-circle"></i>
                            <span><?php echo e($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$registrationEnabled): ?>
                        <div class="alert alert-warning auth-alert mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle"></i>
                            <span>Customer registration is currently disabled by the administrator.</span>
                        </div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>actions/register_action.php" method="POST" class="auth-modern-form" data-register-validation novalidate>
                            <div class="auth-modern-field">
                                <label class="form-label" for="registerFullName">Full name</label>
                                <div class="auth-input-wrap">
                                    <span class="auth-input-icon"><i class="bi bi-person"></i></span>
                                    <input
                                        type="text"
                                        id="registerFullName"
                                        name="name"
                                        class="form-control auth-control<?php echo register_field_class($registerErrors, 'name'); ?>"
                                        value="<?php echo e(old('name')); ?>"
                                        placeholder="Enter your full name"
                                        autocomplete="name"
                                        aria-describedby="registerNameMessage"
                                        data-register-name
                                        required
                                    >
                                </div>
                                <div class="auth-field-message<?php echo register_field_error($registerErrors, 'name') ? ' is-invalid' : ''; ?>" id="registerNameMessage" data-name-message>
                                    <?php echo e(register_field_error($registerErrors, 'name')); ?>
                                </div>
                            </div>

                            <div class="auth-modern-field auth-grid-2">
                                <div>
                                    <label class="form-label" for="registerEmail">Email address</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-envelope"></i></span>
                                        <input
                                            type="email"
                                            id="registerEmail"
                                            name="email"
                                            class="form-control auth-control<?php echo register_field_class($registerErrors, 'email'); ?>"
                                            value="<?php echo e(old('email')); ?>"
                                            placeholder="name@example.com"
                                            autocomplete="email"
                                            inputmode="email"
                                            aria-describedby="registerEmailMessage"
                                            data-register-email
                                            required
                                        >
                                    </div>
                                    <div class="auth-field-message<?php echo register_field_error($registerErrors, 'email') ? ' is-invalid' : ''; ?>" id="registerEmailMessage" data-email-message>
                                        <?php echo e(register_field_error($registerErrors, 'email')); ?>
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label" for="registerPhone">Phone number</label>
                                    <div class="auth-input-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-telephone"></i></span>
                                        <input
                                            type="text"
                                            id="registerPhone"
                                            name="phone"
                                            class="form-control auth-control<?php echo register_field_class($registerErrors, 'phone'); ?>"
                                            value="<?php echo e(old('phone')); ?>"
                                            placeholder="09xxxxxxxxx"
                                            autocomplete="tel"
                                            inputmode="numeric"
                                            aria-describedby="registerPhoneMessage"
                                            data-register-phone
                                            required
                                        >
                                    </div>
                                    <div class="auth-field-message<?php echo register_field_error($registerErrors, 'phone') ? ' is-invalid' : ''; ?>" id="registerPhoneMessage" data-phone-message>
                                        <?php echo e(register_field_error($registerErrors, 'phone')); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="auth-modern-field auth-grid-2">
                                <div>
                                    <label class="form-label" for="registerPassword">Password</label>
                                    <div class="auth-input-wrap auth-password-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-lock"></i></span>
                                        <input
                                            type="password"
                                            id="registerPassword"
                                            name="password"
                                            class="form-control auth-control auth-control-has-toggle<?php echo register_field_class($registerErrors, 'password'); ?>"
                                            placeholder="Create password"
                                            autocomplete="new-password"
                                            aria-describedby="registerPasswordMessage registerPasswordRules"
                                            data-register-password
                                            required
                                        >
                                        <button
                                            type="button"
                                            class="auth-toggle-password"
                                            data-password-toggle
                                            data-target="#registerPassword"
                                            aria-label="Show password"
                                            aria-pressed="false"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="auth-field-message<?php echo register_field_error($registerErrors, 'password') ? ' is-invalid' : ''; ?>" id="registerPasswordMessage" data-password-message>
                                        <?php echo e(register_field_error($registerErrors, 'password')); ?>
                                    </div>
                                    <div class="auth-password-rules" id="registerPasswordRules" data-password-rules aria-label="Password requirements">
                                        <span class="auth-password-rule" data-password-rule="length"><i class="bi bi-circle"></i> 8+ characters</span>
                                        <span class="auth-password-rule" data-password-rule="letter"><i class="bi bi-circle"></i> Letters</span>
                                        <span class="auth-password-rule" data-password-rule="number"><i class="bi bi-circle"></i> Numbers</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label" for="registerPasswordConfirmation">Confirm password</label>
                                    <div class="auth-input-wrap auth-password-wrap">
                                        <span class="auth-input-icon"><i class="bi bi-shield-check"></i></span>
                                        <input
                                            type="password"
                                            id="registerPasswordConfirmation"
                                            name="password_confirmation"
                                            class="form-control auth-control auth-control-has-toggle<?php echo register_field_class($registerErrors, 'password_confirmation'); ?>"
                                            placeholder="Repeat password"
                                            autocomplete="new-password"
                                            aria-describedby="registerPasswordConfirmationMessage"
                                            data-register-confirm-password
                                            required
                                        >
                                        <button
                                            type="button"
                                            class="auth-toggle-password"
                                            data-password-toggle
                                            data-target="#registerPasswordConfirmation"
                                            aria-label="Show password"
                                            aria-pressed="false"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="auth-field-message<?php echo register_field_error($registerErrors, 'password_confirmation') ? ' is-invalid' : ''; ?>" id="registerPasswordConfirmationMessage" data-confirm-password-message>
                                        <?php echo e(register_field_error($registerErrors, 'password_confirmation')); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="auth-terms-note">
                                <i class="bi bi-shield-check"></i>
                                <span>Your details are used only for account, booking, payment, and travel support functions.</span>
                            </div>

                            <button type="submit" class="btn auth-submit-btn w-100">
                                <span>Create customer account</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="auth-helper-row">
                        <span>Already have an account?</span>
                        <a href="<?php echo BASE_URL; ?>login.php">Sign in here <i class="bi bi-arrow-up-right"></i></a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<?php
clear_old_input();
$hide_footer_cta = true;
require_once __DIR__ . '/includes/footer.php';
