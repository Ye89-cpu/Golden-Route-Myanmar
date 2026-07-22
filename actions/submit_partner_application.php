<?php
require_once __DIR__ . '/../includes/partner_program_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('partner_contact.php');
}

$input = [
    'company_name' => trim((string)($_POST['company_name'] ?? '')),
    'company_type' => trim((string)($_POST['company_type'] ?? 'bus_company')),
    'license_no' => trim((string)($_POST['license_no'] ?? '')),
    'contact_name' => trim((string)($_POST['contact_name'] ?? '')),
    'phone' => trim((string)($_POST['phone'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'preferred_contact' => trim((string)($_POST['preferred_contact'] ?? 'phone')),
    'business_address' => trim((string)($_POST['business_address'] ?? '')),
    'website' => trim((string)($_POST['website'] ?? '')),
    'current_routes' => trim((string)($_POST['current_routes'] ?? '')),
    'monthly_booking_estimate' => trim((string)($_POST['monthly_booking_estimate'] ?? '')),
    'message' => trim((string)($_POST['message'] ?? '')),
];

save_old_input($input);

$csrfToken = trim((string)($_POST['csrf_token'] ?? ''));
if (!partner_verify_csrf($csrfToken)) {
    set_flash('error', 'Your form session expired. Please review the information and submit again.');
    redirect('partner_contact.php#partner-application');
}

$allowedTypes = ['bus_company', 'tour_operator', 'both'];
$allowedContacts = ['phone', 'email', 'viber', 'telegram'];

if ($input['company_name'] === '' || $input['contact_name'] === '' || $input['phone'] === '' || $input['email'] === '') {
    set_flash('error', 'Company name, contact name, phone number, and email address are required.');
    redirect('partner_contact.php#partner-application');
}

if (!in_array($input['company_type'], $allowedTypes, true)) {
    set_flash('error', 'Please choose a valid company type.');
    redirect('partner_contact.php#partner-application');
}

if (!in_array($input['preferred_contact'], $allowedContacts, true)) {
    set_flash('error', 'Please choose a valid contact method.');
    redirect('partner_contact.php#partner-application');
}

if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please enter a valid email address.');
    redirect('partner_contact.php#partner-application');
}

if ($input['website'] !== '' && !filter_var($input['website'], FILTER_VALIDATE_URL)) {
    set_flash('error', 'Please enter a valid website URL, including https://');
    redirect('partner_contact.php#partner-application');
}

$monthlyEstimate = $input['monthly_booking_estimate'] === '' ? null : max(0, min(1000000, (int)$input['monthly_booking_estimate']));
$conn = getDBConnection();

try {
    partner_ensure_application_table($conn);

    $applicationCode = partner_application_code();
    $sql = "
        INSERT INTO partner_applications
        (
            application_code,
            company_name,
            company_type,
            license_no,
            contact_name,
            phone,
            email,
            preferred_contact,
            business_address,
            website,
            current_routes,
            monthly_booking_estimate,
            message,
            status,
            created_at,
            updated_at
        )
        VALUES
        (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, ''), 'new', NOW(), NOW())
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the application.');
    }

    $stmt->bind_param(
        'sssssssssssis',
        $applicationCode,
        $input['company_name'],
        $input['company_type'],
        $input['license_no'],
        $input['contact_name'],
        $input['phone'],
        $input['email'],
        $input['preferred_contact'],
        $input['business_address'],
        $input['website'],
        $input['current_routes'],
        $monthlyEstimate,
        $input['message']
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to save the application.');
    }

    $stmt->close();
    $conn->close();

    unset($_SESSION['partner_csrf_token']);
    clear_old_input();
    set_flash('success', 'Application submitted successfully. Your reference number is ' . $applicationCode . '. Please keep it for follow-up.');
    redirect('partner_contact.php');
} catch (Throwable $e) {
    $conn->close();
    set_flash('error', 'The application could not be submitted. Please try again or contact the partner team.');
    redirect('partner_contact.php#partner-application');
}
