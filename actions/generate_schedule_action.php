<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/schedule_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

if (!in_array((string) current_user_role(), ['super_admin', 'bus_admin'], true)) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user_role() === 'super_admin' ? 'admin/schedules.php' : 'bus_admin/generate_schedule.php');
}

function redirect_schedule_page_scope(array $scope): void
{
    if (($scope['mode'] ?? '') === 'super_admin') {
        $companyId = (int)($scope['company_id'] ?? 0);
        redirect('admin/schedules.php' . ($companyId > 0 ? '?company_id=' . $companyId : ''));
    }

    redirect('bus_admin/generate_schedule.php');
}

function validate_route_and_bus_by_company(mysqli $conn, int $companyId, int $routeId, int $busId): array
{
    $routeSql = "
        SELECT id
        FROM routes
        WHERE id = ?
          AND company_id = ?
          AND status = 'active'
        LIMIT 1
    ";
    $routeStmt = $conn->prepare($routeSql);
    if (!$routeStmt) {
        throw new Exception('Failed to validate route.');
    }

    $routeStmt->bind_param('ii', $routeId, $companyId);
    $routeStmt->execute();
    $routeResult = $routeStmt->get_result();
    $routeExists = $routeResult && $routeResult->num_rows === 1;
    $routeStmt->close();

    if (!$routeExists) {
        throw new Exception('Invalid or inactive route selected.');
    }

    $busSql = "
        SELECT id, total_seats
        FROM buses
        WHERE id = ?
          AND company_id = ?
          AND status = 'active'
        LIMIT 1
    ";
    $busStmt = $conn->prepare($busSql);
    if (!$busStmt) {
        throw new Exception('Failed to validate bus.');
    }

    $busStmt->bind_param('ii', $busId, $companyId);
    $busStmt->execute();
    $busResult = $busStmt->get_result();
    $bus = $busResult ? $busResult->fetch_assoc() : null;
    $busStmt->close();

    if (!$bus) {
        throw new Exception('Invalid or inactive bus selected.');
    }

    return $bus;
}

$actionType = trim($_POST['action_type'] ?? '');
$conn = getDBConnection();
$scope = resolve_route_schedule_company_scope($conn);

if (($scope['mode'] ?? '') === 'bus_admin') {
    require_company_permission($conn, 'manage_schedules');
}

$companyId = (int)($scope['company_id'] ?? 0);

try {
    if ($companyId <= 0) {
        throw new Exception('Please choose a company first.');
    }

    if (!in_array($actionType, ['create_template', 'create_and_generate'], true)) {
        throw new Exception('Invalid action type.');
    }

    $routeId = (int)($_POST['route_id'] ?? 0);
    $busId = (int)($_POST['bus_id'] ?? 0);
    $departureTime = trim($_POST['departure_time'] ?? '');
    $arrivalTime = trim($_POST['arrival_time'] ?? '');
    $price = trim($_POST['price'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $weekdaysInput = $_POST['weekdays'] ?? [];
    $activeFrom = trim($_POST['active_from'] ?? '');
    $activeTo = trim($_POST['active_to'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if (!is_array($weekdaysInput)) {
        $weekdaysInput = [];
    }

    $allowedFrequencies = ['daily', 'weekly', 'custom'];
    $allowedStatuses = ['active', 'inactive'];

    if ($routeId <= 0 || $busId <= 0) {
        throw new Exception('Please select both route and bus.');
    }

    if (!is_valid_time_hhmm($departureTime) || !is_valid_time_hhmm($arrivalTime)) {
        throw new Exception('Please enter valid departure and arrival times.');
    }

    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        throw new Exception('Price must be a valid number.');
    }

    if (!in_array($frequency, $allowedFrequencies, true)) {
        throw new Exception('Invalid frequency selected.');
    }

    if (!in_array($status, $allowedStatuses, true)) {
        throw new Exception('Invalid template status selected.');
    }

    if ($activeFrom === '' || $activeTo === '') {
        throw new Exception('Please select active date range.');
    }

    $fromDateObj = DateTime::createFromFormat('Y-m-d', $activeFrom);
    $toDateObj = DateTime::createFromFormat('Y-m-d', $activeTo);

    if (!$fromDateObj || $fromDateObj->format('Y-m-d') !== $activeFrom) {
        throw new Exception('Invalid Active From date.');
    }

    if (!$toDateObj || $toDateObj->format('Y-m-d') !== $activeTo) {
        throw new Exception('Invalid Active To date.');
    }

    if ($toDateObj < $fromDateObj) {
        throw new Exception('Active To date must be after or equal to Active From date.');
    }

    $weekdays = normalize_weekdays($weekdaysInput);

    if (($frequency === 'weekly' || $frequency === 'custom') && empty($weekdays)) {
        throw new Exception('Please select at least one weekday for weekly/custom frequency.');
    }

    $bus = validate_route_and_bus_by_company($conn, $companyId, $routeId, $busId);

    $weekdaysStorage = weekdays_to_storage($weekdays);
    $priceValue = (float) $price;

    $dupSql = "
        SELECT id
        FROM schedule_templates
        WHERE company_id = ?
          AND route_id = ?
          AND bus_id = ?
          AND departure_time = ?
          AND arrival_time = ?
          AND frequency = ?
          AND active_from = ?
          AND active_to = ?
        LIMIT 1
    ";
    $dupStmt = $conn->prepare($dupSql);
    if (!$dupStmt) {
        throw new Exception('Failed to validate duplicate schedule template.');
    }

    $dupStmt->bind_param(
        'iiisssss',
        $companyId,
        $routeId,
        $busId,
        $departureTime,
        $arrivalTime,
        $frequency,
        $activeFrom,
        $activeTo
    );
    $dupStmt->execute();
    $dupResult = $dupStmt->get_result();

    if ($dupResult && $dupResult->num_rows > 0) {
        $dupStmt->close();
        throw new Exception('This schedule template already exists.');
    }
    $dupStmt->close();

    $conn->begin_transaction();

    $insertSql = "
        INSERT INTO schedule_templates
        (
            company_id,
            route_id,
            bus_id,
            departure_time,
            arrival_time,
            price,
            frequency,
            weekdays,
            active_from,
            active_to,
            status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        throw new Exception('Failed to prepare schedule template insert.');
    }

    $insertStmt->bind_param(
        'iiissdsssss',
        $companyId,
        $routeId,
        $busId,
        $departureTime,
        $arrivalTime,
        $priceValue,
        $frequency,
        $weekdaysStorage,
        $activeFrom,
        $activeTo,
        $status
    );

    if (!$insertStmt->execute()) {
        $insertStmt->close();
        throw new Exception('Failed to save schedule template.');
    }

    $templateId = (int) $insertStmt->insert_id;
    $insertStmt->close();

    if ($templateId <= 0) {
        throw new Exception('Schedule template ID could not be generated.');
    }

    $userId = (int) current_user_id();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $entityType = 'schedule_template';

    $auditSql = "
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $auditAction = 'schedule_template_created';
    $auditDescription = 'Created schedule template ID: ' . $templateId;

    $auditStmt = $conn->prepare($auditSql);
    if ($auditStmt) {
        $auditStmt->bind_param('ississ', $userId, $auditAction, $entityType, $templateId, $auditDescription, $ipAddress);
        $auditStmt->execute();
        $auditStmt->close();
    }

    if ($actionType === 'create_and_generate') {
        $templateSql = "
            SELECT *
            FROM schedule_templates
            WHERE id = ?
              AND company_id = ?
            LIMIT 1
        ";
        $templateStmt = $conn->prepare($templateSql);
        if (!$templateStmt) {
            throw new Exception('Failed to reload saved template.');
        }

        $templateStmt->bind_param('ii', $templateId, $companyId);
        $templateStmt->execute();
        $templateResult = $templateStmt->get_result();
        $template = $templateResult ? $templateResult->fetch_assoc() : null;
        $templateStmt->close();

        if (!$template) {
            throw new Exception('Saved template could not be reloaded.');
        }

        $availableSeats = get_active_bus_seat_count(
            $conn,
            (int) $bus['id'],
            (int) $bus['total_seats']
        );

        $tripResult = generate_trips_from_template($conn, $template, $availableSeats);

        $generatedCount = (int)($tripResult['generated_count'] ?? 0);
        $skippedCount = (int)($tripResult['skipped_count'] ?? 0);

        $tripAuditAction = 'trips_generated';
        $tripDescription = 'Generated trips from template ID: ' . $templateId .
            ' | Generated: ' . $generatedCount .
            ' | Skipped: ' . $skippedCount;

        $tripAuditStmt = $conn->prepare($auditSql);
        if ($tripAuditStmt) {
            $tripAuditStmt->bind_param('ississ', $userId, $tripAuditAction, $entityType, $templateId, $tripDescription, $ipAddress);
            $tripAuditStmt->execute();
            $tripAuditStmt->close();
        }

        $conn->commit();
        $conn->close();

        set_flash(
            'success',
            'Template saved and trips generated. Generated: ' . $generatedCount . ', Skipped: ' . $skippedCount
        );
        redirect_schedule_page_scope($scope);
    }

    $conn->commit();
    $conn->close();

    set_flash('success', 'Schedule template created successfully.');
    redirect_schedule_page_scope($scope);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try {
            if ($conn->errno === 0 || $conn->error !== '') {
                $conn->rollback();
            }
        } catch (Throwable $rollbackError) {
        }

        $conn->close();
    }

    set_flash('error', $e->getMessage());
    redirect_schedule_page_scope($scope);
}