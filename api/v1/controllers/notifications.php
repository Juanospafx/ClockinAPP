<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/bootstrap.php';
require_once __DIR__ . '/../../../core/services/NotificationService.php';
require_once __DIR__ . '/../../../core/middlewares/auth.php';

function handle_notifications_list(): void
{
    $userId = require_login();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

    $items = NotificationService::listByUser($userId, $limit);
    $unread = NotificationService::unreadCount($userId);

    json_ok(['notifications' => $items, 'unread_count' => $unread]);
}

function handle_notifications_read(int $notificationId): void
{
    $userId = require_login();
    $ok = NotificationService::markRead($notificationId, $userId);
    if (!$ok) {
        json_error('not_found', 'Notification not found.', 404);
        return;
    }
    json_ok(['message' => 'Notification marked as read.']);
}
