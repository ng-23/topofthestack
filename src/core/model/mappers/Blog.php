<?php

declare(strict_types=1);

namespace tots\Mappers;

use tots\Entities\Blogs\PublishedBlog;
use tots\Entities\Blogs\PublishedBlogFactory;
use PDO;
use Exception;
use DateTimeImmutable;

class BlogMapper extends DataMapper
{
    // contains basic CRUD methods
    // save = create; fetch = read; update = update; delete = delete

    public const SORT_ASC_ORDER = 1;
    public const SORT_DESC_ORDER = 2;
    public const SORT_NO_ORDER = 3;
    public const BODY_CONTENTS_DIR = "resources/blogs/";
    public const BODY_FILE_NAME_REGEX = "#^[a-zA-Z][a-zA-Z0-9_]*$#";

    private PublishedBlogFactory $blog_factory;
    private TagMapper $tag_mapper;

    public function __construct(PDO $db_connection, PublishedBlogFactory $blog_factory, TagMapper $tag_mapper)
    {
        $this->db_connection = $db_connection;
        $this->blog_factory = $blog_factory;
        $this->tag_mapper = $tag_mapper;
    }

    private function blogFromData(array $blog_data): PublishedBlog
    {
        $blog = $this->blog_factory->makeBlog($blog_data["author_id"], $blog_data["title"]);
        $blog->setId($blog_data["blog_id"]);
        $blog->setBodyUri($blog_data["body_uri"]);

        foreach ($blog_data["tags"] as $tag) {
            $blog->addTag($tag->getName());
        }

        $blog->setCreatedAt(new DateTimeImmutable(date(PublishedBlog::DATE_FORMAT, $blog_data["created_at"])));
        $blog->setUpdatedAt(new DateTimeImmutable(date(PublishedBlog::DATE_FORMAT, $blog_data["updated_at"])));

        $blog->setTotalComments($blog_data["total_comments"]);
        $blog->setTotalViews($blog_data["total_views"]);
        $blog->setTotalLikes($blog_data["total_likes"]);

        $blog->setCommentsToday($blog_data["comments_today"]);
        $blog->setViewsToday($blog_data["views_in_day"]);
        $blog->setLikesToday($blog_data["likes_in_day"]);

        return $blog;
    }

    private function blogsFromData(array $blogs_data): array
    {
        $blogs = [];

        foreach ($blogs_data as $blog_data) {
            $blog_data = $this->pushTags($blog_data);

            $blog = $this->blogFromData($blog_data);

            array_push($blogs, $blog);
        }

        return $blogs;
    }

    private function pushTags(array $blog_data): array
    {
        $tags = $this->tag_mapper->fetchByBlogId($blog_data["blog_id"]);

        $blog_data["tags"] = $tags;

        return $blog_data;
    }

    private function validateBodyUri(String $uri): bool
    {
        $is_valid = false;

        // uri should be of pattern base_dir/file_name
        if (str_starts_with($uri, self::BODY_CONTENTS_DIR)) {
            $file_name = substr($uri, strlen(self::BODY_CONTENTS_DIR));
            // can't exceed maximum uri length
            if (strlen(self::BODY_CONTENTS_DIR) + strlen($file_name) <= PublishedBlog::MAX_URI_LEN) {
                // file_name can't contain certain characters
                $has_valid_chars = preg_match(self::BODY_FILE_NAME_REGEX, $file_name);
                if ($has_valid_chars) {
                    $is_valid = true;
                }
            }
        }

        return $is_valid;
    }

    public function existsById(int $blog_id): bool
    {
        $exists = false;

        if ($this->fetchById($blog_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByAuthorAndTitle(int $author_id, String $title): bool
    {
        $exists = false;

        if (sizeof($this->fetchByAuthorAndTitle($author_id, $title, $exact_match = true)) > 0) {
            $exists = true;
        }

        return $exists;
    }

    private function existsByBodyUri(String $body_uri): bool
    {
        $exists = false;

        $query = "SELECT `blog_id` FROM `published_blogs` WHERE `body_uri` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $body_uri, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    private function existsByBodyUriAndId(String $body_uri, int $blog_id): bool
    {
        $exists = false;

        $query = "SELECT `blog_id` FROM `published_blogs` WHERE `body_uri` = :body_uri AND `blog_id` = :blog_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":body_uri", $body_uri, PDO::PARAM_STR);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    private function sortOrderToString(int $sort_order): ?String
    {
        $sort_order_str = "";

        switch ($sort_order) {
            case (self::SORT_ASC_ORDER):
                $sort_order_str = "ASC";
                break;
            case (self::SORT_DESC_ORDER):
                $sort_order_str = "DESC";
                break;
            case (self::SORT_NO_ORDER):
                $sort_order_str = "";
                break;
            default:
                throw new Exception();
        }

        return $sort_order_str;
    }

    private function writeBodyContents(PublishedBlog $blog)
    {
        $body_uri = $blog->getBodyUri();
        $body_contents_file = fopen($_SERVER["DOCUMENT_ROOT"] . "/{$body_uri}", "w");
        fwrite($body_contents_file, $blog->getBodyContents());
        fclose($body_contents_file);
    }

    private function deleteBodyContents(PublishedBlog $blog)
    {
        $body_uri = $blog->getBodyUri();
        $body_contents_filepath = $_SERVER["DOCUMENT_ROOT"] . "/{$body_uri}";;
        unlink($body_contents_filepath);
    }

    private function tagBlog(int $blog_id, String $tag_name)
    {
        $query = "INSERT INTO `tagged_blogs` (`blog_id`, `tag_name`) VALUES (:blog_id, :tag_name)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();
    }

    private function untagBlog(int $blog_id, String $tag_name)
    {
        $query = "DELETE FROM `tagged_blogs`
                WHERE `blog_id` = :blog_id AND `tag_name` = :tag_name";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function save(PublishedBlog $blog)
    {
        /**
         * TODO: consider removing this...
         * This essentially checks that the user didn't just try to upload a completely empty blog
         * of course, they could just type 1 character and then it'd be accepted
         * maybe check the length of the string?
         * but even that isn't perfect, since the structural parts of the HTML (like <tag></>) will add length but not content
         * unless content is wrapped in paragraph tags or something, then you count the number of those?
         */
        if (strlen($blog->getBodyContents()) == 0) {
            throw new Exception();
        }

        $blog_id_param_type = PDO::PARAM_NULL;
        if ($blog->getId()) {
            $this->existsById($blog->getId()) ? throw new Exception() : '';
            $blog_id_param_type = PDO::PARAM_INT;
        }

        if ($this->existsByAuthorAndTitle($blog->getAuthorId(), $blog->getTitle())) {
            throw new Exception();
        }

        if (!$this->validateBodyUri($blog->getBodyUri())) {
            throw new Exception();
        }

        if ($this->existsByBodyUri($blog->getBodyUri())) {
            throw new Exception();
        }

        foreach ($blog->getTags() as $tag_name) {
            if (!$this->tag_mapper->fetchByName($tag_name)) {
                throw new Exception();
            }
        }

        $query = "INSERT INTO `published_blogs` (`blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, 
                `likes_today`, `views_today`, `total_comments`, `total_likes`, `total_views`, `created_at`, `updated_at`) 
                VALUES (:blog_id, :author_id, :body_uri, :title, :cmmnts_today, :likes_today, :views_today, total_cmmnts,
                :total_likes, :total_views, :created_at, :updated_at)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog->getId(), $blog_id_param_type);
        $stmt->bindParam(":author_id", $blog->getAuthorId(), PDO::PARAM_INT);
        $stmt->bindParam(":body_uri", $blog->getBodyUri(), PDO::PARAM_STR);
        $stmt->bindParam(":title", $blog->getTitle(), PDO::PARAM_STR);
        $stmt->bindParam(":cmmnts_today", $blog->getCommentsToday(), PDO::PARAM_INT);
        $stmt->bindParam(":likes_today", $blog->getLikesToday(), PDO::PARAM_INT);
        $stmt->bindParam(":views_today", $blog->getViewsToday(), PDO::PARAM_INT);
        $stmt->bindParam(":total_cmmnts", $blog->getTotalComments(), PDO::PARAM_INT);
        $stmt->bindParam(":total_likes", $blog->getTotalLikes(), PDO::PARAM_INT);
        $stmt->bindParam(":total_views", $blog->getTotalViews(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $blog->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $blog->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        foreach ($blog->getTags() as $tag_name) {
            $this->tagBlog($blog->getId(), $tag_name);
        }

        $this->writeBodyContents($blog);
    }

    public function fetchById(int $blog_id): ?PublishedBlog
    {
        $blog = NULL;

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs`
                WHERE `blog_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $blog_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $blog_data = $this->pushTags($blog_data);

            $blog = $this->blogFromData($blog_data);
        }

        return $blog;
    }

    //TODO: implement sorting param for stuff like most viewed all time, today, etc.
    public function fetchByAuthor(int $author_id, int $amount = 1, int $offset = 0): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs`
                WHERE `author_id` = :author_id LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $blogs_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    //TODO: implement sorting param for stuff like most viewed all time, today, etc.
    public function fetchByTitle(String $title, int $amount = 1, int $offset = 0, bool $exact_match = false): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs`
                WHERE `title`";

        if ($exact_match) {
            $query = $query . " = :title";
        } else {
            $query = $query . " REGEXP :title";
            $title = "(" . $title . ")"; // regex pattern ($title) for inclusive matches
        }

        $query = $query . " LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function fetchByAuthorAndTitle(int $author_id, String $title, bool $exact_match = false): array
    {
        $blogs = [];

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs`
                WHERE `author_id` = :author_id AND `title`";

        if ($exact_match) {
            $query = $query . " = :title";
        } else {
            $query = $query . " REGEXP :title";
            $title = "(" . $title . ")"; // regex pattern ($title) for inclusive matches
        }

        $query = $query . " LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        $stmt->bindParam(":title", $title, PDO::PARAM_STR);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    //TODO: implement sorting param for stuff like most viewed all time, today, etc.
    // or instead of another constant(s), you could define methods like fetchByAuthorAndViewsToday,
    // then just keep the basic ascending and descending sort constants
    public function fetchByAuthorAndCreatedOn(int $author_id, DateTimeImmutable $date, int $amount = 1, int $offset = 0): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` 
                WHERE `author_id` = :author_id AND (`created_at` = :date) LIMIT :lim OFFSET :off";

        $blogs = [];

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        $stmt->bindParam(":date", $date->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function fetchByAuthorAndCreatedBetween(int $author_id, DateTimeImmutable $date1, DateTimeImmutable $date2, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` 
                WHERE `author_id` = :author_id AND (`created_at` > :date1 AND `created_at` < :date2) 
                LIMIT :lim OFFSET :off";

        $blogs = [];

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        $stmt->bindParam(":date1", $date1->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":date2", $date2->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    /**
     * Returns the blog(s) tagged with the tags in tag_names
     */
    public function fetchByTagNames(array $tag_names, int $amount = 1, int $offset = 0): array
    {
        $blogs = [];

        $numb_tags = sizeof($tag_names);

        if ($numb_tags > 0 && $numb_tags <= PublishedBlog::MAX_TAGS) {
            $query = "SELECT DISTINCT `table0`.`blog_id` FROM";
            $from = " `tagged_blogs` `table0`";
            $where = " WHERE";
            $conditions = "";

            $blog_ids = [];

            if ($numb_tags == 1) {
                $where = $where . " `table0`.`tag_name` = ?";
            } else {
                for ($i = 0; $i < $numb_tags - 1; $i++) {
                    $table_numb = $i + 1;
                    $table_alias = "`table{$table_numb}`";

                    $from = $from . ", `tagged_blogs` {$table_alias}";

                    if ($table_numb > 1) {
                        $where = $where . " AND `table0`.`blog_id` = {$table_alias}.`blog_id`";
                        $conditions = $conditions . " AND {$table_alias}.`tag_name` = ?";
                    } else {
                        $where = $where . " `table0`.`blog_id` = {$table_alias}.`blog_id`";
                        $conditions = " AND `table0`.`tag_name` = ? AND {$table_alias}.`tag_name` = ?";
                    }
                }
            }

            $query = $query . $from . $where . $conditions;
            $stmt = $this->db_connection->prepare($query);
            for ($i = 0; $i < $numb_tags; $i++) {
                $placeholder_numb = $i + 1;
                $stmt->bindParam($placeholder_numb, $tag_names[$i], PDO::PARAM_STR);
            }
            $stmt->execute();
            $blog_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($blog_ids as $blog_id) {
                $blog = $this->fetchById($blog_id);
                array_push($blogs, $blog);
            }
        }

        array_slice($blogs, $offset, $amount);
        return $blogs;
    }

    public function fetchByTotalViews(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` ORDER BY `total_views` {$sort_order_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function fetchByViewsToday(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` ORDER BY `views_today` {$sort_order_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function fetchByTotalLikes(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` ORDER BY `total_likes` {$sort_order_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }
        return $blogs;
    }

    public function fetchByLikesToday(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $blogs = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                `views_today`, `total_comments`, `total_likes`, `total_views`, 
                `created_at`, `updated_at` FROM `published_blogs` ORDER BY `likes_today` {$sort_order_str} LIMIT :lim OFFSET :off";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }
        return $blogs;
    }

    //TODO: implement sorting param for stuff like most viewed all time, today, etc.
    public function fetchByCreatedOn(DateTimeImmutable $date, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                    `views_today`, `total_comments`, `total_likes`, `total_views`, 
                    `created_at`, `updated_at` FROM `published_blogs` 
                    WHERE (`created_at` = :date) LIMIT :lim OFFSET :off";

        $blogs = [];

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":date", $date->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function fetchByCreatedBetween(DateTimeImmutable $date1, DateTimeImmutable $date2, int $amount = 1, int $offset = 0): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `blog_id`, `author_id`, `body_uri`, `title`, `comments_today`, `likes_today`, 
                    `views_today`, `total_comments`, `total_likes`, `total_views`, 
                    `created_at`, `updated_at` FROM `published_blogs` 
                    WHERE (`created_at` > :date1 AND `created_at` < :date2) LIMIT :lim OFFSET :off";

        $blogs = [];

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":date1", $date1->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":date2", $date2->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);
        }

        return $blogs;
    }

    public function update(PublishedBlog $blog)
    {
        if (strlen($blog->getBodyContents()) == 0) {
            throw new Exception();
        }

        if (!$blog->getId() or !$this->existsById($blog->getId())) {
            throw new Exception();
        }

        $current_blog_version = $this->fetchById($blog->getId());
        // if we're trying to change blog title and it's already been used by the same author
        if ($current_blog_version->getTitle() != $blog->getTitle() and $this->existsByAuthorAndTitle($blog->getAuthorId(), $blog->getTitle())) {
            throw new Exception();
        }

        foreach ($blog->getTags() as $tag_name) {
            if (!$this->tag_mapper->existsByName($tag_name)) {
                throw new Exception();
            }
        }

        // save this check for last
        // if the uri hasn't stayed the same (aka, if !$this->bodyUriExistsByBlog)
        // then check if the new uri is taken (so if $this->bodyUriExists)
        // if it is taken, throw an exception
        // if not, fetch the current blog entity from the DB (which will have old body uri), 
        // then delete the existing file on disk with the old body uri
        if (!$this->existsByBodyUriAndId($blog->getBodyUri(), $blog->getId())) {
            if ($this->existsByBodyUri($blog->getBodyUri())) {
                throw new Exception();
            }
            $current_blog_version = $this->fetchById($blog->getId());
            $this->deleteBodyContents($current_blog_version);
        }

        $query = "UPDATE `published_blogs` SET `title` = :title, `body_uri` = :body_uri, 
                `comments_today` = :cmmnts_today, `likes_today` = :likes_today,
                `views_today` = :views_today, `total_comments` = :total_cmmnts, `total_likes` = :total_likes, `total_views` = :total_views,
                `created_at` = :created_at, `updated_at` = :updated_at WHERE `blog_id` = :blog_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":title", $blog->getTitle(), PDO::PARAM_STR);
        $stmt->bindParam(":body_uri", $blog->getBodyUri(), PDO::PARAM_STR);
        $stmt->bindParam(":cmmnts_today", $blog->getCommentsToday(), PDO::PARAM_INT);
        $stmt->bindParam(":likes_today", $blog->getLikesToday(), PDO::PARAM_INT);
        $stmt->bindParam(":views_today", $blog->getViewsToday(), PDO::PARAM_INT);
        $stmt->bindParam(":total_cmmnts", $blog->getTotalComments(), PDO::PARAM_INT);
        $stmt->bindParam(":total_likes", $blog->getTotalLikes(), PDO::PARAM_INT);
        $stmt->bindParam(":total_views", $blog->getTotalViews(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $blog->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $blog->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        $new_tag_names = $blog->getTags();
        $old_tag_names = [];
        foreach ($this->tag_mapper->fetchByBlogId($blog->getId()) as $old_tag) {
            $tag_name = $old_tag->getName();
            array_push($old_tag_names, $tag_name);
        }

        $to_delete = array_diff($old_tag_names, $new_tag_names);
        $to_add = array_diff($new_tag_names, $old_tag_names);

        if (sizeof($to_delete) > 0) {
            foreach ($to_delete as $tag_to_delete) {
                $this->untagBlog($blog->getId(), $tag_to_delete);
            }
        }

        if (sizeof($to_add) > 0) {
            foreach ($to_add as $tag_to_add) {
                $this->tagBlog($blog->getId(), $tag_to_add);
            }
        }

        $this->writeBodyContents($blog);
    }

    /**
     * Delete a blog record from the database
     * Returns the deleted blog, or NULL if the blog to delete does not exist
     */
    public function deleteById(int $blog_id): ?PublishedBlog
    // would also probably want to delete any images saved locally that were used in the blog
    // but get to that later...
    {
        $blog = NULL;

        $query = "DELETE FROM `published_blogs`
                WHERE `blog_id` = :blog_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $blog_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $blog = $this->blogFromData($blog_data);
        }

        $this->deleteBodyContents($blog);

        return $blog;
    }

    public function deleteByAuthorId(int $author_id): array
    {
        $blogs = [];

        $query = "DELETE FROM `published_blogs`
                WHERE `author_id` = :author_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":author_id", $author_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $blogs_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $blogs = $this->blogsFromData($blogs_data);

            foreach ($blogs as $blog) {
                $this->deleteBodyContents($blog);
            }
        }

        return $blogs;
    }
}