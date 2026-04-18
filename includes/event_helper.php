<?php
require_once __DIR__ . '/db.php';

if (!function_exists('events_table_exists')) {
    function events_table_exists(mysqli $conn): bool
    {
        $result = $conn->query("SHOW TABLES LIKE 'events'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('event_column_exists')) {
    function event_column_exists(mysqli $conn, string $column): bool
    {
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM events LIKE '{$safeColumn}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('event_upload_dir')) {
    function event_upload_dir(): string
    {
        return __DIR__ . '/../uploads/events/';
    }
}

if (!function_exists('event_upload_db_path')) {
    function event_upload_db_path(string $fileName): string
    {
        return 'uploads/events/' . $fileName;
    }
}

if (!function_exists('event_placeholder_image')) {
    function event_placeholder_image(?string $eventType = null): string
    {
        return match ($eventType) {
            'Tour Event' => 'assets/images/tourh.png',
            'Bus Event' => 'assets/images/bus.png',
            'Festival' => 'assets/images/thin.jpg',
            default => 'assets/images/QR.png',
        };
    }
}

if (!function_exists('event_public_image')) {
    function event_public_image(?string $imagePath, ?string $eventType = null): string
    {
        $cleanPath = trim((string) $imagePath);
        if ($cleanPath !== '') {
            return BASE_URL . ltrim($cleanPath, '/');
        }

        return BASE_URL . ltrim(event_placeholder_image($eventType), '/');
    }
}

if (!function_exists('ensure_events_table_exists')) {
    function ensure_events_table_exists(mysqli $conn): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            event_type VARCHAR(100) NOT NULL DEFAULT 'Promotion',
            description TEXT NULL,
            event_date DATE NULL,
            location VARCHAR(255) NULL,
            image_path VARCHAR(255) NULL,
            status ENUM('active','draft','expired') NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            show_in_slider TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            throw new RuntimeException('Failed to ensure events table exists: ' . $conn->error);
        }

        if (event_column_exists($conn, 'image') && !event_column_exists($conn, 'image_path')) {
            if (!$conn->query("ALTER TABLE events ADD COLUMN image_path VARCHAR(255) NULL AFTER location")) {
                throw new RuntimeException('Failed to add image_path column: ' . $conn->error);
            }
            $conn->query("UPDATE events SET image_path = image WHERE image_path IS NULL AND image IS NOT NULL");
        }

        $alterQueries = [];

        if (!event_column_exists($conn, 'image_path')) {
            $alterQueries[] = "ALTER TABLE events ADD COLUMN image_path VARCHAR(255) NULL AFTER location";
        }

        if (!event_column_exists($conn, 'created_by')) {
            $alterQueries[] = "ALTER TABLE events ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER status";
        }

        if (!event_column_exists($conn, 'show_in_slider')) {
            $alterQueries[] = "ALTER TABLE events ADD COLUMN show_in_slider TINYINT(1) NOT NULL DEFAULT 1 AFTER updated_at";
        }

        foreach ($alterQueries as $alterSql) {
            if (!$conn->query($alterSql)) {
                throw new RuntimeException('Failed to update events table: ' . $conn->error);
            }
        }
    }
}

if (!function_exists('normalize_event_form_data')) {
    function normalize_event_form_data(array $input): array
    {
        return [
            'title' => trim((string)($input['title'] ?? '')),
            'event_type' => trim((string)($input['event_type'] ?? 'Promotion')),
            'description' => trim((string)($input['description'] ?? '')),
            'event_date' => trim((string)($input['event_date'] ?? '')),
            'location' => trim((string)($input['location'] ?? '')),
            'status' => trim((string)($input['status'] ?? 'draft')),
            'show_in_slider' => isset($input['show_in_slider']) ? 1 : 0,
        ];
    }
}

if (!function_exists('validate_event_form_data')) {
    function validate_event_form_data(array $data): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = 'Event title is required.';
        }

        $allowedTypes = ['Promotion', 'Tour Event', 'Bus Event', 'Festival', 'Announcement'];
        if (!in_array($data['event_type'], $allowedTypes, true)) {
            $errors[] = 'Invalid event type selected.';
        }

        $allowedStatuses = ['draft', 'active', 'expired'];
        if (!in_array($data['status'], $allowedStatuses, true)) {
            $errors[] = 'Invalid event status selected.';
        }

        if ($data['event_date'] !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $data['event_date']);
            if (!$date || $date->format('Y-m-d') !== $data['event_date']) {
                $errors[] = 'Event date format is invalid.';
            }
        }

        return $errors;
    }
}

if (!function_exists('get_all_events')) {
    function get_all_events(mysqli $conn): array
    {
        ensure_events_table_exists($conn);

        $events = [];
        $sql = "SELECT * FROM events ORDER BY
                    CASE status
                        WHEN 'active' THEN 1
                        WHEN 'draft' THEN 2
                        WHEN 'expired' THEN 3
                        ELSE 4
                    END,
                    CASE WHEN event_date IS NULL THEN 1 ELSE 0 END,
                    event_date ASC,
                    id DESC";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $result->free();
        }

        return $events;
    }
}

if (!function_exists('get_recent_events')) {
    function get_recent_events(mysqli $conn, int $limit = 5): array
    {
        ensure_events_table_exists($conn);

        $events = [];
        $limit = max(1, $limit);

        $sql = "SELECT * FROM events ORDER BY created_at DESC, id DESC LIMIT {$limit}";
        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $result->free();
        }

        return $events;
    }
}

if (!function_exists('get_public_events')) {
    function get_public_events(mysqli $conn, int $limit = 50): array
    {
        ensure_events_table_exists($conn);

        $events = [];
        $limit = max(1, $limit);

        $sql = "SELECT * FROM events WHERE status = 'active' ORDER BY
                    CASE WHEN event_date IS NULL THEN 1 ELSE 0 END,
                    event_date ASC,
                    id DESC
                LIMIT {$limit}";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $result->free();
        }

        return $events;
    }
}

if (!function_exists('get_slider_events')) {
    function get_slider_events(mysqli $conn, int $limit = 5): array
    {
        ensure_events_table_exists($conn);

        $events = [];
        $limit = max(1, $limit);
        $where = "status = 'active'";

        if (event_column_exists($conn, 'show_in_slider')) {
            $where .= " AND show_in_slider = 1";
        }

        $sql = "SELECT id, title, event_type, description, event_date, location, image_path, status, show_in_slider
                FROM events
                WHERE {$where}
                ORDER BY
                    CASE WHEN event_date IS NULL THEN 1 ELSE 0 END,
                    event_date ASC,
                    id DESC
                LIMIT {$limit}";

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            $result->free();
        }

        return $events;
    }
}

if (!function_exists('get_event_by_id')) {
    function get_event_by_id(mysqli $conn, int $id): ?array
    {
        ensure_events_table_exists($conn);

        $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $event ?: null;
    }
}

if (!function_exists('delete_event_image_file')) {
    function delete_event_image_file(?string $imagePath): void
    {
        if (!$imagePath) {
            return;
        }

        $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

if (!function_exists('process_event_image_upload')) {
    function process_event_image_upload(?array $file, ?string $existingImagePath = null): ?string
    {
        if (!$file || empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            return $existingImagePath;
        }

        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed.');
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        $tmpPath = $file['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded image.');
        }

        $mimeType = mime_content_type($tmpPath);
        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new RuntimeException('Only JPG, PNG, WEBP and GIF images are allowed.');
        }

        $uploadDir = event_upload_dir();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
            throw new RuntimeException('Failed to create event upload directory.');
        }

        $extension = $allowedMimeTypes[$mimeType];
        $safeName = 'event_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        if ($existingImagePath) {
            delete_event_image_file($existingImagePath);
        }

        return event_upload_db_path($safeName);
    }
}

if (!function_exists('insert_event')) {
    function insert_event(mysqli $conn, array $data, ?string $imagePath): int
    {
        $stmt = $conn->prepare("
            INSERT INTO events (title, event_type, description, event_date, location, image_path, status, show_in_slider)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare event insert query.');
        }

        $eventDate = $data['event_date'] !== '' ? $data['event_date'] : null;
        $showInSlider = (int)($data['show_in_slider'] ?? 0);

        $stmt->bind_param(
            'sssssssi',
            $data['title'],
            $data['event_type'],
            $data['description'],
            $eventDate,
            $data['location'],
            $imagePath,
            $data['status'],
            $showInSlider
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to create event.');
        }

        $newId = (int)$stmt->insert_id;
        $stmt->close();

        return $newId;
    }
}

if (!function_exists('update_event')) {
    function update_event(mysqli $conn, int $id, array $data, ?string $imagePath): void
    {
        $stmt = $conn->prepare("
            UPDATE events
            SET title = ?, event_type = ?, description = ?, event_date = ?, location = ?, image_path = ?, status = ?, show_in_slider = ?
            WHERE id = ?
        ");

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare event update query.');
        }

        $eventDate = $data['event_date'] !== '' ? $data['event_date'] : null;
        $showInSlider = (int)($data['show_in_slider'] ?? 0);

        $stmt->bind_param(
            'sssssssii',
            $data['title'],
            $data['event_type'],
            $data['description'],
            $eventDate,
            $data['location'],
            $imagePath,
            $data['status'],
            $showInSlider,
            $id
        );

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to update event.');
        }

        $stmt->close();
    }
}

if (!function_exists('save_event')) {
    function save_event(mysqli $conn, array $data, ?array $imageFile = null, ?int $id = null): int
    {
        ensure_events_table_exists($conn);

        $existing = null;
        if ($id) {
            $existing = get_event_by_id($conn, $id);
            if (!$existing) {
                throw new RuntimeException('Event not found.');
            }
        }

        $imagePath = process_event_image_upload($imageFile, $existing['image_path'] ?? null);

        if ($id) {
            update_event($conn, $id, $data, $imagePath);
            return $id;
        }

        return insert_event($conn, $data, $imagePath);
    }
}

if (!function_exists('delete_event_by_id')) {
    function delete_event_by_id(mysqli $conn, int $id): void
    {
        $event = get_event_by_id($conn, $id);
        if (!$event) {
            throw new RuntimeException('Event not found.');
        }

        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare delete query.');
        }

        $stmt->bind_param('i', $id);

        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to delete event.');
        }

        $stmt->close();

        if (!empty($event['image_path'])) {
            delete_event_image_file($event['image_path']);
        }
    }
}

if (!function_exists('get_event_dashboard_summary')) {
    function get_event_dashboard_summary(mysqli $conn): array
    {
        ensure_events_table_exists($conn);

        $summary = [
            'total_events' => 0,
            'active_events' => 0,
            'draft_events' => 0,
            'expired_events' => 0,
            'slider_events' => 0,
        ];

        $queries = [
            'total_events' => "SELECT COUNT(*) AS total FROM events",
            'active_events' => "SELECT COUNT(*) AS total FROM events WHERE status = 'active'",
            'draft_events' => "SELECT COUNT(*) AS total FROM events WHERE status = 'draft'",
            'expired_events' => "SELECT COUNT(*) AS total FROM events WHERE status = 'expired'",
            'slider_events' => event_column_exists($conn, 'show_in_slider')
                ? "SELECT COUNT(*) AS total FROM events WHERE status = 'active' AND show_in_slider = 1"
                : "SELECT COUNT(*) AS total FROM events WHERE status = 'active'",
        ];

        foreach ($queries as $key => $sql) {
            $result = $conn->query($sql);
            if ($result) {
                $row = $result->fetch_assoc();
                $summary[$key] = (int)($row['total'] ?? 0);
                $result->free();
            }
        }

        return $summary;
    }
}

if (!function_exists('event_status_badge_class')) {
    function event_status_badge_class(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'draft' => 'warning',
            'expired' => 'secondary',
            default => 'dark',
        };
    }
}