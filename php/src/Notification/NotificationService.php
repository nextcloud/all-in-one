<?php
declare(strict_types=1);

namespace AIO\Notification;

/**
 * A minimal flash-notification system: messages are queued in the session and
 * shown once on the next rendered page, so they survive the redirect that
 * follows a successful POST. Rendered by templates/includes/notifications.twig
 * (styling) and public/notifications.js (auto-dismiss of temporary ones).
 */
class NotificationService {
    private const string SESSION_KEY = 'flash_notifications';

    /**
     * Queue a notification to show once on the next page render.
     *
     * @param bool $temporary When true the notification vanishes after a few
     *                        seconds on the client; otherwise it stays until the
     *                        next navigation.
     */
    public function add(string $message, NotificationType $type = NotificationType::Notice, bool $temporary = false) : void {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $_SESSION[self::SESSION_KEY][] = [
            'message' => $message,
            'type' => $type->value,
            'temporary' => $temporary,
        ];
    }

    /**
     * Return all queued notifications and clear the queue. Rebuilt with explicit
     * casts so the shape is guaranteed regardless of what is in the session.
     *
     * @return list<array{message: string, type: string, temporary: bool}>
     */
    public function consume() : array {
        $stored = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);

        $notifications = [];
        if (is_array($stored)) {
            foreach ($stored as $item) {
                if (is_array($item) && isset($item['message'], $item['type'], $item['temporary'])) {
                    $notifications[] = [
                        'message' => (string) $item['message'],
                        'type' => (string) $item['type'],
                        'temporary' => (bool) $item['temporary'],
                    ];
                }
            }
        }
        return $notifications;
    }
}
