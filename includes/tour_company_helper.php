<?php
/**
 * Tour company helper compatibility wrapper.
 *
 * The shared company helper already defines:
 * - get_tour_admin_company()
 * - require_tour_admin_company()
 *
 * Keeping duplicate function definitions here causes PHP fatal errors when
 * files include both company_helper.php and tour_company_helper.php.
 */
require_once __DIR__ . '/company_helper.php';
