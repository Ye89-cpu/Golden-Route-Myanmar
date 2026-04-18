<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/permission_helper.php';

$conn = getDBConnection();
require_company_permission($conn, 'view_ticket');

require_role('bus_admin');

$conn = getDBConnection();
$company = require_bus_admin_company($conn);

$page_title = 'Ticket Scan / Validation';

$allowedCompanyRoles = ['admin', 'staff', 'checker'];
if (!in_array($company['role_in_company'], $allowedCompanyRoles, true)) {
    $conn->close();
    set_flash('error', 'You are not allowed to validate tickets for this company.');
    redirect('bus_admin/dashboard.php');
}

function ticket_status_badge_class(string $status): string
{
    switch ($status) {
        case 'used':
            return 'secondary';
        case 'cancelled':
            return 'danger';
        case 'valid':
        default:
            return 'success';
    }
}

function render_scan_state(string $state): array
{
    switch ($state) {
        case 'valid':
            return ['class' => 'success', 'title' => 'Valid Ticket'];
        case 'used':
            return ['class' => 'warning text-dark', 'title' => 'Already Used'];
        case 'invalid':
        default:
            return ['class' => 'danger', 'title' => 'Invalid Ticket'];
    }
}

function find_ticket_for_company_by_input(mysqli $conn, int $companyId, string $input): ?array
{
    $sql = "
        SELECT
            tk.id AS ticket_id,
            tk.booking_id,
            tk.trip_id,
            tk.ticket_no,
            tk.qr_token,
            tk.qr_image,
            tk.pdf_file,
            tk.status AS ticket_status,
            tk.used_at,
            tk.created_at AS ticket_created_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.user_id,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.company_id,

            c.name AS company_name,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM tickets tk
        INNER JOIN bookings b ON b.id = tk.booking_id
        INNER JOIN trips t ON t.id = tk.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.company_id = ?
          AND (tk.ticket_no = ? OR tk.qr_token = ?)
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $companyId, $input, $input);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $ticket;
}

function find_ticket_for_company_by_id(mysqli $conn, int $companyId, int $ticketId, bool $forUpdate = false): ?array
{
    $sql = "
        SELECT
            tk.id AS ticket_id,
            tk.booking_id,
            tk.trip_id,
            tk.ticket_no,
            tk.qr_token,
            tk.qr_image,
            tk.pdf_file,
            tk.status AS ticket_status,
            tk.used_at,
            tk.created_at AS ticket_created_at,

            b.booking_code,
            b.status AS booking_status,
            b.payment_status,
            b.user_id,

            t.trip_date,
            t.departure_datetime,
            t.arrival_datetime,
            t.company_id,

            c.name AS company_name,
            bus.bus_number,
            fc.name AS from_city_name,
            tc.name AS to_city_name
        FROM tickets tk
        INNER JOIN bookings b ON b.id = tk.booking_id
        INNER JOIN trips t ON t.id = tk.trip_id
        INNER JOIN companies c ON c.id = t.company_id
        INNER JOIN buses bus ON bus.id = t.bus_id
        INNER JOIN routes r ON r.id = t.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE t.company_id = ?
          AND tk.id = ?
        LIMIT 1
    ";

    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $companyId, $ticketId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $ticket;
}

$scanInput = '';
$resultState = null;   // valid / used / invalid
$resultMessage = null;
$ticket = null;
$allowMarkUsed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actionType = trim($_POST['action_type'] ?? '');

    if ($actionType === 'lookup') {
        $scanInput = trim($_POST['scan_input'] ?? '');

        if ($scanInput === '') {
            $resultState = 'invalid';
            $resultMessage = 'Please enter a ticket number or QR token.';
        } else {
            $ticket = find_ticket_for_company_by_input($conn, (int)$company['company_id'], $scanInput);

            if (!$ticket) {
                $resultState = 'invalid';
                $resultMessage = 'Invalid ticket. No matching ticket was found for your company.';
            } elseif ($ticket['ticket_status'] === 'cancelled') {
                $resultState = 'invalid';
                $resultMessage = 'Invalid ticket. This ticket has been cancelled.';
            } elseif ($ticket['booking_status'] !== 'paid' || $ticket['payment_status'] !== 'paid') {
                $resultState = 'invalid';
                $resultMessage = 'Invalid ticket. This booking is not fully paid/verified.';
            } elseif ($ticket['ticket_status'] === 'used' || !empty($ticket['used_at'])) {
                $resultState = 'used';
                $resultMessage = 'This ticket has already been used.';
            } else {
                $resultState = 'valid';
                $resultMessage = 'Ticket is valid and ready for boarding.';
                $allowMarkUsed = true;
            }
        }
    }

    if ($actionType === 'mark_used') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);

        if ($ticketId <= 0) {
            $resultState = 'invalid';
            $resultMessage = 'Invalid ticket ID.';
        } else {
            try {
                $conn->begin_transaction();

                $ticket = find_ticket_for_company_by_id(
                    $conn,
                    (int)$company['company_id'],
                    $ticketId,
                    true
                );

                if (!$ticket) {
                    throw new Exception('Invalid ticket. No matching ticket was found for your company.');
                }

                if ($ticket['ticket_status'] === 'cancelled') {
                    throw new Exception('This ticket has been cancelled.');
                }

                if ($ticket['booking_status'] !== 'paid' || $ticket['payment_status'] !== 'paid') {
                    throw new Exception('This ticket is not eligible for boarding because payment is not verified.');
                }

                if ($ticket['ticket_status'] === 'used' || !empty($ticket['used_at'])) {
                    $resultState = 'used';
                    $resultMessage = 'This ticket has already been used.';
                    $conn->commit();
                } else {
                    $updateSql = "
                        UPDATE tickets
                        SET status = 'used',
                            used_at = NOW()
                        WHERE id = ?
                    ";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param('i', $ticketId);

                    if (!$updateStmt->execute()) {
                        $updateStmt->close();
                        throw new Exception('Failed to mark ticket as used.');
                    }
                    $updateStmt->close();

                    $action = 'ticket_used';
                    $entityType = 'ticket';
                    $description = 'Marked ticket as used: ' . $ticket['ticket_no'];
                    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
                    $userId = current_user_id();

                    $auditSql = "
                        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ";
                    $auditStmt = $conn->prepare($auditSql);
                    $auditStmt->bind_param('ississ', $userId, $action, $entityType, $ticketId, $description, $ipAddress);
                    $auditStmt->execute();
                    $auditStmt->close();

                    $conn->commit();

                    $ticket = find_ticket_for_company_by_id(
                        $conn,
                        (int)$company['company_id'],
                        $ticketId,
                        false
                    );

                    $resultState = 'valid';
                    $resultMessage = 'Ticket validated successfully. Boarding confirmed and marked as used.';
                    $allowMarkUsed = false;
                }
            } catch (Exception $e) {
                $conn->rollback();
                $resultState = 'invalid';
                $resultMessage = $e->getMessage();
            }
        }
    }
}

$conn->close();
require_once __DIR__ . '/../includes/header.php';

$stateMeta = render_scan_state($resultState ?? 'invalid');
?>

<div class="container py-5">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Ticket Scan / Validation</h2>
            <p class="text-muted mb-0">
                Company: <?php echo e($company['company_name']); ?>
            </p>
        </div>

        <div class="mt-3 mt-lg-0">
            <a href="<?php echo BASE_URL; ?>bus_admin/dashboard.php" class="btn btn-outline-secondary">
                Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-3">Scan Ticket</h5>

        <form method="POST" action="<?php echo BASE_URL; ?>bus_admin/scan_ticket.php" data-scan-form="1">
            <input type="hidden" name="action_type" value="lookup">

            <div id="scannerStatus" class="alert alert-secondary rounded-4 py-2 mb-3">
                Scanner ready.
            </div>

            <div class="mb-3">
                <label class="form-label">QR Token or Ticket Number</label>
                <input
                    type="text"
                    name="scan_input"
                    class="form-control"
                    value="<?php echo e($scanInput); ?>"
                    placeholder="Enter qr_token or ticket_no"
                    required
                >
            </div>

            <div class="mb-3">
                <label class="form-label">Camera</label>
                <select id="cameraSelect" class="form-select"></select>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" id="startScannerBtn" class="btn btn-primary">
                    Start Webcam Scanner
                </button>
                <button type="button" id="stopScannerBtn" class="btn btn-outline-secondary">
                    Stop Scanner
                </button>
            </div>

            <div class="mb-3">
                <label class="form-label">Or scan from image</label>
                <input
                    type="file"
                    id="scanImageFile"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.webp"
                >
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="autoSubmitScan" checked>
                <label class="form-check-label" for="autoSubmitScan">
                    Auto-submit after successful scan
                </label>
            </div>

            <div id="qr-reader" class="border rounded-4 p-2 mb-3 bg-white" style="min-height: 280px;"></div>

            <button type="submit" class="btn btn-success w-100">
                Check Ticket Manually
            </button>
        </form>
    </div>
</div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">Validation Result</h5>

                    <?php if ($resultState === null): ?>
                        <div class="alert alert-light border rounded-4 mb-0">
                            Enter a ticket number or QR token to validate a boarding ticket.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-<?php echo e($stateMeta['class']); ?> rounded-4">
                            <div class="fw-bold mb-1"><?php echo e($stateMeta['title']); ?></div>
                            <div><?php echo e($resultMessage); ?></div>
                        </div>

                        <?php if ($ticket): ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div><strong>Ticket Number:</strong> <?php echo e($ticket['ticket_no']); ?></div>
                                    <div><strong>Booking Code:</strong> <?php echo e($ticket['booking_code']); ?></div>
                                    <div><strong>Company:</strong> <?php echo e($ticket['company_name']); ?></div>
                                    <div>
                                        <strong>Route:</strong>
                                        <?php echo e($ticket['from_city_name']); ?> → <?php echo e($ticket['to_city_name']); ?>
                                    </div>
                                    <div><strong>Bus:</strong> <?php echo e($ticket['bus_number']); ?></div>
                                </div>

                                <div class="col-md-6">
                                    <div><strong>Trip Date:</strong> <?php echo e($ticket['trip_date']); ?></div>
                                    <div><strong>Departure:</strong> <?php echo e(date('Y-m-d H:i', strtotime($ticket['departure_datetime']))); ?></div>
                                    <div><strong>Arrival:</strong> <?php echo e(date('Y-m-d H:i', strtotime($ticket['arrival_datetime']))); ?></div>
                                    <div>
                                        <strong>Ticket Status:</strong>
                                        <span class="badge bg-<?php echo e(ticket_status_badge_class($ticket['ticket_status'])); ?>">
                                            <?php echo e(ucfirst($ticket['ticket_status'])); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <strong>Used At:</strong>
                                        <?php echo e($ticket['used_at'] ? date('Y-m-d H:i:s', strtotime($ticket['used_at'])) : '-'); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($allowMarkUsed): ?>
                                <hr>
                                <form method="POST" action="<?php echo BASE_URL; ?>bus_admin/scan_ticket.php">
                                    <input type="hidden" name="action_type" value="mark_used">
                                    <input type="hidden" name="ticket_id" value="<?php echo e($ticket['ticket_id']); ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-success"
                                        onclick="return confirm('Confirm boarding and mark this ticket as used?');"
                                    >
                                        Mark as Used
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>assets/js/html5-qrcode.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/ticket-scanner.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>