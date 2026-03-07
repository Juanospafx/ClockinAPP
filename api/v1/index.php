<?php
declare(strict_types=1);

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../core/response.php';
apply_cors();
header('Content-Type: application/json');

require_once __DIR__ . '/controllers/auth.php';
require_once __DIR__ . '/controllers/users.php';
require_once __DIR__ . '/controllers/projects.php';
require_once __DIR__ . '/controllers/attendance.php';
require_once __DIR__ . '/controllers/timers.php';
require_once __DIR__ . '/controllers/qrs.php';
require_once __DIR__ . '/controllers/locations.php';
require_once __DIR__ . '/controllers/roles.php';
require_once __DIR__ . '/controllers/maps.php';
require_once __DIR__ . '/controllers/uploads.php';
require_once __DIR__ . '/controllers/absences.php';
require_once __DIR__ . '/controllers/manual_attendance.php';
require_once __DIR__ . '/controllers/notifications.php';

$route = trim((string)($_GET['route'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($route === 'auth/login' && $method === 'POST') {
        handle_auth_login();
        return;
    }

    if ($route === 'users' && $method === 'GET') {
        handle_users_list();
        return;
    }
    if ($route === 'users' && $method === 'POST') {
        handle_users_create();
        return;
    }
    if (preg_match('#^users/(\\d+)$#', $route, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT') {
            handle_users_update($id);
            return;
        }
        if ($method === 'DELETE') {
            handle_users_delete($id);
            return;
        }
    }

    if ($route === 'projects' && $method === 'GET') {
        handle_projects_list();
        return;
    }
    if ($route === 'projects' && $method === 'POST') {
        handle_projects_create();
        return;
    }
    if (preg_match('#^projects/(\\d+)$#', $route, $m)) {
        $id = (int)$m[1];
        if ($method === 'GET') {
            handle_projects_get($id);
            return;
        }
        if ($method === 'PUT') {
            handle_projects_update($id);
            return;
        }
        if ($method === 'DELETE') {
            handle_projects_delete($id);
            return;
        }
    }

    if ($route === 'attendance' && $method === 'GET') {
        handle_attendance_list();
        return;
    }
    if ($route === 'attendance' && $method === 'POST') {
        handle_attendance_create();
        return;
    }
    if ($route === 'attendance/recalculate' && $method === 'POST') {
        handle_attendance_recalculate();
        return;
    }
    if (preg_match('#^attendance/(\\d+)$#', $route, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT') {
            handle_attendance_update($id);
            return;
        }
        if ($method === 'DELETE') {
            handle_attendance_delete($id);
            return;
        }
    }

    if ($route === 'timers/active' && $method === 'GET') {
        handle_timers_active();
        return;
    }
    if ($route === 'timers/me' && $method === 'GET') {
        handle_timers_me();
        return;
    }
    if (preg_match('#^timers/(\\d+)/status$#', $route, $m) && $method === 'POST') {
        handle_timers_update((int)$m[1]);
        return;
    }

    if ($route === 'qrs' && $method === 'GET') {
        handle_qrs_list();
        return;
    }
    if ($route === 'qrs' && $method === 'POST') {
        handle_qrs_create();
        return;
    }
    if (preg_match('#^qrs/(\\d+)$#', $route, $m) && $method === 'DELETE') {
        handle_qrs_delete((int)$m[1]);
        return;
    }

    if ($route === 'locations/log' && $method === 'POST') {
        handle_locations_log();
        return;
    }
    if ($route === 'locations/history' && $method === 'GET') {
        handle_locations_history();
        return;
    }
    if ($route === 'locations/users' && $method === 'GET') {
        handle_locations_latest();
        return;
    }

    if ($route === 'roles' && $method === 'GET') {
        handle_roles_list();
        return;
    }

    if ($route === 'maps/key' && $method === 'GET') {
        handle_maps_key();
        return;
    }

    if ($route === 'uploads/profile' && $method === 'POST') {
        handle_upload_profile();
        return;
    }

    // --- Absences ---
    if ($route === 'absences' && $method === 'GET') {
        handle_absences_list();
        return;
    }
    if ($route === 'absences' && $method === 'POST') {
        handle_absences_create();
        return;
    }
    if (preg_match('#^absences/(\\d+)/review$#', $route, $m) && $method === 'PUT') {
        handle_absences_review((int)$m[1]);
        return;
    }
    if ($route === 'absences/auto-mark' && $method === 'POST') {
        handle_absences_auto_mark();
        return;
    }
    if (preg_match('#^absences/(\\d+)$#', $route, $m) && $method === 'DELETE') {
        handle_absences_delete((int)$m[1]);
        return;
    }

    // --- Manual Attendance ---
    if ($route === 'attendance/manual' && $method === 'POST') {
        handle_manual_attendance_create();
        return;
    }

    // --- Notifications ---
    if ($route === 'notifications' && $method === 'GET') {
        handle_notifications_list();
        return;
    }
    if (preg_match('#^notifications/(\\d+)/read$#', $route, $m) && $method === 'PUT') {
        handle_notifications_read((int)$m[1]);
        return;
    }

    json_error('not_found', 'Endpoint not found.', 404);
} catch (Throwable $e) {
    json_error('server_error', 'Server error: ' . $e->getMessage(), 500);
}
