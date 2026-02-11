<?php
declare(strict_types=1);

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

function format_timer_duration_display(int $totalSeconds): string {
    $totalSeconds = max(0, $totalSeconds);
    $hours = (int)floor($totalSeconds / 3600);
    $minutes = (int)floor(($totalSeconds % 3600) / 60);
    $seconds = $totalSeconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
