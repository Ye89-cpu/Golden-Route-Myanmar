<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/company_helper.php';
require_once __DIR__ . '/../includes/schedule_helper.php';

require_role('super_admin');

$conn = getDBConnection();
$scope = resolve_route_schedule_company_scope($conn);
$company = $scope['company'];
$companies = $scope['companies'];
$companyId = (int)($scope['company_id'] ?? 0);
$page_title = 'Admin Schedule Management';
$weekdayOptions = get_weekday_options();

$routes = [];
$buses = [];
$templates = [];
$scheduleSummary = [
    'active_routes' => 0,
    'active_buses' => 0,
    'templates' => 0,
    'generated_trips' => 0,
    'future_open_trips' => 0,
];

if ($companyId > 0) {
    $routeSql = "
        SELECT r.id, fc.name AS from_city_name, tc.name AS to_city_name, r.base_price
        FROM routes r
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        WHERE r.company_id = ? AND r.status = 'active'
        ORDER BY fc.name ASC, tc.name ASC
    ";
    $stmt = $conn->prepare($routeSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $routes[] = $row;
    }
    $stmt->close();

    $busSql = "
        SELECT id, bus_number, bus_type, total_seats
        FROM buses
        WHERE company_id = ? AND status = 'active'
        ORDER BY bus_number ASC
    ";
    $stmt = $conn->prepare($busSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
    }
    $stmt->close();

    $tplSql = "
        SELECT
            st.*,
            fc.name AS from_city_name,
            tc.name AS to_city_name,
            b.bus_number,
            b.bus_type,
            b.total_seats,
            COUNT(t.id) AS generated_trip_count,
            MIN(CASE WHEN t.departure_datetime >= NOW() AND t.status IN ('scheduled','open') THEN t.departure_datetime END) AS next_trip_datetime,
            COALESCE(SUM(t.status = 'open' AND t.departure_datetime >= NOW()), 0) AS future_open_count
        FROM schedule_templates st
        INNER JOIN routes r ON r.id = st.route_id
        INNER JOIN cities fc ON fc.id = r.from_city_id
        INNER JOIN cities tc ON tc.id = r.to_city_id
        INNER JOIN buses b ON b.id = st.bus_id
        LEFT JOIN trips t ON t.schedule_template_id = st.id
        WHERE st.company_id = ?
        GROUP BY st.id, fc.name, tc.name, b.bus_number, b.bus_type, b.total_seats
        ORDER BY st.id DESC
    ";
    $stmt = $conn->prepare($tplSql);
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $templates[] = $row;
    }
    $stmt->close();

    $summarySql = "
        SELECT
            (SELECT COUNT(*) FROM routes WHERE company_id = ? AND status = 'active') AS active_routes,
            (SELECT COUNT(*) FROM buses WHERE company_id = ? AND status = 'active') AS active_buses,
            (SELECT COUNT(*) FROM schedule_templates WHERE company_id = ?) AS templates,
            (SELECT COUNT(*) FROM trips WHERE company_id = ?) AS generated_trips,
            (SELECT COUNT(*) FROM trips WHERE company_id = ? AND status = 'open' AND departure_datetime >= NOW()) AS future_open_trips
    ";
    $stmt = $conn->prepare($summarySql);
    $stmt->bind_param('iiiii', $companyId, $companyId, $companyId, $companyId, $companyId);
    $stmt->execute();
    $summaryResult = $stmt->get_result();
    $scheduleSummary = array_merge($scheduleSummary, $summaryResult ? ($summaryResult->fetch_assoc() ?: []) : []);
    $stmt->close();
}

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<style>
.schedule-page { --schedule-ink:#172033; --schedule-muted:#6d7688; --schedule-brand:#f1ad00; }
.schedule-hero { background:linear-gradient(135deg,#172033,#334561); color:#fff; padding:34px; border-radius:28px; position:relative; overflow:hidden; }
.schedule-hero::after { content:""; width:250px; height:250px; border-radius:50%; background:rgba(255,193,7,.13); position:absolute; right:-80px; top:-120px; }
.schedule-shell { background:#fff; border:1px solid #e9edf3; border-radius:24px; box-shadow:0 14px 38px rgba(23,32,51,.065); }
.schedule-company-select { background:linear-gradient(180deg,#fff,#fbfcfe); }
.schedule-page .form-control,.schedule-page .form-select { min-height:48px; border-radius:13px; border-color:#dfe4ec; }
.schedule-page .form-control:focus,.schedule-page .form-select:focus { border-color:#e2a000; box-shadow:0 0 0 .2rem rgba(226,160,0,.13); }
.schedule-page .btn { border-radius:12px; font-weight:750; }
.schedule-stat { padding:18px; border:1px solid #e9edf3; border-radius:19px; background:#fff; height:100%; }
.schedule-stat-icon { width:42px; height:42px; border-radius:14px; display:grid; place-items:center; background:#fff5d5; color:#b97700; font-size:1.1rem; }
.schedule-stat strong { display:block; font-size:1.65rem; color:var(--schedule-ink); margin-top:12px; }
.schedule-form-title { display:flex; align-items:center; gap:12px; }
.schedule-form-title i { width:44px; height:44px; display:grid; place-items:center; border-radius:14px; background:#fff5d5; color:#b97700; }
.weekday-picker { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:7px; }
.weekday-option input { position:absolute; opacity:0; pointer-events:none; }
.weekday-option label { display:grid; place-items:center; min-height:43px; border:1px solid #dfe4ec; border-radius:12px; font-weight:750; color:#667085; cursor:pointer; transition:.18s ease; }
.weekday-option input:checked + label { background:#172033; color:#fff; border-color:#172033; }
.weekday-option input:disabled + label { opacity:.42; cursor:not-allowed; }
.schedule-preview { background:#f8fafc; border:1px dashed #cfd6e2; border-radius:18px; padding:17px; }
.schedule-preview strong { color:#b97700; font-size:1.4rem; }
.schedule-template-card { border:1px solid #e8ecf2; border-radius:21px; padding:20px; background:#fff; transition:.2s ease; }
.schedule-template-card:hover { transform:translateY(-2px); box-shadow:0 13px 30px rgba(23,32,51,.08); }
.schedule-route { font-size:1.08rem; font-weight:850; color:var(--schedule-ink); }
.schedule-meta { display:flex; flex-wrap:wrap; gap:8px; }
.schedule-meta span { display:inline-flex; align-items:center; gap:5px; padding:7px 10px; border-radius:10px; background:#f5f7fa; color:#5f697b; font-size:.82rem; }
.schedule-timeline { position:relative; padding-left:20px; }
.schedule-timeline::before { content:""; position:absolute; left:5px; top:6px; bottom:6px; width:2px; background:#e4e8ef; }
.schedule-timeline div { position:relative; margin-bottom:12px; }
.schedule-timeline div::before { content:""; position:absolute; left:-19px; top:6px; width:9px; height:9px; border-radius:50%; background:#f1ad00; }
@media(max-width:767.98px){.schedule-hero{padding:26px 22px}.weekday-picker{grid-template-columns:repeat(4,minmax(0,1fr))}}
</style>

<div class="container py-5 schedule-page">
    <section class="schedule-hero mb-4">
        <div class="row align-items-center g-4 position-relative" style="z-index:1;">
            <div class="col-lg-8">
                <span class="text-warning fw-bold text-uppercase small">Super Admin · Operations</span>
                <h1 class="fw-bold mt-2 mb-2">Schedule management</h1>
                <p class="text-white-50 mb-0">Choose a bus company, build reusable schedule templates and generate trips without changing the existing schedule action.</p>
            </div>
            <div class="col-lg-4 text-lg-end"><a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-outline-light"><i class="bi bi-arrow-left me-1"></i>Back to dashboard</a></div>
        </div>
    </section>

    <?php if ($success = get_flash('success')): ?><div class="alert alert-success rounded-4 border-0 shadow-sm"><?php echo e($success); ?></div><?php endif; ?>
    <?php if ($error = get_flash('error')): ?><div class="alert alert-danger rounded-4 border-0 shadow-sm"><?php echo e($error); ?></div><?php endif; ?>

    <div class="schedule-shell schedule-company-select p-4 mb-4">
        <form method="GET" action="<?php echo BASE_URL; ?>admin/schedules.php" class="row g-3 align-items-end">
            <div class="col-lg-8"><label class="form-label fw-semibold">Bus company</label><select name="company_id" class="form-select" required><option value="">Select an approved bus company</option><?php foreach ($companies as $item): ?><option value="<?php echo e($item['id']); ?>" <?php echo $companyId === (int)$item['id'] ? 'selected' : ''; ?>><?php echo e($item['name']); ?> · <?php echo e(ucwords(str_replace('_', ' ', $item['company_type']))); ?></option><?php endforeach; ?></select></div>
            <div class="col-lg-4"><button type="submit" class="btn btn-warning w-100"><i class="bi bi-building-check me-1"></i>Load schedule workspace</button></div>
        </form>
    </div>

    <?php if ($companyId <= 0 || !$company): ?>
        <div class="schedule-shell text-center p-5"><i class="bi bi-calendar2-week fs-1 text-warning"></i><h3 class="mt-3">Select a company to begin</h3><p class="text-muted mb-0">The company ID is retained in the URL as <code>?company_id=</code> after loading.</p></div>
    <?php else: ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
            <div><span class="text-muted small">Current workspace</span><h3 class="fw-bold mb-0"><?php echo e($company['company_name']); ?></h3></div>
            <span class="badge rounded-pill text-bg-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i><?php echo e(ucfirst((string)($company['company_status'] ?? 'approved'))); ?></span>
        </div>

        <div class="row g-3 mb-4">
            <?php
            $stats = [
                ['Active routes', $scheduleSummary['active_routes'], 'bi-signpost-2'],
                ['Active buses', $scheduleSummary['active_buses'], 'bi-bus-front'],
                ['Templates', $scheduleSummary['templates'], 'bi-calendar2-range'],
                ['Generated trips', $scheduleSummary['generated_trips'], 'bi-calendar2-check'],
                ['Future open trips', $scheduleSummary['future_open_trips'], 'bi-ticket-perforated'],
            ];
            foreach ($stats as $stat):
            ?>
                <div class="col-6 col-md-4 col-xl"><div class="schedule-stat"><span class="schedule-stat-icon"><i class="bi <?php echo e($stat[2]); ?>"></i></span><strong><?php echo e($stat[1]); ?></strong><span class="text-muted small"><?php echo e($stat[0]); ?></span></div></div>
            <?php endforeach; ?>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-xl-5">
                <div class="schedule-shell p-4 p-lg-5 sticky-xl-top" style="top:92px;">
                    <div class="schedule-form-title mb-4"><i class="bi bi-calendar-plus"></i><div><h4 class="fw-bold mb-1">Create schedule template</h4><p class="text-muted small mb-0">Generate now or save the template for later.</p></div></div>

                    <?php if (empty($routes) || empty($buses)): ?>
                        <div class="alert alert-warning rounded-4 mb-0"><strong>Setup required.</strong><br>This company needs at least one active route and one active bus before a schedule can be created.</div>
                    <?php else: ?>
                        <form action="<?php echo BASE_URL; ?>actions/generate_schedule_action.php" method="POST" id="scheduleForm" novalidate>
                            <input type="hidden" name="company_id" value="<?php echo e($companyId); ?>">
                            <div class="mb-3"><label class="form-label fw-semibold">Route</label><select name="route_id" id="routeSelect" class="form-select" required><option value="">Select route</option><?php foreach ($routes as $route): ?><option value="<?php echo e($route['id']); ?>" data-price="<?php echo e((float)$route['base_price']); ?>"><?php echo e($route['from_city_name']); ?> → <?php echo e($route['to_city_name']); ?> · <?php echo e(number_format((float)$route['base_price'], 0)); ?> MMK</option><?php endforeach; ?></select></div>
                            <div class="mb-3"><label class="form-label fw-semibold">Bus</label><select name="bus_id" class="form-select" required><option value="">Select bus</option><?php foreach ($buses as $bus): ?><option value="<?php echo e($bus['id']); ?>"><?php echo e($bus['bus_number']); ?> · <?php echo e($bus['bus_type']); ?> · <?php echo e($bus['total_seats']); ?> seats</option><?php endforeach; ?></select></div>
                            <div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Departure</label><input type="time" name="departure_time" id="departureTime" class="form-control" required></div><div class="col-md-6"><label class="form-label fw-semibold">Arrival</label><input type="time" name="arrival_time" id="arrivalTime" class="form-control" required></div></div>
                            <div class="form-text mb-3" id="overnightHint">Arrival earlier than departure is handled as an overnight trip.</div>
                            <div class="mb-3"><label class="form-label fw-semibold">Ticket price (MMK)</label><input type="number" step="0.01" min="0" name="price" id="priceInput" class="form-control" required><div class="form-text">The route base price fills automatically and can still be edited.</div></div>
                            <div class="mb-3"><label class="form-label fw-semibold">Frequency</label><select name="frequency" id="frequencySelect" class="form-select" required><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="custom">Custom weekdays</option></select></div>
                            <div class="mb-3"><label class="form-label fw-semibold d-block">Operating weekdays</label><div class="weekday-picker" id="weekdayPicker"><?php foreach ($weekdayOptions as $value => $label): ?><div class="weekday-option"><input type="checkbox" name="weekdays[]" value="<?php echo e($value); ?>" id="wd_<?php echo e($value); ?>"><label for="wd_<?php echo e($value); ?>"><?php echo e($label); ?></label></div><?php endforeach; ?></div><div class="form-text" id="weekdayHint">Daily frequency automatically includes every day.</div></div>
                            <div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Active from</label><input type="date" name="active_from" id="activeFrom" class="form-control" min="<?php echo date('Y-m-d'); ?>" required></div><div class="col-md-6"><label class="form-label fw-semibold">Active to</label><input type="date" name="active_to" id="activeTo" class="form-control" min="<?php echo date('Y-m-d'); ?>" required></div></div>
                            <div class="mt-3"><label class="form-label fw-semibold">Template status</label><select name="status" class="form-select" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                            <div class="schedule-preview mt-4"><div class="d-flex justify-content-between gap-3"><div><span class="text-muted small d-block">Estimated trip dates</span><strong id="estimatedTrips">0</strong></div><div class="text-end"><span class="text-muted small d-block">Trip timing</span><span class="fw-semibold" id="timingPreview">Choose times</span></div></div></div>
                            <div class="d-grid gap-2 mt-4"><button type="submit" name="action_type" value="create_and_generate" class="btn btn-warning"><i class="bi bi-lightning-charge-fill me-1"></i>Create template + generate trips</button><button type="submit" name="action_type" value="create_template" class="btn btn-outline-dark"><i class="bi bi-save me-1"></i>Save template only</button></div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="schedule-shell p-4 p-lg-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"><div><h4 class="fw-bold mb-1">Schedule templates</h4><p class="text-muted mb-0">Generate missing trips again from any active template.</p></div><span class="badge text-bg-dark rounded-pill px-3 py-2"><?php echo count($templates); ?> templates</span></div>
                    <?php if (empty($templates)): ?>
                        <div class="text-center py-5"><i class="bi bi-calendar2-x fs-1 text-muted"></i><h5 class="mt-3">No templates yet</h5><p class="text-muted mb-0">Create the first schedule using the form.</p></div>
                    <?php else: ?>
                        <div class="d-grid gap-3">
                            <?php foreach ($templates as $tpl): ?>
                                <article class="schedule-template-card">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3"><div><div class="schedule-route"><?php echo e($tpl['from_city_name']); ?> <i class="bi bi-arrow-right mx-1 text-warning"></i> <?php echo e($tpl['to_city_name']); ?></div><div class="text-muted small mt-1"><?php echo e($tpl['bus_number']); ?> · <?php echo e($tpl['bus_type']); ?> · <?php echo e($tpl['total_seats']); ?> seats</div></div><div class="text-md-end"><span class="badge bg-<?php echo e(schedule_status_badge((string)$tpl['status'])); ?>"><?php echo e(ucfirst((string)$tpl['status'])); ?></span><div class="fw-bold mt-2"><?php echo e(number_format((float)$tpl['price'], 0)); ?> MMK</div></div></div>
                                    <div class="schedule-meta mb-3"><span><i class="bi bi-clock"></i><?php echo e(date('H:i', strtotime((string)$tpl['departure_time']))); ?> – <?php echo e(date('H:i', strtotime((string)$tpl['arrival_time']))); ?></span><span><i class="bi bi-repeat"></i><?php echo e(ucfirst((string)$tpl['frequency'])); ?></span><span><i class="bi bi-calendar-week"></i><?php echo e(format_weekdays_display($tpl['weekdays'])); ?></span><span><i class="bi bi-ticket-perforated"></i><?php echo e($tpl['generated_trip_count']); ?> generated</span></div>
                                    <div class="row g-3 align-items-end"><div class="col-md-7"><div class="schedule-timeline"><div><small class="text-muted">Active range</small><strong class="d-block"><?php echo e(date('M d, Y', strtotime((string)$tpl['active_from']))); ?> – <?php echo e(date('M d, Y', strtotime((string)$tpl['active_to']))); ?></strong></div><div class="mb-0"><small class="text-muted">Next available trip</small><strong class="d-block"><?php echo !empty($tpl['next_trip_datetime']) ? e(date('M d, Y · H:i', strtotime((string)$tpl['next_trip_datetime']))) : 'No future scheduled/open trip'; ?></strong></div></div></div><div class="col-md-5"><form action="<?php echo BASE_URL; ?>actions/generate_schedule_action.php" method="POST"><input type="hidden" name="company_id" value="<?php echo e($companyId); ?>"><input type="hidden" name="schedule_template_id" value="<?php echo e($tpl['id']); ?>"><button type="submit" name="action_type" value="generate_existing" class="btn btn-outline-warning w-100" <?php echo $tpl['status'] !== 'active' ? 'disabled' : ''; ?>><i class="bi bi-arrow-repeat me-1"></i>Generate missing trips</button></form><small class="d-block text-muted text-center mt-2"><?php echo e($tpl['future_open_count']); ?> future open trips</small></div></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const form = document.getElementById('scheduleForm');
    if (!form) return;
    const route = document.getElementById('routeSelect');
    const price = document.getElementById('priceInput');
    const frequency = document.getElementById('frequencySelect');
    const weekdays = Array.from(document.querySelectorAll('#weekdayPicker input[type="checkbox"]'));
    const weekdayHint = document.getElementById('weekdayHint');
    const from = document.getElementById('activeFrom');
    const to = document.getElementById('activeTo');
    const departure = document.getElementById('departureTime');
    const arrival = document.getElementById('arrivalTime');
    const estimate = document.getElementById('estimatedTrips');
    const timing = document.getElementById('timingPreview');

    route.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (option && option.dataset.price && (!price.value || price.dataset.autoFilled === '1')) {
            price.value = option.dataset.price;
            price.dataset.autoFilled = '1';
        }
    });
    price.addEventListener('input', () => { price.dataset.autoFilled = '0'; });

    function refreshFrequency() {
        const daily = frequency.value === 'daily';
        weekdays.forEach((checkbox) => { checkbox.disabled = daily; if (daily) checkbox.checked = false; });
        weekdayHint.textContent = daily ? 'Daily frequency automatically includes every day.' : 'Select at least one operating weekday.';
        calculateEstimate();
    }

    function calculateEstimate() {
        if (!from.value || !to.value) { estimate.textContent = '0'; return; }
        const start = new Date(from.value + 'T00:00:00');
        const end = new Date(to.value + 'T00:00:00');
        if (end < start) { estimate.textContent = 'Invalid'; return; }
        const selected = weekdays.filter((item) => item.checked).map((item) => item.value);
        const dayMap = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        let count = 0;
        for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
            if (frequency.value === 'daily' || selected.includes(dayMap[cursor.getDay()])) count++;
        }
        estimate.textContent = String(count);
    }

    function refreshTiming() {
        if (!departure.value || !arrival.value) { timing.textContent = 'Choose times'; return; }
        timing.textContent = departure.value + ' → ' + arrival.value + (arrival.value <= departure.value ? ' · overnight' : '');
    }

    frequency.addEventListener('change', refreshFrequency);
    weekdays.forEach((checkbox) => checkbox.addEventListener('change', calculateEstimate));
    from.addEventListener('change', function () { to.min = this.value || '<?php echo date('Y-m-d'); ?>'; if (to.value && to.value < this.value) to.value = this.value; calculateEstimate(); });
    to.addEventListener('change', calculateEstimate);
    departure.addEventListener('change', refreshTiming);
    arrival.addEventListener('change', refreshTiming);

    form.addEventListener('submit', function (event) {
        if ((frequency.value === 'weekly' || frequency.value === 'custom') && !weekdays.some((item) => item.checked)) {
            event.preventDefault();
            alert('Please select at least one weekday for weekly/custom frequency.');
            return;
        }
        if (from.value && to.value && to.value < from.value) {
            event.preventDefault();
            alert('Active To date must be the same as or later than Active From date.');
        }
    });

    refreshFrequency();
    refreshTiming();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
