<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';
require_once realpath(dirname(__FILE__) . '../../../../../') . '/utils/is_image.php';

class Notification extends Entity
{
    public const VALID_TYPES = [
        "TYPE_SYSTEM" => 0,
        "TYPE_BLOG_COMMENT" => 1,
        "TYPE_FOLLOW_REQ" => 2,
        "TYPE_BLOG_FROM_FOLLOWING" => 3,
    ];

    public const DATE_FORMAT = "Y-m-d H:i:s";
    public const MAX_HEADER_LEN = 64;
    public const MAX_BODY_LEN = 150;
    public const DEFAULT_ICON = "logo_small1.png";

    private int $user_id;
    private int $type;
    private String $header, $body;
    private String $icon_uri, $icon_img_data;
    private bool $seen;
    private DateTimeImmutable $created_at;

    public function __construct(int $user_id, int $type, String $header, String $body, ?int $notification_id = NULL)
    {
        $this->id = $notification_id;
        $this->setType($type);
        $this->user_id = $user_id;
        $this->header = $this->setHeader($header);
        $this->body = $this->setBody($body);
        $this->seen = false;
        $this->setCreatedAt(NULL);
        $this->setIconUri(NULL);
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getHeader(): String
    {
        return $this->header;
    }

    public function getBody(): String
    {
        return $this->body;
    }

    public function getIconUri(): String
    {
        return $this->icon_uri;
    }

    public function getIconImageData(): String
    {
        return $this->icon_img_data;
    }

    public function wasSeen(): bool
    {
        return $this->seen;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setId(?int $notification_id)
    {
        $this->id = $notification_id;
    }

    public function setType(int $type)
    {
        $is_valid = false;

        foreach (self::VALID_TYPES as $valid_type) {
            if ($type == $valid_type) {
                $this->type = $type;
                $is_valid = true;
                break;
            }
        }

        if (!$is_valid) {
            throw new Exception();
        }
    }

    public function setSeen(bool $seen)
    {
        $this->seen = $seen;
    }

    public function setIconUri(?String $uri)
    {
        if ($uri) {
            $uri_len = strlen($uri);
            if ($uri_len < PublishedBlog::MIN_URI_LEN or $uri_len > PublishedBlog::MAX_URI_LEN) {
                throw new Exception();
            }
            $this->icon_uri = $uri;
        } else {
            $this->icon_uri = NotificationMapper::ICON_DIR . "/" . self::DEFAULT_ICON;

            $this->setIconImageData(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$this->icon_uri}"));
        }
    }

    public function setIconImageData(String $icon_img_data)
    {
        // TODO: consider reimplmenting icon file size check...

        if (!is_png($icon_img_data) or !is_jpg($icon_img_data)) {
            throw new Exception();
        }

        $this->icon_img_data = $icon_img_data;
    }

    public function setHeader(String $header)
    {
        $header_len = strlen($header);
        if ($header_len == 0 or $header_len > self::MAX_HEADER_LEN) {
            throw new Exception();
        }
        $this->header = $header;
    }

    public function setBody(String $body)
    {
        $body_len = strlen($body);
        if ($body_len == 0 or $body_len > self::MAX_BODY_LEN) {
            throw new Exception();
        }

        $this->body = $body;
    }

    public function setCreatedAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->created_at = $date;
        } else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }

    public function toArray(): array
    {
        $reflect = new ReflectionClass($this);
        $properties = $reflect->getProperties();
        $data = [];
        foreach ($properties as $property) {
            $property_name = $property->getName();
            $data[$property_name] = $this->$property_name;
        }
        return $data;
    }
}
