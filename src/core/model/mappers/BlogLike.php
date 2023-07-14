<?php

declare(strict_types=1);

namespace tots\Mappers;

use tots\Entities\Blogs\BlogLike;
use PDO;
use DateTimeImmutable;
use Exception;

class BlogLikeMapper extends DataMapper
{
    private BlogMapper $blog_mapper;
    private UserMapper $user_mapper;

    public function __construct(PDO $db_connection, BlogMapper $blog_mapper, UserMapper $user_mapper)
    {
        parent::__construct($db_connection);
        $this->blog_mapper = $blog_mapper;
        $this->user_mapper = $user_mapper;
    }

    private function blogLikeFromData(array $like_data): BlogLike
    {
        $like = new BlogLike($like_data["blog_id"], $like_data["user_id"]);
        // have to wrap "created_at" in date() because it's stored as a BIGINT unix timestamp in the DB
        $like->setCreatedAt(new DateTimeImmutable(date(BlogLike::DATE_FORMAT, $like_data["created_at"])));

        return $like;
    }

    private function blogLikesFromData(array $likes_data)
    {
        $likes = [];

        foreach ($likes_data as $like_data) {
            $like = $this->blogLikeFromData($like_data);

            array_push($likes, $like);
        }

        return $likes;
    }

    public function existsByUserAndBlog(int $user_id, int $blog_id)
    {
        $exists = false;

        if ($this->fetchByUserAndBlog($user_id, $blog_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(BlogLike $like)
    {
        if (!$this->user_mapper->existsById($like->getUserId())) {
            throw new Exception();
        }

        if (!$this->blog_mapper->existsById($like->getBlogId())) {
            throw new Exception();
        }

        // you can't like the same blog twice
        if ($this->existsByUserAndBlog($like->getUserId(), $like->getBlogId())) {
            throw new Exception();
        }

        $query = "INSERT INTO `liked_blogs` (`blog_id`, `user_id`, `created_at`) 
                VALUES (:blog_id, :user_id, :created_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $like->getBlogId(), PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $like->getUserId(), PDO::PARAM_INT);
        // have to do this conversion since the DB stores times as BIGINT unix timestamps
        $stmt->bindParam(":created_at", $like->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Returns the like(s) associated with the blog
     */
    public function fetchByBlogId(int $blog_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `blog_id`, `user_id`, `created_at` FROM `liked_blogs`
                WHERE `blog_id` = :blog_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes = $this->blogLikesFromData($likes_data);
        }

        return $likes;
    }

    /**
     * Returns the like(s) associated with the user
     */
    public function fetchByUserId(int $user_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `blog_id`, `user_id`, `created_at` FROM `liked_blogs`
                WHERE `user_id` = :user_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $likes = $this->blogLikesFromData($likes_data);
        }

        return $likes;
    }

    public function fetchByUserAndBlog(int $user_id, int $blog_id)
    {
        $like = NULL;

        $query = "SELECT `blog_id`, `user_id`, `created_at` FROM `liked_blogs`
                WHERE `user_id` = :user_id AND `blog_id` = :blog_id LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $like_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $like = $this->blogLikesFromData($like_data);
        }

        return $like;
    }

    // TODO: do we even need an update method? what's there to update with a like?

    public function deleteByUserId(int $user_id)
    {
        $likes = [];

        $query = "DELETE FROM `liked_blogs`
                WHERE `user_id` = :user_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $likes = $this->blogLikesFromData($likes_data);
        }

        return $likes;
    }

    public function deleteByUserAndBlog(int $user_id, int $blog_id)
    {
        $like = NULL;

        $query = "DELETE FROM `liked_blogs`
                WHERE `user_id` = :user_id AND `blog_id` = :blog_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $like_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $like = $this->blogLikesFromData($like_data);
        }

        return $like;
    }
}
