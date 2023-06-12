<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class CommentLike extends Entity
{
    public const DATE_FORMAT = "Y-m-d H:i:s";

    // here, id field is implied to be the comment id...

    private int $user_id; // id of the user liking the comment
    private DateTimeImmutable $created_at;

    public function __construct(int $comment_id, int $user_id)
    {
        $this->id = $comment_id;
        $this->user_id = $user_id;

        $this->setCreatedAt(NULL);
    }

    public function getId(): ?int
    {
        return $this->getCommentId();
    }

    public function getCommentId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->created_at = $date;
        } else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }
}
