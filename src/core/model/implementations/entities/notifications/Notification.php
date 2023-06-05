<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class Notification extends Entity {
    private int $user_id;
    private String $header, $body;
    private ?String $image_uri;
    private int $seen;
    private int $created_at;

    private const MAX_HEADER_LEN = 64;
    private const MAX_BODY_LEN = 120;
    private const MAX_URI_LEN = 255;

    public function __construct(int $user_id, String $header, String $body, ?int $notification_id = NULL) {
        $this->id = $notification_id;
        $this->user_id = $user_id;
        $this->header = $this->setHeader($header);
        $this->body = $this->setBody($body);
        $this->seen = 0;
        $this->created_at = time();
        $this->image_uri = NULL;
    }

    public function getUserId(): int {
        return $this->user_id;
    }

    public function getHeader(): String {
        return $this->header;
    }

    public function getBody(): String {
        return $this->body;
    }

    public function getImageUri(): String {
        return $this->image_uri;
    }

    public function wasSeen(): int {
        return $this->seen;
    }

    public function getCreatedAt(): int {
        return $this->created_at;
    }
    
    public function setSeen(int $seen = 0) {
        if($seen < 0 or $seen > 1) {
            throw new Exception();
        }
        $this->seen = $seen;
    }

    public function setImageUri(?String $uri = NULL) {
        if($uri) {
            $uri_len = strlen($uri);
            if($uri_len == 0 or $uri_len > self::MAX_URI_LEN) {
                throw new Exception();
            }
        }
        
        $this->image_uri = $uri;
    }

    public function setHeader(String $header) {
        $header_len = strlen($header);
        if($header_len == 0 or $header_len > self::MAX_HEADER_LEN) {
            throw new Exception();
        }
        $this->header = $header;
    }

    public function setBody(String $body) {
        $body_len = strlen($body);
        if($body_len == 0 or $body_len > self::MAX_BODY_LEN) {
            throw new Exception();
        }
        $this->body = $body;
    }

    public function setCreatedAt(?int $unix_timestamp = NULL) {
        if($unix_timestamp) {
            $this->created_at = $unix_timestamp;
        }
        else {
            $this->created_at = time();
        }
    }

    
    
}