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
    public const MIN_IMG_URI_LEN = 5;
    public const MAX_IMG_URI_LEN = 255;
    public const DEFAULT_IMG = "logo_small1.png";

    private int $user_id;
    private int $type;
    private String $header, $body;
    private String $image_uri, $image_data;
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
        $this->setImageUri(NULL);
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

    public function getImageUri(): String
    {
        return $this->image_uri;
    }

    public function getImageData(): String
    {
        return $this->image_data;
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

    public function setImageUri(?String $uri)
    {
        if ($uri) {
            $uri_len = strlen($uri);
            if ($uri_len < self::MIN_IMG_URI_LEN or $uri_len > self::MAX_IMG_URI_LEN) {
                throw new Exception();
            }
            $this->image_uri = $uri;
        } else {
            $this->image_uri = NotificationMapper::IMG_DIR . "/" . self::DEFAULT_IMG;

            $this->setImageData(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/{$this->image_uri}"));
        }
    }

    /**
     * bug: someone could set the default uri
     * then overwrite its image data and save
     * need to prevent that somehow
     * bug exists in other classes with image URI stuff, but fix it later...
     */
    public function setImageData(String $image_data)
    {
        $size_in_bytes = strlen($image_data);
        if ($size_in_bytes == 0 or $size_in_bytes > UserMapper::PFP_MAX_FILE_SIZE_MB * User::BYTES_IN_MB) {
            throw new Exception();
        }

        if (!is_png($image_data) or !is_jpg($image_data)) {
            throw new Exception();
        }

        $this->image_data = $image_data;
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
