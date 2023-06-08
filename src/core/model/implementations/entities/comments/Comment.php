<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';

class Comment extends Entity
{
    public const DATE_FORMAT = "Y-m-d";
    public const MAX_CONTENTS_LEN = 135;

    private int $commentor_id;
    private int $blog_id;
    private String $contents;
    private int $total_likes;
    private DateTimeImmutable $created_at, $updated_at;


    public function __construct(int $commentor_id, int $blog_id, String $contents, ?int $comment_id = NULL)
    {
        $this->id = $comment_id;
        $this->commentor_id = $commentor_id;
        $this->blog_id = $blog_id;

        $this->total_likes = 0;

        $this->setContents($contents);
        $this->setCreatedAt(NULL);
        $this->setUpdatedAt(NULL);
    }

    public function getCommentorId(): int
    {
        return $this->commentor_id;
    }

    public function getBlogId(): int
    {
        return $this->blog_id;
    }

    public function getContents(): String
    {
        return $this->contents;
    }

    public function getTotalLikes(): int
    {
        return $this->total_likes;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setId(?int $comment_id)
    {
        $this->id = $comment_id;
    }

    public function setContents(String $contents)
    {
        $contents_len = strlen($contents);

        if ($contents_len == 0 or $contents_len > self::MAX_CONTENTS_LEN) {
            throw new LengthException();
        }

        $this->contents = $contents;
    }

    public function setTotalLikes(int $likes)
    {
        if ($likes < 0) {
            throw new Exception();
        }
        $this->total_likes = $likes;
    }

    public function setCreatedAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->created_at = $date;
        } else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }

    public function setUpdatedAt(?DateTimeImmutable $date)
    {
        if ($date) {
            $this->updated_at = $date;
        } else {
            $this->updated_at = DateTimeImmutable::createFromFormat(Comment::DATE_FORMAT, $this->created_at->format(Comment::DATE_FORMAT));
        }
    }

    public function toArray()
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
