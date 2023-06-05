<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/comments/Comment.php';

class CommentMapper extends DataMapper {
    public function commentFromData(array $comment_data): Comment {
        $comment = new Comment($comment_data["commentor_id"], $comment_data["blog_id"], $comment_data["text"],
        $comment_data["comment_id"]);

        $comment->setTotalLikes($comment_data["total_likes"]);
        
        $comment->setCreatedAt(DateTimeImmutable::createFromFormat(Comment::DATE_FORMAT, $comment_data["created_at"]));
        $comment->setUpdatedAt(DateTimeImmutable::createFromFormat(Comment::DATE_FORMAT, $comment_data["updated_at"]));
        
        return $comment;
    }

    public function commentsFromData(array $comments_data): array {
        $comments = [];
        foreach($comments_data as $comment_data) {
            $comment = $this->commentFromData($comment_data);
            array_push($comments, $comment);
        }
        return $comments;
    }

    public function save(Comment $comment) {

    }

    public function update(Comment $comment) {

    }

    public function fetchById(int $comment_id) {
        $comment = NULL;

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE comment_id=?";
        
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $comment_id, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            $comment_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $comment = $this->commentFromData($comment_data);
        }

        return $comment;
    }

    public function fetchByCommentorId(int $commentor_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE commentor_id=? LIMIT ? OFFSET ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $commentor_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    public function fetchByBlogId(int $blog_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, 
                `total_likes`, `created_at`, `updated_at` FROM `comments`
                WHERE `blog_id`=? LIMIT ? OFFSET ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    /**
     * Returns the comment(s) with the most likes on a blog in descending order
     * Not gonna add a $sort_order param because it doesn't make sense to want the least liked comments (which would just be 0)
     */
    public function fetchByLikes(int $blog_id, int $amount = 1) {
        if($amount < 0) {
            throw new Exception();
        }

        $comments = [];

        $query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, `total_likes`, 
                `created_at`, `updated_at` FROM `comments` WHERE `blog_id` = :blog_id
                ORDER BY `total_likes` DESC LIMIT :lim";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_d", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }
        return $comments;
    }

    public function fetchByCreatedAt(int $blog_id, String $when = "on", DateTimeImmutable $date1, ?DateTimeImmutable $date2, int $amount = 1, int $offset = 0, ?String $sort_order = NULL) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        if($sort_order) {
            $sort_order = strtoupper($sort_order);
            if($sort_order != "ASC" || $sort_order != "DESC") {
                throw new Exception(); // big TODO: throw more specific exceptions
            }
        }

        $base_query = "SELECT `comment_id`, `commentor_id`, `blog_id`, `contents`, `total_likes`, 
                    `created_at`, `updated_at` FROM `comments` WHERE `blog_id` = :blog_id 
                    AND :condition ORDER BY `created_at` {$sort_order} LIMIT :lim OFFSET :off";

        $query = "";

        $stmt = NULL;

        $comments = [];

        if (!$date2) {
            $date2 = new DateTimeImmutable("now");
        }

        if ($when == "on" || $when == "before" || $when == "after") {
            $condition = "(`created_at` = :date1 OR `created_at` = :date2)";
        }

        elseif ($when == "between") {
            $condition = "(`created_at > :date1 AND `created_at` < :date2)";
        }

        else {
            throw new Exception();
        }

        $query = str_replace(":condition", $condition, $base_query);

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":date1", $date1, PDO::PARAM_STR);
        $stmt->bindParam(":date2", $date2, PDO::PARAM_STR);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $comments_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $comments = $this->commentsFromData($comments_data);
        }

        return $comments;
    }

    public function delete(Comment $comment) {

    }
}