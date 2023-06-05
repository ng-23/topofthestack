<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/comments/CommentLike.php';

class CommentLikeMapper extends DataMapper {
    public function commentLikeFromData(array $like_data): CommentLike {
        $like = new CommentLike($like_data["comment_id"], $like_data["user_id"]);
        $like->setCreatedAt(DateTimeImmutable::createFromFormat(CommentLike::DATE_FORMAT, $like_data["created_at"]));
        
        return $like;
    }

    
    /**
     * Returns the like(s) associated with the comment
     */
    public function fetchByCommentId(int $comment_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `comment_id`, `user_id`, `created_at` FROM liked_comments
                WHERE comment_id=? LIMIT ? OFFSET ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $comment_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach($likes_data as $like_data) {
                $like = $this->commentLikeFromData($like_data);
                array_push($likes, $like);
            }
        }
        return $likes;
    }

    /**
     * Returns the like(s) made by the user
     */
    public function fetchByUserId(int $user_id, int $amount = 1, int $offset = 0) {
        if($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $likes = [];

        $query = "SELECT `comment_id`, `user_id`, `created_at` FROM liked_comments
                WHERE user_id=? LIMIT ? OFFSET ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $amount, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();

        if($stmt->rowCount() >= 1) {
            $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach($likes_data as $like_data) {
                $like = $this->commentLikeFromData($like_data);
                array_push($likes, $like);
            }
        }
        return $likes;
    }
}