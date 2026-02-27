<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/ProjectService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_projects_list(): void {
    require_role(['admin', 'special', 'user']);
    $projects = ProjectService::listProjects();
    json_ok(['projects' => $projects]);
}

function handle_projects_get(int $id): void {
    require_role(['admin', 'special', 'user']);
    $project = ProjectService::getProject($id);
    if (!$project) {
        json_error('not_found', 'Project not found.', 404);
        return;
    }
    json_ok(['project' => $project]);
}

function handle_projects_create(): void {
    require_role(['admin']);
    $data = read_json_body();
    $result = ProjectService::createProject($data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_projects_update(int $id): void {
    require_role(['admin']);
    $data = read_json_body();
    $result = ProjectService::updateProject($id, $data);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}

function handle_projects_delete(int $projectId): void {
    require_role(['admin']);
    $result = ProjectService::deleteProject($projectId);
    if (isset($result['error'])) {
        json_error($result['error']['code'], $result['error']['message'], $result['status'] ?? 400);
        return;
    }
    json_ok($result['data']);
}
