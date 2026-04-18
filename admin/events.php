<?php
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/event_helper.php';

require_role('super_admin');

$page_title = 'Manage Events';
$conn = getDBConnection();
ensure_events_table_exists($conn);

$editingId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$deleteId = isset($_GET['delete']) ? (int)$_GET['delete'] : 0;
$editingEvent = $editingId > 0 ? get_event_by_id($conn, $editingId) : null;

if ($deleteId > 0) {
    try {
        delete_event_by_id($conn, $deleteId);
        set_flash('success', 'Event deleted successfully.');
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
    }
    $conn->close();
    redirect('admin/events.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['event_id'] ?? 0);
    $data = normalize_event_form_data($_POST);
    $errors = validate_event_form_data($data);

    if (!empty($errors)) {
        set_flash('error', implode(' ', $errors));
        save_old_input(array_merge($_POST, ['show_in_slider' => $data['show_in_slider']]));
        $conn->close();
        redirect('admin/events.php' . ($id > 0 ? '?edit=' . $id : ''));
    }

    try {
        save_event($conn, $data, $_FILES['image'] ?? null, $id > 0 ? $id : null);
        clear_old_input();
        set_flash('success', $id > 0 ? 'Event updated successfully.' : 'Event created successfully.');
    } catch (Throwable $e) {
        set_flash('error', $e->getMessage());
        save_old_input(array_merge($_POST, ['show_in_slider' => $data['show_in_slider']]));
        $conn->close();
        redirect('admin/events.php' . ($id > 0 ? '?edit=' . $id : ''));
    }

    $conn->close();
    redirect('admin/events.php');
}

$summary = get_event_dashboard_summary($conn);
$events = get_all_events($conn);
$conn->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <?php if ($success = get_flash('success')): ?>
        <div class="alert alert-success"><?php echo e($success); ?></div>
    <?php endif; ?>

    <?php if ($error = get_flash('error')): ?>
        <div class="alert alert-danger"><?php echo e($error); ?></div>
    <?php endif; ?>

    <div class="admin-hero-panel mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <span class="section-kicker">Super Admin · Events</span>
                <h1 class="page-title mb-2">Manage events & promotions</h1>
                <p class="page-subtitle mb-0">
                    Create dashboard campaigns, seasonal offers, travel promotions and announcement records for Golden Route Myanmar.
                </p>
            </div>
            <div class="col-lg-4">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="btn btn-nav-soft">Back to Dashboard</a>
                    <a href="#event-form-card" class="btn btn-brand"><?php echo $editingEvent ? 'Edit Selected Event' : 'Add New Event'; ?></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Total Events</span>
                <strong><?php echo e($summary['total_events']); ?></strong>
                <small>All events and promotions</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Active Events</span>
                <strong><?php echo e($summary['active_events']); ?></strong>
                <small>Currently active records</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>In Slider</span>
                <strong><?php echo e($summary['slider_events']); ?></strong>
                <small>Showing on homepage</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="metric-card">
                <span>Draft / Expired</span>
                <strong><?php echo e($summary['draft_events'] + $summary['expired_events']); ?></strong>
                <small>Hidden from public view</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="panel-card event-form-card" id="event-form-card">
                <div class="panel-card-header">
                    <h4><?php echo $editingEvent ? 'Update Event' : 'Add New Event'; ?></h4>
                    <p><?php echo $editingEvent ? 'Edit the selected event details below.' : 'Create a new admin event or promotion.'; ?></p>
                </div>

                <?php
                    $oldShowInSlider = old('show_in_slider', null);
                    $formData = $editingEvent ?: [
                        'id' => 0,
                        'title' => old('title'),
                        'event_type' => old('event_type', 'Promotion'),
                        'description' => old('description'),
                        'event_date' => old('event_date'),
                        'location' => old('location'),
                        'image_path' => '',
                        'status' => old('status', 'draft'),
                        'show_in_slider' => $oldShowInSlider === null ? 1 : (int)$oldShowInSlider,
                    ];
                ?>

                <form action="<?php echo BASE_URL; ?>admin/events.php" method="POST" enctype="multipart/form-data" class="row g-3">
                    <input type="hidden" name="event_id" value="<?php echo e($formData['id'] ?? 0); ?>">

                    <div class="col-12">
                        <label class="form-label">Event Title</label>
                        <input type="text" name="title" class="form-control" required value="<?php echo e($formData['title'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Event Type</label>
                        <select name="event_type" class="form-select">
                            <?php foreach (['Promotion', 'Tour Event', 'Bus Event', 'Festival', 'Announcement'] as $type): ?>
                                <option value="<?php echo e($type); ?>" <?php echo (($formData['event_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                    <?php echo e($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label): ?>
                                <option value="<?php echo e($value); ?>" <?php echo (($formData['status'] ?? '') === $value) ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Event Date</label>
                        <input type="date" name="event_date" class="form-control" value="<?php echo e($formData['event_date'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control" value="<?php echo e($formData['location'] ?? ''); ?>" placeholder="Yangon, Bagan, Mandalay...">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Write a short event or promotion description..."><?php echo e($formData['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Event Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if (!empty($formData['image_path'])): ?>
                            <div class="event-image-preview mt-3">
                                <img src="<?php echo BASE_URL . e($formData['image_path']); ?>" alt="Event image preview">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                id="showInSlider"
                                name="show_in_slider"
                                value="1"
                                <?php echo !empty($formData['show_in_slider']) ? 'checked' : ''; ?>
                            >
                            <label class="form-check-label" for="showInSlider">
                                Show this event in homepage event & promotion slider
                            </label>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-brand px-4"><?php echo $editingEvent ? 'Update Event' : 'Save Event'; ?></button>
                        <?php if ($editingEvent): ?>
                            <a href="<?php echo BASE_URL; ?>admin/events.php" class="btn btn-nav-soft">Cancel Edit</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="panel-card h-100">
                <div class="panel-card-header">
                    <h4>All Events</h4>
                    <p>Latest event records for admin management only.</p>
                </div>

                <?php if (empty($events)): ?>
                    <div class="empty-inline-box">No events found. Create your first event from the form.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 event-admin-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Slider</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo e($event['title']); ?></div>
                                            <div class="small text-muted"><?php echo e($event['location'] ?: 'No location'); ?></div>
                                        </td>
                                        <td><?php echo e($event['event_type']); ?></td>
                                        <td><?php echo !empty($event['event_date']) ? e($event['event_date']) : '—'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo !empty($event['show_in_slider']) ? 'primary' : 'secondary'; ?>">
                                                <?php echo !empty($event['show_in_slider']) ? 'Shown' : 'Hidden'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo e(event_status_badge_class((string)$event['status'])); ?>">
                                                <?php echo e(ucfirst((string)$event['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo BASE_URL; ?>admin/events.php?edit=<?php echo (int)$event['id']; ?>" class="btn btn-sm btn-nav-soft">Edit</a>
                                            <a href="<?php echo BASE_URL; ?>admin/events.php?delete=<?php echo (int)$event['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this event?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>