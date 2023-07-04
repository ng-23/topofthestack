<?php

declare(strict_types=1);

require_once realpath(dirname(__FILE__) . '../../../') . '/abstractions/mappers/DataMapper.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/drafts/BlogDraft.php';
require_once realpath(dirname(__FILE__) . '../../') . '/entities/drafts/BlogDraftFactory.php';

class DraftMapper extends DataMapper
{
    public const BODY_CONTENTS_DIR = "resources/drafts/";

    private TagMapper $tag_mapper;
    private BlogDraftFactory $draft_factory;

    public function __construct(PDO $db_connection, TagMapper $tag_mapper, BlogDraftFactory $draft_factory)
    {
        parent::__construct($db_connection);
        $this->tag_mapper = $tag_mapper;
        $this->draft_factory = $draft_factory;
    }

    public function draftFromData(array $draft_data): BlogDraft
    {
        $draft = $this->draft_factory->makeBlogDraft($draft_data["drafter_id"]);

        $draft->setBodyUri($draft_data["body_uri"]);
        $draft->setName($draft_data["name"]);
        $draft->setId($draft_data["draft_id"]);

        $draft->setPublishedBlogId($draft_data["published_blog_id"]);

        $draft->setTitle($draft_data["title"]);

        foreach ($draft_data["tags"] as $tag) {
            $draft->addTag($tag->getName());
        }

        $draft->setCreatedAt(new DateTimeImmutable(date(BlogDraft::DATE_FORMAT, $draft_data["created_at"])));
        $draft->setUpdatedAt(new DateTimeImmutable(date(BlogDraft::DATE_FORMAT, $draft_data["updated_at"])));

        return $draft;
    }

    public function draftsFromData(array $drafts_data): array
    {
        $drafts = [];

        foreach ($drafts_data as $draft_data) {
            $draft_data = $this->pushTags($draft_data);

            $draft = $this->draftFromData($draft_data);

            array_push($drafts, $draft);
        }

        return $drafts;
    }

    private function pushTags(array $draft_data): array
    {
        $tags = $this->tag_mapper->fetchByDraftId($draft_data["draft_id"]);

        $draft_data["tags"] = $tags;

        return $draft_data;
    }

    public function existsById(int $draft_id): bool
    {
        $exists = false;

        if ($this->fetchById($draft_id)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByDrafterAndDraftName(int $drafter_id, String $draft_name): bool
    {
        $exists = false;

        if ($this->fetchByDrafterAndDraftName($drafter_id, $draft_name)) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByBodyUri(String $body_uri): bool
    {
        $exists = false;

        // TODO: consider having a private fetchByBodyUri method that this method wraps the result of like other existsBy* methods
        $query = "SELECT `draft_id` FROM `blog_drafts` WHERE `body_uri` = ?";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $body_uri, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
    }

    public function existsByBodyUriAndId(String $body_uri, int $draft_id)
    {
        $exists = false;

        $query = "SELECT `draft_it` FROM `blog_drafts` WHERE `body_uri` = :body_uri AND `draft_id` = :draft_id";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":body_uri", $body_uri, PDO::PARAM_STR);
        $stmt->bindParam(":draft_id", $draft_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $exists = true;
        }

        return $exists;
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
                $has_valid_chars = preg_match(BlogMapper::BODY_FILE_NAME_REGEX, $file_name);
                if ($has_valid_chars) {
                    $is_valid = true;
                }
            }
        }

        return $is_valid;
    }

    private function tagDraft(int $draft_id, String $tag_name)
    {
        $query = "INSERT INTO `tagged_drafts` (`draft_id`, `tag_name`) VALUES (:draft_id, :tag_name)";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":draft_id", $draft_id, PDO::PARAM_INT);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();
    }

    // should untagging/tagging really be done in this mapper?
    private function untagDraft(int $draft_id, String $tag_name)
    {
        $query = "DELETE FROM `tagged_drafts`
                WHERE `draft_id` = :draft_id AND `tag_name` = :tag_name";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":draft", $draft_id, PDO::PARAM_INT);
        $stmt->bindParam(":tag_name", $tag_name, PDO::PARAM_STR);
        $stmt->execute();
    }

    private function writeBodyContents(BlogDraft $draft)
    {
        // this should probably validate the body uri instead of just assuming it's ok
        $body_contents_file = fopen($_SERVER["DOCUMENT_ROOT"] . "/" . $draft->getBodyUri(), "w");
        fwrite($body_contents_file, $draft->getBodyContents());
        fclose($body_contents_file);
    }

    private function deleteBodyContents(BlogDraft $draft)
    {
        $body_uri = $draft->getBodyUri();
        $body_contents_filepath = $_SERVER["DOCUMENT_ROOT"] . "/{$body_uri}";;
        unlink($body_contents_filepath);
    }

    public function save(BlogDraft $draft)
    {
        $draft_id_param_type = PDO::PARAM_NULL;
        if ($draft->getId()) {
            $this->existsById($draft->getId()) ? throw new Exception() : '';
            $draft_id_param_type = PDO::PARAM_INT;
        }

        if ($this->existsByDrafterAndDraftName($draft->getDrafterId(), $draft->getName())) {
            throw new Exception();
        }

        if (!$this->validateBodyUri($draft->getBodyUri())) {
            throw new Exception();
        }

        if ($this->existsByBodyUri($draft->getBodyUri())) {
            throw new Exception();
        }

        foreach ($draft->getTags() as $tag_name) {
            if (!$this->tag_mapper->existsByName($tag_name)) {
                throw new Exception();
            }
        }

        $query = "INSERT INTO `blog_drafts` (`draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, `name`, 
                `title`, `created_at`, `updated_at`) VALUES (:draft_id,
                :drafter_id, :body_uri, :published_blog_id, :name, :title,
                :created_at, :updated_at)";
        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":draft_id", $draft->getId(), $draft_id_param_type);
        $stmt->bindParam(":drafter_id", $draft->getDrafterId(), PDO::PARAM_INT);
        $stmt->bindParam(":body_uri", $draft->getBodyUri(), PDO::PARAM_STR);
        $stmt->bindParam(":published_blog_id", $draft->getPublishedBlogId(), PDO::PARAM_INT);
        $stmt->bindParam(":name", $draft->getName(), PDO::PARAM_STR);
        $stmt->bindParam(":title", $draft->getTitle(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $draft->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $draft->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        foreach ($draft->getTags() as $tag_name) {
            $this->tagDraft($draft->getId(), $tag_name);
        }

        $this->writeBodyContents($draft);
    }

    public function fetchById(int $draft_id): ?BlogDraft
    {
        $draft = NULL;

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                WHERE `draft_id` = ?";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(1, $draft_id);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $draft_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $draft_data = $this->pushTags($draft_data);

            $draft = $this->draftFromData($draft_data);
        }

        return $draft;
    }

    public function fetchByDrafterId(int $drafter_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $drafts = [];

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                WHERE `drafter_id` = :drafter_id LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":drafter_id", $drafter_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        return $drafts;
    }

    /**
     * Returns the draft(s) that are associated with a particular published blog
     */
    public function fetchByPublishedBlogId(int $published_blog_id, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $drafts = [];

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                WHERE `published_blog_id` = :blog_id LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":blog_id", $published_blog_id, PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }
        return $drafts;
    }

    public function fetchByDrafterAndDraftName(int $drafter_id, String $draft_name, int $amount = 1, int $offset = 0, bool $exact_match = true)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $is_valid_name = preg_match(BlogDraft::NAME_REGEX, $draft_name);
        if (!$is_valid_name) {
            return [];
        }

        $drafts = [];

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                WHERE `drafter_id` = :drafter_id AND";

        if ($exact_match) {
            $query = $query . " = :draft_name";
        } else {
            $query = $query . " REGEXP :draft_name";
            $draft_name = "(" . $draft_name . ")"; // regex pattern ($draft_name) for inclusive matches
        }

        $query = $query . " LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":drafter_id", $drafter_id, PDO::PARAM_INT);
        $stmt->bindParam(":draft_name", $draft_name, PDO::PARAM_STR);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $drafts_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        return $drafts;
    }

    public function fetchByDrafterAndCreatedOn(int $drafter_id, DateTimeImmutable $date, int $amount = 0, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                WHERE `drafter_id` = :drafter_id AND (`created_at` = :date) LIMIT :lim OFFSET :off";

        $drafts = [];

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":drafter_id", $drafter_id, PDO::PARAM_INT);
        $stmt->bindParam(":date", $date->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        return $drafts;
    }

    public function fetchByDrafterAndCreatedBetween(int $drafter_id, DateTimeImmutable $date1, DateTimeImmutable $date2, int $amount = 1, int $offset = 0)
    {
        if ($amount < 0 || $offset < 0) {
            throw new Exception();
        }

        $query = "SELECT `draft_id`, `drafter_id`, `body_uri`, `published_blog_id`, 
                    `name`, `title`, `created_at`, `updated_at` FROM `blog_drafts`
                    WHERE `drafter_id` = :drafter_id AND (`created_at` > :date1 AND `created_at` < :date2)
                    LIMIT :lim OFFSET :off";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":drafter_id", $drafter_id, PDO::PARAM_INT);
        $stmt->bindParam(":date1", $date1->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":date2", $date2->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":lim", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":off", $offset, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        return $drafts;
    }

    public function update(BlogDraft $draft)
    {
        $draft_id_param_type = PDO::PARAM_NULL;
        if ($draft->getId()) {
            $this->existsById($draft->getId()) ? throw new Exception() : '';
            $draft_id_param_type = PDO::PARAM_INT;
        }

        $current_draft_version = $this->fetchById($draft->getId());
        if ($current_draft_version->getName() != $draft->getName() and $this->existsByDrafterAndDraftName($draft->getDrafterId(), $draft->getName())) {
            throw new Exception();
        }

        foreach ($draft->getTags() as $tag_name) {
            if (!$this->tag_mapper->existsByName($tag_name)) {
                throw new Exception();
            }
        }

        if (!$this->existsByBodyUriAndId($draft->getBodyUri(), $draft->getId())) {
            if ($this->existsByBodyUri($draft->getBodyUri())) {
                throw new Exception();
            }
            $this->deleteBodyContents($current_draft_version);
        }

        $query = "UPDATE `blog_drafts` SET `body_uri` = :body_uri, `published_blog_id` = :pub_blog_id, `name` = :name, `title` = :title,
                `created_at` = :created_at, `updated_at` = :updated_at WHERE `draft_id` = :draft_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":draft_id", $draft->getId(), $draft_id_param_type);
        $stmt->bindParam(":body_uri", $draft->getBodyUri(), PDO::PARAM_STR);
        $stmt->bindParam(":pub_blog_id", $draft->getPublishedBlogId(), PDO::PARAM_INT);
        $stmt->bindParam(":name", $draft->getName(), PDO::PARAM_STR);
        $stmt->bindParam(":title", $draft->getTitle(), PDO::PARAM_STR);
        $stmt->bindParam(":created_at", $draft->getCreatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->bindParam(":updated_at", $draft->getUpdatedAt()->getTimestamp(), PDO::PARAM_INT);
        $stmt->execute();

        $new_tag_names = $draft->getTags();
        $old_tag_names = [];
        foreach ($this->tag_mapper->fetchByDraftId($draft->getId()) as $old_tag) {
            $tag_name = $old_tag->getName();
            array_push($old_tag_names, $tag_name);
        }

        $to_delete = array_diff($old_tag_names, $new_tag_names);
        $to_add = array_diff($new_tag_names, $old_tag_names);

        if (sizeof($to_delete) > 0) {
            foreach ($to_delete as $tag_to_delete) {
                $this->untagDraft($draft->getId(), $tag_to_delete);
            }
        }

        if (sizeof($to_add) > 0) {
            foreach ($to_add as $tag_to_add) {
                $this->tagDraft($draft->getId(), $tag_to_add);
            }
        }

        $this->writeBodyContents($draft);
    }

    public function deleteById(int $draft_id): ?BlogDraft
    {
        $draft = NULL;

        $query = "DELETE FROM `blog_drafts`
                WHERE `draft_id` = :draft_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":draft_id", $draft_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $draft_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $draft = $this->draftFromData($draft_data);
        }

        $this->deleteBodyContents($draft);

        return $draft;
    }

    public function deleteByDrafterId(int $drafter_id)
    {
        $drafts = [];

        $query = "DELETE FROM `blog_drafts`
                WHERE `drafter_id` = :drafter_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":drafter_id", $drafter_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        foreach ($drafts as $draft) {
            $this->deleteBodyContents($draft);
        }

        return $drafts;
    }

    public function deleteByPublishedBlogId(int $published_blog_id)
    {
        $drafts = [];

        $query = "DELETE FROM `blog_drafts`
                WHERE `published_blog_id` = :pub_blog_id";

        $stmt = $this->db_connection->prepare($query);
        $stmt->bindParam(":pub_blog_id", $published_blog_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $drafts_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $drafts = $this->draftsFromData($drafts_data);
        }

        foreach ($drafts as $draft) {
            $this->deleteBodyContents($draft);
        }

        return $drafts;
    }
}
