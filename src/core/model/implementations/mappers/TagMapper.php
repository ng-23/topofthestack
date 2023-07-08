<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/tags/Tag.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/tags/TagFactory.php';

class TagMapper extends DataMapper
{
    public const SORT_NO_ORDER = 0;
    public const SORT_ASC_ORDER = 1;
    public const SORT_DESC_ORDER = 2;

    public TagFactory $tag_factory;

    public function __construct(PDO $db_connection, TagFactory $tag_factory)
    {
        parent::__construct($db_connection);
        $this->tag_factory = $tag_factory;
    }

    public function tagFromData(array $tag_data): Tag
    {
        $tag = $this->tag_factory->makeTag($tag_data["name"]);
        $tag->setId($tag_data["tag_id"]);

        $tag->setTotalTaggedWith($tag_data["total_tagged_with"]);
        $tag->setTaggedWithToday($tag_data["tagged_with_today"]);
        $tag->setCreatedAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $tag_data["created_at"])));
        $tag->setUpdatedAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $tag_data["updated_at"])));

        return $tag;
    }

    public function tagsFromData(array $tags_data): array
    {
        $tags = [];

        foreach ($tags_data as $tag_data) {
            $tag = $this->tagFromData($tag_data);
            array_push($tags_data, $tag);
        }

        return $tags;
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

    public function existsById(int $tag_id): bool
    {
        $exists = false;

        if ($this->fetchById($tag_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByName(String $tag_name): bool
    {
        $exists = false;

        if ($this->fetchByName($tag_name)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(Tag $tag)
    {
        $tag_id_param_type = PDO::PARAM_NULL;
        if ($tag->getId()) {
            $this->existsById($tag->getId()) ? throw new Exception() : '';
            $tag_id_param_type = PDO::PARAM_INT;
        }

        if ($this->existsByName($tag->getName())) {
            throw new Exception();
        }

        $query = "INSERT INTO `tags` (`tag_id`, `name`, `tagged_with_today`, `total_tagged_with`, 
                            `created_at`, `updated_at`) VALUES (:tag_id, :name, :tagged_today,
                            :tagged_total, :created_at, :updated_at)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_id", $tag->getId(), $tag_id_param_type);
        $stmt->bindParam(":name", $tag->getName(), PDO::PARAM_STR);
        $stmt->bindParam(":tagged_today", $tag->getTaggedWithToday(), PDO::PARAM_INT);
        $stmt->bindParam(":tagged_total", $tag->getTotalTaggedWith(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $tag->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $tag->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function fetchById(int $tag_id): ?Tag
    {
        $tag = NULL;

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM `tags`
                WHERE `tag_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $tag_id);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $tag = $this->tagFromData($tag_data);
        }

        return $tag;
    }

    // TODO: consider adding an extact_match param
    public function fetchByName(String $tag_name): ?Tag
    {
        $tag = NULL;

        // is doing checks like this before querying even worth it?
        $valid_name = preg_match(Tag::NAME_REGEX, $tag_name);
        if ($valid_name) {
            $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM `tags`
                WHERE `name` = ?";

            $stmt = $this->db_connection->prepare($query);
            $stmt->bindParam(1, $tag_name);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $tag = $this->tagFromData($tag_data);
            }
        }

        return $tag;
    }

    public function fetchByTotalTaggedWith(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $tags = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM tags
                ORDER BY `total_tagged_with` {$sort_order_str} LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tags = $this->tagsFromData($tags_data);
        }

        return $tags;
    }

    public function fetchByTaggedWithToday(int $amount = 1, int $offset = 0, int $sort_order = self::SORT_DESC_ORDER): array
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $tags = [];

        $sort_order_str = $this->sortOrderToString($sort_order);

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM tags
                ORDER BY `tagged_with_today` {$sort_order_str} LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tags = $this->tagsFromData($tags_data);
        }

        return $tags;
    }

    public function fetchByBlogId(int $blog_id): array
    {
        $tags = [];

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, `total_tagged_with`, 
                `created_at`, `updated_at` FROM `tagged_blogs`
                WHERE `blog_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $blog_id);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tags = $this->tagsFromData($tags_data);
        }

        return $tags;
    }

    public function fetchByDraftId(int $draft_id): array
    {
        $tags = [];

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, `total_tagged_with`, 
                `created_at`, `updated_at` FROM `tagged_drafts`
                WHERE `draft_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $draft_id);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $tags = $this->tagsFromData($tags_data);
        }

        return $tags;
    }

    public function update(Tag $tag)
    {
        if (!$tag->getId() or !$this->existsById($tag->getId())) {
            throw new Exception();
        }

        $current_tag_version = $this->fetchById($tag->getId());
        // if we're attempting to change tag name but it's already taken
        if ($current_tag_version->getName() != $tag->getName() and $this->existsByName($tag->getName())) {
            throw new Exception();
        }

        $query = "UPDATE `tags` SET `name` = :tag_name, `tagged_with_today` = :tagged_today,
                `total_tagged_with` = :tagged_total, `created_at` = :created_at, `updated_at` = :updated_at 
                WHERE `tag_id` = :tag_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_id", $tag->getId(), PDO::PARAM_INT);
        $stmt->bindParam(":name", $tag->getName(), PDO::PARAM_STR);
        $stmt->bindParam(":tagged_today", $tag->getTaggedWithToday(), PDO::PARAM_INT);
        $stmt->bindParam(":tagged_total", $tag->getTotalTaggedWith(), PDO::PARAM_INT);
        $stmt->bindParam(":created_at", $tag->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $tag->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();
    }

    public function deleteById(int $tag_id): ?Tag
    {
        $tag = NULL;

        $query = "DELETE FROM `tags`
                WHERE `tag_id` = :tag_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_id", $tag_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $tag = $this->tagFromData($tag_data);
        }

        return $tag;
    }

    public function deleteByName(String $tag_name): ?Tag
    {
        $tag = NULL;

        $query = "DELETE FROM `tags`
                WHERE `name` = :tag_name";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $tag = $this->tagFromData($tag_data);
        }

        return $tag;
    }
}
