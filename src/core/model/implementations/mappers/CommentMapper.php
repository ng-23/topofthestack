<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/comments/Comment.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/comments/CommentFactory.php';
require_once("UserMapper.php");
require_once("BlogMapper.php");

class CommentMapper extends DataMapper
{
    public const SORT_MOST_LIKES = 1;
    public const SORT_NEWEST_FIRST = 2;
    public const SORT_OLDEST_FIRST = 3;
    public const SORT_NO_ORDER = 4;

    private CommentFactory $comment_factory;
    private UserMapper $user_mapper;
    private BlogMapper $blog_mapper;

    public function __construct(PDO $db_connection, CommentFactory $comment_factory, UserMapper $user_mapper, BlogMapper $blog_mapper)
    {
        parent::__construct($db_connection);
        $this->comment_factory = $comment_factory;
        $this->user_mapper = $user_mapper;
        $this->blog_mapper = $blog_mapper;
    }

    private function commentFromData(array $comment_data): Comment
    {
        $comment = $this->comment_factory->makeComment($comment_data["blog_id"], $comment_data["commentor_id"]);
        $comment->setId($comment_data["comment_id"]);
        $comment->setContents($comment_data["contents"]);

        $comment->setTotalLikes($comment_data["total_likes"]);

        $comment->setCreatedAt(new DateTimeImmutable(date(Comment::DATE_FORMAT, $comment_data["created_at"])));
        $comment->setUpdatedAt(new DateTimeImmutable(date(Comment::DATE_FORMAT, $comment_data["updated_at"])));

        return $comment;
    }

    private function commentsFromData(array $comments_data): array
    {
        $comments = [];

        foreach ($comments_data as $comment_data) {
            $comment = $this->commentFromData($comment_data);
            array_push($comments, $comment);
        }

        return $comments;
    }

    private function sortByToString(int $sort_by): ?String
    {
        $sort_by_str = "";

        switch ($sort_by) {
            case (self::SORT_MOST_LIKES):
                $sort_by_str = "ORDER BY `likes` DESC";
                break;
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

    public function existsById(int $comment_id): bool
    {
        $exists = false;

        if ($this->fetchById($comment_id)) {
            $exists = true;
        }

        return $exists;
    }

    // TODO: consider renaming and changing a little
    public function existsByBlogAndCommentId(int $blog_id, int $comment_id): bool
    {
        $exists = false;

        if ($this->fetchByBlogAndCommentId($blog_id, $comment_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByCommentorAndCommentId(int $commentor_id, int $comment_id): bool
    {
        $exists = false;

        if ($this->fetchByCommentorAndCommentId($commentor_id, $comment_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(Comment $comment)
    {
        if ($this->existsById($comment->getId())) {
            throw new Exception();
        }

        if (!$this->user_mapper->existsById($comment->getCommentorId())) {
            throw new Exception();
        }

        if (!$this->blog_mapper->existsById($comment->getBlogId())) {
            throw new Exception();
        }

        $query = "INSERT INTO `comments` (`comment_id`, `commentor_id`, `blog_id`, `contents`, `total_likes`, 
                `created_at`, `updated_at`) VALUES (:comment_id, :commentor_id, :blog_id, :contents, :total_likes,
                :created_at, :updated_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":comment_id", $comment->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":commentor_id", $comment->getCommentorId(), PDO::PARAM_INT);
        $stmt->bindParam(":blog_id", $comment->getBlogId(), PDO::PARAM_INT);
        $stmt->bindParam(":contents", $comment->getContents(), PDO::PARAM_STR);
        $stmt->bindParam(":total_likes", $comment->getTotalLikes(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $comment->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $comment->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function fetchById(int $comment_id)
    {
        $comment = NULL;

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `comment_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = $this->commentFromData($comment_data);
        }

        return $comment;
    }

    public function fetchByCommentorId(int $commentor_id, int $amount = 1, int $offset = 0, int $sort_by = self::SORT_NO_ORDER)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $sort_by_str = $this->sortByToString($sort_by);

        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `commentor_id` = :commentor_id {$sort_by_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":commentor_id", $commentor_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    // TODO: consider splitting this and similar methods into stuff like fetchByBlogAndNewestFirst or fetchByBlogAndMostLikes
    public function fetchByBlogId(int $blog_id, int $amount = 1, int $offset = 0, int $sort_by = self::SORT_MOST_LIKES)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $sort_by_str = $this->sortByToString($sort_by);

        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `blog_id` = :blog_id {$sort_by_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    public function fetchByBlogAndCommentId(int $blog_id, int $comment_id)
    {
        $comment = NULL;

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `blog_id` = : blog_id AND `comment_id` = :comment_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = $this->commentFromData($comment_data);
        }

        return $comment;
    }

    // TODO: consider changing/deprecating this and similar methods, maybe merge them in some way with related methods...
    public function fetchByCommentorAndCommentId(int $commentor_id, int $comment_id)
    {
        $comment = NULL;

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `commentor_id` = :commentor_id AND `comment_id` = :comment_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":commentor_id", $commentor_id, PDO::PARAM_INT);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = $this->commentFromData($comment_data);
        }

        return $comment;
    }

    // this would be used for a when a user is browsing the comments they've made
    // and maybe they want to see what comments they made on a specific day
    public function fetchByCommentorAndCreatedOn(int $commentor_id, DateTimeImmutable $date, int $amount = 1, int $offset = 0)
    {
        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `commentor_id` = :commentor_id AND `created_at` = :created_at LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":commentor_id", $commentor_id, PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $date->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    // this method and the one above are perhaps a little redundant because of the sort param for the fetchByCommentorId method
    // but they offer more control over sorting by date instead of just newest or oldest first, so may be value in keeping them...
    public function fetchByCommentorAndCreatedBetween(int $commentor_id, DateTimeImmutable $date1, DateTimeImmutable $date2, int $amount = 1, int $offset = 0)
    {
        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `commentor_id` = :commentor_id AND 
                (`created_at` > :date1 AND `created_at` < :date2) LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":commentor_id", $commentor_id, PDO::PARAM_INT);
        $stmt->bindParam(":date1", $date1->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":date2", $date2->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }

        return $comments;
    }

    public function update(Comment $comment)
    {
        if (!$this->existsById($comment->getId())) {
            throw new Exception();
        }

        // the comment exists, but does it exist on this blog?
        if (!$this->existsByBlogAndCommentId($comment->getBlogId(), $comment->getId())) {
            throw new Exception();
        }

        // the comment exists on this blog, but was it written by this commentor (user)?
        if (!$this->existsByCommentorAndCommentId($comment->getCommentorId(), $comment->getId())) {
            throw new Exception();
        }

        $query = "UPDATE `comments` SET `contents` = :contents, `total_likes` = :total_likes,
                `created_at` = :created_at, `updated_at` = :updated_at WHERE `comment_id` = :comment_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":comment_id", $comment->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":contents", $comment->getContents(), PDO::PARAM_STR);
        $stmt->bindParam(":total_likes", $comment->getTotalLikes(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $comment->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $comment->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteById(int $comment_id): ?Comment
    {
        $comment = NULL;

        $query = "DELETE FROM `comments` WHERE `comment_id` = :comment_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":comment_id", $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = $this->commentFromData($comment_data);
        }

        return $comment;
    }

    public function deleteByCommentorId(int $commentor_id)
    {
        $comments = [];

        $query = "DELETE FROM `comments` WHERE `commentor_id` = :commentor_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":commentor_id", $commentor_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    public function deleteByBlogId(int $blog_id)
    {
        $comments = [];

        $query = "DELETE FROM `comments` WHERE `blog_id` = :blog_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }
}
