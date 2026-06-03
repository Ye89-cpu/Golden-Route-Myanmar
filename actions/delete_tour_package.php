<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';

require_role(['tour_admin', 'super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user_role() === 'super_admin' ? 'admin/business_reports.php' : 'tour_admin/packages.php');
}

$conn = getDBConnection();

$packageId = (int)($_POST['package_id'] ?? 0);
$returnUrl = trim((string)($_POST['return_url'] ?? ''));

try {
    if ($packageId <= 0) {
        throw new Exception('Invalid package selected.');
    }

    $companyId = 0;

    if (current_user_role() === 'tour_admin') {
        $company = require_tour_admin_company($conn);
        $companyId = (int)$company['company_id'];
    }

    if ($companyId > 0) {
        $packageSql = "SELECT id, company_id, title FROM tour_packages WHERE id = ? AND company_id = ? LIMIT 1";
        $packageStmt = $conn->prepare($packageSql);
        $packageStmt->bind_param('ii', $packageId, $companyId);
    } else {
        $packageSql = "SELECT id, company_id, title FROM tour_packages WHERE id = ? LIMIT 1";
        $packageStmt = $conn->prepare($packageSql);
        $packageStmt->bind_param('i', $packageId);
    }

    $packageStmt->execute();
    $package = $packageStmt->get_result()->fetch_assoc();
    $packageStmt->close();

    if (!$package) {
        throw new Exception('Tour package not found or not allowed.');
    }

    $usageSql = "
        SELECT COUNT(*) AS booking_count
        FROM bookings b
        INNER JOIN tour_batches tb ON tb.id = b.tour_batch_id
        WHERE tb.tour_package_id = ?
    ";
    $usageStmt = $conn->prepare($usageSql);
    $usageStmt->bind_param('i', $packageId);
    $usageStmt->execute();
    $usage = $usageStmt->get_result()->fetch_assoc();
    $usageStmt->close();

    if ((int)($usage['booking_count'] ?? 0) > 0) {
        throw new Exception('This tour package already has bookings. Set it inactive instead of deleting.');
    }

    $conn->begin_transaction();

    $deleteBatchesSql = "DELETE FROM tour_batches WHERE tour_package_id = ?";
    $deleteBatchesStmt = $conn->prepare($deleteBatchesSql);
    $deleteBatchesStmt->bind_param('i', $packageId);
    $deleteBatchesStmt->execute();
    $deleteBatchesStmt->close();

    $deletePackageSql = "DELETE FROM tour_packages WHERE id = ?";
    $deletePackageStmt = $conn->prepare($deletePackageSql);
    $deletePackageStmt->bind_param('i', $packageId);
    $deletePackageStmt->execute();
    $deletePackageStmt->close();

    $conn->commit();
    $conn->close();

    set_flash('success', 'Tour package and its empty batches deleted successfully.');

    if ($returnUrl !== '' && str_starts_with($returnUrl, BASE_URL)) {
        header('Location: ' . $returnUrl);
        exit;
    }

    redirect(current_user_role() === 'super_admin' ? 'admin/business_reports.php' : 'tour_admin/packages.php');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    $conn->close();
    set_flash('error', $e->getMessage());
    redirect(current_user_role() === 'super_admin' ? 'admin/business_reports.php' : 'tour_admin/packages.php');
}
