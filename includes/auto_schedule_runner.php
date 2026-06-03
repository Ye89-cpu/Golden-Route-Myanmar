<?php
// includes/auto_schedule_runner.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schedule_helper.php';

/*
    This function tries to use:
    Golden-Route-Myanmar/storage/logs

    If localhost permission blocks it, it will use:
    system temp folder

    So "Cannot create lock file" error will be fixed.
*/

if (!function_exists('grm_auto_schedule_runtime_dir')) {
    function grm_auto_schedule_runtime_dir(): string
    {
        $projectRoot = dirname(__DIR__);
        $storageDir = $projectRoot . '/storage';
        $logDir = $storageDir . '/logs';

        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        if (is_dir($logDir) && is_writable($logDir)) {
            return $logDir;
        }

        // Fallback for Linux/LAMPP permission problem
        $fallbackDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'golden_route_myanmar_logs';

        if (!is_dir($fallbackDir)) {
            @mkdir($fallbackDir, 0777, true);
        }

        return $fallbackDir;
    }
}

if (!function_exists('grm_auto_schedule_log')) {
    function grm_auto_schedule_log(string $message): void
    {
        $logDir = grm_auto_schedule_runtime_dir();
        $logFile = $logDir . '/auto_schedule_runner.log';

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

if (!function_exists('grm_auto_schedule_get_templates')) {
    function grm_auto_schedule_get_templates(mysqli $conn): array
    {
        $templates = [];

        $sql = "
            SELECT
                st.*,
                b.total_seats
            FROM schedule_templates st
            INNER JOIN companies c ON c.id = st.company_id
            INNER JOIN routes r ON r.id = st.route_id AND r.company_id = st.company_id
            INNER JOIN buses b ON b.id = st.bus_id AND b.company_id = st.company_id
            WHERE st.status = 'active'
              AND r.status = 'active'
              AND b.status = 'active'
              AND c.status = 'approved'
              AND c.company_type IN ('bus_company', 'both')
            ORDER BY st.id ASC
        ";

        $result = $conn->query($sql);

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $templates[] = $row;
            }
        }

        return $templates;
    }
}

if (!function_exists('grm_auto_schedule_calculate_range')) {
    function grm_auto_schedule_calculate_range(array $template, int $daysAhead = 30, bool $extendExpiredTemplates = true): ?array
    {
        $today = new DateTime('today');

        $targetTo = clone $today;
        $targetTo->modify('+' . max(1, $daysAhead) . ' days');

        $activeFrom = new DateTime((string)$template['active_from']);
        $activeTo = new DateTime((string)$template['active_to']);

        if ($extendExpiredTemplates && $activeTo < $today) {
            $start = clone $today;
            $end = clone $targetTo;
        } else {
            $start = $activeFrom > $today ? clone $activeFrom : clone $today;
            $end = $activeTo < $targetTo ? clone $activeTo : clone $targetTo;
        }

        if ($end < $start) {
            return null;
        }

        return [
            'from' => $start->format('Y-m-d'),
            'to'   => $end->format('Y-m-d'),
        ];
    }
}

if (!function_exists('grm_auto_schedule_update_old_trip_status')) {
    function grm_auto_schedule_update_old_trip_status(mysqli $conn): array
    {
        $updated = [
            'departed' => 0,
            'completed' => 0,
        ];

        $completedSql = "
            UPDATE trips
            SET status = 'completed'
            WHERE status IN ('scheduled', 'open', 'full', 'departed')
              AND arrival_datetime < NOW()
        ";

        if ($conn->query($completedSql)) {
            $updated['completed'] = max(0, (int)$conn->affected_rows);
        }

        $departedSql = "
            UPDATE trips
            SET status = 'departed'
            WHERE status IN ('scheduled', 'open', 'full')
              AND departure_datetime <= NOW()
              AND arrival_datetime >= NOW()
        ";

        if ($conn->query($departedSql)) {
            $updated['departed'] = max(0, (int)$conn->affected_rows);
        }

        return $updated;
    }
}

if (!function_exists('grm_auto_schedule_runner')) {
    function grm_auto_schedule_runner(bool $force = false, int $daysAhead = 30, bool $extendExpiredTemplates = true): array
    {
        $runtimeDir = grm_auto_schedule_runtime_dir();

        $lastRunFile = $runtimeDir . '/auto_schedule_last_run.txt';
        $lockFile = $runtimeDir . '/auto_schedule_runner.lock';

        $todayKey = date('Y-m-d');

        if (!$force && file_exists($lastRunFile)) {
            $lastRunDate = trim((string)@file_get_contents($lastRunFile));

            if ($lastRunDate === $todayKey) {
                return [
                    'status' => 'skipped',
                    'message' => 'Auto schedule already ran today.',
                    'generated' => 0,
                    'skipped' => 0,
                    'templates' => 0,
                    'runtime_dir' => $runtimeDir,
                ];
            }
        }

        $lockHandle = @fopen($lockFile, 'c');

        if (!$lockHandle) {
            return [
                'status' => 'error',
                'message' => 'Cannot create lock file. Runtime directory is not writable: ' . $runtimeDir,
                'generated' => 0,
                'skipped' => 0,
                'templates' => 0,
                'runtime_dir' => $runtimeDir,
            ];
        }

        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);

            return [
                'status' => 'locked',
                'message' => 'Auto schedule runner is already running.',
                'generated' => 0,
                'skipped' => 0,
                'templates' => 0,
                'runtime_dir' => $runtimeDir,
            ];
        }

        $conn = null;

        $summary = [
            'status' => 'success',
            'message' => 'Auto schedule runner completed.',
            'generated' => 0,
            'skipped' => 0,
            'templates' => 0,
            'departed_updated' => 0,
            'completed_updated' => 0,
            'errors' => [],
            'runtime_dir' => $runtimeDir,
        ];

        try {
            if (!$force && file_exists($lastRunFile)) {
                $lastRunDate = trim((string)@file_get_contents($lastRunFile));

                if ($lastRunDate === $todayKey) {
                    flock($lockHandle, LOCK_UN);
                    fclose($lockHandle);

                    return [
                        'status' => 'skipped',
                        'message' => 'Auto schedule already ran today.',
                        'generated' => 0,
                        'skipped' => 0,
                        'templates' => 0,
                        'runtime_dir' => $runtimeDir,
                    ];
                }
            }

            $conn = getDBConnection();

            $statusUpdates = grm_auto_schedule_update_old_trip_status($conn);

            $summary['departed_updated'] = $statusUpdates['departed'];
            $summary['completed_updated'] = $statusUpdates['completed'];

            $templates = grm_auto_schedule_get_templates($conn);
            $summary['templates'] = count($templates);

            foreach ($templates as $template) {
                try {
                    $range = grm_auto_schedule_calculate_range(
                        $template,
                        $daysAhead,
                        $extendExpiredTemplates
                    );

                    if ($range === null) {
                        continue;
                    }

                    $templateForRun = $template;
                    $templateForRun['active_from'] = $range['from'];
                    $templateForRun['active_to'] = $range['to'];

                    $availableSeats = get_active_bus_seat_count(
                        $conn,
                        (int)$template['bus_id'],
                        (int)($template['total_seats'] ?? 0)
                    );

                    $result = generate_trips_from_template(
                        $conn,
                        $templateForRun,
                        $availableSeats
                    );

                    $summary['generated'] += (int)($result['generated_count'] ?? 0);
                    $summary['skipped'] += (int)($result['skipped_count'] ?? 0);
                } catch (Throwable $templateError) {
                    $summary['errors'][] = 'Template ID ' . ($template['id'] ?? '-') . ': ' . $templateError->getMessage();

                    grm_auto_schedule_log(
                        'Template error | ID: ' . ($template['id'] ?? '-') . ' | ' . $templateError->getMessage()
                    );
                }
            }

            @file_put_contents($lastRunFile, $todayKey);

            grm_auto_schedule_log(
                'Run completed | Templates: ' . $summary['templates'] .
                ' | Generated: ' . $summary['generated'] .
                ' | Skipped: ' . $summary['skipped'] .
                ' | Departed updated: ' . $summary['departed_updated'] .
                ' | Completed updated: ' . $summary['completed_updated']
            );

            if ($conn instanceof mysqli) {
                $conn->close();
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            return $summary;
        } catch (Throwable $e) {
            if ($conn instanceof mysqli) {
                $conn->close();
            }

            grm_auto_schedule_log('Fatal error: ' . $e->getMessage());

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'generated' => 0,
                'skipped' => 0,
                'templates' => 0,
                'runtime_dir' => $runtimeDir,
            ];
        }
    }
}