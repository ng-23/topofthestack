<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class BlogLike extends Entity
{
    public const DATE_FORMAT = "Y-m-d";

    // here, id field is implied to be published blog id

    private int $user_id; // id of user liking the blog
    private DateTimeImmutable $created_at; // use for keeping track of number of likes per day for a blog

    public function __construct(int $blog_id, int $user_id)
    {
        $this->id = $blog_id;
        $this->user_id = $user_id;

        $this->setCreatedAt(NULL);
    }

    public function getBlogId(): int
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
