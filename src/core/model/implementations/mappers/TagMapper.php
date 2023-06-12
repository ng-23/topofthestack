<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/tags/Tag.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/tags/TagFactory.php';

class TagMapper extends DataMapper
{
    public TagFactory $tag_factory;

    public function __construct(PDO $db_connection, TagFactory $tag_factory)
    {
        parent::__construct($db_connection);
        $this->tag_factory = $tag_factory;
    }

    public function tagFromData(array $tag_data)
    {
        $tag = $this->tag_factory->makeTag($tag_data["name"]);
        $tag->setId($tag_data["tag_id"]);

        $tag->setTotalTaggedWith($tag_data["total_tagged_with"]);
        $tag->setTaggedWithToday($tag_data["tagged_with_today"]);
        $tag->setCreatedAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $tag_data["created_at"])));
        $tag->setUpdatedAt(new DateTimeImmutable(date(Tag::DATE_FORMAT, $tag_data["updated_at"])));

        return $tag;
    }

    public function existsById(int $tag_id)
    {
        $exists = false;

        if ($this->fetchById($tag_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByName(String $tag_name)
    {
        $exists = false;

        if ($this->fetchByName($tag_name)) {
            $exists = true;
        }

        return $exists;
    }

    public function save(Tag $tag)
    {
        if (!$this->existsById($tag->getId())) {
            throw new Exception();
        }

        if (!$this->existsByName($tag->getName())) {
            throw new Exception();
        }

        $query = "INSERT INTO `tags` (`tag_id`, `name`, `tagged_with_today`, `total_tagged_with`, 
                            `created_at`, `updated_at`) VALUES (:tag_id, :name, :tagged_today,
                            :tagged_total, :created_at, :updated_at)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_id", $tag->getId(), PDO::PARAM_INT);
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

        return $tag;
    }

    /**
     * Returns the most used tag(s) all time
     * TODO: add sort order params to sort by most/least tagged with
     */
    public function fetchByTotalTaggedWith(int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $tags = [];

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM tags
                ORDER BY `total_tagged_with` LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach ($tags_data as $tag_data) {
                $tag = $this->tagFromData($tag_data);
                array_push($tags, $tag);
            }
        }
        return $tags;
    }

    /**
     * Returns the most used tag(s) today
     * TODO: add sort order param
     */
    public function fetchByTaggedWithToday(int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $tags = [];

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, 
                `total_tagged_with`, `created_at`, `updated_at` FROM tags
                ORDER BY `tagged_with_today` LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetch(PDO::FETCH_ASSOC);
            foreach ($tags_data as $tag_data) {
                $tag = $this->tagFromData($tag_data);
                array_push($tags, $tag);
            }
        }
        return $tags;
    }

    public function fetchByBlogId(int $blog_id)
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
            foreach ($tags_data as $tag_data) {
                $tag = $this->tagFromData($tag_data);
                array_push($tags, $tag);
            }
        }
        return $tags;
    }

    public function fetchByDraftId(int $draft_id)
    {
        $tags = [];

        $query = "SELECT `tag_id`, `name`, `tagged_with_today`, `total_tagged_with`, 
                `created_at`, `updated_at` FROM tagged_drafts
                WHERE `draft_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $draft_id);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tags_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($tags_data as $tag_data) {
                $tag = $this->tagFromData($tag_data);
                array_push($tags, $tag);
            }
        }
        return $tags;
    }

    public function update(Tag $tag, bool $change_name = false)
    {
        if (!$this->existsById($tag->getId())) {
            throw new Exception();
        }

        if ($change_name) {
            // if we're changing the name but it's already taken, there's a problem
            $this->existsByName($tag->getName()) ? throw new Exception() : '';
        } else {
            // if we're not changing the name but no tags have it, there's a problem
            !$this->existsByName($tag->getName()) ? throw new Exception() : '';
        }

        // this is a little awkward because now you have to set updated_at to correct date manually, it isn't automatic
        // but it would also be awkward I suppose if the mapper silently used a different value for updated_at rather than the one set in the entity
        // maybe just exclude the setter for updated_at in the entity? that also seems a little awkward though...
        // if you exclude the setter but keep the field, it will just have a default value that will end up just being ignored in some cases
        // maybe exclude it as a field altogether? but that won't work, because the web app needs to display that info sometimes...
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

    public function deleteById(int $tag_id)
    {
        $tag = NULL;

        $query = "DELETE FROM `tags`
                WHERE `tag_id` = :tag_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_id", $tag_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $tag = $this->tagFromData($tag_data);
        }

        return $tag;
    }

    public function deleteByName(String $tag_name)
    {
        $tag = NULL;

        $query = "DELETE FROM `tags`
                WHERE `name` = :tag_name";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $tag_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $tag = $this->tagFromData($tag_data);
        }

        return $tag;
    }
}
