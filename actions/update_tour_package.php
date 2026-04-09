<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/tour_company_helper.php';

require_role('tour_admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('tour_admin/packages.php');
}

$conn = getDBConnection();
$company = require_tour_admin_company($conn);

$packageId = (int)($_POST['package_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = trim($_POST['price'] ?? '');
$durationDays = (int)($_POST['duration_days'] ?? 0);
$hotelInfo = trim($_POST['hotel_info'] ?? '');
$transportInfo = trim($_POST['transport_info'] ?? '');
$itinerary = trim($_POST['itinerary'] ?? '');
$includedServices = trim($_POST['included_services'] ?? '');
$excludedServices = trim($_POST['excluded_services'] ?? '');
$status = trim($_POST['status'] ?? '');
$existingCoverImage = trim($_POST['existing_cover_image'] ?? '');

try {
    if ($packageId <= 0) {
        throw new Exception('Invalid package ID.');
    }

    $ownerSql = "SELECT id FROM tour_packages WHERE id = ? AND company_id = ? LIMIT 1";
    $ownerStmt = $conn->prepare($ownerSql);
    $ownerStmt->bind_param('ii', $packageId, $company['company_id']);
    $ownerStmt->execute();
    $ownerResult = $ownerStmt->get_result();

    if ($ownerResult->num_rows !== 1) {
        $ownerStmt->close();
        throw new Exception('You are not allowed to update this package.');
    }
    $ownerStmt->close();

    if ($title === '' || $description === '') {
        throw new Exception('Title and description are required.');
    }

    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        throw new Exception('Price must be a valid number.');
    }

    if ($durationDays <= 0) {
        throw new Exception('Duration must be greater than 0.');
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new Exception('Invalid status.');
    }

    $coverImagePath = $existingCoverImage ?: null;

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['cover_image'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Failed to upload cover image.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimeMap[$mimeType])) {
            throw new Exception('Only JPG, PNG, and WEBP images are allowed.');
        }

        $uploadDir = __DIR__ . '/../uploads/tour_packages/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = 'tour_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeMap[$mimeType];
        $dest = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Failed to save cover image.');
        }

        $coverImagePath = 'uploads/tour_packages/' . $fileName;
    }

    $priceValue = (float)$price;

    $sql = "
        UPDATE tour_packages
        SET
            title = ?,
            description = ?,
            price = ?,
            duration_days = ?,
            hotel_info = ?,
            transport_info = ?,
            itinerary = ?,
            included_services = ?,
            excluded_services = ?,
            cover_image = ?,
            status = ?
        WHERE id = ? AND company_id = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssdisssssssii',
        $title,
        $description,
        $priceValue,
        $durationDays,
        $hotelInfo,
        $transportInfo,
        $itinerary,
        $includedServices,
        $excludedServices,
        $coverImagePath,
        $status,
        $packageId,
        $company['company_id']
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to update tour package.');
    }
    $stmt->close();

    $conn->close();
    set_flash('success', 'Tour package updated successfully.');
    redirect('tour_admin/packages.php');
} catch (Exception $e) {
    $conn->close();
    set_flash('error', $e->getMessage());
    redirect('tour_admin/packages.php?edit=' . $packageId);
}