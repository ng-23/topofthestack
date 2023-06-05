<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/notifications/Notification.php';

class NotificationMapper extends DataMapper {
    public function notificationFromData(array $notification_data): Notification {
        $notification = new Notification($notification_data["user_id"], $notification_data["header"], 
        $notification_data["body"], $notification_data["notification_id"]);
        
        $notification->setSeen($notification_data["seen"]);
        $notification->setImageUri($notification_data["image_uri"]);
        // is it safe to assume this is a Unix timestamp or is there some check we should perform?
        $notification->setCreatedAt($notification_data["created_at"]);

        return $notification;
    }

    public function save() {

    }

    public function fetchById(int $notification_id): ?Notification {
        $notification = NULL;

        $query = "SELECT `notification_id`, `user_id`, `header`, `body`, 
                `image_uri`, `seen`, `created_at` FROM notifications
                WHERE notification_id=?";
        
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification = $this->notificationFromData($notification_data);
        }
        return $notification;
    }

    public function fetchByUserId(int $user_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $notifications = [];

        $query = "SELECT `notification_id`, `user_id`, `header`, `body`, 
                `image_uri`, `seen`, `created_at` FROM notifications
                WHERE `user_id` = ?";
        
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $notifications_data = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach($notifications_data as $notification_data) {
                $notification = $this->notificationFromData($notification_data);
                array_push($notifications, $notification);
            }
        }
        return $notifications;
    }

    public function update() {
        
    }

}