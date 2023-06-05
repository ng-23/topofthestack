<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class Tag extends Entity{
    public const DATE_FORMAT = "Y-m-d";
    public const MIN_NAME_LEN = 3;
    public const MAX_NAME_LEN = 15;
    public const VALID_NAME_REGEX = "#^[0-9a-zA-Z_.-]{" . self::MIN_NAME_LEN . "," . self::MAX_NAME_LEN . "}$#";

    private String $name;
    private int $total_tagged_with;
    private int $tagged_with_today;
    private DateTimeImmutable $created_at, $updated_at;

    public function __construct(String $name, ?int $tag_id = NULL) {
        $this->id = $tag_id;
        $this->total_tagged_with = $this->tagged_with_today = 0;
        $this->setName($name);
        $this->created_at = $this->setCreatedAt(NULL);
        $this->updated_at = $this->setUpdatedAt(NULL);
    }

    public function getName(): String {
        return $this->name;
    }

    public function getTotalTaggedWith(): int {
        return $this->total_tagged_with;
    }

    public function getTaggedWithToday(): int {
        return $this->tagged_with_today;
    }

    public function getCreatedAt(): DateTimeImmutable {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeImmutable {
        return $this->updated_at;
    }

    public function setName(String $name) {
        $is_valid = preg_match(self::VALID_NAME_REGEX, $name);
        if(!$is_valid) {
            throw new Exception();
        }

        $this->name = $name;
    }

    public function setTotalTaggedWith(int $tagged_with) {
        if($tagged_with < 0) {
            throw new Exception();
        }
        $this->total_tagged_with = 0;
    }

    public function setTaggedWithToday(int $tagged_with) {
        if($tagged_with < 0) {
            throw new Exception();
        }
        $this->tagged_with_today = 0;
    }

    public function setCreatedAt(?DateTimeImmutable $date) {
        if($date) {
            $this->created_at = $date;
        }

        else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }

    public function setUpdatedAt(?DateTimeImmutable $date) {
        if($date) {
            $this->updated_at = $date;
        }

        else {
            $this->updated_at = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->created_at->format(self::DATE_FORMAT));
        }
    }

    public function toArray(): array {
        $reflect = new ReflectionClass($this);
        $properties = $reflect->getProperties();
        $data = [];
        foreach($properties as $property) {
            $property_name = $property->getName();
            $data[$property_name] = $this->$property_name;
        }
        return $data;
    }
}