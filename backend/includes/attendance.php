<?php
require_once __DIR__ . '/db.php';

function round_time_to_next_quarter(DateTime $date): DateTime {
    $minutes = (int)$date->format('i');
    $remainder = $minutes % 15;
    if ($remainder !== 0) {
        $date->add(new DateInterval('PT' . (15 - $remainder) . 'M'));
    }
    $date->setTime((int)$date->format('H'), (int)$date->format('i'), 0);
    return $date;
}

function round_time_to_previous_quarter(DateTime $date): DateTime {
    $minutes = (int)$date->format('i');
    $remainder = $minutes % 15;
    if ($remainder !== 0) {
        $date->sub(new DateInterval('PT' . $remainder . 'M'));
    }
    $date->setTime((int)$date->format('H'), (int)$date->format('i'), 0);
    return $date;
}

function calculate_daily_work_duration(PDO $pdo, int $userId, string $date, ?array $pendingEvent = null): int {
    $utc = new DateTimeZone('UTC');
    $stmt = $pdo->prepare(
        "SELECT type, original_time FROM attendance_records
         WHERE user_id = ? AND DATE(original_time) = ?
         ORDER BY original_time ASC, id ASC"
    );
    $stmt->execute([$userId, $date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($pendingEvent) {
        $events[] = $pendingEvent;
        usort($events, function ($a, $b) {
            return strtotime($a['original_time']) - strtotime($b['original_time']);
        });
    }

    if (empty($events)) {
        return 0;
    }

    $elapsedSeconds = 0;
    $segmentStart = null;

    foreach ($events as $event) {
        $eventTime = new DateTime($event['original_time'], $utc);

        switch ($event['type']) {
            case 'entry':
            case 'resume':
            case 'end_lunch':
                if ($segmentStart === null) {
                    $segmentStart = $eventTime;
                }
                break;
            case 'exit':
            case 'pause':
            case 'start_lunch':
                if ($segmentStart !== null) {
                    $elapsedSeconds += $eventTime->getTimestamp() - $segmentStart->getTimestamp();
                    $segmentStart = null;
                }
                break;
        }
    }

    return (int)round($elapsedSeconds / 60);
}

function map_timer_status_from_type(string $type): ?int {
    switch ($type) {
        case 'entry':
        case 'resume':
        case 'end_lunch':
            return 1;
        case 'pause':
        case 'start_lunch':
            return 2;
        case 'exit':
            return 3;
        default:
            return null;
    }
}

function find_open_timer_entry(PDO $pdo, int $userId, bool $forUpdate = false): ?array {
    $sql = "SELECT * FROM attendance_records WHERE user_id = :user_id AND type = 'entry' AND status IN (1, 2) ORDER BY original_time DESC, id DESC LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_timer_entry_by_id(PDO $pdo, int $timerId, bool $forUpdate = false): ?array {
    $sql = "SELECT * FROM attendance_records WHERE id = :id AND type = 'entry' LIMIT 1";
    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $timerId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function calculate_timer_metrics(PDO $pdo, array $entryRow, ?DateTime $referenceTime = null): array {
    $utc = new DateTimeZone('UTC');
    $reference = $referenceTime ? clone $referenceTime : new DateTime('now', $utc);
    if ($reference->getTimezone()->getName() !== 'UTC') {
        $reference->setTimezone($utc);
    }

    $stmt = $pdo->prepare(
        "SELECT id, type, original_time FROM attendance_records
         WHERE user_id = :user_id AND original_time >= :start_time
         ORDER BY original_time ASC, id ASC"
    );
    $stmt->execute([
        'user_id' => $entryRow['user_id'],
        'start_time' => $entryRow['original_time']
    ]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $elapsedSeconds = 0;
    $segmentStart = null;

    foreach ($events as $event) {
        $eventTime = new DateTime($event['original_time'], $utc);
        if ((int)$event['id'] !== (int)$entryRow['id'] && $event['type'] === 'entry') {
            break;
        }

        switch ($event['type']) {
            case 'entry':
            case 'resume':
            case 'end_lunch':
                if ($segmentStart === null) {
                    $segmentStart = $eventTime;
                }
                break;
            case 'pause':
            case 'start_lunch':
            case 'exit':
                if ($segmentStart !== null) {
                    $elapsedSeconds += max(0, $eventTime->getTimestamp() - $segmentStart->getTimestamp());
                    $segmentStart = null;
                }
                if ($event['type'] === 'exit') {
                    break 2;
                }
                break;
        }
    }

    $runningSince = null;
    if ($segmentStart !== null) {
        $elapsedSeconds += max(0, $reference->getTimestamp() - $segmentStart->getTimestamp());
        $runningSince = clone $segmentStart;
    }

    return [
        'duration_seconds' => (int)$elapsedSeconds,
        'running_since' => $runningSince,
    ];
}

function format_timer_duration_display(int $totalSeconds): string {
    $totalSeconds = max(0, $totalSeconds);
    $hours = (int)floor($totalSeconds / 3600);
    $minutes = (int)floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

?>
