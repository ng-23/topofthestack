<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/notifications/Notification.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/notifications/NotificationFactory.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/User.php';


class NotificationMapper extends DataMapper
{
    public const SORT_NEWEST_FIRST = 1;
    public const SORT_OLDEST_FIRST = 2;
    public const SORT_NO_ORDER = 3;
    public const IMG_DIR = "resources/images";

    private NotificationFactory $notification_factory;
    private UserMapper $user_mapper;

    public function __construct(PDO $db_connection, NotificationMapper $notification_factory, UserMapper $user_mapper)
    {
        parent::__construct($db_connection);
        $this->notification_factory = $notification_factory;
        $this->user_mapper = $user_mapper;
    }

    private function notificationFromData(array $notification_data): Notification
    {
        $notification = $this->notification_factory->makeNotification($notification_data["user_id"]);

        $notification->setType($notification_data["type"]);
        $notification->setHeader($notification_data["header"]);
        $notification->setBody($notification_data["body"]);
        $notification->setId($notification_data["notification_id"]);
        $notification->setSeen((bool)$notification["seen"]);
        $notification->setImageUri($notification_data["image_uri"]);
        $notification->setCreatedAt(new DateTimeImmutable(date(Notification::DATE_FORMAT, $notification_data["created_at"])));

        return $notification;
    }

    private function notificationsFromData(array $notifications_data): array
    {
        $notifications = [];

        foreach ($notifications_data as $notification_data) {
            $notification = $this->notificationFromData($notification_data);
            array_push($notifications, $notification);
        }

        return $notifications;
    }

    private function checkUserSettingsAllowNotification(int $user_id, int $notification_type): bool
    {
        // TODO: consider redesigning this method and how notification settings are handled
        // as well as notification type constants

        $allowed = false;

        $user_notifying = $this->user_mapper->fetchById($user_id);
        $user_settings = $user_notifying->getSettings();

        if ($notification_type == Notification::VALID_TYPES["TYPE_SYSTEM"]) {
            $allowed = true;
        } elseif ($notification_type == Notification::VALID_TYPES["TYPE_BLOG_COMMENT"] and $user_settings["notify_blog_comment"]) {
            $allowed = true;
        } elseif ($notification_type == Notification::VALID_TYPES["TYPE_FOLLOW_REQ"] and $user_settings["notify_follow_request"]) {
            $allowed = true;
        } elseif ($notification_type == Notification::VALID_TYPES["TYPE_BLOG_FROM_FOLLOWING"] and $user_settings["notify_blog_from_following"]) {
            $allowed = true;
        }

        return $allowed;
    }

    private function sortByToString(int $sort_by): ?String
    {
        $sort_by_str = "";

        switch ($sort_by) {
            case (self::SORT_NEWEST_FIRST):
                $sort_by_str = "ORDER BY `created_at` DESC";
                break;
            case (self::SORT_OLDEST_FIRST):
                $sort_by_str = "ORDER BY `created_at` ASC";
                break;
            case (self::SORT_NO_ORDER):
                $sort_by_str = "";
                break;
            default:
                throw new Exception();
        }

        return $sort_by_str;
    }

    private function saveImage(Notification $notification)
    {
        $img_uri = $notification->getImageUri();
        $img_file = fopen($_SERVER["DOCUMENT_ROOT"] . "/{$img_uri}", "w");
        fwrite($img_file, $notification->getImageData());
        fclose($img_file);
    }

    private function deleteImage(Notification $notification)
    {
        $img_uri = $notification->getImageUri();
        $img_file = $_SERVER["DOCUMENT_ROOT"] . "/{$img_uri}";;
        unlink($img_file);
    }

    public function existsById(int $notification_id): bool
    {
        $exists = false;

        if ($this->fetchById($notification_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByUserAndNotificationId(int $user_id, int $notification_id): bool
    {
        $exists = false;

        if ($this->fetchByUserAndNotificationId($user_id, $notification_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByImageUri(String $image_uri): bool
    {

        $exists = false;

        $query = "SELECT `notification_id` FROM `notifications` WHERE `image_uri` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $image_uri, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByImageUriAndId(String $image_uri, int $notification_id): bool
    {
        $exists = false;

        $query = "SELECT `notification_id` FROM `notifications` WHERE `image_uri` = :img_uri AND `notification_id` = :id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":img_uri", $image_uri, PDO::PARAM_STR);
        $stmt->bindParam(":id", $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    public function save(Notification $notification)
    {
        /**
         * Should the mapper be responsible for determine if the notification is allowed to be sent or not?
         * like if the user has disabled notifications from follow requests, and we're trying to insert a notification for a follow request
         * should the mapper be the one to throw an error indicating that setting?
         * if not the mapper, it would have to be a service...
         * i think it should be the mapper, since ultimately the mapper is what inserts the record
         * and it shouldn't be designed such that you have to go through a service first to check the receiving user's settings
         */

        if ($this->existsById($notification->getId())) {
            throw new Exception();
        }

        if (!$this->user_mapper->existsById($notification->getUserId())) {
            throw new Exception();
        }

        // check that user's settings permit sending this type of notification
        /**
         * is this a violation of single responsibility principle?
         * in other words, should we not be doing this check in this method? should it be done in (for example) a service instead?
         * i ask this because eventually I will need a mapper for the followers/following table
         * and when inserting a record into that table (aka, initiating a follower request), should that also send a notification
         * if permitted by the followee's settings?
         * i can see both sides, but personally I kind of lean towards yes, it should, since it just seems natural/logical
         * still, it's called the save method, not the saveAndSendANotificationIfPossible method...
         * i think this check below is actually different from aforementioned scenario though, since it's business logic (a check) and
         * not an action (sending something)
         * so it's fine to check settings here, but when doing something like sending a notification, that should be done separately from
         * the insert method
         * also, this applies to the blog and comment mappers - need to send notifications when a blog is posted to followers and send
         * a notification when a comment is made on someone's blog
         */
        if (!$this->checkUserSettingsAllowNotification($notification->getUserId(), $notification->getType())) {
            throw new Exception();
        }

        $default_img_uri = self::IMG_DIR . "/" . Notification::DEFAULT_IMG;
        if ($notification->getImageUri() == $default_img_uri) {
            // probably shouldn't be using _SERVER like this
            // makes testing harder because now the web server has to be online
            $default_img_data = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$default_img_uri}");
            if ($default_img_data != $notification->getImageData()) {
                throw new Exception();
            }
        } else {
            if (!$this->existsByImageUri($notification->getImageUri())) {
                throw new Exception();
            }
        }

        $query = "INSERT INTO `notifications` (`notification_id`, `user_id`, `typ`, `header`, `body`, `image_uri`, `seen`, `created_at`) 
                VALUES (:notification_id, :user_id, :type, :header, :body, :image_uri, :seen, :created_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":notification_id", $notification->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $notification->getUserId(), PDO::PARAM_INT);
        $stmt->bindParam(":type", $notification->getType(), PDO::PARAM_INT);
        $stmt->bindParam(":header", $notification->getHeader(), PDO::PARAM_STR);
        $stmt->bindParam(":body", $notification->getBody(), PDO::PARAM_STR);
        $stmt->bindParam(":image_uri", $notification->getImageUri(), PDO::PARAM_STR);
        $stmt->bindParam(":seen", (int)$notification->wasSeen(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $notification->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        $this->saveImage($notification);
    }

    public function fetchById(int $notification_id): ?Notification
    {
        $notification = NULL;

        $query = "SELECT `notification_id`, `user_id`, `type`, `header`, `body`, 
                `image_uri`, `seen`, `created_at` FROM `notifications`
                WHERE `notification_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification = $this->notificationFromData($notification_data);
        }
        return $notification;
    }

    public function fetchByUserId(int $user_id, int $amount = 1, int $offset = 0, bool $exclude_seen = false, int $sort_by = self::SORT_NEWEST_FIRST): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $notifications = [];

        $query = "SELECT `notification_id`, `user_id`, `type`, `header`, `body`, 
                `image_uri`, `seen`, `created_at` FROM `notifications`
                WHERE `user_id` = ?";

        if ($exclude_seen) {
            $query = $query . " AND `seen` = 0";
        }

        $sort_by_str = $this->sortByToString($sort_by);
        $query = $query . $sort_by_str;

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $notifications_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = $this->notificationsFromData($notifications_data);
        }

        return $notifications;
    }

    // when would this be used?
    public function fetchByUserAndNotificationId(int $user_id, int $notification_id): ?Notification
    {
        $notification = NULL;

        $query = "SELECT `notification_id`, `user_id`, `type`, `header`, `body`, 
                `image_uri`, `seen`, `created_at` FROM `notifications`
                WHERE `user_id` = :user_id AND `notification_id` = :notification_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":notification_id", $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification = $this->notificationFromData($notification_data);
        }

        return $notification;
    }

    // TODO: is this really needed? consider deprecating...
    // should you really be able to update notifications at all?
    public function update(Notification $notification)
    {
        if (!$this->existsById($notification->getId())) {
            throw new Exception();
        }

        // a notification with this ID exists, but is it associated with this user?
        if (!$this->existsByUserAndNotificationId($notification->getUserId(), $notification->getId())) {
            throw new Exception();
        }

        $default_img_uri = self::IMG_DIR . "/" . Notification::DEFAULT_IMG;
        $current_notif_version = $this->fetchById($notification->getId());
        if ($notification->getImageUri() == $default_img_uri) {
            $default_img_data = file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$default_img_uri}");
            if ($default_img_data != $notification->getImageData()) {
                throw new Exception();
            }
        } else {
            if (!$this->existsByImageUriAndId($notification->getImageUri(), $notification->getId())) {
                if ($this->$notification($notification->getImageUri())) {
                    throw new Exception();
                }
                $this->deleteImage($current_notif_version); // don't delete the image associated with default uri
            }
        }

        // you cannot change the notification or user id, nor the notification type
        // probably shouldn't be able to update created_at (this goes for most other mappers too)
        $query = "UPDATE `notifications` SET `header` = :header,`body` = :body, `image_uri` = :img_uri, 
                `seen` = :seen, `created_at` = :created_at WHERE `notification_id` = :notification_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":notification_id", $notification->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":header", $notification->getHeader(), PDO::PARAM_STR);
        $stmt->bindParam(":body", $notification->getBody(), PDO::PARAM_STR);
        $stmt->bindParam(":image_uri", $notification->getImageUri(), PDO::PARAM_STR);
        $stmt->bindParam(":seen", (int)$notification->wasSeen(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $notification->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        $this->saveImage($notification);
    }

    public function deleteById(int $notification_id)
    {
        $notification = NULL;

        $query = "DELETE FROM `notifications` WHERE `notification_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification = $this->notificationFromData($notification_data);
        }

        return $notification;
    }

    public function deleteByUserId(int $user_id)
    {
        $notifications = [];

        $query = "DELETE FROM `notifications` WHERE `user_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $notifications_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notifications = $this->notificationsFromData($notifications_data);
        }

        return $notifications;
    }

    public function deleteByUserAndNotificationId(int $user_id, int $notification_id)
    {
        $notification = NULL;

        $query = "DELETE FROM `notifications` WHERE `user_id` = :user_id AND `notification_id` = :notification_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":notification_id", $notification_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $notification_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $notification = $this->notificationFromData($notification_data);
        }

        return $notification;
    }
}
