<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Privacy Policy - Golden Route Myanmar';

require_once __DIR__ . '/includes/header.php';
?>

<section class="policy-hero-section">
    <div class="container">
        <div class="policy-hero-card">
            <span class="section-kicker">Legal & Trust</span>
            <h1 class="policy-page-title">Privacy Policy</h1>
            <p class="policy-page-subtitle">
                Golden Route Myanmar values your privacy and aims to keep your personal information safe,
                transparent, and responsibly managed while you use our bus ticket and tour booking platform.
            </p>
            <div class="policy-meta-row">
                <span>Effective Date: <?php echo date('Y-m-d'); ?></span>
                <span>Applies to: Website users, registered customers, and booking users</span>
            </div>
        </div>
    </div>
</section>

<section class="policy-content-section pb-5">
    <div class="container">
        <div class="policy-layout">
            <aside class="policy-sidebar">
                <div class="policy-side-card">
                    <h5>On this page</h5>
                    <a href="#introduction">1. Introduction</a>
                    <a href="#collect">2. Information We Collect</a>
                    <a href="#use">3. How We Use Information</a>
                    <a href="#share">4. Sharing of Information</a>
                    <a href="#cookies">5. Cookies</a>
                    <a href="#security">6. Security</a>
                    <a href="#retention">7. Data Retention</a>
                    <a href="#choices">8. Your Choices</a>
                    <a href="#children">9. Children’s Privacy</a>
                    <a href="#updates">10. Policy Updates</a>
                    <a href="#contact">11. Contact</a>
                </div>
            </aside>

            <div class="policy-main-card">
                <section id="introduction" class="policy-block">
                    <h2>1. Introduction</h2>
                    <p>
                        This Privacy Policy explains how Golden Route Myanmar collects, uses, stores, and protects
                        your information when you browse our website, create an account, search for routes,
                        book bus tickets, request tours, or communicate with us.
                    </p>
                    <p>
                        We are committed to handling your information in a responsible and respectful way.
                        By using this website, you agree to the practices described in this policy.
                    </p>
                </section>

                <section id="collect" class="policy-block">
                    <h2>2. Information We Collect</h2>
                    <p>Depending on how you use the platform, we may collect the following types of information:</p>

                    <div class="policy-info-grid">
                        <div class="policy-info-box">
                            <h4>Account Information</h4>
                            <p>Name, email address, phone number, password hash, profile image, and account role.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Booking Information</h4>
                            <p>Trip selection, route details, travel date, selected seats, passenger details, booking history, and payment submission details.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Tour Information</h4>
                            <p>Tour package selection, traveler information, requirements submitted during checkout, and booking records.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Technical Information</h4>
                            <p>Basic browser, device, session, and website interaction information needed to keep the website secure and functional.</p>
                        </div>
                    </div>

                    <p>
                        We may also collect support-related information when you send us questions, upload payment proof,
                        or contact us regarding cancellations, refunds, or booking changes.
                    </p>
                </section>

                <section id="use" class="policy-block">
                    <h2>3. How We Use Information</h2>
                    <ul class="policy-list">
                        <li>To create and manage user accounts</li>
                        <li>To search, confirm, and process ticket or tour bookings</li>
                        <li>To verify payments and generate tickets, vouchers, or QR records</li>
                        <li>To show booking history and account-related features</li>
                        <li>To improve platform performance, safety, and user experience</li>
                        <li>To communicate booking status, payment review results, and service updates</li>
                        <li>To prevent fraud, misuse, unauthorized access, or suspicious activity</li>
                    </ul>
                </section>

                <section id="share" class="policy-block">
                    <h2>4. Sharing of Information</h2>
                    <p>
                        Golden Route Myanmar does not sell your personal information. We may share limited information
                        only when it is necessary for service operation, booking fulfillment, legal compliance,
                        fraud prevention, or business administration.
                    </p>
                    <p>
                        For example, relevant booking information may be visible to authorized bus company staff,
                        tour operators, or administrators only when needed to process reservations, verify payment,
                        confirm travelers, or support customer service.
                    </p>
                </section>

                <section id="cookies" class="policy-block">
                    <h2>5. Cookies</h2>
                    <p>
                        We use cookies and similar small browser storage tools mainly to keep the website working properly,
                        maintain user sessions, protect login activity, and remember certain temporary preferences.
                    </p>
                    <p>
                        More detailed information about the cookies used by this platform can be found on our
                        <a href="<?php echo BASE_URL; ?>cookie_policy.php">Cookie Policy</a> page.
                    </p>
                </section>

                <section id="security" class="policy-block">
                    <h2>6. Security</h2>
                    <p>
                        We take reasonable technical and administrative measures to protect your information,
                        including secured login flows, access controls, user roles, and internal management restrictions.
                        However, no website or storage system can guarantee absolute security in every situation.
                    </p>
                    <p>
                        Users are also responsible for keeping their password private and for logging out from shared devices.
                    </p>
                </section>

                <section id="retention" class="policy-block">
                    <h2>7. Data Retention</h2>
                    <p>
                        We keep information for as long as it is reasonably necessary to operate the platform,
                        maintain booking history, comply with legal or business obligations, resolve disputes,
                        and support accounting or service records.
                    </p>
                </section>

                <section id="choices" class="policy-block">
                    <h2>8. Your Choices</h2>
                    <ul class="policy-list">
                        <li>You may review and update parts of your account information where account features allow it.</li>
                        <li>You may choose not to continue using the site if you do not agree with this policy.</li>
                        <li>You may control cookies through your browser settings, although some site features may stop working correctly.</li>
                    </ul>
                </section>

                <section id="children" class="policy-block">
                    <h2>9. Children’s Privacy</h2>
                    <p>
                        This platform is intended for general users making travel-related bookings.
                        We do not knowingly collect personal information from children without appropriate permission or legal basis.
                    </p>
                </section>

                <section id="updates" class="policy-block">
                    <h2>10. Policy Updates</h2>
                    <p>
                        We may update this Privacy Policy from time to time to reflect service improvements,
                        new features, legal requirements, or business changes. The updated version will be posted on this page.
                    </p>
                </section>

                <section id="contact" class="policy-block mb-0">
                    <h2>11. Contact</h2>
                    <p>
                        For privacy-related questions, booking concerns, or account issues, please contact Golden Route Myanmar
                        through the official support information shown in the footer or contact section of this website.
                    </p>
                </section>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>