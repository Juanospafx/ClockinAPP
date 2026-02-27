<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/AbsenceService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';
require_once __DIR__ . '/../../../core/services/AuthService.php';

/**
 * POST /absences — create absence report (multipart/form-data for file upload)
 */
function handle_absences_create(): void
{
    $userId = require_login();

    // Read data from POST (multipart) or JSON body
    $data = !empty($_POST) ? $_POST : read_json_body();
    $file = $_FILES['evidence'] ?? null;

    $result = AbsenceService::createAbsence($userId, $data, $file);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

/**
 * GET /absences — list absences (users see own, admins see all with filters)
 */
function handle_absences_list(): void
{
    $currentUserId = require_login();
    $currentRole = AuthService::getCurrentUserRole() ?? 'user';

    $filters = [];

    if ($currentRole === 'admin') {
        // Admin can filter by any user
        if (!empty($_GET['user_id'])) {
            $filters['user_id'] = (int)$_GET['user_id'];
        }
    } else {
        // Regular users only see their own
        $filters['user_id'] = $currentUserId;
    }

    if (!empty($_GET['project_id'])) $filters['project_id'] = $_GET['project_id'];
    if (!empty($_GET['reason']))     $filters['reason']     = $_GET['reason'];
    if (!empty($_GET['status']))     $filters['status']     = $_GET['status'];
    if (!empty($_GET['date_from']))  $filters['date_from']  = $_GET['date_from'];
    if (!empty($_GET['date_to']))    $filters['date_to']    = $_GET['date_to'];
    if (!empty($_GET['limit']))      $filters['limit']      = $_GET['limit'];

    // Check for summary mode
    if (isset($_GET['summary'])) {
        $summary = AbsenceService::getSummary($filters);
        json_ok(['summary' => $summary]);
        return;
    }

    $absences = AbsenceService::listAbsences($filters);
    json_ok(['absences' => $absences]);
}

/**
 * PUT /absences/:id/review — approve or reject (admin only)
 */
function handle_absences_review(int $absenceId): void
{
    $adminId = require_login();
    require_role(['admin']);

    $data = read_json_body();
    $status = $data['status'] ?? '';

    $result = AbsenceService::reviewAbsence($absenceId, $status, $adminId);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

/**
 * DELETE /absences/:id — delete absence
 */
function handle_absences_delete(int $absenceId): void
{
    $userId = require_login();
    $role = AuthService::getCurrentUserRole() ?? 'user';

    $result = AbsenceService::deleteAbsence($absenceId, $userId, $role);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
