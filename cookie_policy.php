<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Cookie Policy - Golden Route Myanmar';

require_once __DIR__ . '/includes/header.php';
?>

<section class="policy-hero-section">
    <div class="container">
        <div class="policy-hero-card">
            <span class="section-kicker">Legal & Trust</span>
            <h1 class="policy-page-title">Cookie Policy</h1>
            <p class="policy-page-subtitle">
                This Cookie Policy explains what cookies are, why Golden Route Myanmar uses them,
                and what types of information they may store while you use the website.
            </p>
            <div class="policy-meta-row">
                <span>Effective Date: <?php echo date('Y-m-d'); ?></span>
                <span>Focus: Essential website operation, login sessions, and user convenience</span>
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
                    <a href="#what">1. What Are Cookies?</a>
                    <a href="#why">2. Why We Use Cookies</a>
                    <a href="#types">3. Types of Cookies We Use</a>
                    <a href="#store">4. What Cookies May Store</a>
                    <a href="#thirdparty">5. Third-Party Cookies</a>
                    <a href="#manage">6. How to Control Cookies</a>
                    <a href="#updates">7. Updates to This Policy</a>
                </div>
            </aside>

            <div class="policy-main-card">
                <section id="what" class="policy-block">
                    <h2>1. What Are Cookies?</h2>
                    <p>
                        Cookies are small text files placed on your device by a website.
                        They help the site remember certain information about your visit,
                        such as whether you are logged in, which actions you have completed,
                        or what settings should remain active during your browsing session.
                    </p>
                </section>

                <section id="why" class="policy-block">
                    <h2>2. Why We Use Cookies</h2>
                    <ul class="policy-list">
                        <li>To keep users logged in securely during a session</li>
                        <li>To support booking flows and page-to-page functionality</li>
                        <li>To maintain temporary website preferences or session state</li>
                        <li>To improve security and help detect suspicious activity</li>
                        <li>To provide a smoother and more reliable browsing experience</li>
                    </ul>
                </section>

                <section id="types" class="policy-block">
                    <h2>3. Types of Cookies We Use</h2>

                    <div class="policy-info-grid">
                        <div class="policy-info-box">
                            <h4>Essential Cookies</h4>
                            <p>These are necessary for the website to function, including login sessions, navigation continuity, and protected user actions.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Session Cookies</h4>
                            <p>These are temporary cookies that help the website remember you while you move between pages during the same visit.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Preference Cookies</h4>
                            <p>These may remember limited user preferences if such settings are offered on the platform in the future.</p>
                        </div>
                        <div class="policy-info-box">
                            <h4>Security Cookies</h4>
                            <p>These may help protect the website, reduce abuse, and support secure authenticated access.</p>
                        </div>
                    </div>
                </section>

                <section id="store" class="policy-block">
                    <h2>4. What Cookies May Store</h2>
                    <p>Depending on your activity, cookies used by this website may store or help remember information such as:</p>

                    <ul class="policy-list">
                        <li>A temporary session identifier</li>
                        <li>Your login state after authentication</li>
                        <li>Security-related session values</li>
                        <li>Short-term booking or navigation continuity while using the site</li>
                        <li>Temporary convenience settings needed for smoother user experience</li>
                    </ul>

                    <p>
                        Cookies used for essential platform operation do not normally store full payment information.
                        Sensitive information should be managed through secured server-side processing rather than simple browser cookies.
                    </p>
                </section>

                <section id="thirdparty" class="policy-block">
                    <h2>5. Third-Party Cookies</h2>
                    <p>
                        At present, Golden Route Myanmar mainly relies on essential platform functionality.
                        If third-party tools, analytics, embedded maps, or marketing services are added later,
                        this policy should be updated to reflect those changes more specifically.
                    </p>
                </section>

                <section id="manage" class="policy-block">
                    <h2>6. How to Control Cookies</h2>
                    <p>
                        Most browsers allow you to review, block, or delete cookies through browser settings.
                        However, disabling essential cookies may prevent important features from working correctly,
                        including login, booking steps, or session-based pages.
                    </p>
                </section>

                <section id="updates" class="policy-block mb-0">
                    <h2>7. Updates to This Policy</h2>
                    <p>
                        We may revise this Cookie Policy when the website changes, when new tools are added,
                        or when legal and privacy requirements evolve.
                    </p>
                    <p>
                        Please also review our <a href="<?php echo BASE_URL; ?>privacy_policy.php">Privacy Policy</a>
                        for broader information about how user data is handled on this platform.
                    </p>
                </section>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>