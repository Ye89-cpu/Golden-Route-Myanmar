<?php
$currentPartnerPage = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
$partnerNavItems = [
    ['file' => 'partners.php', 'label' => 'Overview', 'icon' => 'bi-grid-1x2'],
    ['file' => 'partner_finance.php', 'label' => 'Commission & Payments', 'icon' => 'bi-wallet2'],
    ['file' => 'partner_reports.php', 'label' => 'Reports', 'icon' => 'bi-bar-chart'],
    ['file' => 'partner_manuals.php', 'label' => 'Admin Manuals', 'icon' => 'bi-journal-check'],
    ['file' => 'partner_contact.php', 'label' => 'Apply & Contact', 'icon' => 'bi-chat-square-text'],
];
?>
<nav class="partner-subnav" aria-label="Partner portal navigation">
    <div class="container">
        <div class="partner-subnav-scroll">
            <?php foreach ($partnerNavItems as $item): ?>
                <a
                    href="<?php echo BASE_URL . e($item['file']); ?>"
                    class="partner-subnav-link <?php echo $currentPartnerPage === $item['file'] ? 'is-active' : ''; ?>"
                >
                    <i class="bi <?php echo e($item['icon']); ?>"></i>
                    <span><?php echo e($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
