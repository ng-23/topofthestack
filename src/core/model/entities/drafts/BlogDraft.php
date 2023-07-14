<?php

declare(strict_types=1);

namespace tots\Entities\Drafts;

use tots\Entities\Entity;
use HTMLPurifier;
use DateTimeImmutable;
use Exception;
use LengthException;
use OutOfBoundsException;
use OverflowException;
use tots\Entities\Blogs\PublishedBlog;
use tots\Entities\Tags\Tag;
use ReflectionClass;
use tots\Exceptions\BadDraftNameException;
use tots\Exceptions\BadIdException;
use tots\Exceptions\BadTagNameException;
use tots\Exceptions\BadTitleException;
use tots\Exceptions\DuplicateKeyException;

class BlogDraft extends Entity
{
    public const DATE_FORMAT = "Y-m-d H:i:s";
    public const MIN_NAME_LEN = 1;
    public const MAX_NAME_LEN = 16;
    public const NAME_REGEX = "@^[a-zA-Z#][a-zA-Z0-9_]{" . self::MIN_NAME_LEN - 1 . "," . self::MAX_NAME_LEN - 1 . "}$@";

    private HTMLPurifier $html_purifier;
    private int $drafter_id;
    private ?int $published_blog_id;
    private String $body_uri, $body_contents;
    /**
     * this is basically like a file name for the draft client side
     * for published blogs, this is equivalent to the title
     * which is why it isn't a separate field in that class
     * like a title, draft names must be unique (per user)
     */
    private String $name;
    private ?String $title; // this can be null until the user decides to push the draft to a published blog
    private array $tags;
    private DateTimeImmutable $created_at, $updated_at;

    public function __construct(HTMLPurifier $html_purifier, int $drafter_id, String $body_uri, String $name, ?String $draft_id = NULL)
    {
        $this->id = $draft_id;
        $this->drafter_id = $drafter_id;
        $this->html_purifier = $html_purifier;
        $this->body_uri = $this->setBodyUri($body_uri);
        $this->name = $this->setName($name);

        $this->published_blog_id = NULL;
        $this->body_contents = "";

        $this->tags = [];

        $this->setCreatedAt(NULL);
        $this->setUpdatedAt(NULL);
    }

    public function getDrafterId(): int
    {
        return $this->drafter_id;
    }

    public function getBodyUri(): String
    {
        return $this->body_uri;
    }

    public function getTitle(): ?String
    {
        return $this->title;
    }

    public function getName(): String
    {
        return $this->name;
    }

    public function getPublishedBlogId(): ?int
    {
        return $this->published_blog_id;
    }

    public function getBodyContents(): String
    {
        return $this->body_contents;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function addTag(String $tag_name)
    {
        if (sizeof($this->tags) == PublishedBlog::MAX_TAGS) {
            throw new OverflowException();
        }

        $is_valid = preg_match(Tag::NAME_REGEX, $tag_name);
        if (!$is_valid) {
            // BadTagNameException
            throw new BadTagNameException();
        }

        if (in_array($tag_name, $this->tags)) {
            // DuplicateKeyException
            throw new DuplicateKeyException();
        }

        array_push($this->tags, $tag_name);
    }

    public function removeTag(String $tag_name)
    {
        if (!in_array($tag_name, $this->tags)) {
            throw new OutOfBoundsException();
        }

        $this->tags = array_diff($this->tags, [$tag_name]);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setId(?int $draft_id)
    {
        if ($draft_id and $draft_id <= 0) {
            // BadIdException
            throw new BadIdException();
        }

        $this->id = $draft_id;
    }

    public function setBodyUri(String $uri)
    {
        $uri_len = strlen($uri);
        if ($uri_len < PublishedBlog::MIN_URI_LEN or $uri_len > PublishedBlog::MAX_URI_LEN) {
            throw new LengthException();
        }

        $this->body_uri = $uri;
    }

    public function setBodyContents(String $html_contents)
    {
        $this->body_contents = $this->html_purifier->purify($html_contents);
    }

    public function setTitle(?String $title = NULL)
    {
        if ($title) {
            $is_valid = preg_match(PublishedBlog::TITLE_REGEX, $title);
            if (!$is_valid) {
                // BadTitleException
                throw new BadTitleException();
            }
        }

        $this->title = $title;
    }

    public function setName(String $name)
    {
        $is_valid = preg_match(self::NAME_REGEX, $name);
        if (!$is_valid) {
            // BadDraftNameException
            throw new BadDraftNameException();
        }
        $this->name = $name;
    }

    public function setPublishedBlogId(?int $published_blog_id)
    {
        if ($published_blog_id and $published_blog_id <= 0) {
            // BadIdException
            throw new BadIdException();
        }
        $this->published_blog_id = $published_blog_id;
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
            $this->updated_at = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->created_at->format(self::DATE_FORMAT));
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