<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class Follower extends Entity
{
    // here, id field is implied to be that of the follower

    private int $followee_id; // this is who the follower is following
    private DateTimeImmutable $created_at;

    public function __construct(int $follower_id, int $followee_id)
    {
        $this->id = $follower_id;
        $this->setFolloweeId($followee_id);
        $this->setCreatedAt(NULL);
    }

    // adding this just for clarity sake, may remove...
    public function getFollowerId(): int
    {
        return $this->getId();
    }

    public function getFolloweeId(): int
    {
        return $this->followee_id;
    }

    public function getCreatedAt(): DateTimeImmutable {
        return $this->created_at;
    }

    // also adding just for clarity sake
    public function setId(int $follower_id)
    {
        $this->setFollowerId($follower_id);
    }

    public function setFollowerId(int $follower_id)
    {
        // cannot follow yourself
        if ($follower_id == $this->followee_id) {
            throw new Exception();
        }

        $this->id = $follower_id;
    }

    public function setFolloweeId(int $followee_id)
    {
        if ($followee_id == $this->id) {
            throw new Exception();
        }

        $this->followee_id = $followee_id;
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
