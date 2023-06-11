<?php

declare(strict_types=1);

require_once("Notification.php");

class NotificationFactory
{
    public const DEFAULT_TYPE = Notification::VALID_TYPES["TYPE_SYSTEM"];
    public const DEFAULT_HEADER = "System Message";
    public const DEFAULT_BODY = "System alert!";

    public function makeNotification(int $user_id)
    {
        return new Notification($user_id, self::DEFAULT_TYPE, self::DEFAULT_HEADER, self::DEFAULT_BODY);
    }
}
