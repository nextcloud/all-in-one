<?php
declare(strict_types=1);

namespace AIO\Notification;

enum NotificationType: string {
    case Notice = 'notice';   // friendly (light green) — informational / success
    case Warning = 'warning'; // orange-leaning yellow (amber) — something needs attention
}
