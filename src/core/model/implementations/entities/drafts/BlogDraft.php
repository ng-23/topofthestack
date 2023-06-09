<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';
require_once realpath(dirname(__FILE__) . '../../../../../../../') . '/vendor/autoload.php';

class BlogDraft extends Entity
{
    public const DATE_FORMAT = "Y-m-d";
    public const MAX_TAGS = 3;

    private HTMLPurifier $html_purifier;
    private int $drafter_id;
    private ?int $published_blog_id;
    private String $body_uri;
    private String $body_contents;
    private String $name;
    private ?String $title;
    private array $tags;
    private DateTimeImmutable $created_at, $updated_at;

    private const MAX_NAME_LEN = 16;
    private const MAX_TITLE_LEN = 100;
    private const MAX_URI_LEN = 255;

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

        $this->setCreatedAt();
        $this->setUpdatedAt();
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
        if (sizeof($this->tags) == self::MAX_TAGS) {
            throw new Exception();
        }

        $is_valid = preg_match(Tag::VALID_NAME_REGEX, $tag_name);
        if (!$is_valid) {
            throw new Exception();
        }

        if (in_array($tag_name, $this->tags)) {
            throw new Exception();
        }

        array_push($this->tags, $tag_name);
    }

    public function removeTag(String $tag_name)
    {
        if (!in_array($tag_name, $this->tags)) {
            throw new Exception();
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

    public function setBodyUri(String $uri)
    {
        $uri_len = strlen($uri);
        if ($uri_len == 0 or $uri_len > self::MAX_URI_LEN) {
            throw new Exception();
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
            $title_len = strlen($title);
            if ($title_len == 0 or $title_len > self::MAX_TITLE_LEN) {
                throw new Exception();
            }
        }
        $this->title = $title;
    }

    public function setName(String $name)
    {
        $name_len = strlen($name);
        if ($name_len == 0 || $name_len > self::MAX_NAME_LEN) {
            throw new Exception();
        }
        $this->name = $name;
    }

    public function setPublishedBlogId(?int $published_blog_id)
    {
        if ($published_blog_id < 0) {
            throw new Exception();
        }
        $this->published_blog_id = $published_blog_id;
    }

    public function setCreatedAt(?DateTimeImmutable $date = NULL)
    {
        if ($date) {
            $this->created_at = $date;
        } else {
            $this->created_at = new DateTimeImmutable("now");
        }
    }

    public function setUpdatedAt(?DateTimeImmutable $date = NULL)
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
