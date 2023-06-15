<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/users/Follower.php';
require_once("UserMapper.php");

class FollowersMapper extends DataMapper
{
    private UserMapper $user_mapper;

    public function __construct(PDO $db_connection, UserMapper $user_mapper)
    {
        parent::__construct($db_connection);
        $this->user_mapper = $user_mapper;
    }

    private function followerFromData(array $follower_data): Follower
    {
        $follower = new Follower($follower_data["follower_id"], $follower_data["followee_id"]);
        $follower->setCreatedAt(new DateTimeImmutable(date(Follower::DATE_FORMAT, $follower_data["created_at"])));

        return $follower;
    }

    private function followersFromData(array $followers_data): array
    {
        $followers = [];

        foreach ($followers_data as $follower_data) {
            $follower = $this->followerFromData($follower_data);
            array_push($followers, $follower);
        }

        return $followers;
    }

    public function isFollowing(int $follower_id, int $followee_id): bool
    {
        $following = false;

        if ($follower_id != $followee_id) {
            $follower = $this->fetchByFolloweeAndFollowerId($followee_id, $follower_id);
            if ($follower) {
                $following = true;
            }
        }

        return $following;
    }

    public function save(Follower $follower)
    {
        if (!$this->user_mapper->existsById($follower->getFolloweeId())) {
            throw new Exception();
        }

        if (!$this->user_mapper->existsById($follower->getFollowerId())) {
            throw new Exception();
        }

        $query = "INSERT INTO `followers` (`follower_id`, `followee_id`, `created_at`) 
                VALUES (:follower_id, :followee_id, :created_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":follower_id", $follower_id, PDO::PARAM_INT);
        $stmt->bindParam(":followee_id", $followee_id, PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $follower->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function fetchByFolloweeId(int $followee_id): array
    {
        $followers = [];

        $query = "SELECT `follower_id`, `followee_id`, `created_at`
                FROM `followers` WHERE `followee_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $followee_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $followers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $followers = $this->followersFromData($followers_data);
        }

        return $followers;
    }

    public function fetchByFolloweeAndFollowerId(int $followee_id, int $follower_id): ?Follower
    {
        $follower = NULL;

        $query = "SELECT `follower_id`, `followee_id`, `created_at`
                FROM `followers` WHERE `followee_id` = :followee_id AND `follower_id` = :follower_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":followee_id", $followee_id, PDO::PARAM_INT);
        $stmt->bindParam(":follower_id", $follower_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $follower_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $follower = $this->followerFromData($follower_data);
        }

        return $follower;
    }

    // TODO: consider adding update method if needed

    public function deleteByFolloweeId(int $followee_id)
    {
        $followers = [];

        $query = "DELETE FROM `followers` WHERE `followee_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":followee_id", $followee_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $followers_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $followers = $this->followersFromData($followers_data);
        }

        return $followers;
    }

    public function deleteByFolloweeAndFollowerId(int $followee_id, int $follower_id)
    {
        $follower = NULL;

        $query = "DELETE FROM `followers` WHERE `followee_id` = :followee_id AND `follower_id` = :follower_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":followee_id", $followee_id, PDO::PARAM_INT);
        $stmt->bindParam(":follower_id", $follower_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $follower_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $follower = $this->followerFromData($follower_data);
        }

        return $follower;
    }
}
