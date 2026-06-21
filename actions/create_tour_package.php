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

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = trim($_POST['price'] ?? '');
$durationDays = (int)($_POST['duration_days'] ?? 0);
$hotelInfo = trim($_POST['hotel_info'] ?? '');
$transportInfo = trim($_POST['transport_info'] ?? '');
$itinerary = trim($_POST['itinerary'] ?? '');
$includedServices = trim($_POST['included_services'] ?? '');
$excludedServices = trim($_POST['excluded_services'] ?? '');
$status = trim($_POST['status'] ?? 'active');
$defaultBatchStartDate = trim($_POST['default_batch_start_date'] ?? '');
$defaultBatchCapacity = (int)($_POST['default_batch_capacity'] ?? 20);

save_old_input($_POST);

function create_tour_package_upload_cover_image(): ?string
{
    if (!isset($_FILES['cover_image']) || (int)($_FILES['cover_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['cover_image'];

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Failed to upload cover image.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = (string)$finfo->file($file['tmp_name']);

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeMap[$mimeType])) {
        throw new Exception('Only JPG, PNG, and WEBP images are allowed.');
    }

    $uploadDir = __DIR__ . '/../uploads/tour_packages/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new Exception('Failed to create tour image upload folder.');
    }

    $fileName = 'tour_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowedMimeMap[$mimeType];
    $dest = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Failed to save cover image.');
    }

    return 'uploads/tour_packages/' . $fileName;
}

try {
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

    if ($defaultBatchCapacity <= 0) {
        $defaultBatchCapacity = 20;
    }

    if ($defaultBatchStartDate !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $defaultBatchStartDate);
        if (!$dt || $dt->format('Y-m-d') !== $defaultBatchStartDate) {
            throw new Exception('Default batch start date must be YYYY-MM-DD.');
        }
    } else {
        $defaultBatchStartDate = date('Y-m-d', strtotime('+1 day'));
    }

    $coverImagePath = create_tour_package_upload_cover_image();
    $priceValue = (float)$price;

    $conn->begin_transaction();

    $sql = "
        INSERT INTO tour_packages
        (
            company_id,
            title,
            description,
            price,
            duration_days,
            hotel_info,
            transport_info,
            itinerary,
            included_services,
            excluded_services,
            cover_image,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare package insert.');
    }

    $stmt->bind_param(
        'issdisssssss',
        $company['company_id'],
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
        $status
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Failed to create tour package.');
    }

    $packageId = (int)$stmt->insert_id;
    $stmt->close();

    /*
     * Important workflow fix:
     * One active package must be bookable immediately by customers.
     * Therefore the system auto-creates one open batch for the new package.
     */
    if ($status === 'active') {
        $endDate = date('Y-m-d', strtotime($defaultBatchStartDate . ' +' . max($durationDays - 1, 0) . ' day'));
        $batchStatus = 'open';
        $bookedCount = 0;

        $batchSql = "
            INSERT INTO tour_batches
            (
                company_id,
                tour_package_id,
                start_date,
                end_date,
                capacity,
                booked_count,
                price,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $batchStmt = $conn->prepare($batchSql);
        if (!$batchStmt) {
            throw new Exception('Failed to prepare default batch insert.');
        }

        $batchStmt->bind_param(
            'iissiids',
            $company['company_id'],
            $packageId,
            $defaultBatchStartDate,
            $endDate,
            $defaultBatchCapacity,
            $bookedCount,
            $priceValue,
            $batchStatus
        );

        if (!$batchStmt->execute()) {
            $batchStmt->close();
            throw new Exception('Package was created but default batch could not be created.');
        }
        $batchStmt->close();
    }

    $conn->commit();
    clear_old_input();
    $conn->close();

    set_flash('success', 'Tour package created successfully. An open default batch was also created, so customers can book now.');
    redirect('tour_admin/packages.php');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
    }

    try {
        $conn->close();
    } catch (Throwable $closeError) {
    }

    set_flash('error', $e->getMessage());
    redirect('tour_admin/packages.php');
}
