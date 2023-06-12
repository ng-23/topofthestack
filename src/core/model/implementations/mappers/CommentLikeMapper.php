<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/comments/CommentLike.php';
require_once("UserMapper.php");
require_once("CommentMapper.php");

class CommentLikeMapper extends DataMapper
{
    private UserMapper $user_mapper;
    private CommentMapper $comment_mapper;

    public function __construct(PDO $db_connection, UserMapper $user_mapper, CommentMapper $comment_mapper)
    {
        parent::__construct($db_connection);
        $this->user_mapper = $user_mapper;
        $this->comment_mapper = $comment_mapper;
    }

    private function commentLikeFromData(array $like_data): CommentLike
    {
        $like = new CommentLike($like_data["comment_id"], $like_data["user_id"]);
        $like->setCreatedAt(new DateTimeImmutable(date(CommentLike::DATE_FORMAT, $like_data["created_at"])));

        return $like;
    }

    private function commentLikesFromData(array $likes_data): array
    {
        $likes = [];

        foreach ($likes_data as $like_data) {
            $like = $this->commentLikeFromData($like_data);

            array_push($likes, $like);
        }

        return $likes;
    }

    public function existsByUserAndCommentId(int $user_id, int $comment_id): bool
    {
        $exists = false;

        if ($this->fetchByUserAndCommentId($user_id, $comment_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(CommentLike $like)
    {
        if (!$this->user_mapper->existsById($like->getUserId())) {
            throw new Exception();
        }

        if (!$this->comment_mapper->existsById($like->getCommentId())) {
            throw new Exception();
        }

        // you can't like the same comment twice
        if ($this->existsByUserAndCommentId($like->getUserId(), $like->getCommentId())) {
            throw new Exception();
        }

        $query = "INSERT INTO `liked_comments` (`comment_id`, `user_id`, `created_at`) 
                VALUES (:comment_id, :user_id, :created_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":comment_id", $like->getCommentId(), PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $like->getUserId(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $like->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Returns the like(s) associated with the comment
     */
    public function fetchByCommentId(int $comment_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `comment_id`, `user_id`, `created_at` FROM `liked_comments`
                WHERE `comment_id` = :comment_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes = $this->commentLikesFromData($likes_data);
        }

        return $likes;
    }

    /**
     * Returns the like(s) made by the user
     */
    public function fetchByUserId(int $user_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `comment_id`, `user_id`, `created_at` FROM `liked_comments`
                WHERE `user_id` = :user_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes = $this->commentLikesFromData($likes_data);
        }

        return $likes;
    }

    public function fetchByUserAndCommentId(int $user_id, int $comment_id)
    {
        $like = NULL;

        $query = "SELECT `comment_id`, `user_id`, `created_at` FROM `liked_comments`
                WHERE `user_id` = :user_id AND `comment_id` = :comment_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $like_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $like = $this->commentLikesFromData($like_data);
        }

        return $like;
    }

    // consider an update method, but I really don't think it's necessary...

    public function deleteByUserId(int $user_id)
    {
        $likes = [];

        $query = "DELETE FROM `liked_comments`
                WHERE `user_id` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $likes = $this->commentLikesFromData($likes_data);
        }

        return $likes;
    }

    public function deleteByUserAndComment(int $user_id, int $comment_id)
    {
        $like = NULL;

        $query = "DELETE FROM `liked_comments`
                WHERE `user_id` = :user_id AND `comment_id` = :comment_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $like_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $like = $this->commentLikeFromData($like_data);
        }

        return $like;
    }
}
