<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../../') . '/abstractions/entities/Entity.php';
require_once realpath(dirname(__FILE__) . '../../../../../../../') . '/vendor/autoload.php';

class PublishedBlog extends Entity
{
    public const DATE_FORMAT = "Y-m-d H:i:s";
    public const MAX_TAGS = 3;

    private HTMLPurifier $html_purifier;
    private int $author_id;
    private String $body_uri, $body_contents;
    private String $title;
    private array $tags; // consider making this an array of actual Tag objects...
    private int $total_comments, $total_likes, $total_views;
    private int $comments_today, $likes_today, $views_today;
    private DateTimeImmutable $created_at, $updated_at;

    private const MAX_URI_LEN = 255;
    private const MAX_TITLE_LEN = 100;

    public function __construct(HTMLPurifier $html_purifier, int $author_id, String $body_uri, String $title, ?int $blog_id = NULL)
    {
        $this->id = $blog_id;
        $this->author_id = $author_id;
        $this->title = $this->setTitle($title);
        $this->body_uri = $this->setBodyUri($body_uri);
        $this->body_contents = "";

        $this->tags = [];

        $this->total_comments = 0;
        $this->total_views = 0;
        $this->total_likes = 0;

        $this->comments_today = 0;
        $this->views_today = 0;
        $this->likes_today = 0;

        $this->setCreatedAt(NULL);
        $this->setUpdatedAt(NULL);

        $this->html_purifier = $html_purifier;
    }

    public function getAuthorId(): int
    {
        return $this->author_id;
    }

    public function getBodyUri(): String
    {
        return $this->body_uri;
    }

    public function getBodyContents(): String
    {
        return $this->body_contents;
    }

    public function getTitle(): String
    {
        return $this->title;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getTotalComments(): int
    {
        return $this->total_comments;
    }

    public function getTotalViews(): int
    {
        return $this->total_views;
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

    public function getCommentsToday(): int
    {
        return $this->comments_today;
    }

    public function getViewsToday(): int
    {
        return $this->views_today;
    }

    public function getLikesToday(): int
    {
        return $this->likes_today;
    }

    public function setId(?int $blog_id)
    {
        if ($blog_id) {
            if ($blog_id <= 0) {
                throw new Exception();
            }
        }

        $this->id = $blog_id;
    }

    public function setBodyUri(String $uri)
    {
        $uri_len = strlen($uri);
        if ($uri_len == 0 or $uri_len > self::MAX_URI_LEN) {
            throw new Exception();
        }

        $this->body_uri = $uri;
    }

    public function setBodyContents(String $html)
    {
        /**
         * this is where sanitation and validation should take place
         * "You don't sanitize input. You validate input, and sanitize output." 
         * see http://htmlpurifier.org/comparison
         * do i need to do anything other than just pass the html_contents directly to HTMLPurifier?
         * see https://blog.andrewshell.org/htmlpurifier-article/
         */

        // is it really this simple? there's gotta be something else I need to do here...
        // will i need to create a custom config at all for tinyMCE editor?
        $this->body_contents = $this->html_purifier->purify($html);
    }

    public function setTitle(String $title)
    {
        $title_len = strlen($title);
        if ($title_len == 0 or $title_len > self::MAX_TITLE_LEN) {
            throw new Exception();
        }
        $this->title = $title;
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

    public function setTotalComments(int $comments)
    {
        if ($comments < 0) {
            throw new Exception();
        }
        $this->total_comments = $comments;
    }

    public function setTotalViews(int $views)
    {
        if ($views < 0) {
            throw new Exception();
        }
        $this->total_views = $views;
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
            $this->updated_at = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $this->created_at->format(self::DATE_FORMAT));
        }
    }

    public function setCommentsToday(int $comments)
    {
        if ($comments < 0) {
            throw new Exception();
        }
        $this->comments_today = $comments;
    }

    public function setViewsToday(int $views)
    {
        if ($views < 0) {
            throw new Exception();
        }
        $this->views_today = $views;
    }

    public function setLikesToday(int $likes)
    {
        if ($likes < 0) {
            throw new Exception();
        }
        $this->likes_today = $likes;
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
