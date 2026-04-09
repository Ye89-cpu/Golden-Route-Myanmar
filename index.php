<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

$page_title = 'Home - Myanmar Bus & Tour Booking';

$conn = getDBConnection();

$stats = [
    'open_trips' => 0,
    'active_tours' => 0,
    'approved_companies' => 0,
    'tickets_issued' => 0,
];

try {
    $queries = [
        'open_trips' => "SELECT COUNT(*) AS total FROM trips WHERE status = 'open' AND trip_date >= CURDATE()",
        'active_tours' => "SELECT COUNT(*) AS total FROM tour_packages WHERE status = 'active'",
        'approved_companies' => "SELECT COUNT(*) AS total FROM companies WHERE status = 'approved'",
        'tickets_issued' => "SELECT COUNT(*) AS total FROM tickets",
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $stats[$key] = (int)($row['total'] ?? 0);
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // Keep page alive with fallback stats.
}

$conn->close();

require_once __DIR__ . '/includes/header.php';
?>

<section class="hero-section">
    <div class="container">
        <div class="hero-card">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="section-kicker">Fast • Secure • Organized</span>
                    <h1 class="hero-title">Book bus tickets and tour packages across Myanmar</h1>
                    <p class="hero-text">
                        Search routes, choose seats, submit payment proof, receive QR tickets,
                        and manage bookings in one clean platform designed for customers and admins.
                    </p>

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand btn-lg">Search Bus</a>
                        <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft btn-lg">Explore Tours</a>
                    </div>

                    <div class="home-highlight-grid mt-4">
                        <div class="feature-chip">QR ticket validation</div>
                        <div class="feature-chip">PDF voucher download</div>
                        <div class="feature-chip">Payment review workflow</div>
                        <div class="feature-chip">Role-based admin access</div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="hero-side-panel">
                        <div class="route-showcase">
                            <div class="route-stop">
                                <small>Popular route</small>
                                <strong>Yangon</strong>
                            </div>
                            <div class="route-divider">→</div>
                            <div class="route-stop text-end">
                                <small>Destination</small>
                                <strong>Mandalay</strong>
                            </div>
                        </div>

                        <div class="route-meta-grid mt-4">
                            <div class="route-meta-card">
                                <span>Trips Open</span>
                                <strong><?php echo number_format($stats['open_trips']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Tour Packages</span>
                                <strong><?php echo number_format($stats['active_tours']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Approved Companies</span>
                                <strong><?php echo number_format($stats['approved_companies']); ?></strong>
                            </div>
                            <div class="route-meta-card">
                                <span>Tickets Issued</span>
                                <strong><?php echo number_format($stats['tickets_issued']); ?></strong>
                            </div>
                        </div>

                        <div class="hero-note mt-4">
                            Designed for customers, bus companies, tour operators and super admin control.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="section-heading text-center mb-5">
            <span class="section-kicker">Core Features</span>
            <h2 class="page-title mb-2">Everything needed for booking and operations</h2>
            <p class="page-subtitle mx-auto">A smoother experience from search to payment verification and QR check-in.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">🚌</div>
                    <h5>Bus Ticket Search</h5>
                    <p>Choose route, date, seat layout, and complete passenger information with clarity.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">💳</div>
                    <h5>Payment Submission</h5>
                    <p>Upload payment proof and track verification status from your booking history.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">🎫</div>
                    <h5>QR / PDF Output</h5>
                    <p>Generate tickets and vouchers after approval for easier boarding and check-in.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="info-card h-100">
                    <div class="info-card-icon">📊</div>
                    <h5>Admin Dashboard</h5>
                    <p>Monitor approvals, payments, notifications, refunds, and system activity in one place.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="pb-5">
    <div class="container">
        <div class="cta-banner">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <span class="section-kicker">Ready to start?</span>
                    <h3 class="mb-2">Search available trips or browse curated tour packages</h3>
                    <p class="mb-0 text-muted">Use the platform as a customer, or manage the system with admin dashboards.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo BASE_URL; ?>search_bus.php" class="btn btn-brand me-2">Search Bus</a>
                    <a href="<?php echo BASE_URL; ?>tours.php" class="btn btn-nav-soft">Tours</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>